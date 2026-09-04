<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * Browse the audit trail, newest first, with filters for who, what,
     * which patient and when.
     */
    public function index(Request $request): Response
    {
        $request->validate([
            'user' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $filters = [
            'search' => trim((string) $request->string('search')),
            'action' => (string) $request->string('action'),
            'user' => $request->integer('user') ?: null,
            'patient' => trim((string) $request->string('patient')),
            'type' => (string) $request->string('type'),
            'from' => (string) $request->string('from'),
            'to' => (string) $request->string('to'),
        ];

        $entries = AuditLog::query()
            ->with(['patient:id,file_number,surname,first_name,other_names'])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $term = "%{$filters['search']}%";

                $query->where(function (Builder $q) use ($term) {
                    $q->where('label', 'like', $term)
                        ->orWhere('user_name', 'like', $term)
                        ->orWhere('route', 'like', $term)
                        ->orWhere('ip_address', 'like', $term);
                });
            })
            ->when($filters['action'] !== '', fn (Builder $q) => $q->where('action', $filters['action']))
            ->when($filters['user'], fn (Builder $q) => $q->where('user_id', $filters['user']))
            ->when($filters['type'] !== '', fn (Builder $q) => $q->where('auditable_type', $filters['type']))
            ->when($filters['patient'] !== '', function (Builder $query) use ($filters) {
                $term = "%{$filters['patient']}%";

                $query->whereIn('patient_id', Patient::query()
                    ->select('id')
                    ->where(function (Builder $q) use ($term) {
                        $q->where('file_number', 'like', $term)
                            ->orWhere('surname', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('other_names', 'like', $term);
                    }));
            })
            ->when($filters['from'] !== '', fn (Builder $q) => $q->where('occurred_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when($filters['to'] !== '', fn (Builder $q) => $q->where('occurred_at', '<=', Carbon::parse($filters['to'])->endOfDay()))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $entry) => $this->row($entry));

        return Inertia::render('admin/Audit', [
            'entries' => $entries,
            'filters' => $filters,
            'actions' => AuditLog::ACTIONS,
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name]),
            'types' => AuditLog::query()
                ->whereNotNull('auditable_type')
                ->distinct()
                ->orderBy('auditable_type')
                ->pluck('auditable_type')
                ->map(fn (string $type) => ['value' => $type, 'label' => AuditLog::labelForType($type)])
                ->sortBy('label')
                ->values(),
            'summary' => [
                'total' => AuditLog::query()->count(),
                'first_at' => AuditLog::query()->min('occurred_at'),
                'last_at' => AuditLog::query()->max('occurred_at'),
            ],
        ]);
    }

    /**
     * Recompute the hash chain on demand and report whether it is intact.
     */
    public function verify(): RedirectResponse
    {
        $result = $this->audit->verify();

        Inertia::flash('toast', $result['intact']
            ? ['type' => 'success', 'message' => "Audit trail intact: {$result['checked']} entries verified."]
            : ['type' => 'error', 'message' => "Audit trail broken at entry #{$result['broken_id']}. That entry, or one before it, was altered or removed outside the application."]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AuditLog $entry): array
    {
        $patient = $entry->patient;

        return [
            'id' => $entry->id,
            'occurred_at' => $entry->occurred_at->isoFormat('D MMM YYYY, HH:mm:ss'),
            'user' => $entry->user_name ?? 'System',
            'user_id' => $entry->user_id,
            'action' => $entry->action,
            'label' => $entry->label,
            'type' => $entry->typeLabel(),
            'route' => $entry->route,
            'ip_address' => $entry->ip_address,
            'user_agent' => $entry->user_agent,
            'patient' => $patient ? [
                'id' => $patient->id,
                'file_number' => $patient->file_number,
                'name' => $patient->fullName(),
                'url' => route('patients.show', $patient),
            ] : null,
            'changes' => $this->changes($entry),
            'hash' => substr($entry->hash, 0, 12),
        ];
    }

    /**
     * The changed attributes as field/before/after rows.
     *
     * @return list<array{field: string, old: string|null, new: string|null}>
     */
    private function changes(AuditLog $entry): array
    {
        $old = $entry->old_values ?? [];
        $new = $entry->new_values ?? [];

        $fields = array_values(array_unique([...array_keys($old), ...array_keys($new)]));

        return array_map(fn (string $field) => [
            'field' => $field,
            'old' => array_key_exists($field, $old) ? $this->display($old[$field]) : null,
            'new' => array_key_exists($field, $new) ? $this->display($new[$field]) : null,
        ], $fields);
    }

    private function display(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $encoded === false ? null : $encoded;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
