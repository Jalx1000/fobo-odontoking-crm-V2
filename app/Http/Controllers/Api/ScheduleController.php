<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Webkul\User\Models\User;
use Webkul\Activity\Models\Activity;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class ScheduleController extends Controller
{
    public function getHorarios(Request $request)
    {
        $query = User::query()->whereNotNull('horario')->where('status', 1);

        if ($request->has('doctorId')) {
            $query->where('id', $request->doctorId);
        }

        $users = $query->get([
            'id as doctorId',
            'name as doctorName',
            'horario',
        ]);

        if ($users->isEmpty()) {
            return response()->json(['message' => 'No se encontraron horarios para los criterios de búsqueda especificados.'], 404);
        }

        $horarios = $users->map(function ($user) {
            return [
                'doctorId'   => $user->doctorId,
                'doctorName' => $user->doctorName,
                'horarioRaw' => $user->horario,
            ];
        });

        return response()->json($horarios);
    }

    public function getDisponibilidad(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'doctorId' => 'required|integer',
            'date'     => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $doctorId = $request->input('doctorId');
        $date = Carbon::createFromFormat('Y-m-d', $request->input('date'))->startOfDay();

        $doctor = User::find($doctorId);

        if (! $doctor || ! $doctor->horario) {
            return response()->json(['message' => 'Doctor no encontrado o sin horario definido.'], 404);
        }

        $workingHours = $this->parseWorkingHours($doctor->horario, $date);

        if (empty($workingHours)) {
            return response()->json(['message' => 'El doctor no trabaja en la fecha especificada.'], 404);
        }

        $appointments = Activity::where('user_id', $doctorId)
            ->whereDate('schedule_from', $date)
            ->get(['schedule_from', 'schedule_to']);

        $bookedPeriods = $appointments->map(function ($appt) {
            return CarbonPeriod::create($appt->schedule_from, $appt->schedule_to);
        });

        $availableSlots = [];

        foreach ($workingHours as $workPeriod) {
            $availability = $workPeriod;

            foreach ($bookedPeriods as $bookedPeriod) {
                if ($availability->overlaps($bookedPeriod)) {
                    $diff = $availability->diff($bookedPeriod);
                    $availability = $diff[0] ?? null; // Asumimos que la resta puede dar 0, 1 o 2 periodos

                    if (!$availability) break; // El bloque de trabajo está completamente ocupado
                }
            }

            if ($availability) {
                $availableSlots[] = [
                    'doctorId'  => $doctorId,
                    'date'      => $date->toDateString(),
                    'startTime' => $availability->getStartDate()->format('H:i'),
                    'endTime'   => $availability->getEndDate()->format('H:i'),
                ];
            }
        }

        return response()->json($availableSlots);
    }

    private function parseWorkingHours(string $horarioText, Carbon $date): array
    {
        $dayOfWeekName = $date->locale('es')->dayName;
        $lines = explode("\n", $horarioText);
        $workingPeriods = [];

        foreach ($lines as $line) {
            if (stripos($line, $dayOfWeekName) !== false) {
                preg_match_all('/(\d{2}:\d{2})-(\d{2}:\d{2})/i', $line, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $start = Carbon::createFromFormat('Y-m-d H:i', $date->toDateString() . ' ' . $match[1]);
                    $end = Carbon::createFromFormat('Y-m-d H:i', $date->toDateString() . ' ' . $match[2]);
                    $workingPeriods[] = CarbonPeriod::create($start, $end);
                }
                break;
            }
        }

        return $workingPeriods;
    }
}
