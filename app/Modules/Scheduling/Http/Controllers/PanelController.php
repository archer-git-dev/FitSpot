<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Http\Requests\CalendarRequest;
use App\Modules\Scheduling\Http\Requests\ProfileRequest;
use App\Modules\Scheduling\Http\Requests\RulesRequest;
use App\Modules\Scheduling\Http\Requests\ScheduleRequest;
use App\Modules\Scheduling\Http\Requests\ServiceRequest;
use App\Modules\Scheduling\Repositories\TrainingServiceRepository;
use App\Modules\Scheduling\Repositories\WorkspaceRepository;
use App\Modules\Scheduling\Services\CalendarService;
use App\Modules\Scheduling\Services\ScheduleResolver;
use App\Modules\Scheduling\Services\ScheduleService;
use App\Modules\Scheduling\Services\TrainingServiceService;
use App\Modules\Scheduling\Services\WorkspaceService;
use Carbon\CarbonImmutable;
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
        $resolver = app(ScheduleResolver::class);
        $today = CarbonImmutable::now($workspace->timezone)->startOfDay();
        $availability = $resolver->range($workspace, $today->format('Y-m-d'), $today->addDays($workspace->rules->horizon_days - 1)->format('Y-m-d'));
        $hasAvailability = collect($availability)->contains(fn ($day) => count($day['intervals']) > 0);
        $calendar = null;
        if ($section === 'schedule') {
            $range = $request->validate(['from' => 'sometimes|required|date_format:Y-m-d', 'to' => 'required_with:from|date_format:Y-m-d|after_or_equal:from']);
            $from = $range['from'] ?? $today->startOfMonth()->startOfWeek()->format('Y-m-d');
            $to = $range['to'] ?? CarbonImmutable::parse($from)->addDays(41)->format('Y-m-d');
            abort_if(CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 41, 422);
            $calendar = $resolver->range($workspace, $from, $to);
        }
        $warnings = [];
        foreach ($workspace->services as $service) {
            $needed = $service->duration + $workspace->rules->buffer_before + $workspace->rules->buffer_after;
            if (! collect($availability)->contains(fn ($day) => collect($day['intervals'])->contains(fn ($i) => $needed <= $i['end'] - $i['start']))) {
                $warnings[] = $service->id;
            }
        }

        return Inertia::render('Panel', ['calendar' => $calendar, 'today' => $today->format('Y-m-d'), 'hasAvailability' => $hasAvailability, 'section' => $section, 'workspace' => $data, 'timezones' => \DateTimeZone::listIdentifiers(), 'publicUrl' => rtrim(config('fitspot.public_url'), '/').'/p/'.$workspace->slug, 'warnings' => $warnings, 'telegramLinked' => $request->user()->telegram()->exists(), 'hasPassword' => filled($request->user()->password)]);
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

    public function calendarRange(Request $request, ScheduleResolver $resolver)
    {
        $range = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        abort_if(CarbonImmutable::parse($range['from'])->diffInDays(CarbonImmutable::parse($range['to'])) > 41, 422);
        $w = $this->workspaces->forUser($request->user());
        Gate::authorize('update', $w);

        return response()->json(['days' => $resolver->range($w, $range['from'], $range['to'])]);
    }

    public function calendar(CalendarRequest $request, CalendarService $service)
    {
        $w = $this->workspaces->forUser($request->user());
        Gate::authorize('update', $w);
        $service->replace($w, $request->validated('days'));

        return back(303)->with('success', 'Расписание выбранных дат сохранено.');
    }

    public function resetDate(Request $request, string $date, CalendarService $service)
    {
        validator(['date' => $date], ['date' => 'required|date_format:Y-m-d'])->validate();
        $w = $this->workspaces->forUser($request->user());
        Gate::authorize('update', $w);
        $service->reset($w, $date);

        return back(303)->with('success', 'Дата возвращена к недельному шаблону.');
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
