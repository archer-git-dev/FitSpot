<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Models\Workspace;
use App\Modules\Scheduling\Repositories\CalendarRepository;
use Carbon\CarbonImmutable;

class ScheduleResolver
{
    public function __construct(private CalendarRepository $repository) {}

    public function range(Workspace $workspace, string $from, string $to): array
    {
        $workspace->loadMissing('intervals');
        $overrides = $this->repository->range($workspace, $from, $to)->keyBy('date');
        $days = [];
        for ($date = CarbonImmutable::createFromFormat('!Y-m-d', $from, $workspace->timezone); $date->format('Y-m-d') <= $to; $date = $date->addDay()) {
            $key = $date->format('Y-m-d');
            $override = $overrides->get($key);
            $items = $override ? $override->intervals : $workspace->intervals->where('day', $date->isoWeekday());
            $days[] = ['date' => $key, 'day' => $date->isoWeekday(), 'override' => $override !== null, 'intervals' => $items->map(fn ($i) => ['start' => $i->start, 'end' => $i->end])->values()->all()];
        }

        return $days;
    }

    public function forDate(Workspace $workspace, string $date): array
    {
        return $this->range($workspace, $date, $date)[0]['intervals'];
    }
}
