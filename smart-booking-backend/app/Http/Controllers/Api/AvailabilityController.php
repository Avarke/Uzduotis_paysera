<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientBooking;
use App\Models\WorkRule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{


    public function index(Request $request): JsonResponse
    {

        // validate input
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();

        // no past dates
        if ($date->lt(now()->startOfDay())) {
            return response()->json([
                'date' => $date->toDateString(),
                'slots' => [],
                'message' => 'Past dates are not bookable.',
            ], 200);
        }

        // day_of_week convention: 0=Sunday..6=Saturday
        $dayOfWeek = $date->dayOfWeek;


        // get all the rules for that specific day
        $rules = WorkRule::query()
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time')
            ->get();

        if ($rules->isEmpty()) {
            return response()->json([
                'date' => $date->toDateString(),
                'slots' => [],
            ]);
        }

        // get already booked times
        $booked = ClientBooking::query()
            ->whereDate('date', $date->toDateString())
            ->pluck('start_time')
            ->map(fn ($t) => substr((string)$t, 0, 5)) // "HH:MM:SS" -> "HH:MM"
            ->all();

        // turn into a fast lookup set
        $bookedSet = array_flip($booked);

        $slots = [];

        foreach ($rules as $rule) {
            $slotMinutes = (int)$rule->slot_minutes;

            $ruleStart = $this->normalizeTimeToSeconds((string)$rule->start_time);
            $ruleEnd   = $this->normalizeTimeToSeconds((string)$rule->end_time);

            $start = Carbon::createFromFormat('Y-m-d H:i:s', $date->toDateString() . ' ' . $ruleStart);
            $end   = Carbon::createFromFormat('Y-m-d H:i:s', $date->toDateString() . ' ' . $ruleEnd);


            if ($date->isToday()) {
                $now = now();
                if ($end->lte($now)) { // is end <= now?
                    continue;
                }

                // if the rule has started, move on to the next slot
                if ($start->lt($now)) {
                    $start = $this->ceilToSlot($now, $slotMinutes, $rule->start_time, $date);
                    if ($start->gte($end)) {
                        continue;
                    }
                }
            }

            // generate slots
            for ($t = $start->copy(); $t->lt($end); $t->addMinutes($slotMinutes)) {
                $hhmm = $t->format('H:i');

                // check if the time is booked
                if (!isset($bookedSet[$hhmm])) {
                    $slots[] = $hhmm;
                }
            }
        }

        $slots = array_values(array_unique($slots));
        sort($slots);

        return response()->json([
            'date' => $date->toDateString(),
            'slots' => $slots,
        ]);
    }

    private function ceilToSlot(Carbon $now, int $slotMinutes, string $ruleStartTime, Carbon $date): Carbon
    {
        $ruleStartTime = $this->normalizeTimeToSeconds($ruleStartTime);
        $ruleStart = Carbon::createFromFormat('Y-m-d H:i:s', $date->toDateString() . ' ' . $ruleStartTime);

        if ($now->lte($ruleStart)) {
            return $ruleStart;
        }

        $diff = $ruleStart->diffInMinutes($now);
        $remainder = $diff % $slotMinutes;
        $minutesToAdd = $remainder === 0 ? 0 : ($slotMinutes - $remainder);

        $candidate = $now->copy()->addMinutes($minutesToAdd)->second(0);

        // ensure not before ruleStart
        return $candidate->lt($ruleStart) ? $ruleStart : $candidate;
    }

    private function normalizeTimeToSeconds(string $time): string
    {
        // "18:00" -> "18:00:00", "18:00:00" stays as-is
        return strlen($time) === 5 ? ($time . ':00') : $time;
    }

}
