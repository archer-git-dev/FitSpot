<?php

namespace App\Modules\Scheduling\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

class TrainerProfileUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public string $event_id;

    public string $occurred_at;

    public function __construct(public int $workspace_id, public int $object_id)
    {
        $this->event_id = (string) Str::uuid();
        $this->occurred_at = now()->toIso8601String();
    }
}
