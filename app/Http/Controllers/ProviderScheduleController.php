<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProviderScheduleRequest;
use App\Models\ProviderSchedule;
use App\Models\ScheduleBlock;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProviderScheduleController extends Controller
{
    /**
     * Manage provider weekly availability and time-off blocks.
     */
    public function index(): Response
    {
        $providers = User::query()
            ->active()
            ->where(fn ($q) => $q
                ->whereHas('roles.modules', fn ($m) => $m->whereIn('modules.slug', ['clinical', 'nursing']))
                ->orWhereHas('roles', fn ($r) => $r->where('grants_all_modules', true)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]);

        $schedules = ProviderSchedule::query()
            ->with('servicePoint:id,name')
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->map(fn (ProviderSchedule $s) => [
                'id' => $s->id,
                'provider_id' => $s->provider_id,
                'service_point_id' => $s->service_point_id,
                'service_point' => $s->servicePoint?->name,
                'weekday' => $s->weekday,
                'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5),
                'slot_minutes' => $s->slot_minutes,
                'is_active' => $s->is_active,
            ]);

        $blocks = ScheduleBlock::query()
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->get()
            ->map(fn (ScheduleBlock $b) => [
                'id' => $b->id,
                'provider_id' => $b->provider_id,
                'starts_at' => $b->starts_at->toIso8601String(),
                'ends_at' => $b->ends_at->toIso8601String(),
                'starts_label' => $b->starts_at->isoFormat('ddd D MMM, h:mm a'),
                'ends_label' => $b->ends_at->isoFormat('h:mm a'),
                'reason' => $b->reason,
            ]);

        return Inertia::render('appointments/Schedules', [
            'providers' => $providers,
            'schedules' => $schedules,
            'blocks' => $blocks,
            'servicePoints' => ServicePoint::active()->get(['id', 'name']),
        ]);
    }

    /**
     * Add a weekly availability row.
     */
    public function storeSchedule(StoreProviderScheduleRequest $request): RedirectResponse
    {
        ProviderSchedule::create([...$request->validated(), 'is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Availability added.']);

        return back();
    }

    /**
     * Update a weekly availability row.
     */
    public function updateSchedule(StoreProviderScheduleRequest $request, ProviderSchedule $schedule): RedirectResponse
    {
        $schedule->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Availability updated.']);

        return back();
    }

    /**
     * Remove a weekly availability row.
     */
    public function deleteSchedule(ProviderSchedule $schedule): RedirectResponse
    {
        $schedule->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Availability removed.']);

        return back();
    }

    /**
     * Add a time-off block.
     */
    public function storeBlock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider_id' => ['required', Rule::exists('users', 'id')],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        ScheduleBlock::create([...$data, 'created_by' => $request->user()->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Time-off added.']);

        return back();
    }

    /**
     * Remove a time-off block.
     */
    public function deleteBlock(ScheduleBlock $block): RedirectResponse
    {
        $block->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Time-off removed.']);

        return back();
    }
}
