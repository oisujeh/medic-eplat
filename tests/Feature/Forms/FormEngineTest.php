<?php

use App\Support\Forms\Calculations;
use App\Support\Forms\Conditions;
use App\Support\Forms\FormDefinition;
use App\Support\Forms\FormEvaluator;

/**
 * A definition exercising every mechanism: skip logic, calculation, a
 * cross-field rule, a coded field and a repeating group.
 *
 * @return array<string, mixed>
 */
function engineDefinition(): array
{
    return [
        'pages' => [
            [
                'key' => 'main',
                'title' => 'Main',
                'sections' => [
                    [
                        'key' => 'body',
                        'title' => 'Body',
                        'fields' => [
                            ['key' => 'weight', 'type' => 'numeric', 'label' => 'Weight', 'concept_id' => 1, 'unit' => 'kg', 'min' => 0, 'max' => 300, 'required' => true],
                            ['key' => 'height', 'type' => 'numeric', 'label' => 'Height', 'concept_id' => 2, 'unit' => 'cm', 'min' => 20, 'max' => 250],
                            ['key' => 'bmi', 'type' => 'calculated', 'label' => 'BMI', 'concept_id' => 3, 'calc' => [
                                'op' => 'round', 'args' => [
                                    ['op' => '/', 'args' => [['field' => 'weight'], ['op' => '^', 'args' => [['op' => '/', 'args' => [['field' => 'height'], 100]], 2]]]],
                                    1,
                                ],
                            ]],
                            ['key' => 'pregnant', 'type' => 'boolean', 'label' => 'Pregnant', 'concept_id' => 4,
                                'show_when' => ['all' => [['field' => 'sex', 'op' => 'eq', 'value' => 'F'], ['field' => 'weight', 'op' => 'gt', 'value' => 0]]]],
                            ['key' => 'sex', 'type' => 'coded', 'label' => 'Sex', 'concept_id' => 5, 'required' => true, 'options' => [
                                ['value' => 'F', 'label' => 'Female', 'concept_id' => 50],
                                ['value' => 'M', 'label' => 'Male', 'concept_id' => 51],
                            ]],
                            ['key' => 'lmp', 'type' => 'date', 'label' => 'LMP', 'concept_id' => 6, 'required' => true, 'not_future' => true,
                                'show_when' => ['field' => 'pregnant', 'op' => 'eq', 'value' => true]],
                            ['key' => 'symptoms', 'type' => 'coded', 'label' => 'Symptoms', 'concept_id' => 7, 'multiple' => true, 'options' => [
                                ['value' => 'fever', 'label' => 'Fever'],
                                ['value' => 'cough', 'label' => 'Cough'],
                            ]],
                            ['key' => 'end', 'type' => 'datetime', 'label' => 'End', 'concept_id' => 8, 'validate' => [
                                ['when' => ['field' => 'end', 'op' => 'lt', 'value' => ['field' => 'start']], 'message' => 'End must follow start.'],
                            ]],
                            ['key' => 'start', 'type' => 'datetime', 'label' => 'Start', 'concept_id' => 9],
                        ],
                    ],
                    [
                        'key' => 'children',
                        'title' => 'Children',
                        'repeat' => ['min' => 0, 'max' => 3],
                        'fields' => [
                            ['key' => 'child_weight', 'type' => 'numeric', 'label' => 'Child weight', 'concept_id' => 10, 'required' => true],
                            ['key' => 'child_height', 'type' => 'numeric', 'label' => 'Child height', 'concept_id' => 11],
                            ['key' => 'child_bmi', 'type' => 'calculated', 'label' => 'Child BMI', 'concept_id' => 12, 'calc' => [
                                'op' => 'round', 'args' => [['op' => '/', 'args' => [['field' => 'child_weight'], ['op' => '^', 'args' => [['op' => '/', 'args' => [['field' => 'child_height'], 100]], 2]]]], 1],
                            ]],
                            ['key' => 'child_sick', 'type' => 'boolean', 'label' => 'Sick', 'concept_id' => 13],
                            ['key' => 'child_note', 'type' => 'text', 'label' => 'Note', 'concept_id' => 14, 'required' => true,
                                'show_when' => ['field' => 'child_sick', 'op' => 'eq', 'value' => true]],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

// ------------------------------------------------------------ Conditions

test('conditions compare, group and read other fields', function () {
    $data = ['a' => 5, 'b' => '5', 'name' => 'Malaria, severe', 'list' => ['x', 'y'], 'flag' => true, 'blank' => ''];
    $lookup = fn (string $kind, string $key) => $kind === 'context' ? ['age' => 40][$key] ?? null : ($data[$key] ?? null);

    expect(Conditions::evaluate(['field' => 'a', 'op' => 'eq', 'value' => 5], $lookup))->toBeTrue()
        ->and(Conditions::evaluate(['field' => 'a', 'op' => 'eq', 'value' => ['field' => 'b']], $lookup))->toBeTrue()
        ->and(Conditions::evaluate(['field' => 'a', 'op' => 'gt', 'value' => ['context' => 'age']], $lookup))->toBeFalse()
        ->and(Conditions::evaluate(['field' => 'a', 'op' => 'in', 'value' => [1, 5]], $lookup))->toBeTrue()
        ->and(Conditions::evaluate(['field' => 'name', 'op' => 'contains', 'value' => 'severe'], $lookup))->toBeTrue()
        ->and(Conditions::evaluate(['field' => 'list', 'op' => 'contains', 'value' => 'y'], $lookup))->toBeTrue()
        ->and(Conditions::evaluate(['field' => 'flag', 'op' => 'eq', 'value' => 'yes'], $lookup))->toBeTrue()
        ->and(Conditions::evaluate(['field' => 'blank', 'op' => 'empty'], $lookup))->toBeTrue()
        ->and(Conditions::evaluate(['field' => 'missing', 'op' => 'not_empty'], $lookup))->toBeFalse()
        ->and(Conditions::evaluate(['all' => [['field' => 'a', 'op' => 'gte', 'value' => 5], ['not' => ['field' => 'flag', 'op' => 'eq', 'value' => false]]]], $lookup))->toBeTrue()
        ->and(Conditions::evaluate(['any' => [['field' => 'a', 'op' => 'lt', 'value' => 1], ['field' => 'a', 'op' => 'ne', 'value' => 9]]], $lookup))->toBeTrue()
        ->and(Conditions::evaluate(null, $lookup))->toBeTrue()
        ->and(Conditions::evaluate(['field' => 'missing', 'op' => 'gt', 'value' => 1], $lookup))->toBeFalse();

    expect(Conditions::problems(['field' => 'a', 'op' => 'between'], 'here'))->toHaveCount(1)
        ->and(Conditions::fieldReferences(['all' => [['field' => 'a', 'op' => 'eq', 'value' => ['field' => 'b']], ['not' => ['field' => 'c', 'op' => 'empty']]]]))->toBe(['a', 'b', 'c']);
});

// ---------------------------------------------------------- Calculations

test('calculations follow the expression tree and go missing with any missing input', function () {
    $data = ['weight' => 70, 'height' => 175, 'start' => '2026-09-13 08:00', 'end' => '2026-09-13 09:45'];
    $lookup = fn (string $kind, string $key) => $kind === 'context' ? ['age' => 40][$key] ?? null : ($data[$key] ?? null);

    $bmi = ['op' => 'round', 'args' => [['op' => '/', 'args' => [['field' => 'weight'], ['op' => '^', 'args' => [['op' => '/', 'args' => [['field' => 'height'], 100]], 2]]]], 1]];

    expect(Calculations::evaluate($bmi, $lookup))->toBe(22.9)
        ->and(Calculations::evaluate(['op' => 'minutes_between', 'args' => [['field' => 'start'], ['field' => 'end']]], $lookup))->toBe(105.0)
        ->and(Calculations::evaluate(['op' => '+', 'args' => [['context' => 'age'], 1]], $lookup))->toEqual(41)
        ->and(Calculations::evaluate(['op' => 'if', 'args' => [['field' => 'weight', 'op' => 'gt', 'value' => 60], 1, 0]], $lookup))->toBe(1)
        ->and(Calculations::evaluate(['op' => '*', 'args' => [['field' => 'weight'], ['field' => 'missing']]], $lookup))->toBeNull()
        ->and(Calculations::evaluate(['op' => '/', 'args' => [1, 0]], $lookup))->toBeNull()
        ->and(Calculations::evaluate(['op' => 'max', 'args' => [3, ['field' => 'weight'], 12]], $lookup))->toBe(70);

    expect(Calculations::problems(['op' => 'sqrt', 'args' => [1]], 'here'))->toHaveCount(1)
        ->and(Calculations::fieldReferences($bmi))->toBe(['weight', 'height']);
});

// ------------------------------------------------------------ Definition

test('a definition is checked for keys, types, concepts, options and references', function () {
    $good = FormDefinition::fromArray(engineDefinition());
    expect($good->issues())->toBe([]);

    $bad = FormDefinition::fromArray([
        'pages' => [[
            'key' => 'p1',
            'sections' => [[
                'key' => 'p1',
                'fields' => [
                    ['key' => 'Bad Key', 'type' => 'numeric', 'label' => 'x', 'concept' => 'C'],
                    ['key' => 'a', 'type' => 'colour', 'label' => 'a', 'concept' => 'C'],
                    ['key' => 'b', 'type' => 'coded', 'label' => 'b', 'concept' => 'C', 'options' => [['value' => 1, 'label' => 'One'], ['value' => 1, 'label' => 'Uno']]],
                    ['key' => 'c', 'type' => 'text', 'label' => 'c'],
                    ['key' => 'd', 'type' => 'calculated', 'label' => 'd', 'concept' => 'C'],
                    ['key' => 'e', 'type' => 'numeric', 'label' => 'e', 'concept' => 'C', 'min' => 10, 'max' => 1, 'show_when' => ['field' => 'zzz', 'op' => 'eq', 'value' => 1]],
                    ['key' => 'f', 'type' => 'numeric', 'label' => 'f', 'concept' => 'C', 'calc' => 1],
                ],
            ], [
                'key' => 'rep',
                'repeat' => [],
                'fields' => [['key' => 'inner', 'type' => 'text', 'label' => 'inner', 'concept' => 'C']],
            ], [
                'key' => 'outer',
                'fields' => [['key' => 'reads_inner', 'type' => 'text', 'label' => 'x', 'concept' => 'C', 'show_when' => ['field' => 'inner', 'op' => 'empty']]],
            ]],
        ]],
    ]);

    $issues = implode("\n", $bad->issues());

    expect($issues)
        ->toContain('used more than once')
        ->toContain('lowercase letters')
        ->toContain('unknown type "colour"')
        ->toContain('option value "1" appears twice')
        ->toContain('Field "c" must bind to a dictionary concept')
        ->toContain('calculated field needs a "calc"')
        ->toContain('"min" is greater than "max"')
        ->toContain('refers to "zzz", which is not a field')
        ->toContain('only a calculated field may carry "calc"')
        ->toContain('refers to "inner" inside repeating group "rep"');

    expect(FormDefinition::fromArray([])->issues())->toHaveCount(1);
});

test('concept codes are collected, resolved to ids and stripped again for export', function () {
    $definition = FormDefinition::fromArray([
        'pages' => [['key' => 'p', 'sections' => [[
            'key' => 's', 'concept' => 'GROUP',
            'fields' => [
                ['key' => 'a', 'type' => 'coded', 'label' => 'a', 'concept' => 'A', 'options' => [['value' => 'x', 'label' => 'X', 'concept' => 'AX']]],
                ['key' => 'b', 'type' => 'text', 'label' => 'b', 'concept_id' => 99],
            ],
        ]]]],
    ]);

    expect($definition->conceptCodes())->toBe(['GROUP', 'A', 'AX']);

    $ids = ['GROUP' => 1, 'A' => 2, 'AX' => 3];
    $resolved = $definition->resolveConcepts(fn (string $code) => $ids[$code] ?? null);

    expect($resolved->conceptCodes())->toBe([])
        ->and($resolved->field('a')['concept_id'])->toBe(2)
        ->and($resolved->field('a')['options'][0]['concept_id'])->toBe(3)
        ->and($resolved->sections()[0]['concept_id'])->toBe(1)
        ->and($resolved->withoutConceptIds()->conceptCodes())->toBe(['GROUP', 'A', 'AX'])
        ->and($resolved->withoutConceptIds()->field('b')['concept_id'])->toBe(99);
});

test('labels may be translated per field', function () {
    expect(FormDefinition::label(['label' => ['en' => 'Weight', 'ha' => 'Nauyi']], 'ha'))->toBe('Nauyi')
        ->and(FormDefinition::label(['label' => ['en' => 'Weight', 'ha' => 'Nauyi']], 'yo'))->toBe('Weight')
        ->and(FormDefinition::label(['label' => 'Plain']))->toBe('Plain')
        ->and(FormDefinition::label(['title' => 'Section'], attribute: 'title'))->toBe('Section');
});

// ------------------------------------------------------------- Evaluator

test('the evaluator hides, calculates, validates and only records what is visible', function () {
    $definition = FormDefinition::fromArray(engineDefinition());

    $evaluator = new FormEvaluator($definition, [
        'weight' => '70', 'height' => '175', 'sex' => 'M', 'pregnant' => true, 'lmp' => '',
        'symptoms' => ['fever', 'cough'], 'start' => '2026-09-13T08:00', 'end' => '2026-09-13T07:00',
        'children' => [],
        'stray' => 'dropped',
    ]);

    $data = $evaluator->data();

    expect($data['weight'])->toBe(70.0)
        ->and($data['bmi'])->toBe(22.9)
        ->and($data)->not->toHaveKey('stray');

    // A male patient never sees "pregnant", so the LMP it would reveal is
    // neither required nor recorded; the end time fails its own rule.
    expect($evaluator->errors())->toBe(['end' => 'End must follow start.']);

    $rows = collect($evaluator->rows());

    expect($rows->pluck('field_key')->all())->toBe(['weight', 'height', 'bmi', 'sex', 'symptoms', 'symptoms', 'end', 'start'])
        ->and($rows->firstWhere('field_key', 'sex'))->toMatchArray(['value_type' => 'coded', 'value_reference' => 'M', 'value_text' => 'Male', 'value_concept_id' => 51])
        ->and($rows->firstWhere('field_key', 'bmi'))->toMatchArray(['value_numeric' => 22.9, 'concept_id' => 3])
        ->and($rows->where('field_key', 'symptoms')->pluck('value_reference')->all())->toBe(['fever', 'cough']);
});

test('required, range, list and type rules speak in the field label', function () {
    $definition = FormDefinition::fromArray(engineDefinition());

    $errors = (new FormEvaluator($definition, [
        'weight' => 'heavy', 'height' => 500, 'sex' => 'X', 'symptoms' => ['fever', 'rash'],
    ]))->errors();

    expect($errors)->toMatchArray([
        'weight' => 'Weight must be a number.',
        'height' => 'Height must be at most 250 cm.',
        'sex' => 'Sex has a choice that is not on the list.',
        'symptoms' => 'Symptoms has a choice that is not on the list.',
    ]);

    $errors = (new FormEvaluator($definition, ['sex' => 'F', 'weight' => 60, 'pregnant' => 'yes', 'lmp' => '2099-01-01']))->errors();

    expect($errors)->toBe(['lmp' => 'LMP cannot be in the future.']);

    $errors = (new FormEvaluator($definition, ['sex' => 'F', 'weight' => 60, 'pregnant' => true]))->errors();

    expect($errors)->toBe(['lmp' => 'LMP is required.']);
});

test('repeating groups are isolated: each repeat sees only its own values', function () {
    $definition = FormDefinition::fromArray(engineDefinition());

    $evaluator = new FormEvaluator($definition, [
        'weight' => 70, 'sex' => 'M',
        'children' => [
            ['child_weight' => 20, 'child_height' => 100, 'child_sick' => true, 'child_note' => 'Fever'],
            ['child_weight' => 30, 'child_height' => 120, 'child_sick' => false, 'child_note' => 'should not be recorded'],
            ['child_weight' => '', 'child_height' => 90, 'child_sick' => true],
        ],
    ]);

    $data = $evaluator->data();

    expect($data['children'][0]['child_bmi'])->toBe(20.0)
        ->and($data['children'][1]['child_bmi'])->toBe(20.8)
        ->and($data['children'][2]['child_bmi'])->toBeNull();

    expect($evaluator->errors())->toBe([
        'children.2.child_weight' => 'Child weight is required.',
        'children.2.child_note' => 'Note is required.',
    ]);

    $rows = collect($evaluator->rows())->where('group_key', 'children');

    // The second child's note is hidden (not sick), so it is not recorded;
    // the third has no weight, so no BMI row.
    expect($rows->where('repeat_index', 0)->pluck('field_key')->all())->toBe(['child_weight', 'child_height', 'child_bmi', 'child_sick', 'child_note'])
        ->and($rows->where('repeat_index', 1)->pluck('field_key')->all())->toBe(['child_weight', 'child_height', 'child_bmi', 'child_sick'])
        ->and($rows->where('repeat_index', 2)->pluck('field_key')->all())->toBe(['child_height', 'child_sick']);

    $tooMany = new FormEvaluator($definition, ['weight' => 70, 'sex' => 'M', 'children' => array_fill(0, 4, ['child_weight' => 1])]);

    expect($tooMany->errors())->toHaveKey('children');
});
