<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Http\Requests\BookAppointmentRequest;
use App\Http\Requests\RescheduleAppointmentRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointments) {}

    /**
     * The scheduling calendar — day, week or agenda.
     */
    public function index(Request $request): Response
    {
        $view = in_array($request->string('view')->value(), ['day', 'week', 'agenda'], true)
            ? $request->string('view')->value()
            : 'day';
        $date = $request->date('date') ?? today();

        [$from, $to] = match ($view) {
            'week' => [$date->copy()->startOfWeek(Carbon::SUNDAY), $date->copy()->startOfWeek(Carbon::SUNDAY)->addWeek()],
            'agenda' => [today(), today()->copy()->addMonth()],
            default => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
        };

        $providerId = $request->integer('provider_id') ?: null;
        $servicePointId = $request->integer('service_point_id') ?: null;

        // Optional patient to pre-fill the booking dialog (e.g. arriving from a
        // patient profile), independent of the calendar filters.
        $prefill = null;
        if ($request->filled('patient_id') && ($patient = Patient::find($request->integer('patient_id')))) {
            $prefill = [
                'id' => $patient->id,
                'name' => $patient->fullName(),
                'file_number' => $patient->file_number,
            ];
        }

        $appointments = Appointment::query()
            ->where('scheduled_start', '>=', $from)
            ->where('scheduled_start', '<', $to)
            ->when($view === 'agenda', fn ($q) => $q->occupying())
            ->when($providerId, fn ($q) => $q->where('provider_id', $providerId))
            ->when($servicePointId, fn ($q) => $q->where('service_point_id', $servicePointId))
            ->with(['patient:id,file_number,surname,first_name,other_names', 'provider:id,name', 'servicePoint:id,name'])
            ->orderBy('scheduled_start')
            ->get()
            ->map(fn (Appointment $a) => $this->present($a));

        return Inertia::render('appointments/Index', [
            'appointments' => $appointments,
            'filters' => [
                'view' => $view,
                'date' => $date->toDateString(),
                'provider_id' => $providerId,
                'service_point_id' => $servicePointId,
            ],
            'providers' => $this->providers(),
            'servicePoints' => ServicePoint::active()->get(['id', 'name', 'slug']),
            'priorities' => collect(Priority::cases())->map(fn (Priority $p) => ['value' => $p->value, 'label' => $p->label()]),
            'prefill' => $prefill,
        ]);
    }

    /**
     * Bookable slots for a provider on a day (JSON, for the booking dialog).
     */
    public function slots(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_id' => ['required', Rule::exists('users', 'id')],
            'service_point_id' => ['nullable', Rule::exists('service_points', 'id')],
            'date' => ['required', 'date'],
        ]);

        $provider = User::findOrFail($data['provider_id']);
        $servicePoint = isset($data['service_point_id']) ? ServicePoint::find($data['service_point_id']) : null;
        $date = Carbon::parse($data['date']);

        $slots = collect($this->appointments->availableSlots($provider, $servicePoint, $date))
            ->map(fn (array $slot) => [
                'start' => $slot['start']->toIso8601String(),
                'label' => $slot['start']->format('h:i A'),
                'available' => $slot['available'],
            ]);

        return response()->json(['slots' => $slots]);
    }

    /**
     * Patient typeahead for the booking dialog (JSON).
     */
    public function patientSearch(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->value());

        $patients = Patient::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('file_number', 'like', "%{$q}%")
                ->orWhere('surname', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%")
                ->orWhere('other_names', 'like', "%{$q}%")))
            ->orderBy('surname')
            ->limit(10)
            ->get()
            ->map(fn (Patient $p) => [
                'id' => $p->id,
                'name' => $p->fullName(),
                'file_number' => $p->file_number,
            ]);

        return response()->json(['patients' => $patients]);
    }

    /**
     * Book an appointment.
     */
    public function store(BookAppointmentRequest $request): RedirectResponse
    {
        $this->appointments->book(
            patient: Patient::findOrFail($request->integer('patient_id')),
            servicePoint: ServicePoint::findOrFail($request->integer('service_point_id')),
            start: Carbon::parse($request->input('scheduled_start')),
            durationMinutes: $request->integer('duration_minutes'),
            actor: $request->user(),
            provider: $request->filled('provider_id') ? User::find($request->integer('provider_id')) : null,
            priority: Priority::from($request->input('priority', Priority::Normal->value)),
            reason: $request->input('reason'),
            note: $request->input('note'),
            encounterId: $request->integer('encounter_id') ?: null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Appointment booked.']);

        return back();
    }

    /**
     * Register a walk-in and place the patient straight into the queue.
     */
    public function walkIn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', Rule::exists('patients', 'id')],
            'service_point_id' => ['required', Rule::exists('service_points', 'id')->where('is_active', true)],
            'provider_id' => ['nullable', Rule::exists('users', 'id')],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'reason' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->appointments->walkIn(
            patient: Patient::findOrFail($data['patient_id']),
            servicePoint: ServicePoint::findOrFail($data['service_point_id']),
            actor: $request->user(),
            provider: isset($data['provider_id']) ? User::find($data['provider_id']) : null,
            priority: Priority::from($data['priority'] ?? Priority::Normal->value),
            reason: $data['reason'] ?? null,
            note: $data['note'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Walk-in checked in.']);

        return back();
    }

    /**
     * Reschedule (or reassign) an appointment.
     */
    public function update(RescheduleAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $this->appointments->reschedule(
            appointment: $appointment,
            newStart: Carbon::parse($request->input('scheduled_start')),
            newProvider: $request->filled('provider_id') ? User::find($request->integer('provider_id')) : null,
            durationMinutes: $request->integer('duration_minutes') ?: null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Appointment rescheduled.']);

        return back();
    }

    /**
     * Check the patient in (routes them into the target queue).
     */
    public function checkIn(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->appointments->checkIn($appointment, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Patient checked in.']);

        return back();
    }

    /**
     * Cancel an appointment.
     */
    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->appointments->cancel($appointment, $request->user(), $request->input('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Appointment cancelled.']);

        return back();
    }

    /**
     * Mark an appointment as a no-show.
     */
    public function noShow(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->appointments->markNoShow($appointment, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Marked as no-show.']);

        return back();
    }

    /**
     * Present an appointment for the calendar.
     *
     * @return array<string, mixed>
     */
    private function present(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'source' => $appointment->source->value,
            'source_label' => $appointment->source->label(),
            'priority' => $appointment->priority->value,
            'reason' => $appointment->reason,
            'note' => $appointment->note,
            'start' => $appointment->scheduled_start->toIso8601String(),
            'end' => $appointment->scheduled_end->toIso8601String(),
            'duration' => $appointment->duration_minutes,
            'time_label' => $appointment->scheduled_start->format('h:i A'),
            'date' => $appointment->scheduled_start->toDateString(),
            'can_check_in' => $appointment->status->isOpen(),
            'patient' => [
                'id' => $appointment->patient->id,
                'name' => $appointment->patient->fullName(),
                'file_number' => $appointment->patient->file_number,
                'url' => route('patients.show', $appointment->patient_id),
            ],
            'provider' => $appointment->provider?->name,
            'provider_id' => $appointment->provider_id,
            'service_point' => $appointment->servicePoint->name,
            'service_point_id' => $appointment->service_point_id,
        ];
    }

    /**
     * Users who can deliver care (clinical / nursing staff, plus full-access
     * users) and are therefore bookable as providers.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function providers(): Collection
    {
        return User::query()
            ->active()
            ->where(fn ($q) => $q
                ->whereHas('roles.modules', fn ($m) => $m->whereIn('modules.slug', ['clinical', 'nursing']))
                ->orWhereHas('roles', fn ($r) => $r->where('grants_all_modules', true)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]);
    }
}
