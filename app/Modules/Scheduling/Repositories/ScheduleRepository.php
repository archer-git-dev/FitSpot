<?php

namespace App\Modules\Scheduling\Repositories;

use App\Modules\Scheduling\Models\Workspace;

class ScheduleRepository
{
    public function replace(Workspace $workspace, array $intervals): void
    {
        Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
        $workspace->intervals()->delete();
        $workspace->intervals()->createMany($intervals);
    }

    public function rules(Workspace $workspace, array $data): void
    {
        $workspace->rules()->update($data);
    }
}
