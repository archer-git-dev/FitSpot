<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Events\BookingRulesUpdated;
use App\Modules\Scheduling\Events\WeeklyScheduleUpdated;
use App\Modules\Scheduling\Models\Workspace;
use App\Modules\Scheduling\Repositories\ScheduleRepository;
use Illuminate\Support\Facades\DB;

class ScheduleService
{
    public function __construct(private ScheduleRepository $repository) {}

    public function replace(Workspace $workspace, array $intervals): void
    {
        usort($intervals, fn ($a, $b) => [$a['day'], $a['start']] <=> [$b['day'], $b['start']]);
        $merged = [];
        foreach ($intervals as $item) {
            $last = count($merged) - 1;
            if ($last >= 0 && $merged[$last]['day'] == $item['day'] && $merged[$last]['end'] == $item['start']) {
                $merged[$last]['end'] = $item['end'];
            } else {
                $merged[] = $item;
            }
        }
        DB::transaction(function () use ($workspace, $merged) {
            $this->repository->replace($workspace, $merged);
            WeeklyScheduleUpdated::dispatch($workspace->id, $workspace->id);
        });
    }

    public function rules(Workspace $workspace, array $data): void
    {
        DB::transaction(function () use ($workspace, $data) {
            $this->repository->rules($workspace, $data);
            BookingRulesUpdated::dispatch($workspace->id, $workspace->id);
        });
    }
}
