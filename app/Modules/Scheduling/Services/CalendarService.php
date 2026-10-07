<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Events\CalendarScheduleUpdated;
use App\Modules\Scheduling\Models\Workspace;
use App\Modules\Scheduling\Repositories\CalendarRepository;
use Illuminate\Support\Facades\DB;

class CalendarService
{
    public function __construct(private CalendarRepository $repository, private IntervalValidator $intervals) {}

    public function replace(Workspace $workspace, array $days): void
    {
        DB::transaction(function () use ($workspace, $days) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            foreach ($days as $day) {
                $this->repository->replace($workspace, $day['date'], $this->intervals->merge($day['intervals']));
            }
            CalendarScheduleUpdated::dispatch($workspace->id, $workspace->id);
        });
    }

    public function reset(Workspace $workspace, string $date): void
    {
        DB::transaction(function () use ($workspace, $date) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $this->repository->reset($workspace, $date);
            CalendarScheduleUpdated::dispatch($workspace->id, $workspace->id);
        });
    }
}
