<?php

namespace Webkul\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Webkul\Admin\Services\IncomingAppointmentService;

class ShareMeDataWebhookController extends Controller
{
    public function __construct(
        protected IncomingAppointmentService $incomingAppointmentService
    ) {}

    public function receive(Request $request): JsonResponse
    {
        $secret = config('services.sharemedata.webhook_secret');

        if ($secret) {
            $token = $request->header('X-Webhook-Secret') ?? $request->query('secret', '');

            if (! hash_equals($secret, (string) $token)) {
                Log::warning('ShareMeData Webhook: token inválido', ['ip' => $request->ip()]);

                return response()->json(['message' => 'Unauthorized'], 401);
            }
        }

        $data = $request->validate([
            'physician._id'   => 'required|string',
            'patient.phone'   => 'required|string',
            'slot.start'      => 'required|string',
            'slot.end'        => 'required|string',
        ]);

        // validate() returns only validated keys — merge back full payload for service
        $data = $request->all();

        Log::info('ShareMeData Webhook: payload recibido', ['ip' => $request->ip(), '_id' => $data['_id'] ?? null]);

        try {
            $result = $this->incomingAppointmentService->processWebhook($data);

            return response()->json(['success' => true, ...$result], 201);
        } catch (\Exception $e) {
            Log::error('ShareMeData Webhook: Error procesando cita', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Error interno'], 500);
        }
    }
}
