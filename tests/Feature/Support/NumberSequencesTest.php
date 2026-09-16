<?php

use App\Enums\NumberKind;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\FacilitySettings;
use App\Services\NumberSequences;
use Tests\TestCase;

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

test('the file number prefix and check digit follow the facility profile', function () {
    $facility = app(FacilitySettings::class);
    $facility->save([...TestCase::TEST_FACILITY, 'patient_number_prefix' => 'lgh', 'patient_number_check_digit' => true]);

    $numbers = app(NumberSequences::class);
    $year = now()->year;
    $expectedDigit = NumberKind::checkDigitFor(sprintf('%d%06d', $year, 1));

    expect($numbers->next(NumberKind::Patient))->toBe(sprintf('LGH/%d/000001-%d', $year, $expectedDigit))
        // Other series keep their fixed form.
        ->and($numbers->next(NumberKind::Visit))->toBe(sprintf('V/%d/000001', $year));

    $facility->save([...TestCase::TEST_FACILITY, 'patient_number_prefix' => '', 'patient_number_check_digit' => false]);

    expect($numbers->next(NumberKind::Patient))->toBe(sprintf('MEP/%d/000002', $year));
});

test('the check digit is a Luhn digit over the numeric part', function () {
    expect(NumberKind::checkDigitFor('7992739871'))->toBe(3)
        ->and(NumberKind::checkDigitFor('2026000001'))->toBeBetween(0, 9);
});

test('operational records receive a public ULID when created', function () {
    $patient = Patient::factory()->create();
    $visit = Visit::factory()->for($patient)->create();

    expect($patient->ulid)->toHaveLength(26)
        ->and($visit->ulid)->toHaveLength(26)
        ->and($patient->ulid)->not->toBe($visit->ulid);
});
