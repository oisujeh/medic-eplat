<?php

use App\Enums\ResultFlag;
use App\Models\LabResult;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\User;
use App\Services\LabWorkflowService;
use Database\Seeders\LabCompendiumSeeder;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
    $this->seed(LabCompendiumSeeder::class);
});

/**
 * Give haemoglobin banded limits: adult women, adult men and children.
 */
function bandHaemoglobin(): LabTest
{
    $hb = LabTest::where('code', 'HB')->firstOrFail();

    $hb->referenceRanges()->createMany([
        ['sex' => 'F', 'age_min_days' => 15 * 365, 'low' => 12.0, 'high' => 15.5, 'critical_low' => 7.0, 'critical_high' => 20.0],
        ['sex' => 'M', 'age_min_days' => 15 * 365, 'low' => 13.5, 'high' => 17.5, 'critical_low' => 7.0, 'critical_high' => 20.0],
        ['sex' => null, 'age_min_days' => 0, 'age_max_days' => 15 * 365 - 1, 'low' => 11.0, 'high' => 14.0, 'critical_low' => 6.0, 'critical_high' => 20.0],
    ]);

    return $hb->fresh();
}

test('the band that matches the patient\'s age and sex is chosen', function () {
    $hb = bandHaemoglobin();

    expect($hb->rangeFor(30 * 365, 'F'))->toMatchArray(['low' => 12.0, 'high' => 15.5])
        ->and($hb->rangeFor(30 * 365, 'M'))->toMatchArray(['low' => 13.5, 'high' => 17.5])
        ->and($hb->rangeFor(5 * 365, 'M'))->toMatchArray(['low' => 11.0, 'high' => 14.0, 'critical_low' => 6.0]);
});

test('a test with no bands keeps its single facility-wide range', function () {
    $wbc = LabTest::where('code', 'WBC')->firstOrFail();

    expect($wbc->rangeFor(30 * 365, 'F'))->toMatchArray([
        'low' => $wbc->reference_low,
        'high' => $wbc->reference_high,
        'critical_low' => null,
    ]);
});

test('an order copies the patient\'s limits onto the result line and flags critical values', function () {
    bandHaemoglobin();
    $woman = Patient::factory()->create(['sex' => 'F', 'date_of_birth' => now()->subYears(30)->toDateString()]);
    $child = Patient::factory()->create(['sex' => 'M', 'date_of_birth' => now()->subYears(4)->toDateString()]);
    $physician = User::factory()->create();
    $tests = LabTest::where('code', 'HB')->get();

    $womanLine = app(LabWorkflowService::class)->createOrder($woman, $physician, $tests)->results()->firstOrFail();
    $childLine = app(LabWorkflowService::class)->createOrder($child, $physician, $tests)->results()->firstOrFail();

    expect((float) $womanLine->reference_low)->toBe(12.0)
        ->and((float) $womanLine->critical_low)->toBe(7.0)
        ->and($womanLine->reference_range)->toBe('12 – 15.5 g/dL')
        ->and((float) $childLine->reference_low)->toBe(11.0);

    $womanLine->value = '6.2';
    expect($womanLine->deriveFlag())->toBe(ResultFlag::Critical);

    $womanLine->value = '10.1';
    expect($womanLine->deriveFlag())->toBe(ResultFlag::Low);

    $womanLine->value = '13.0';
    expect($womanLine->deriveFlag())->toBe(ResultFlag::Normal);
});

test('a result with no critical limits never flags critical on its own', function () {
    $line = new LabResult(['reference_low' => 4, 'reference_high' => 11, 'value' => '0.5']);

    expect($line->deriveFlag())->toBe(ResultFlag::Low);
});
