<?php

namespace App\Services;

use App\Enums\NumberKind;
use Illuminate\Support\Facades\DB;

/**
 * Issues the facility's business numbers (file, visit, accession, admission,
 * pregnancy, claim and referral numbers) from per-year series held in
 * `number_sequences`.
 *
 * The series row is locked while a number is taken, so two staff members
 * registering at the same moment never receive the same number, and a number
 * is minted inside the caller's transaction rather than derived from a
 * database key after the fact.
 */
class NumberSequences
{
    public function next(NumberKind $kind, ?int $year = null): string
    {
        $year ??= now()->year;

        $sequence = DB::transaction(function () use ($kind, $year): int {
            DB::table('number_sequences')->insertOrIgnore([
                'kind' => $kind->value,
                'year' => $year,
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $series = DB::table('number_sequences')
                ->where('kind', $kind->value)
                ->where('year', $year)
                ->lockForUpdate()
                ->first(['id', 'next_value']);

            DB::table('number_sequences')
                ->where('id', $series->id)
                ->update(['next_value' => $series->next_value + 1, 'updated_at' => now()]);

            return (int) $series->next_value;
        });

        return $kind->format($year, $sequence);
    }
}
