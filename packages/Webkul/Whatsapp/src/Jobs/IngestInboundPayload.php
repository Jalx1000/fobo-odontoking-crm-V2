<?php

namespace Webkul\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Webkul\Whatsapp\Models\MessageProxy;
use Webkul\Whatsapp\Services\ConversationResolver;

class IngestInboundPayload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array  $payload  Raw WhatsApp Cloud API webhook payload.
     */
    public function __construct(public array $payload) {}

    public function handle(ConversationResolver $resolver): void
    {
        foreach ($this->payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                $profileName = $value['contacts'][0]['profile']['name'] ?? null;

                foreach ($value['messages'] ?? [] as $message) {
                    $this->ingestMessage($message, $profileName, $resolver);
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->ingestStatus($status);
                }
            }
        }
    }

    /**
     * Apply an outbound delivery status update (sent/delivered/read/failed) to
     * the matching message, never regressing to an earlier state.
     */
    protected function ingestStatus(array $status): void
    {
        $waMessageId = $status['id'] ?? null;
        $newStatus = $status['status'] ?? null;

        if (! $waMessageId || ! $newStatus) {
            return;
        }

        $model = MessageProxy::modelClass();

        $message = $model::where('wa_message_id', $waMessageId)->first();

        if (! $message) {
            return;
        }

        $rank = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 3];

        if (($rank[$newStatus] ?? 0) < ($rank[$message->status] ?? 0)) {
            return; // don't regress (e.g. a late "delivered" after "read")
        }

        $message->update(['status' => $newStatus]);
    }

    /**
     * Persist a single inbound message idempotently.
     */
    protected function ingestMessage(array $message, ?string $profileName, ConversationResolver $resolver): void
    {
        $waMessageId = $message['id'] ?? null;

        $model = MessageProxy::modelClass();

        if ($waMessageId && $model::where('wa_message_id', $waMessageId)->exists()) {
            return; // already processed
        }

        $from = $message['from'] ?? null;

        if (! $from) {
            return;
        }

        $conversation = $resolver->findOrCreate($from, $profileName);

        $type = $this->normalizeType($message['type'] ?? 'unknown');

        $model::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'inbound',
            'type'            => $type,
            'body'            => $this->extractBody($message, $type),
            'wa_message_id'   => $waMessageId,
            'reply_to_id'     => $this->resolveReplyTo($message),
            'status'          => 'delivered',
            'sender'          => 'contact',
            'payload'         => $message,
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'unread_count'    => $conversation->unread_count + 1,
        ])->save();
    }

    /**
     * Map Meta's message type to our stored type.
     */
    protected function normalizeType(string $type): string
    {
        return match ($type) {
            'text', 'image', 'video', 'audio', 'document', 'sticker', 'location' => $type,
            'contacts' => 'contact',
            default    => $type, // button, interactive, unknown, ...
        };
    }

    /**
     * Best-effort human-readable body (caption for media, text for text, etc.).
     * Media files themselves are downloaded in Sprint 1 (HU-06).
     */
    protected function extractBody(array $message, string $type): ?string
    {
        return match ($type) {
            'text'     => $message['text']['body'] ?? null,
            'image', 'video', 'document', 'audio' => $message[$message['type']]['caption'] ?? null,
            'button'   => $message['button']['text'] ?? null,
            default    => null,
        };
    }

    /**
     * Resolve the local message a reply refers to (Meta sends context.id = wamid).
     */
    protected function resolveReplyTo(array $message): ?int
    {
        $contextId = $message['context']['id'] ?? null;

        if (! $contextId) {
            return null;
        }

        return MessageProxy::modelClass()::where('wa_message_id', $contextId)->value('id');
    }
}
