<?php

namespace App\Modules\Scheduling\Policies;

use App\Models\User;
use App\Modules\Scheduling\Models\Workspace;

class WorkspacePolicy
{
    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }
}
