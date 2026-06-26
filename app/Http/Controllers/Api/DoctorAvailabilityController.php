<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Admin\Services\AvailabilityResolverService;
use Webkul\Doctor\Repositories\DoctorRepository;

class DoctorAvailabilityController extends Controller
{
    public function __construct(
        protected DoctorRepository $doctorRepository,
        protected AvailabilityResolverService $resolver,
    ) {}

    /**
     * Disponibilidad REAL de un doctor: slots libres de ShareMeData menos las
     * citas locales del CRM. Si SMD no se puede consultar, degrada a la
     * disponibilidad local e informa el motivo (`source`/`degraded`/`reason`).
     *
     * GET /api/doctors/{id}/available-slots?date=YYYY-MM-DD&days=7&duration_minutes=30&specialty=&subsidiary=
     */
    public function availableSlots(Request $request, int $id): JsonResponse
    {
        if ($id <= 0) {
            return response()->json(['message' => 'Doctor not found.'], 404);
        }

        $data = $request->validate([
            'date'             => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today', 'before:' . now()->addMonths(6)->toDateString()],
            'days'             => ['sometimes', 'integer', 'min:1', 'max:30'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'specialty'        => ['sometimes', 'string', 'max:120'],
            'subsidiary'       => ['sometimes', 'string', 'max:120'],
        ]);

        $doctor = $this->doctorRepository->findWhere([
            ['id', '=', $id],
            ['is_active', '=', true],
        ])->first();

        if (! $doctor) {
            return response()->json(['message' => 'Doctor not found.'], 404);
        }

        $startDate = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $days      = (int) ($data['days'] ?? 7);

        $result = $this->resolver->resolve($id, $startDate, $days, [
            'duration_minutes' => $data['duration_minutes'] ?? 0,
            'specialty'        => $data['specialty']        ?? null,
            'subsidiary'       => $data['subsidiary']       ?? null,
        ]);

        return response()->json($result);
    }
}
