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

        if ($this->overlapsExisting($data['day_of_week'], $data['start_time'], $data['end_time'])) {
            return response()->json([
                'message' => 'Work rule overlaps an existing rule for that weekday.',
            ], 422);
        }

        $rule = WorkRule::create($data);

        return response()->json($rule, 201);
    }

    

    public function update(Request $request, WorkRule $work_rule): JsonResponse
    {
        $data = $this->validated($request);

        if ($this->overlapsExisting($data['day_of_week'], $data['start_time'], $data['end_time'], $work_rule->id)) {
            return response()->json([
                'message' => 'Work rule overlaps an existing rule for that weekday.',
            ], 422);
        }

        $work_rule->update($data);

        return response()->json($work_rule);
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
            'slot_minutes' => ['required', 'integer', Rule::in([15, 30, 60])],
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




}
