<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Events\ServiceCreated;
use App\Modules\Scheduling\Events\ServiceStatusChanged;
use App\Modules\Scheduling\Events\ServiceUpdated;
use App\Modules\Scheduling\Models\TrainingService;
use App\Modules\Scheduling\Models\Workspace;
use App\Modules\Scheduling\Repositories\TrainingServiceRepository;
use Illuminate\Support\Facades\DB;

class TrainingServiceService
{
    public function __construct(private TrainingServiceRepository $repository) {}

    public function save(Workspace $workspace, array $data, ?TrainingService $service = null): TrainingService
    {
        return DB::transaction(function () use ($workspace, $data, $service) {
            $result = $this->repository->save($workspace, $data, $service);
            if ($service) {
                ServiceUpdated::dispatch($workspace->id, $result->id);
            } else {
                ServiceCreated::dispatch($workspace->id, $result->id);
            }

            return $result;
        });
    }

    public function status(Workspace $workspace, TrainingService $service, bool $active): void
    {
        DB::transaction(function () use ($workspace, $service, $active) {
            $this->repository->save($workspace, ['active' => $active], $service);
            ServiceStatusChanged::dispatch($workspace->id, $service->id);
        });
    }
}
