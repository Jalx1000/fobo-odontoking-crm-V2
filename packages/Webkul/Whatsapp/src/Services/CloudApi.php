<?php

namespace Webkul\Whatsapp\Services;

use Illuminate\Support\Facades\Http;

class CloudApi
{
    /**
     * Send a plain text message through the WhatsApp Cloud API.
     *
     * @param  string  $to  Recipient phone (any format; digits are extracted).
     * @param  string|null  $replyToWamid  wamid of the message being replied to.
     * @return array Decoded Graph API response (contains messages[0].id = wamid).
     */
    public function sendText(string $to, string $body, ?string $replyToWamid = null): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => PhoneNumber::digits($to),
            'type'              => 'text',
            'text'              => ['body' => $body],
        ];

        if ($replyToWamid) {
            $payload['context'] = ['message_id' => $replyToWamid];
        }

        $response = Http::withToken((string) config('whatsapp.cloud.token'))
            ->acceptJson()
            ->post($this->endpoint('messages'), $payload);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * Build a Graph API endpoint URL for the configured phone number id.
     */
    protected function endpoint(string $path): string
    {
        $version = (string) config('whatsapp.cloud.api_version');
        $phoneId = (string) config('whatsapp.cloud.phone_number_id');

        return "https://graph.facebook.com/{$version}/{$phoneId}/{$path}";
    }
}
