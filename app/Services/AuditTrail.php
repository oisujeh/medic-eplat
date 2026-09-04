<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Contracts\AuditableRecord;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonSerializable;
use Stringable;

/**
 * Writes the append-only, hash-chained audit trail.
 *
 * Every entry hashes its own content together with the previous entry's hash,
 * so removing, editing or reordering rows behind the application's back
 * breaks the chain, which verify() detects.
 */
class AuditTrail
{
    private bool $paused = false;

    /**
     * Record an action in the trail.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(
        string $action,
        ?Model $subject = null,
        ?int $patientId = null,
        ?array $old = null,
        ?array $new = null,
        ?string $label = null,
    ): ?AuditLog {
        if ($this->paused) {
            return null;
        }

        $user = auth()->user();
        $request = $this->currentRequest();

        $fields = [
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->name,
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'label' => $label ?? $this->labelFor($subject),
            'patient_id' => $patientId,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 512) : null,
            'route' => $request?->route()?->getName(),
            'occurred_at' => now(),
        ];

        // The previous row is locked for the duration of the write so two
        // concurrent requests cannot both chain onto the same predecessor.
        return DB::transaction(function () use ($fields) {
            $previous = AuditLog::query()
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first(['id', 'hash']);

            $log = new AuditLog;
            $log->forceFill($fields);
            $log->previous_hash = $previous?->hash;
            $log->hash = $this->hashFor($this->hashableFields($log), $log->previous_hash);
            $log->save();

            return $log;
        });
    }

    /**
     * Record a model lifecycle event with the attributes that changed.
     */
    public function recordModelEvent(string $event, Model&AuditableRecord $model): void
    {
        if ($this->paused) {
            return;
        }

        $excluded = $model->auditExcludedAttributes();

        [$old, $new] = match ($event) {
            'created' => [null, $this->only($model->getAttributes(), $excluded)],
            'deleted' => [$this->only($model->getRawOriginal(), $excluded), null],
            default => $this->diff($model, $excluded),
        };

        // An update that touched nothing worth recording (a refreshed
        // remember token, a timestamp bump) is not an audit event.
        if ($event === 'updated' && empty($new)) {
            return;
        }

        $this->record($event, $model, $model->auditPatientId(), $old, $new);
    }

    /**
     * Record that part of a patient's chart was opened.
     */
    public function recordAccess(Model&AuditableRecord $model): void
    {
        $this->record(AuditLog::ACTION_VIEWED, $model, $model->auditPatientId());
    }

    /**
     * Run a callback without writing to the trail (bulk imports, fixtures).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutRecording(callable $callback): mixed
    {
        $previous = $this->paused;
        $this->paused = true;

        try {
            return $callback();
        } finally {
            $this->paused = $previous;
        }
    }

    /**
     * Walk the whole chain and recompute every hash.
     *
     * @param  (callable(int): void)|null  $onProgress  Called with the number of entries checked so far.
     * @return array{intact: bool, checked: int, broken_id: int|null}
     */
    public function verify(?callable $onProgress = null): array
    {
        $previousHash = null;
        $checked = 0;
        $brokenId = null;

        AuditLog::query()
            ->orderBy('id')
            ->chunk(500, function ($logs) use (&$previousHash, &$checked, &$brokenId, $onProgress) {
                foreach ($logs as $log) {
                    $expected = $this->hashFor($this->hashableFields($log), $previousHash);

                    if ($log->previous_hash !== $previousHash || $log->hash !== $expected) {
                        $brokenId = $log->id;

                        return false;
                    }

                    $previousHash = $log->hash;
                    $checked++;
                }

                if ($onProgress) {
                    $onProgress($checked);
                }

                return true;
            });

        return ['intact' => $brokenId === null, 'checked' => $checked, 'broken_id' => $brokenId];
    }

    /**
     * The hash of an entry's content chained to its predecessor.
     *
     * @param  array<string, mixed>  $fields
     */
    public function hashFor(array $fields, ?string $previousHash): string
    {
        $payload = json_encode($fields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        return hash('sha256', ($previousHash ?? '')."\n".$payload);
    }

    /**
     * The entry fields covered by the hash, in a fixed order.
     *
     * @return array<string, mixed>
     */
    private function hashableFields(AuditLog $log): array
    {
        return [
            'user_id' => $log->user_id,
            'user_name' => $log->user_name,
            'action' => $log->action,
            'auditable_type' => $log->auditable_type,
            'auditable_id' => $log->auditable_id,
            'label' => $log->label,
            'patient_id' => $log->patient_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'route' => $log->route,
            'occurred_at' => $log->occurred_at->format('Y-m-d H:i:s.u'),
        ];
    }

    private function labelFor(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        return $subject instanceof AuditableRecord
            ? $subject->auditLabel()
            : class_basename($subject).' #'.$subject->getKey();
    }

    /**
     * Before/after values for the attributes an update changed.
     *
     * @param  list<string>  $excluded
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function diff(Model $model, array $excluded): array
    {
        $new = $this->only($model->getChanges(), $excluded);
        $old = [];

        foreach (array_keys($new) as $key) {
            $old[$key] = $this->normalise($model->getRawOriginal($key));
        }

        return [$old, $new];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $excluded
     * @return array<string, mixed>
     */
    private function only(array $attributes, array $excluded): array
    {
        $kept = [];

        foreach ($attributes as $key => $value) {
            if (! in_array($key, $excluded, true)) {
                $kept[$key] = $this->normalise($value);
            }
        }

        return $kept;
    }

    /**
     * Reduce an attribute to something JSON can hold and re-hash identically.
     */
    private function normalise(mixed $value): mixed
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof BackedEnum => $value->value,
            $value instanceof JsonSerializable => $value->jsonSerialize(),
            $value instanceof Stringable => (string) $value,
            is_object($value) => get_object_vars($value),
            default => $value,
        };
    }

    private function currentRequest(): ?Request
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        return app()->bound('request') ? app('request') : null;
    }
}
