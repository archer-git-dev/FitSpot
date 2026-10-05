<?php

namespace App\Modules\Scheduling\Policies;

use App\Models\User;
use App\Modules\Scheduling\Models\TrainingService;

class TrainingServicePolicy
{
    public function update(User $user, TrainingService $service): bool
    {
        return $user->workspace?->id === $service->workspace_id;
    }
}
