<?php

use App\Enums\ConceptClass;
use App\Enums\ObservationCode;
use App\Models\Concept;
use App\Models\Observation;
use App\Models\Patient;
use App\Models\User;
use App\Services\ObservationService;
use Database\Seeders\TerminologySeeder;

test('every reading is bound to the concept its code is registered as', function () {
    $this->seed(TerminologySeeder::class);

    $set = app(ObservationService::class)->record(
        Patient::factory()->create(),
        User::factory()->create(),
        ['temperature' => 38.4, 'weight' => 70, 'height' => 175],
    );

    $temperature = $set->observations->firstWhere('code', ObservationCode::Temperature);

    expect($temperature->concept_id)->not->toBeNull()
        ->and($temperature->concept->class)->toBe(ConceptClass::Observation)
        ->and($temperature->concept->name)->toBe('Temperature')
        ->and($temperature->concept->codeIn('LOINC'))->toBe('8310-5');

    // The derived BMI is bound too, so line lists can query it by concept.
    $bmi = $set->observations->firstWhere('code', ObservationCode::Bmi);
    expect($bmi->concept_id)->not->toBeNull();

    // Readings are queryable by concept.
    expect(Observation::where('concept_id', $temperature->concept_id)->count())->toBe(1)
        ->and(Concept::find($temperature->concept_id)->name)->toBe('Temperature');
});

test('a reading still saves when the dictionary has not been seeded', function () {
    $set = app(ObservationService::class)->record(
        Patient::factory()->create(),
        User::factory()->create(),
        ['pulse' => 88],
    );

    expect($set->observations)->toHaveCount(1)
        ->and($set->observations->first()->concept_id)->toBeNull();
});
