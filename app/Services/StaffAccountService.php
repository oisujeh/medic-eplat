<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StaffAccountService
{
    /**
     * Tables whose rows belong to the account itself rather than to the
     * facility's records. These are expected to disappear with the account and
     * so do not count as a footprint.
     *
     * @var array<int, string>
     */
    private const ACCOUNT_OWNED_TABLES = ['role_user', 'passkeys'];

    /**
     * Columns referencing `users`, keyed by table name.
     *
     * @var array<string, array<int, string>>|null
     */
    private ?array $referenceMap = null;

    /**
     * Deactivate a staff account.
     *
     * The row is kept so every historical reference to the member of staff —
     * the note they signed, the result they verified — stays intact. They can
     * no longer sign in, and drop out of provider and assignee pickers.
     */
    public function deactivate(User $user): void
    {
        $user->forceFill(['deactivated_at' => now()])->save();
    }

    /**
     * Restore a deactivated staff account.
     */
    public function reactivate(User $user): void
    {
        $user->forceFill(['deactivated_at' => null])->save();
    }

    /**
     * Permanently delete a staff account.
     *
     * Only ever allowed for an account with no footprint — a mistyped account
     * that never touched a record. Anything else must be deactivated instead,
     * because deleting it would null out clinical attribution and cascade away
     * the provider's schedules.
     *
     * @throws ValidationException
     */
    public function delete(User $user): void
    {
        $footprint = $this->footprint($user);

        if ($footprint !== []) {
            throw ValidationException::withMessages([
                'delete' => $this->footprintMessage($footprint),
            ]);
        }

        DB::transaction(function () use ($user) {
            $user->roles()->detach();
            $user->delete();
        });
    }

    /**
     * Determine whether the account can be permanently deleted.
     */
    public function canBeDeleted(User $user): bool
    {
        return $this->footprint($user) === [];
    }

    /**
     * Which of the given accounts are referenced by any facility record.
     *
     * Answers "which of these are deletable?" for a whole listing in one pass
     * over the reference map, rather than re-counting per user. Pass the ids on
     * the current page to keep the work bounded as the record set grows.
     *
     * @param  Collection<int, int>|null  $onlyUserIds  All accounts when null.
     * @return Collection<int, int>
     */
    public function referencedUserIds(?Collection $onlyUserIds = null): Collection
    {
        if ($onlyUserIds !== null && $onlyUserIds->isEmpty()) {
            return collect();
        }

        $ids = collect();

        foreach ($this->referenceMap() as $table => $columns) {
            foreach ($columns as $column) {
                $ids = $ids->merge(
                    DB::table($table)
                        ->whereNotNull($column)
                        ->when($onlyUserIds !== null, fn ($query) => $query->whereIn($column, $onlyUserIds))
                        ->distinct()
                        ->pluck($column)
                );
            }
        }

        return $ids->unique()->values();
    }

    /**
     * Count the rows referencing this account, keyed by table.
     *
     * Derived from the schema rather than a hand-written list, so a migration
     * that adds a new reference to `users` is covered automatically instead of
     * silently widening what a delete destroys.
     *
     * @return array<string, int>
     */
    public function footprint(User $user): array
    {
        $counts = [];

        foreach ($this->referenceMap() as $table => $columns) {
            $count = DB::table($table)
                ->where(function ($query) use ($columns, $user) {
                    foreach ($columns as $column) {
                        $query->orWhere($column, $user->id);
                    }
                })
                ->count();

            if ($count > 0) {
                $counts[$table] = $count;
            }
        }

        return $counts;
    }

    /**
     * Build the map of tables/columns holding a foreign key to `users`.
     *
     * @return array<string, array<int, string>>
     */
    private function referenceMap(): array
    {
        if ($this->referenceMap !== null) {
            return $this->referenceMap;
        }

        $map = [];

        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if (in_array($table, [...self::ACCOUNT_OWNED_TABLES, 'users'], true)) {
                continue;
            }

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (($foreignKey['foreign_table'] ?? null) === 'users') {
                    $map[$table] = [...($map[$table] ?? []), ...$foreignKey['columns']];
                }
            }
        }

        return $this->referenceMap = $map;
    }

    /**
     * Describe a footprint for the administrator refusing the delete.
     *
     * @param  array<string, int>  $footprint
     */
    private function footprintMessage(array $footprint): string
    {
        $summary = collect($footprint)
            ->map(fn (int $count, string $table) => $count.' '.str_replace('_', ' ', $table))
            ->take(3)
            ->join(', ');

        return "This account is referenced by facility records ({$summary}) and cannot be deleted "
            .'without losing that history. Deactivate it instead — the account keeps its records '
            .'but can no longer sign in.';
    }
}
