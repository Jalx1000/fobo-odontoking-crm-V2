<?php

namespace Webkul\Doctor\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Doctor\Repositories\ShiftRepository;

class DoctorAvailabilityService
{
    public const SLOT_DURATION_MINUTES = 60;

    public function __construct(
        protected ShiftRepository $shiftRepository
    ) {}

    /**
     * Retorna disponibilidad de los próximos 7 días para múltiples doctores de una sola pasada.
     * Usado por el índice paginado de la API.
     *
     * @return array<int, Collection> keyed by doctor_id
     */
    public function forDoctors(array $doctorIds, Carbon $startDate, Carbon $endDate): array
    {
        $shifts = DB::table('doctor_shifts')
            ->whereIn('doctor_id', $doctorIds)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->groupBy('doctor_id');

        $bookings = DB::table('activities')
            ->join('doctor_activities', 'activities.id', '=', 'doctor_activities.activity_id')
            ->whereIn('doctor_activities.doctor_id', $doctorIds)
            ->whereBetween('activities.schedule_from', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s'),
            ])
            ->select('doctor_activities.doctor_id', 'activities.schedule_from', 'activities.schedule_to')
            ->get()
            ->groupBy('doctor_id');

        $result = [];
        foreach ($doctorIds as $doctorId) {
            $result[$doctorId] = $this->buildSlots(
                $shifts->get($doctorId, collect()),
                $bookings->get($doctorId, collect())
            );
        }

        return $result;
    }

    /**
     * Retorna el schedule detallado día a día para un único doctor.
     * Usado por el endpoint show() de la API.
     *
     * @return array<int, array{date: string, slots: array}>
     */
    public function weekScheduleForDoctor(int $doctorId, Carbon $startDate, Carbon $endDate): array
    {
        $shifts = $this->shiftRepository->findWhere([
            ['doctor_id', '=', $doctorId],
            ['date', '>=', $startDate->toDateString()],
            ['date', '<=', $endDate->toDateString()],
        ]);

        $bookings = DB::table('activities')
            ->join('doctor_activities', 'activities.id', '=', 'doctor_activities.activity_id')
            ->where('doctor_activities.doctor_id', $doctorId)
            ->whereBetween('activities.schedule_from', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->endOfDay()->format('Y-m-d H:i:s'),
            ])
            ->select('activities.schedule_from', 'activities.schedule_to')
            ->get();

        $schedule = [];
        $cursor = $startDate->copy();

        for ($i = 0; $i < 7; $i++) {
            $dateStr = $cursor->toDateString();

            $dayShifts = $shifts->filter(function ($shift) use ($dateStr) {
                $shiftDate = $shift->date instanceof Carbon
                    ? $shift->date->toDateString()
                    : $shift->date;

                return $shiftDate === $dateStr;
            });

            $daySlots = [];

            foreach ($dayShifts as $shift) {
                $shiftStart = Carbon::parse($dateStr.' '.$shift->start_time);
                $shiftEnd = Carbon::parse($dateStr.' '.$shift->end_time);
                $slotCursor = $shiftStart->copy();

                while ($slotCursor->lt($shiftEnd)) {
                    $slotEnd = $slotCursor->copy()->addMinutes(self::SLOT_DURATION_MINUTES);

                    if ($slotEnd->gt($shiftEnd)) {
                        break;
                    }

                    $status = 'available';
                    foreach ($bookings as $booking) {
                        $bStart = Carbon::parse($booking->schedule_from);
                        $bEnd = Carbon::parse($booking->schedule_to);

                        if ($slotCursor->lt($bEnd) && $slotEnd->gt($bStart)) {
                            $status = 'booked';
                            break;
                        }
                    }

                    $daySlots[] = [
                        'start_time' => $slotCursor->format('H:i'),
                        'end_time'   => $slotEnd->format('H:i'),
                        'status'     => $status,
                    ];

                    $slotCursor->addMinutes(self::SLOT_DURATION_MINUTES);
                }
            }

            usort($daySlots, fn ($a, $b) => strcmp($a['start_time'], $b['start_time']));

            $schedule[] = [
                'date'  => $dateStr,
                'slots' => $daySlots,
            ];

            $cursor->addDay();
        }

        return $schedule;
    }

    /**
     * Construye slots libres a partir de bloques de turno y citas existentes.
     */
    protected function buildSlots(Collection $shiftBlocks, Collection $doctorBookings): Collection
    {
        $slots = collect();

        foreach ($shiftBlocks as $block) {
            $current = Carbon::parse($block->date.' '.$block->start_time);
            $end = Carbon::parse($block->date.' '.$block->end_time);

            while ($current->copy()->addMinutes(self::SLOT_DURATION_MINUTES)->lte($end)) {
                $slotEnd = $current->copy()->addMinutes(self::SLOT_DURATION_MINUTES);

                $isBooked = $doctorBookings->contains(function ($booking) use ($current, $slotEnd) {
                    $bStart = Carbon::parse($booking->schedule_from);
                    $bEnd = Carbon::parse($booking->schedule_to);

                    return $current->lt($bEnd) && $slotEnd->gt($bStart);
                });

                if (! $isBooked) {
                    $slots->push([
                        'id'         => $block->id,
                        'doctor_id'  => $block->doctor_id,
                        'date'       => $block->date,
                        'start_time' => $current->format('H:i:s'),
                        'end_time'   => $slotEnd->format('H:i:s'),
                    ]);
                }

                $current->addMinutes(self::SLOT_DURATION_MINUTES);
            }
        }

        return $slots;
    }
}
