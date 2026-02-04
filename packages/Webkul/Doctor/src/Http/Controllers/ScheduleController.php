<?php

namespace Webkul\Doctor\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Doctor\Repositories\DoctorRepository;
use Webkul\Doctor\Repositories\ShiftRepository;

class ScheduleController extends Controller
{
    public function __construct(
        protected DoctorRepository $doctorRepository,
        protected ShiftRepository $shiftRepository
    ) {}

    public function index(): View
    {
        return view('doctor::schedules.index');
    }

    public function week(Request $request): JsonResponse
    {
        $start = Carbon::parse($request->query('start', Carbon::now()->startOfWeek()));
        $end = (clone $start)->endOfWeek();

        $doctors = $this->doctorRepository->all();

        $shifts = $this->shiftRepository->findWhere([
            ['date', '>=', $start->toDateString()],
            ['date', '<=', $end->toDateString()],
        ]);

        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $days[] = [
                'date' => $cursor->toDateString(),
                'label' => $cursor->isoFormat('ddd, D MMM'),
            ];
            $cursor->addDay();
        }

        $data = [
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'days' => $days,
            'doctors' => $doctors->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
            ])->values(),
            'shifts' => $shifts->map(fn ($s) => [
                'id' => $s->id,
                'doctor_id' => $s->doctor_id,
                'date' => $s->date->toDateString(),
                'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5),
                'notes' => $s->notes,
            ])->values(),
        ];

        return new JsonResponse($data);
    }

    public function create(): View
    {
        $doctors = $this->doctorRepository->all();

        return view('doctor::schedules.form', compact('doctors'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'doctor_id'  => ['required', 'integer', 'exists:doctors,id'],
            'date'       => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
            'notes'      => ['nullable', 'string'],
        ]);

        $conflict = DB::table('doctor_shifts')
            ->where('doctor_id', $validated['doctor_id'])
            ->where('date', $validated['date'])
            ->where('start_time', '<', $validated['end_time'])
            ->where('end_time', '>', $validated['start_time'])
            ->exists();

        if ($conflict) {
            if ($request->ajax()) {
                return new JsonResponse(['message' => 'Conflicto de horario detectado'], 422);
            }

            return redirect()->back()->withErrors(['start_time' => 'Conflicto de horario detectado'])->withInput();
        }

        $shift = $this->shiftRepository->create($validated);

        if ($request->ajax()) {
            return new JsonResponse([
                'id'         => $shift->id,
                'doctor_id'  => $shift->doctor_id,
                'date'       => $shift->date->toDateString(),
                'start_time' => substr($shift->start_time, 0, 5),
                'end_time'   => substr($shift->end_time, 0, 5),
                'notes'      => $shift->notes,
            ], 201);
        }

        session()->flash('success', 'Turno creado correctamente');

        return redirect()->route('admin.schedules.index');
    }

    public function edit(int $id): View
    {
        $shift = $this->shiftRepository->findOrFail($id);
        $doctors = $this->doctorRepository->all();

        return view('doctor::schedules.form', compact('shift', 'doctors'));
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'doctor_id'  => ['required', 'integer', 'exists:doctors,id'],
            'date'       => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
            'notes'      => ['nullable', 'string'],
        ]);

        $conflict = DB::table('doctor_shifts')
            ->where('id', '!=', $id)
            ->where('doctor_id', $validated['doctor_id'])
            ->where('date', $validated['date'])
            ->where('start_time', '<', $validated['end_time'])
            ->where('end_time', '>', $validated['start_time'])
            ->exists();

        if ($conflict) {
            if ($request->ajax()) {
                return new JsonResponse(['message' => 'Conflicto de horario detectado'], 422);
            }

            return redirect()->back()->withErrors(['start_time' => 'Conflicto de horario detectado'])->withInput();
        }

        $shift = $this->shiftRepository->update($validated, $id);

        if ($request->ajax()) {
            return new JsonResponse([
                'id'         => $shift->id,
                'doctor_id'  => $shift->doctor_id,
                'date'       => $shift->date->toDateString(),
                'start_time' => substr($shift->start_time, 0, 5),
                'end_time'   => substr($shift->end_time, 0, 5),
                'notes'      => $shift->notes,
            ], 200);
        }

        session()->flash('success', 'Turno actualizado correctamente');

        return redirect()->route('admin.schedules.index');
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->shiftRepository->delete($id);

            return new JsonResponse(['message' => 'Turno eliminado correctamente'], 200);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => 'No se pudo eliminar el turno'], 400);
        }
    }
}
