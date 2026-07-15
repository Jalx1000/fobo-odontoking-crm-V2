<?php

namespace Webkul\Whatsapp\Services;

use Illuminate\Database\Eloquent\Model;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Whatsapp\Models\ConversationProxy;

class ConversationResolver
{
    /**
     * Find the conversation for a WhatsApp number, creating it (and linking the
     * matching Person/Lead) when it does not yet exist.
     */
    public function findOrCreate(string $phone, ?string $name = null): Model
    {
        $normalized = PhoneNumber::normalize($phone);

        $conversation = ConversationProxy::modelClass()::where('wa_phone', $normalized)->first();

        if ($conversation) {
            if ($name && ! $conversation->wa_name) {
                $conversation->update(['wa_name' => $name]);
            }

            return $conversation;
        }

        $person = $this->resolvePerson($normalized);

        return ConversationProxy::modelClass()::create([
            'wa_phone'   => $normalized,
            'wa_name'    => $name,
            'person_id'  => $person?->id,
            'lead_id'    => $person ? optional($person->leads()->latest()->first())->id : null,
            'status'     => $person ? 'open' : 'unassigned',
        ]);
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
