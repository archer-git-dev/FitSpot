<?php

namespace App\Modules\Scheduling\Repositories;

use App\Modules\Scheduling\Models\TrainingService;
use App\Modules\Scheduling\Models\Workspace;

class TrainingServiceRepository
{
    public function find(Workspace $workspace, int $id): TrainingService
    {
        return $workspace->services()->findOrFail($id);
    }

    public function save(Workspace $workspace, array $data, ?TrainingService $service = null): TrainingService
    {
        if ($service) {
            $service->update($data);

            return $service->refresh();
        }

        return $workspace->services()->create($data);
    }
}
