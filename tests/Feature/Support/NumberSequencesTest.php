<?php

use App\Enums\NumberKind;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\NumberSequences;

test('numbers are issued in order within a kind and year', function () {
    $numbers = app(NumberSequences::class);

    expect($numbers->next(NumberKind::Patient))->toBe(sprintf('MEP/%d/000001', now()->year))
        ->and($numbers->next(NumberKind::Patient))->toBe(sprintf('MEP/%d/000002', now()->year))
        ->and($numbers->next(NumberKind::LabOrder))->toBe(sprintf('LAB/%d/000001', now()->year));
});

test('each year starts its own series', function () {
    $numbers = app(NumberSequences::class);

    expect($numbers->next(NumberKind::Visit, 2025))->toBe('V/2025/000001')
        ->and($numbers->next(NumberKind::Visit, 2026))->toBe('V/2026/000001')
        ->and($numbers->next(NumberKind::Visit, 2025))->toBe('V/2025/000002');
});

test('a number taken inside a rolled-back transaction is not reissued out of order', function () {
    $numbers = app(NumberSequences::class);

    try {
        DB::transaction(function () use ($numbers) {
            $numbers->next(NumberKind::Admission);
            throw new RuntimeException('abandon');
        });
    } catch (RuntimeException) {
    }

    // The abandoned number is released with the rollback, so the series
    // continues from the start rather than leaving a gap.
    expect($numbers->next(NumberKind::Admission))->toBe(sprintf('ADM/%d/000001', now()->year));
});

test('operational records receive a public ULID when created', function () {
    $patient = Patient::factory()->create();
    $visit = Visit::factory()->for($patient)->create();

    expect($patient->ulid)->toHaveLength(26)
        ->and($visit->ulid)->toHaveLength(26)
        ->and($patient->ulid)->not->toBe($visit->ulid);
});
