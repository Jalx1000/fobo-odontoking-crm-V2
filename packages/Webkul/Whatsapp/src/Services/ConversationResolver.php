<?php

namespace Webkul\Whatsapp\Services;

use Illuminate\Database\Eloquent\Model;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Whatsapp\Gateways\Dto\ContactIdentity;
use Webkul\Whatsapp\Models\ConversationProxy;

class ConversationResolver
{
    /**
     * Resolve the conversation for an inbound message.
     *
     * Identity is provider-dependent: Cloud API gives a phone, Kommo gives its
     * lead id (and the phone only after a lookup, which may fail). Match on
     * whichever is available, preferring the provider's own id since it is
     * exact.
     */
    public function resolve(ContactIdentity $contact, ?string $providerConversationId, string $gateway): ?Model
    {
        $model = ConversationProxy::modelClass();

        if ($providerConversationId) {
            // Key STRICTLY by the provider's id. Falling back to the phone here
            // would merge a second Kommo lead into the first lead's thread, and
            // replies would then be addressed to the wrong lead.
            $conversation = $model::where('gateway', $gateway)
                ->where('provider_conversation_id', $providerConversationId)
                ->first();
        } elseif ($contact->phone) {
            $conversation = $model::where('wa_phone', PhoneNumber::normalize($contact->phone))->first();
        } else {
            return null; // nothing to key on
        }

        if ($conversation) {
            return $this->backfill($conversation, $contact, $providerConversationId);
        }

        $person = $contact->phone ? $this->resolvePerson($contact->phone) : null;

        return $model::create([
            'gateway'                  => $gateway,
            'wa_phone'                 => PhoneNumber::normalize($contact->phone) ?: null,
            'provider_conversation_id' => $providerConversationId,
            'wa_name'                  => $contact->name,
            'person_id'                => $person?->id,
            'lead_id'                  => $person ? optional($person->leads()->latest()->first())->id : null,
            'status'                   => $person ? 'open' : 'unassigned',
        ]);
    }

    /**
     * Fill in details we learn later (the provider id on a phone-matched
     * conversation, a name, or a Person once the number gets registered).
     */
    protected function backfill(Model $conversation, ContactIdentity $contact, ?string $providerConversationId): Model
    {
        $updates = [];

        if ($providerConversationId && ! $conversation->provider_conversation_id) {
            $updates['provider_conversation_id'] = $providerConversationId;
        }

        if ($contact->name && ! $conversation->wa_name) {
            $updates['wa_name'] = $contact->name;
        }

        if ($contact->phone && ! $conversation->wa_phone) {
            $updates['wa_phone'] = PhoneNumber::normalize($contact->phone);
        }

        if (! $conversation->person_id && $contact->phone) {
            if ($person = $this->resolvePerson($contact->phone)) {
                $updates['person_id'] = $person->id;
                $updates['lead_id'] = optional($person->leads()->latest()->first())->id;
                $updates['status'] = 'open';
            }
        }

        if ($updates) {
            $conversation->update($updates);
        }

        return $conversation;
    }

    /**
     * Find or create the conversation for an outbound message addressed by phone.
     */
    public function findOrCreate(string $phone, ?string $name = null): Model
    {
        return $this->resolve(
            new ContactIdentity(phone: $phone, name: $name),
            null,
            (string) config('whatsapp.gateway'),
        );
    }

    /**
     * Match an incoming number against Person.contact_numbers. Uses a trailing
     * digit LIKE prefilter, then confirms in PHP against normalized values.
     */
    public function resolvePerson(string $phone): ?Model
    {
        $tail = PhoneNumber::tail($phone);

        if (strlen($tail) < 7) {
            return null;
        }

        return PersonProxy::modelClass()::where('contact_numbers', 'like', '%'.$tail.'%')
            ->get()
            ->first(function ($person) use ($tail) {
                foreach ((array) $person->contact_numbers as $number) {
                    $value = is_array($number) ? ($number['value'] ?? '') : $number;

                    if (str_ends_with(PhoneNumber::digits($value), $tail)) {
                        return true;
                    }
                }

                return false;
            });
    }
}
