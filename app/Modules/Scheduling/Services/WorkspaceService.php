<?php

namespace App\Modules\Scheduling\Services;

use App\Models\User;
use App\Modules\Scheduling\Events\TrainerProfileUpdated;
use App\Modules\Scheduling\Events\WorkspaceCreated;
use App\Modules\Scheduling\Models\Workspace;
use App\Modules\Scheduling\Repositories\WorkspaceRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkspaceService
{
    public function __construct(private WorkspaceRepository $repository) {}

    public function create(User $user): Workspace
    {
        $base = substr(Str::slug($user->name), 0, min(26, 39 - strlen((string) $user->id)));
        if (strlen($base) < 3 || in_array($base, config('fitspot.reserved_slugs'))) {
            $base = 'trainer';
        }
        // User ID makes concurrent signups deterministic; unique DB constraint is the final guard.
        $slug = $base.'-'.$user->id;
        if (Workspace::where('slug', $slug)->exists()) {
            $slug = 'trainer-'.$user->id.'-'.Str::lower(Str::random(6));
        }
        $workspace = $this->repository->create($user, $slug);
        $workspace->rules()->create([]);
        WorkspaceCreated::dispatch($workspace->id, $workspace->id);

        return $workspace;
    }

    public function update(Workspace $workspace, array $data, ?UploadedFile $photo = null, bool $remove = false): void
    {
        $new = null;
        $old = $workspace->photo_path;
        if ($photo) {
            $bytes = file_get_contents($photo->getRealPath());
            $image = imagecreatefromstring($bytes);
            if (! $image) {
                throw new \RuntimeException('Invalid image');
            }
            $ratio = min(1, 1024 / imagesx($image), 1024 / imagesy($image));
            $resized = imagecreatetruecolor(max(1, (int) (imagesx($image) * $ratio)), max(1, (int) (imagesy($image) * $ratio)));
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), imagesx($image), imagesy($image));
            ob_start();
            imagepng($resized);
            $encoded = ob_get_clean();
            imagedestroy($resized);
            imagedestroy($image);
            $new = 'trainer-photos/'.Str::uuid().'.png';
            if (! Storage::disk('local')->put($new, $encoded)) {
                throw new \RuntimeException('Cannot store image');
            }
            $data['photo_path'] = $new;
        } elseif ($remove) {
            $data['photo_path'] = null;
        }
        try {
            DB::transaction(function () use ($workspace, $data) {
                $this->repository->update($workspace, $data);
                TrainerProfileUpdated::dispatch($workspace->id, $workspace->id);
            });
        } catch (\Throwable $e) {
            if ($new) {
                Storage::disk('local')->delete($new);
            }
            throw $e;
        }
        if (($new || $remove) && $old) {
            Storage::disk('local')->delete($old);
        }
    }
}
