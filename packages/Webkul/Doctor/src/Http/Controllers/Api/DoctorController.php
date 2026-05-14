<?php

namespace Webkul\Doctor\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Doctor\Repositories\DoctorRepository;
use Webkul\Doctor\Services\DoctorAvailabilityService;

class DoctorController extends Controller
{
    public function __construct(
        protected DoctorRepository $doctorRepository,
        protected DoctorAvailabilityService $availabilityService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $page = (int) $request->query('page', 1);
            $limit = (int) $request->query('limit', 10);

            if ($limit > 100) {
                $limit = 100;
            }
            if ($limit < 1) {
                $limit = 10;
            }

            $query = $this->doctorRepository->getModel()->newQuery()->with(['specialties', 'attribute_values']);

            if ($request->has('specialty')) {
                $query->whereHas('specialties', function ($q) use ($request) {
                    $q->where('name', 'like', '%'.$request->query('specialty').'%');
                });
            }

            if ($request->has('name')) {
                $query->where('name', 'like', '%'.$request->query('name').'%');
            }

            $doctors = $query->paginate($limit, ['*'], 'page', $page);
            $doctorIds = $doctors->pluck('id')->toArray();
            $startDate = Carbon::now()->startOfDay();
            $endDate = Carbon::now()->addDays(6)->endOfDay();

            $availability = $this->availabilityService->forDoctors($doctorIds, $startDate, $endDate);

            $doctors->getCollection()->transform(function ($doctor) use ($availability) {
                $doctor->availability = $availability[$doctor->id] ?? collect();

                return $doctor;
            });

            return response()->json([
                'data' => $doctors->items(),
                'meta' => [
                    'current_page' => $doctors->currentPage(),
                    'per_page'     => $doctors->perPage(),
                    'total'        => $doctors->total(),
                    'last_page'    => $doctors->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error processing request', 'error' => $e->getMessage()], 400);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            if (! is_numeric($id)) {
                return response()->json(['message' => 'Invalid doctor ID format'], 400);
            }

            $doctor = $this->doctorRepository->with(['specialties', 'attribute_values'])->find($id);

            if (! $doctor) {
                return response()->json(['message' => 'Doctor not found'], 404);
            }

            $startDate = Carbon::now();
            $endDate = $startDate->copy()->addDays(6);

            $response = $doctor->toArray();
            $response['schedule'] = $this->availabilityService->weekScheduleForDoctor((int) $id, $startDate, $endDate);

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }
}
