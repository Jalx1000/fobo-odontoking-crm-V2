<?php

namespace Webkul\Admin\Http\Controllers\Activity;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Activity\Repositories\FileRepository;
use Webkul\Admin\DataGrids\Activity\ActivityDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Webkul\Admin\Exceptions\AppointmentException;
use Webkul\Admin\Services\AppointmentService;

class ActivityController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected ActivityRepository  $activityRepository,
        protected FileRepository      $fileRepository,
        protected AttributeRepository $attributeRepository,
        protected LeadRepository      $leadRepository,
        protected PipelineRepository  $pipelineRepository,
        protected PersonRepository    $personRepository,
        protected AppointmentService  $appointmentService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('admin::activities.index');
    }

    /**
     * Returns a listing of the resource.
     */
    public function get(): JsonResponse
    {
        if (request()->get('view_type') === 'calendar' && request()->get('calendar_mode') === 'doctor') {
            $view = request()->get('calendar_view', 'week'); // 'week' | 'month' | 'day'
            if ($view === 'month') {
                $cursor = request()->get('start')
                    ? Carbon::parse(request()->get('start'))->startOfMonth()
                    : Carbon::now()->startOfMonth();
                $start = $cursor->copy()->startOfMonth();
                $end = $cursor->copy()->endOfMonth();
            } elseif ($view === 'day') {
                $cursor = request()->get('start')
                    ? Carbon::parse(request()->get('start'))->startOfDay()
                    : Carbon::now()->startOfDay();
                $start = $cursor->copy()->startOfDay();
                $end = $cursor->copy()->endOfDay();
            } else {
                $start = request()->get('start')
                    ? Carbon::parse(request()->get('start'))->startOfWeek()
                    : Carbon::now()->startOfWeek();
                $end = (clone $start)->endOfWeek();
            }
            $startDate = $start->copy()->setTime(0, 0, 1)->format('Y-m-d H:i:s');
            $endDate = $end->copy()->setTime(23, 59, 59)->format('Y-m-d H:i:s');

            $doctors = DB::table('doctors')
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get();

            $prefix = DB::getTablePrefix();

            $appointments = DB::table('activities')
                ->leftJoin('doctor_activities', 'activities.id', '=', 'doctor_activities.activity_id')
                ->leftJoin('activity_participants', 'activities.id', '=', 'activity_participants.activity_id')
                ->leftJoin('persons as p', 'activity_participants.person_id', '=', 'p.id')
                ->select([
                    'activities.id',
                    'activities.title',
                    'activities.type',
                    'activities.is_done',
                    'activities.schedule_from as start',
                    'activities.schedule_to as end',
                    'activities.location',
                    'doctor_activities.doctor_id',
                    DB::raw('GROUP_CONCAT(' . $prefix . 'p.name SEPARATOR ", ") as participants'),
                ])
                ->whereBetween('activities.schedule_from', [$startDate, $endDate])
                ->whereIn('activities.type', (function () {
                    $allowed = ['call', 'meeting', 'lunch', 'time_off'];
                    $types = request()->get('activity_types');
                    if (is_array($types)) {
                        $filtered = array_values(array_intersect($types, $allowed));
                        return count($filtered) ? $filtered : $allowed;
                    }
                    return $allowed;
                })())
                ->groupBy('activities.id')
                ->orderBy('activities.schedule_from')
                ->get();

            $pipeline = $this->pipelineRepository->getDefaultPipeline();
            $stages = $pipeline ? $pipeline->stages()->get() : [];

            // Enrich appointments with calculated duration and doctor names if needed
            // For now, doctor_id is present. We can join doctors table if we want multiple doctor names per appointment,
            // but the current structure assumes one main doctor per doctor_activities entry or we group them.
            // Given the current query structure (leftJoin doctor_activities), if an activity has multiple doctors, it might duplicate rows or we need group_concat.
            // Let's assume for this view we want to show the doctor name.

            // Re-query to get better details including doctor name and lead status/pipeline stage if applicable.
            $appointments = DB::table('activities')
                ->leftJoin('doctor_activities', 'activities.id', '=', 'doctor_activities.activity_id')
                ->leftJoin('doctors', 'doctor_activities.doctor_id', '=', 'doctors.id')
                ->leftJoin('activity_participants', 'activities.id', '=', 'activity_participants.activity_id')
                ->leftJoin('persons as p', 'activity_participants.person_id', '=', 'p.id')
                ->leftJoin('lead_activities', 'activities.id', '=', 'lead_activities.activity_id')
                ->leftJoin('leads', 'lead_activities.lead_id', '=', 'leads.id')
                ->leftJoin('product_activities', 'activities.id', '=', 'product_activities.activity_id')
                ->leftJoin('products', 'product_activities.product_id', '=', 'products.id')
                ->select([
                    'activities.id',
                    'activities.title',
                    'activities.type',
                    'activities.comment',
                    'activities.is_done',
                    'activities.schedule_from as start',
                    'activities.schedule_to as end',
                    'doctor_activities.doctor_id',
                    'doctors.name as doctor_name',
                    'leads.id as lead_id',
                    'leads.lead_pipeline_stage_id',
                    'products.id as product_id',
                    'products.name as product_name',
                    DB::raw('MAX(' . $prefix . 'p.id) as person_id'),
                    DB::raw('MAX(' . $prefix . 'p.name) as person_name'),
                ])
                ->whereBetween('activities.schedule_from', [$startDate, $endDate])
                ->whereIn('activities.type', (function () {
                    $allowed = ['call', 'meeting', 'lunch', 'time_off'];
                    $types = request()->get('activity_types');
                    if (is_array($types)) {
                        $filtered = array_values(array_intersect($types, $allowed));
                        return count($filtered) ? $filtered : $allowed;
                    }
                    return $allowed;
                })())
                ->groupBy([
                    'activities.id',
                    'activities.title',
                    'activities.type',
                    'activities.comment',
                    'activities.is_done',
                    'activities.schedule_from',
                    'activities.schedule_to',
                    'doctor_activities.doctor_id',
                    'doctors.name',
                    'leads.id',
                    'leads.lead_pipeline_stage_id',
                    'products.id',
                    'products.name',
                ])
                ->orderBy('activities.schedule_from')
                ->get();

            $availability = DB::table('doctor_shifts')
                ->select(['id', 'doctor_id', 'date', 'start_time', 'end_time'])
                ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->orderBy('doctor_id')
                ->orderBy('date')
                ->orderBy('start_time')
                ->get();

            // Transformar bloques de disponibilidad en slots de 30 minutos
            $availability = $this->generateSlotsFromAvailability($availability);

            return response()->json([
                'range'        => [
                    'start' => $start->format('Y-m-d'),
                    'end'   => $end->format('Y-m-d'),
                ],
                'days'         => $view === 'month'
                    ? collect(range(0, $start->daysInMonth - 1))->map(function ($i) use ($start) {
                        $d = $start->copy()->addDays($i);
                        return [
                            'date'  => $d->format('Y-m-d'),
                            'label' => $d->locale(app()->getLocale())->isoFormat('ddd D/MM'),
                        ];
                    })
                    : ($view === 'day'
                        ? collect([[
                            'date'  => $start->format('Y-m-d'),
                            'label' => $start->locale(app()->getLocale())->isoFormat('ddd D/MM'),
                        ]])
                        : collect(range(0, 6))->map(function ($i) use ($start) {
                            $d = $start->copy()->addDays($i);
                            return [
                                'date'  => $d->format('Y-m-d'),
                                'label' => $d->locale(app()->getLocale())->isoFormat('ddd D/MM'),
                            ];
                        })
                    ),
                'doctors'      => $doctors,
                'appointments' => $appointments,
                'availability' => $availability,
                'calendar_view' => $view,
                'stages'       => $stages,
            ]);
        }

        if (! request()->has('view_type')) {
            return datagrid(ActivityDataGrid::class)->process();
        }

        $startDate = request()->get('startDate')
            ? Carbon::createFromTimeString(request()->get('startDate') . ' 00:00:01')
            : Carbon::now()->startOfWeek()->format('Y-m-d H:i:s');

        $endDate = request()->get('endDate')
            ? Carbon::createFromTimeString(request()->get('endDate') . ' 23:59:59')
            : Carbon::now()->endOfWeek()->format('Y-m-d H:i:s');

        $activities = $this->activityRepository->getActivities([$startDate, $endDate])->toArray();

        return response()->json([
            'activities' => $activities,
        ]);
    } 

    /**
     * Store a newly created resource in storage.
     */
    public function store(): RedirectResponse|JsonResponse
    {
        $this->validate(request(), [
            'type'          => 'required',
            'comment'       => 'required_if:type,note',
            'schedule_from' => 'required_unless:type,note,file',
            'schedule_to'   => 'required_unless:type,note,file',
            'file'          => 'required_if:type,file',
        ]);

        // --- UNIFICACIÓN: Si es una consulta (meeting), usamos la lógica robusta ---
        if (request('type') === 'meeting') {
            $participants = request()->input('participants');
            $doctorId = ($participants['doctors'] ?? [])[0] ?? null;
            $personId = ($participants['persons'] ?? [])[0] ?? null;

            if (!$doctorId || !$personId) {
                $msg = "Debes seleccionar al menos un Doctor y un Paciente para agendar una consulta.";
                if (request()->ajax()) return response()->json(['message' => $msg], 422);
                session()->flash('error', $msg);
                return redirect()->back();
            }

            // Adaptamos los datos para el procesador unificado
            $appointmentData = [
                'person'        => ['id' => $personId],
                'doctor_id'     => $doctorId,
                'product_id'    => request('product_id'),
                'lead_id'       => request('lead_id') ?: null,
                'title'         => request('title'),
                'reason'        => request('comment'),
                'schedule_from' => request('schedule_from'),
                'schedule_to'   => request('schedule_to'),
            ];

            return $this->processMedicalAppointment($appointmentData);
        }

        Event::dispatch('activity.create.before');

        $activity = $this->activityRepository->create(array_merge(request()->all(), [
            'is_done' => request('type') == 'note' ? 1 : 0,
            'user_id' => auth()->guard('user')->user()->id,
        ]));

        Event::dispatch('activity.create.after', $activity);

        if (request()->ajax()) {
            return response()->json([
                'data'    => new ActivityResource($activity),
                'message' => trans('admin::app.activities.create-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.activities.create-success'));

        return redirect()->back();
    }

    public function storeAppointment(): JsonResponse
    {
        $this->validate(request(), [
            'person'                 => 'required|array',
            'person.id'              => 'nullable|exists:persons,id',
            'person.name'            => 'required|string|max:255',
            'person.phone'           => 'required_without:person.id|nullable|string|min:7|max:20',
            'doctor_id'              => 'required|exists:doctors,id',
            'product_id'             => 'required|exists:products,id',
            'date'                   => 'required|date_format:Y-m-d',
            'start_time'             => 'required|date_format:H:i',
            'end_time'               => 'nullable|date_format:H:i',
            'duration_minutes'       => 'nullable|integer|min:5|max:480',
            'reason'                 => 'nullable|string',
        ]);

        $date = request('date');
        $startTime = request('start_time');
        $scheduleFrom = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$startTime}");

        if (! empty(request('duration_minutes'))) {
            $scheduleTo = $scheduleFrom->copy()->addMinutes((int) request('duration_minutes'));
        } else {
            $scheduleTo = Carbon::createFromFormat('Y-m-d H:i', "{$date} " . request('end_time'));
        }

        $appointmentData = [
            'person'        => request('person'),
            'doctor_id'     => request('doctor_id'),
            'product_id'    => request('product_id'),
            'lead_id'       => request('lead_id') ?: null,
            'reason'        => request('reason'),
            'schedule_from' => $scheduleFrom->format('Y-m-d H:i:s'),
            'schedule_to'   => $scheduleTo->format('Y-m-d H:i:s'),
        ];

        return $this->processMedicalAppointment($appointmentData);
    }

    /**
     * Procesador unificado de consultas médicas. Delega en AppointmentService.
     */
    private function processMedicalAppointment(array $data): JsonResponse|RedirectResponse
    {
        try {
            $result = $this->appointmentService->process($data);

            if (request()->ajax()) {
                // Devolver ActivityResource para que el evento on-activity-added
                // reciba el formato correcto y pueda renderizar en el panel de actividades.
                $activity = $this->activityRepository
                    ->with(['participants', 'doctors', 'leads'])
                    ->find($result['activity_id']);

                return response()->json([
                    'data'    => new ActivityResource($activity),
                    'message' => $result['message'],
                ]);
            }

            session()->flash('success', $result['message']);

            return redirect()->back();
        } catch (AppointmentException $e) {
            if (request()->ajax()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'details' => $e->getDetails(),
                ], 422);
            }

            session()->flash('error', $e->getMessage());

            return redirect()->back();
        } catch (\Exception $e) {
            Log::error('AppointmentService error: ' . $e->getMessage());

            $msg = "Error interno al crear la cita.";

            if (request()->ajax()) {
                return response()->json(['message' => $msg], 500);
            }

            session()->flash('error', $msg);

            return redirect()->back();
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $activity = $this->activityRepository->with(['participants', 'doctors'])->findOrFail($id);

        $leadId = old('lead_id') ?? optional($activity->leads()->first())->id;

        $lookUpEntityData = $this->attributeRepository->getLookUpEntity('leads', $leadId);

        return view('admin::activities.edit', compact('activity', 'lookUpEntityData'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update($id): RedirectResponse|JsonResponse
    {
        Event::dispatch('activity.update.before', $id);

        $data = request()->all();

        $activity = $this->activityRepository->update($data, $id);

        /**
         * We will not use `empty` directly here because `lead_id` can be a blank string
         * from the activity form. However, on the activity view page, we are only updating the
         * `is_done` field, so `lead_id` will not be present in that case.
         */
        if (isset($data['lead_id'])) {
            $lead = $this->leadRepository->find($data['lead_id']);

            if ($lead) {
                $activity->leads()->sync([$data['lead_id']]);

                if (isset($data['lead_pipeline_stage_id'])) {
                    $lead->update(['lead_pipeline_stage_id' => $data['lead_pipeline_stage_id']]);
                }
            } else {
                $activity->leads()->sync([]);
            }
        }

        if (isset($data['product_id'])) {
            $activity->products()->sync(
                ! empty($data['product_id'])
                    ? [$data['product_id']]
                    : []
            );
        }

        if (isset($data['doctor_id'])) {
            $activity->doctors()->sync(
                ! empty($data['doctor_id'])
                    ? [$data['doctor_id']]
                    : []
            );
        }

        Event::dispatch('activity.update.after', $activity);

        if (request()->ajax()) {
            return response()->json([
                'data'    => new ActivityResource($activity),
                'message' => trans('admin::app.activities.update-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.activities.update-success'));

        return redirect()->route('admin.activities.index');
    }

    /**
     * Mass Update the specified resources.
     */
    public function massUpdate(MassUpdateRequest $massUpdateRequest): JsonResponse
    {
        $activities = $this->activityRepository->findWhereIn('id', $massUpdateRequest->input('indices'));

        foreach ($activities as $activity) {
            Event::dispatch('activity.update.before', $activity->id);

            $activity = $this->activityRepository->update([
                'is_done' => $massUpdateRequest->input('value'),
            ], $activity->id);

            Event::dispatch('activity.update.after', $activity);
        }

        return response()->json([
            'message' => trans('admin::app.activities.mass-update-success'),
        ]);
    }

    /**
     * Download file from storage.
     */
    public function download(int $id): StreamedResponse
    {
        try {
            $file = $this->fileRepository->findOrFail($id);

            return Storage::download($file->path);
        } catch (\Exception $exception) {
            abort(404);
        }
    }

    /*
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $activity = $this->activityRepository->findOrFail($id);

        try {
            Event::dispatch('activity.delete.before', $id);

            $activity?->delete($id);

            Event::dispatch('activity.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.activities.destroy-success'),
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.activities.destroy-failed'),
            ], 400);
        }
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $activities = $this->activityRepository->findWhereIn('id', $massDestroyRequest->input('indices'));

        try {
            foreach ($activities as $activity) {
                Event::dispatch('activity.delete.before', $activity->id);

                $this->activityRepository->delete($activity->id);

                Event::dispatch('activity.delete.after', $activity->id);
            }

            return response()->json([
                'message' => trans('admin::app.activities.mass-destroy-success'),
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.activities.mass-delete-failed'),
            ], 400);
        }
    }

    /**
     * Transforma bloques continuos de disponibilidad en slots de tiempo fijos.
     *
     * @param  \Illuminate\Support\Collection  $availability
     * @param  int  $durationMinutes
     * @return \Illuminate\Support\Collection
     */
    private function generateSlotsFromAvailability($availability, $durationMinutes = 30)
    {
        $slots = collect();

        foreach ($availability as $block) {
            $current = Carbon::parse($block->date . ' ' . $block->start_time);
            $end = Carbon::parse($block->date . ' ' . $block->end_time);

            while ($current->copy()->addMinutes($durationMinutes)->lte($end)) {
                $slots->push([
                    'id'         => $block->id, // Mantenemos el ID del bloque original
                    'doctor_id'  => $block->doctor_id,
                    'date'       => $block->date,
                    'start_time' => $current->format('H:i:s'),
                    'end_time'   => $current->copy()->addMinutes($durationMinutes)->format('H:i:s'),
                ]);

                $current->addMinutes($durationMinutes);
            }
        }

        return $slots;
    }
}
