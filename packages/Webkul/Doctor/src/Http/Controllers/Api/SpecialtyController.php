<?php

namespace Webkul\Doctor\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Doctor\Repositories\SpecialtyRepository;

class SpecialtyController extends Controller
{
    public function __construct(
        protected SpecialtyRepository $specialtyRepository
    ) {}

    public function index(): JsonResponse
    {
        try {
            $specialties = $this->specialtyRepository->all();

            return response()->json([
                'data' => $specialties
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
