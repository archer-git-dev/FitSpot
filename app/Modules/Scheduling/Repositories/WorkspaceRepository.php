<?php

namespace App\Modules\Scheduling\Repositories;

use App\Models\User;
use App\Modules\Scheduling\Models\Workspace;

class WorkspaceRepository
{
    public function forUser(User $user): Workspace
    {
        return Workspace::where('owner_id', $user->id)->firstOrFail();
    }

    public function create(User $user, string $slug): Workspace
    {
        return Workspace::create(['owner_id' => $user->id, 'name' => $user->name, 'slug' => $slug]);
    }

    public function update(Workspace $workspace, array $data): Workspace
    {
        $workspace->update($data);

        return $workspace->refresh();
    }
}
