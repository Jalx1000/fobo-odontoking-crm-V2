<?php

namespace Webkul\Doctor\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Doctor\Repositories\DoctorRepository;

class DoctorController extends Controller
{
    public function __construct(
        protected DoctorRepository $doctorRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $page = (int) $request->query('page', 1);
            $limit = (int) $request->query('limit', 10);
            
            if ($limit > 100) $limit = 100;
            if ($limit < 1) $limit = 10;

            // Apply filters
            $query = $this->doctorRepository->getModel()->newQuery();

            if ($request->has('specialty')) {
                $query->whereHas('specialties', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->query('specialty') . '%');
                });
            }

            if ($request->has('name')) {
                $query->where('name', 'like', '%' . $request->query('name') . '%');
            }

            // Pagination
            $doctors = $query->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'data' => $doctors->items(),
                'meta' => [
                    'current_page' => $doctors->currentPage(),
                    'per_page' => $doctors->perPage(),
                    'total' => $doctors->total(),
                    'last_page' => $doctors->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error processing request', 'error' => $e->getMessage()], 400);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            if (!is_numeric($id)) {
                return response()->json(['message' => 'Invalid doctor ID format'], 400);
            }

            $doctor = $this->doctorRepository->with(['specialties'])->find($id);

            if (!$doctor) {
                return response()->json(['message' => 'Doctor not found'], 404);
            }

            return response()->json($doctor);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
