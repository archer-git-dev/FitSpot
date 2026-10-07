<?php

namespace App\Modules\Scheduling\Repositories;

use App\Modules\Scheduling\Models\Workspace;

class CalendarRepository
{
    public function range(Workspace $workspace, string $from, string $to)
    {
        return $workspace->calendarDays()->whereBetween('date', [$from, $to])->with('intervals')->get();
    }

    public function replace(Workspace $workspace, string $date, array $intervals): void
    {
        $day = $workspace->calendarDays()->firstOrCreate(['date' => $date]);
        $day->intervals()->delete();
        $day->intervals()->createMany($intervals);
    }

    public function reset(Workspace $workspace, string $date): void
    {
        $workspace->calendarDays()->where('date', $date)->delete();
    }
}
