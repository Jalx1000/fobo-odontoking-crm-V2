<?php

namespace Webkul\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Webkul\Whatsapp\Models\MessageProxy;
use Webkul\Whatsapp\Services\CloudApi;

class SendMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $messageId) {}

    public function handle(CloudApi $api): void
    {
        $message = MessageProxy::modelClass()::with(['conversation', 'replyTo'])->find($this->messageId);

        if (! $message || $message->status !== 'queued') {
            return;
        }

        try {
            $response = $api->sendText(
                $message->conversation->wa_phone,
                (string) $message->body,
                $message->replyTo?->wa_message_id,
            );

            $message->update([
                'wa_message_id' => $response['messages'][0]['id'] ?? null,
                'status'        => 'sent',
            ]);
        } catch (Throwable $e) {
            $message->update(['status' => 'failed']);

            report($e);
        }
    }
}
