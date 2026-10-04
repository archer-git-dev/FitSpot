<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Http\Requests\ProfileRequest;
use App\Modules\Scheduling\Http\Requests\RulesRequest;
use App\Modules\Scheduling\Http\Requests\ScheduleRequest;
use App\Modules\Scheduling\Http\Requests\ServiceRequest;
use App\Modules\Scheduling\Repositories\TrainingServiceRepository;
use App\Modules\Scheduling\Repositories\WorkspaceRepository;
use App\Modules\Scheduling\Services\ScheduleService;
use App\Modules\Scheduling\Services\TrainingServiceService;
use App\Modules\Scheduling\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PanelController extends Controller
{
    public function __construct(private WorkspaceRepository $workspaces, private TrainingServiceRepository $services) {}

    public function show(Request $request, string $section = 'overview')
    {
        abort_unless(in_array($section, ['overview', 'profile', 'schedule', 'services', 'rules', 'access']), 404);
        $workspace = $this->workspaces->forUser($request->user());
        Gate::authorize('update', $workspace);
        $workspace->load(['intervals', 'rules', 'services']);
        $data = $workspace->toArray();
        unset($data['photo_path']);
        $data['photo_url'] = $workspace->photo_path ? route('workspace.photo') : null;
        $warnings = [];
        foreach ($workspace->services as $service) {
            $needed = $service->duration + $workspace->rules->buffer_before + $workspace->rules->buffer_after;
            if (! $workspace->intervals->contains(fn ($i) => $i->end - $i->start >= $needed)) {
                $warnings[] = $service->id;
            }
        }

        return Inertia::render('Panel', ['section' => $section, 'workspace' => $data, 'timezones' => \DateTimeZone::listIdentifiers(), 'publicUrl' => rtrim(config('fitspot.public_url'), '/').'/p/'.$workspace->slug, 'warnings' => $warnings, 'telegramLinked' => $request->user()->telegram()->exists(), 'hasPassword' => filled($request->user()->password)]);
    }

    public function profile(ProfileRequest $request, WorkspaceService $service)
    {
        $w = $this->workspaces->forUser($request->user());
        Gate::authorize('update', $w);
        $service->update($w, $request->safe()->except(['photo', 'remove_photo']), $request->file('photo'), $request->boolean('remove_photo'));

        return back(303)->with('success', 'Профиль сохранён.');
    }

    public function schedule(ScheduleRequest $request, ScheduleService $service)
    {
        $w = $this->workspaces->forUser($request->user());
        Gate::authorize('update', $w);
        $service->replace($w, $request->validated('intervals'));

        return back(303)->with('success', 'График сохранён.');
    }

    public function rules(RulesRequest $request, ScheduleService $service)
    {
        $w = $this->workspaces->forUser($request->user());
        Gate::authorize('update', $w);
        $service->rules($w, $request->validated());

        return back(303)->with('success', 'Правила сохранены.');
    }

    public function saveService(ServiceRequest $request, TrainingServiceService $service, ?int $id = null)
    {
        $w = $this->workspaces->forUser($request->user());
        $item = $id ? $this->services->find($w, $id) : null;
        if ($item) {
            Gate::authorize('update', $item);
        }
        $service->save($w, $request->serviceData(), $item);

        return back(303)->with('success', 'Услуга сохранена.');
    }

    public function serviceStatus(Request $request, int $id, TrainingServiceService $service)
    {
        $w = $this->workspaces->forUser($request->user());
        $item = $this->services->find($w, $id);
        Gate::authorize('update', $item);
        $data = $request->validate(['active' => 'required|boolean']);
        $service->status($w, $item, $data['active']);

        return back(303)->with('success', 'Статус услуги изменён.');
    }

    public function photo(Request $request)
    {
        $w = $this->workspaces->forUser($request->user());
        Gate::authorize('update', $w);
        abort_unless($w->photo_path && Storage::disk('local')->exists($w->photo_path), 404);

        return response()->file(Storage::disk('local')->path($w->photo_path), ['Cache-Control' => 'private, no-store']);
    }
}
