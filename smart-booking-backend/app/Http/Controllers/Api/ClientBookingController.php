<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientBooking;
use App\Models\Service;
use App\Models\WorkRule;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientBookingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')],
            'client_email' => ['required', 'email'],
        ]);

        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        if ($date->lt(now()->startOfDay())) {
            return response()->json(['message' => 'Cannot book past dates.'], 422);
        }

        $service = Service::query()->where('id', $data['service_id'])->first();
        if (!$service || !$service->is_active) {
            return response()->json(['message' => 'Service is not available.'], 422);
        }

        $dayOfWeek = $date->dayOfWeek;
        $rules = WorkRule::query()
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time')
            ->get();

        if ($rules->isEmpty()) {
            return response()->json(['message' => 'No working hours for this day.'], 422);
        }

        $startTime = $data['start_time'] . ':00'; // "HH:MM:SS"


        // find a rule that allows this start time
        $matchingRule = $rules->first(function ($rule) use ($startTime, $date) {
            $ruleStart = $this->normalizeTimeToSeconds((string)$rule->start_time);
            $ruleEnd   = $this->normalizeTimeToSeconds((string)$rule->end_time);

            // within [start, end)
            if (!($ruleStart <= $startTime && $startTime < $ruleEnd)) {
                return false;
            }

            // alignment to slot grid
            $ruleStartDt = Carbon::createFromFormat('Y-m-d H:i:s', $date->toDateString() . ' ' . $ruleStart);
            $slotDt      = Carbon::createFromFormat('Y-m-d H:i:s', $date->toDateString() . ' ' . $startTime);

            $diff = $ruleStartDt->diffInMinutes($slotDt);
            return $diff % (int)$rule->slot_minutes === 0;
        });

        if (!$matchingRule) {
            return response()->json(['message' => 'Selected time is not available.'], 422);
        }

        //compute end_time
        $start = Carbon::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $data['start_time']);
        $end = $start->copy()->addMinutes((int)$service->duration_minutes);

        // if booking is for today, prevent booking in the past (relative to now)
        if ($date->isToday() && $start->lt(now())) {
            return response()->json(['message' => 'Cannot book a past time today.'], 422);
        }

        try {
            $booking = ClientBooking::create([
                'service_id' => (int)$data['service_id'],
                'date' => $data['date'],
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
                'client_email' => $data['client_email'],
            ]);
        } catch (QueryException $e) {
            // SQLite unique constraint => double booking
            if (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'unique')) {
                return response()->json(['message' => 'This time slot is already booked.'], 409);
            }
            throw $e;
        }

        return response()->json([
            'id' => $booking->id,
            'date' => $booking->date->toDateString(),
            'start_time' => substr($booking->start_time, 0, 5),
            'end_time' => $booking->end_time ? substr($booking->end_time, 0, 5) : null,
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
            ],
            'client_email' => $booking->client_email,
        ], 201);
    }


    private function normalizeTimeToSeconds(string $time): string
    {
        return strlen($time) === 5 ? ($time . ':00') : $time;
    }

}
