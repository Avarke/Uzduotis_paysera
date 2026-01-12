<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class WorkRuleController extends Controller
{
    public function index(): JsonResponse
    {
        $rules = WorkRule::query()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return response()->json($rules);
    }



    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        if ($resp = $this->assertSlotFitsWindow($data)) {
            return $resp;
        }

        if ($this->overlapsExisting($data['day_of_week'], $data['start_time'], $data['end_time'])) {
            return response()->json([
                'message' => 'Work rule overlaps an existing rule for that weekday.',
            ], 422);
        }

        $rule = WorkRule::create($data);

        return response()->json($rule, 201);
    }




    public function destroy(WorkRule $work_rule): JsonResponse
    {
        $work_rule->delete();
        return response()->json(null, 204);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);
    }


    // overlap check

    private function overlapsExisting(int $dayOfWeek, string $start, string $end, ?int $ignoreId = null): bool
    {
        $q = WorkRule::query()
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start);

        if ($ignoreId !== null) {
            $q->where('id', '!=', $ignoreId);
        }

        return $q->exists();
    }

    private function timeToMinutes(string $time): int
    {
        // "HH:MM" (validated) -> minutes since 00:00
        [$h, $m] = array_map('intval', explode(':', $time));
        return $h * 60 + $m;
    }

    private function assertSlotFitsWindow(array $data): ?JsonResponse
    {
        $startMin = $this->timeToMinutes($data['start_time']);
        $endMin   = $this->timeToMinutes($data['end_time']);
        $window   = $endMin - $startMin; // end_time is after start_time

        if ((int)$data['slot_minutes'] > $window) {
            return response()->json([
                'message' => 'slot_minutes cannot be longer than the working time window.',
            ], 422);
        }

        return null;
    }


}
