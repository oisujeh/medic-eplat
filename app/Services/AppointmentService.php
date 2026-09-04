<?php

namespace App\Services;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\Priority;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\ScheduleBlock;
use App\Models\ServicePoint;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function __construct(private readonly PatientFlowService $flow) {}

    /**
     * Bookable slots for a provider on a given day, each flagged available or
     * not. Slots come from the provider's weekly templates for that weekday,
     * minus existing appointments, time-off blocks and past times.
     *
     * @return array<int, array{start: Carbon, end: Carbon, available: bool}>
     */
    public function availableSlots(User $provider, ?ServicePoint $servicePoint, CarbonInterface $date): array
    {
        $day = $date->copy()->startOfDay();
        $weekday = $day->dayOfWeek; // 0 = Sunday … 6 = Saturday

        $schedules = $provider->providerSchedules()
            ->where('is_active', true)
            ->where('weekday', $weekday)
            ->when($servicePoint, fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('service_point_id')
                ->orWhere('service_point_id', $servicePoint->id)))
            ->orderBy('start_time')
            ->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        $dayEnd = $day->copy()->endOfDay();
        $booked = Appointment::query()
            ->occupying()
            ->where('provider_id', $provider->id)
            ->inRange($day, $dayEnd)
            ->get(['scheduled_start', 'scheduled_end']);
        $blocks = ScheduleBlock::query()
            ->where('provider_id', $provider->id)
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $day)
            ->get(['starts_at', 'ends_at']);

        $now = now();
        $slots = [];

        foreach ($schedules as $schedule) {
            $cursor = $day->copy()->setTimeFromTimeString($schedule->start_time);
            $end = $day->copy()->setTimeFromTimeString($schedule->end_time);

            while ($cursor->copy()->addMinutes($schedule->slot_minutes)->lessThanOrEqualTo($end)) {
                $slotEnd = $cursor->copy()->addMinutes($schedule->slot_minutes);

                $available = $cursor->greaterThanOrEqualTo($now)
                    && ! $this->overlapsAny($cursor, $slotEnd, $booked, 'scheduled_start', 'scheduled_end')
                    && ! $this->overlapsAny($cursor, $slotEnd, $blocks, 'starts_at', 'ends_at');

                $slots[$cursor->format('H:i')] = [
                    'start' => $cursor->copy(),
                    'end' => $slotEnd,
                    'available' => $available,
                ];

                $cursor = $slotEnd;
            }
        }

        ksort($slots);

        return array_values($slots);
    }

    /**
     * Book an appointment, rejecting provider conflicts and (when the provider
     * has templates for that day) slots outside their availability.
     */
    public function book(
        Patient $patient,
        ServicePoint $servicePoint,
        CarbonInterface $start,
        int $durationMinutes,
        User $actor,
        ?User $provider = null,
        Priority $priority = Priority::Normal,
        AppointmentSource $source = AppointmentSource::Booked,
        ?string $reason = null,
        ?string $note = null,
        ?int $encounterId = null,
    ): Appointment {
        $end = $start->copy()->addMinutes($durationMinutes);

        if ($provider) {
            $this->assertNoConflict($provider, $start, $end);

            if ($source !== AppointmentSource::WalkIn) {
                $this->assertWithinAvailability($provider, $servicePoint, $start, $end);
            }
        }

        return Appointment::create([
            'patient_id' => $patient->id,
            'provider_id' => $provider?->id,
            'service_point_id' => $servicePoint->id,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'duration_minutes' => $durationMinutes,
            'status' => AppointmentStatus::Scheduled,
            'source' => $source,
            'priority' => $priority,
            'reason' => $reason,
            'note' => $note,
            'encounter_id' => $encounterId,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Move an appointment to a new slot (and optionally a new provider).
     */
    public function reschedule(
        Appointment $appointment,
        CarbonInterface $newStart,
        ?User $newProvider = null,
        ?int $durationMinutes = null,
    ): Appointment {
        abort_unless($appointment->status->isOpen(), 422);

        $duration = $durationMinutes ?? $appointment->duration_minutes;
        $end = $newStart->copy()->addMinutes($duration);
        $provider = $newProvider ?? $appointment->provider;

        if ($provider) {
            $this->assertNoConflict($provider, $newStart, $end, ignore: $appointment);
            $this->assertWithinAvailability($provider, $appointment->servicePoint, $newStart, $end);
        }

        $appointment->update([
            'provider_id' => $provider?->id,
            'scheduled_start' => $newStart,
            'scheduled_end' => $end,
            'duration_minutes' => $duration,
        ]);

        return $appointment;
    }

    /**
     * Cancel an appointment.
     */
    public function cancel(Appointment $appointment, User $actor, ?string $reason = null): Appointment
    {
        abort_unless($appointment->status->isOpen(), 422);

        $appointment->update([
            'status' => AppointmentStatus::Cancelled,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        return $appointment;
    }

    /**
     * Mark a scheduled appointment as a no-show.
     */
    public function markNoShow(Appointment $appointment, User $actor): Appointment
    {
        abort_unless($appointment->status->isOpen(), 422);

        $appointment->update(['status' => AppointmentStatus::NoShow]);

        return $appointment;
    }

    /**
     * Check a patient in: place them in the target service point's queue (via
     * the shared patient-flow service) and link the resulting visit/queue entry.
     */
    public function checkIn(Appointment $appointment, User $actor): Appointment
    {
        abort_unless($appointment->status->isOpen(), 422);

        $appointment->loadMissing('patient', 'servicePoint', 'provider');

        $entry = $this->flow->route(
            patient: $appointment->patient,
            servicePoint: $appointment->servicePoint,
            actor: $actor,
            priority: $appointment->priority,
            note: $appointment->note,
            visitReason: $appointment->reason,
            assignedTo: $appointment->provider,
        );

        $appointment->update([
            'status' => AppointmentStatus::CheckedIn,
            'visit_id' => $entry->visit_id,
            'queue_entry_id' => $entry->id,
            'checked_in_by' => $actor->id,
            'checked_in_at' => now(),
        ]);

        return $appointment;
    }

    /**
     * Register a walk-in: create an appointment for now and immediately check
     * the patient into the queue.
     */
    public function walkIn(
        Patient $patient,
        ServicePoint $servicePoint,
        User $actor,
        ?User $provider = null,
        Priority $priority = Priority::Normal,
        int $durationMinutes = 30,
        ?string $reason = null,
        ?string $note = null,
    ): Appointment {
        $appointment = $this->book(
            patient: $patient,
            servicePoint: $servicePoint,
            start: now(),
            durationMinutes: $durationMinutes,
            actor: $actor,
            provider: $provider,
            priority: $priority,
            source: AppointmentSource::WalkIn,
            reason: $reason,
            note: $note,
        );

        return $this->checkIn($appointment, $actor);
    }

    /**
     * Reject a booking that overlaps another of the provider's appointments.
     */
    private function assertNoConflict(User $provider, CarbonInterface $start, CarbonInterface $end, ?Appointment $ignore = null): void
    {
        $clash = Appointment::query()
            ->occupying()
            ->where('provider_id', $provider->id)
            ->inRange($start, $end)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                'scheduled_start' => 'This provider already has an appointment during that time.',
            ]);
        }
    }

    /**
     * Reject a booking outside the provider's availability — but only when the
     * provider actually has templates for that weekday (otherwise booking is
     * unrestricted so the system is usable before schedules are configured).
     */
    private function assertWithinAvailability(User $provider, ?ServicePoint $servicePoint, CarbonInterface $start, CarbonInterface $end): void
    {
        $slots = $this->availableSlots($provider, $servicePoint, $start);

        if ($slots === []) {
            return; // no template for that day → unrestricted
        }

        $fits = collect($slots)->contains(fn ($slot) => $slot['start']->equalTo($start)
            && $slot['end']->greaterThanOrEqualTo($end)
            && $slot['available']);

        if (! $fits) {
            throw ValidationException::withMessages([
                'scheduled_start' => 'That time is outside the provider\'s available slots.',
            ]);
        }
    }

    /**
     * Whether [start, end) overlaps any record's [startKey, endKey) window.
     *
     * @param  Collection<int, Model>  $records
     */
    private function overlapsAny(CarbonInterface $start, CarbonInterface $end, $records, string $startKey, string $endKey): bool
    {
        return $records->contains(fn ($r) => $start->lessThan($r->{$endKey}) && $end->greaterThan($r->{$startKey}));
    }
}
