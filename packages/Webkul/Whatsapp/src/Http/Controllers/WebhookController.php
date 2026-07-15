<?php

namespace Webkul\Whatsapp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Webkul\Whatsapp\Jobs\IngestInboundPayload;

class WebhookController extends Controller
{
    /**
     * Webhook verification handshake (Meta calls this once on subscription).
     *
     * Query params arrive as hub.mode / hub.verify_token / hub.challenge; PHP
     * converts the dots to underscores.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');

        $expected = (string) config('whatsapp.cloud.verify_token');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Receive inbound events. Validates the payload signature, then queues the
     * raw payload for processing and returns 200 fast.
     */
    public function receive(Request $request): JsonResponse
    {
        if (! $this->isValidSignature($request)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        IngestInboundPayload::dispatch($request->all())
            ->onQueue(config('whatsapp.queue'));

        return response()->json(['status' => 'ok']);
    }

    /**
     * Verify the X-Hub-Signature-256 header against the raw request body using
     * the app secret (HMAC SHA-256).
     */
    protected function isValidSignature(Request $request): bool
    {
        $secret = (string) config('whatsapp.cloud.app_secret');

        if ($secret === '') {
            return false;
        }

        $signature = (string) $request->header('X-Hub-Signature-256', '');

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
