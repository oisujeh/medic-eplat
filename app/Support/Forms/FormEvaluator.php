<?php

namespace App\Support\Forms;

use App\Enums\FormFieldType;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Applies a definition to a set of answers: decides what is visible, fills
 * in calculated fields, finds what is wrong, and flattens the answers into
 * rows for the value table.
 *
 * Answers are keyed by field key. A repeating section is keyed by its own
 * key and holds a list of instances, each an object of that section's
 * fields — so one repeat can never see another's values (PRD FRM-05).
 */
final class FormEvaluator
{
    /**
     * @var array<string, mixed>
     */
    private array $data;

    /**
     * Each field with the section and page that hold it, by key.
     *
     * @var array<string, array{field: array<string, mixed>, section: array<string, mixed>, page: array<string, mixed>}>
     */
    private array $index = [];

    /**
     * Keys whose visibility is being worked out, to stop a definition that
     * makes two fields depend on each other looping forever.
     *
     * @var array<string, true>
     */
    private array $resolving = [];

    /**
     * @param  array<string, mixed>  $data  the answers as entered
     * @param  array<string, mixed>  $context  patient context: age, age_days, sex, encounter_at, today
     */
    public function __construct(
        private readonly FormDefinition $definition,
        array $data,
        private readonly array $context = [],
    ) {
        foreach ($this->definition->pages() as $page) {
            foreach ($this->sectionsOf($page) as $section) {
                foreach ($this->fieldsOf($section) as $field) {
                    $this->index[$field['key']] = ['field' => $field, 'section' => $section, 'page' => $page];
                }
            }
        }

        $this->data = $this->normalise($data);
    }

    /**
     * The answers, normalised and with every calculated field filled in.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * What is wrong with the answers, keyed by field path: "weight", or
     * "specimens.1.site" inside a repeating section. Empty when the form
     * can be completed.
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        $errors = [];

        foreach ($this->definition->pages() as $page) {
            if (! $this->isVisible($page, null)) {
                continue;
            }

            foreach ($this->sectionsOf($page) as $section) {
                if (! $this->isVisible($section, null)) {
                    continue;
                }

                if (FormDefinition::isRepeating($section)) {
                    $instances = $this->data[$section['key']] ?? [];
                    $min = (int) ($section['repeat']['min'] ?? 0);
                    $max = $section['repeat']['max'] ?? null;

                    if (count($instances) < $min) {
                        $errors[$section['key']] = FormDefinition::label($section, attribute: 'title')." needs at least {$min} ".($min === 1 ? 'entry' : 'entries').'.';
                    }

                    if ($max !== null && count($instances) > (int) $max) {
                        $errors[$section['key']] = FormDefinition::label($section, attribute: 'title')." allows at most {$max} entries.";
                    }

                    foreach ($instances as $i => $instance) {
                        foreach ($this->fieldsOf($section) as $field) {
                            $this->checkField($field, $instance, "{$section['key']}.{$i}.{$field['key']}", $errors);
                        }
                    }

                    continue;
                }

                foreach ($this->fieldsOf($section) as $field) {
                    $this->checkField($field, null, $field['key'], $errors);
                }
            }
        }

        return $errors;
    }

    /**
     * One row per recorded answer on the visible parts of the form, ready
     * for the value table. A multi-select gives one row per choice.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ($this->definition->pages() as $page) {
            if (! $this->isVisible($page, null)) {
                continue;
            }

            foreach ($this->sectionsOf($page) as $section) {
                if (! $this->isVisible($section, null)) {
                    continue;
                }

                if (FormDefinition::isRepeating($section)) {
                    foreach ($this->data[$section['key']] ?? [] as $i => $instance) {
                        foreach ($this->fieldsOf($section) as $field) {
                            if ($this->isVisible($field, $instance)) {
                                $rows = [...$rows, ...$this->rowsFor($field, $instance[$field['key']] ?? null, $section['key'], $i)];
                            }
                        }
                    }

                    continue;
                }

                foreach ($this->fieldsOf($section) as $field) {
                    if ($this->isVisible($field, null)) {
                        $rows = [...$rows, ...$this->rowsFor($field, $this->data[$field['key']] ?? null, null, null)];
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * Whether a page, section or field is shown, given its show_when.
     *
     * @param  array<string, mixed>  $entity
     * @param  array<string, mixed>|null  $scope  the repeat instance the entity is evaluated in
     */
    public function isVisible(array $entity, ?array $scope): bool
    {
        return Conditions::evaluate($entity['show_when'] ?? null, $this->lookup($scope));
    }

    /**
     * A resolver for "field" and "context" references: the repeat instance
     * first, then the form's top-level answers, then the patient context.
     * A field that is hidden (its own rule, its section's or its page's)
     * reads as empty, so skip logic never acts on an answer nobody can see.
     *
     * @param  array<string, mixed>|null  $scope
     * @return callable(string $kind, string $name): mixed
     */
    public function lookup(?array $scope): callable
    {
        return function (string $kind, string $name) use ($scope): mixed {
            if ($kind === 'context') {
                return $this->context[$name] ?? null;
            }

            $value = $scope !== null && array_key_exists($name, $scope) ? $scope[$name] : ($this->data[$name] ?? null);

            if (! isset($this->index[$name]) || isset($this->resolving[$name])) {
                return $value;
            }

            $this->resolving[$name] = true;

            try {
                ['field' => $field, 'section' => $section, 'page' => $page] = $this->index[$name];

                $shown = $this->isVisible($page, null)
                    && $this->isVisible($section, null)
                    && $this->isVisible($field, $scope);
            } finally {
                unset($this->resolving[$name]);
            }

            return $shown ? $value : null;
        };
    }

    /**
     * Coerce every answer to the type its field declares, drop what does
     * not belong on the form, and fill in the calculated fields.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalise(array $input): array
    {
        $data = [];

        foreach ($this->definition->sections() as $section) {
            $fields = $this->fieldsOf($section);

            if (FormDefinition::isRepeating($section)) {
                $instances = $input[$section['key']] ?? [];
                $data[$section['key']] = [];

                foreach (is_array($instances) && array_is_list($instances) ? $instances : [] as $instance) {
                    $clean = [];

                    foreach ($fields as $field) {
                        if (FormFieldType::tryFrom((string) ($field['type'] ?? ''))?->isDerived()) {
                            continue;
                        }

                        $clean[$field['key']] = $this->coerce($field, is_array($instance) ? ($instance[$field['key']] ?? null) : null);
                    }

                    $data[$section['key']][] = $clean;
                }

                continue;
            }

            foreach ($fields as $field) {
                if (FormFieldType::tryFrom((string) ($field['type'] ?? ''))?->isDerived()) {
                    continue;
                }

                $data[$field['key']] = $this->coerce($field, $input[$field['key']] ?? null);
            }
        }

        $this->data = $data;

        // Calculated fields read the normalised answers, so they come last;
        // one inside a repeat sees only its own instance.
        foreach ($this->definition->sections() as $section) {
            foreach ($this->fieldsOf($section) as $field) {
                if (($field['type'] ?? null) !== FormFieldType::Calculated->value) {
                    continue;
                }

                if (FormDefinition::isRepeating($section)) {
                    foreach ($this->data[$section['key']] as $i => $instance) {
                        $this->data[$section['key']][$i][$field['key']] = Calculations::evaluate($field['calc'] ?? null, $this->lookup($instance));
                    }
                } else {
                    $this->data[$field['key']] = Calculations::evaluate($field['calc'] ?? null, $this->lookup(null));
                }
            }
        }

        return $this->data;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function coerce(array $field, mixed $value): mixed
    {
        $type = FormFieldType::tryFrom((string) ($field['type'] ?? ''));

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === null) {
            return null;
        }

        return match ($type) {
            FormFieldType::Numeric => is_numeric($value) ? (float) $value : $value,
            FormFieldType::Boolean => match (true) {
                is_bool($value) => $value,
                is_string($value) && in_array(mb_strtolower($value), ['1', 'true', 'yes'], true) => true,
                is_string($value) && in_array(mb_strtolower($value), ['0', 'false', 'no'], true) => false,
                is_numeric($value) => (bool) $value,
                default => $value,
            },
            FormFieldType::Coded => ($field['multiple'] ?? false)
                ? (is_array($value) ? array_values(array_map(fn ($v) => is_scalar($v) ? (string) $v : $v, $value)) : [(string) $value])
                : (is_scalar($value) ? (string) $value : $value),
            FormFieldType::Drug, FormFieldType::Diagnosis, FormFieldType::LabTest => is_array($value) ? $value : null,
            default => is_scalar($value) ? (string) $value : $value,
        };
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>|null  $scope
     * @param  array<string, string>  $errors
     */
    private function checkField(array $field, ?array $scope, string $path, array &$errors): void
    {
        $type = FormFieldType::tryFrom((string) ($field['type'] ?? ''));

        if ($type === null || $type->isDerived() || ! $this->isVisible($field, $scope)) {
            return;
        }

        $value = $scope !== null ? ($scope[$field['key']] ?? null) : ($this->data[$field['key']] ?? null);
        $label = FormDefinition::label($field);

        if (Conditions::isEmpty($value)) {
            if ($field['required'] ?? false) {
                $errors[$path] = "{$label} is required.";
            }

            return;
        }

        $problem = $this->typeProblem($type, $field, $value, $label);

        if ($problem !== null) {
            $errors[$path] = $problem;

            return;
        }

        foreach (is_array($field['validate'] ?? null) ? $field['validate'] : [] as $rule) {
            if (is_array($rule) && Conditions::evaluate($rule['when'] ?? null, $this->lookup($scope))) {
                $errors[$path] = (string) ($rule['message'] ?? "{$label} is not valid.");

                return;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function typeProblem(FormFieldType $type, array $field, mixed $value, string $label): ?string
    {
        switch ($type) {
            case FormFieldType::Numeric:
                if (! is_numeric($value)) {
                    return "{$label} must be a number.";
                }

                if (($field['integer'] ?? false) && (float) $value != (int) $value) {
                    return "{$label} must be a whole number.";
                }

                if (isset($field['min']) && is_numeric($field['min']) && (float) $value < (float) $field['min']) {
                    return "{$label} must be at least {$field['min']}".(isset($field['unit']) ? " {$field['unit']}" : '').'.';
                }

                if (isset($field['max']) && is_numeric($field['max']) && (float) $value > (float) $field['max']) {
                    return "{$label} must be at most {$field['max']}".(isset($field['unit']) ? " {$field['unit']}" : '').'.';
                }

                return null;

            case FormFieldType::Coded:
                $allowed = array_map(fn ($o) => (string) ($o['value'] ?? ''), is_array($field['options'] ?? null) ? $field['options'] : []);
                $chosen = ($field['multiple'] ?? false) ? (is_array($value) ? $value : [$value]) : [$value];

                foreach ($chosen as $choice) {
                    if (! is_scalar($choice) || ! in_array((string) $choice, $allowed, true)) {
                        return "{$label} has a choice that is not on the list.";
                    }
                }

                return null;

            case FormFieldType::Text:
                if (! is_string($value)) {
                    return "{$label} must be text.";
                }

                if (isset($field['max_length']) && mb_strlen($value) > (int) $field['max_length']) {
                    return "{$label} must be {$field['max_length']} characters or fewer.";
                }

                return null;

            case FormFieldType::Date:
            case FormFieldType::Datetime:
                $moment = self::parseMoment($value);

                if ($moment === null || ($type === FormFieldType::Date && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value))) {
                    return "{$label} must be a valid ".($type === FormFieldType::Date ? 'date' : 'date and time').'.';
                }

                if (($field['not_future'] ?? false) && $moment->isFuture()) {
                    return "{$label} cannot be in the future.";
                }

                return null;

            case FormFieldType::Boolean:
                return is_bool($value) ? null : "{$label} must be yes or no.";

            case FormFieldType::Drug:
                return is_array($value) && is_numeric($value['id'] ?? null) ? null : "{$label} must be picked from the drug dictionary.";

            case FormFieldType::Diagnosis:
                return is_array($value) && is_string($value['code'] ?? null) && $value['code'] !== '' ? null : "{$label} must be an ICD-10 diagnosis.";

            case FormFieldType::LabTest:
                return is_array($value) && is_numeric($value['id'] ?? null) ? null : "{$label} must be picked from the test catalogue.";

            default:
                return null;
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<array<string, mixed>>
     */
    private function rowsFor(array $field, mixed $value, ?string $groupKey, ?int $repeatIndex): array
    {
        $type = FormFieldType::tryFrom((string) ($field['type'] ?? ''));

        if ($type === null || Conditions::isEmpty($value)) {
            return [];
        }

        $base = [
            'field_key' => $field['key'],
            'concept_id' => $field['concept_id'] ?? null,
            'group_key' => $groupKey,
            'repeat_index' => $repeatIndex,
            'value_type' => $type->valueType(),
            'value_numeric' => null,
            'value_text' => null,
            'value_concept_id' => null,
            'value_reference' => null,
            'value_date' => null,
            'value_datetime' => null,
            'value_boolean' => null,
            'unit' => $field['unit'] ?? null,
        ];

        switch ($type) {
            case FormFieldType::Numeric:
            case FormFieldType::Calculated:
                return is_numeric($value) ? [[...$base, 'value_numeric' => (float) $value]] : [];

            case FormFieldType::Coded:
                $rows = [];

                foreach (($field['multiple'] ?? false) ? (array) $value : [$value] as $choice) {
                    $option = collect(is_array($field['options'] ?? null) ? $field['options'] : [])
                        ->first(fn ($o) => is_array($o) && (string) ($o['value'] ?? '') === (string) $choice);

                    $rows[] = [
                        ...$base,
                        'value_reference' => (string) $choice,
                        'value_text' => $option ? FormDefinition::label($option) : (string) $choice,
                        'value_concept_id' => $option['concept_id'] ?? null,
                    ];
                }

                return $rows;

            case FormFieldType::Text:
                return [[...$base, 'value_text' => (string) $value]];

            case FormFieldType::Date:
                return [[...$base, 'value_date' => self::parseMoment($value)?->toDateString()]];

            case FormFieldType::Datetime:
                return [[...$base, 'value_datetime' => self::parseMoment($value)]];

            case FormFieldType::Boolean:
                return [[...$base, 'value_boolean' => (bool) $value]];

            case FormFieldType::Drug:
                return [[...$base, 'value_concept_id' => (int) ($value['id'] ?? 0) ?: null, 'value_text' => (string) ($value['name'] ?? '')]];

            case FormFieldType::Diagnosis:
                return [[...$base, 'value_reference' => (string) ($value['code'] ?? ''), 'value_text' => (string) ($value['description'] ?? '')]];

            default:
                return [[...$base, 'value_reference' => (string) ($value['id'] ?? ''), 'value_text' => (string) ($value['name'] ?? '')]];
        }
    }

    /**
     * @param  array<string, mixed>  $page
     * @return list<array<string, mixed>>
     */
    private function sectionsOf(array $page): array
    {
        return array_values(array_filter(is_array($page['sections'] ?? null) ? $page['sections'] : [], 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $section
     * @return list<array<string, mixed>>
     */
    private function fieldsOf(array $section): array
    {
        return array_values(array_filter(
            is_array($section['fields'] ?? null) ? $section['fields'] : [],
            fn ($f) => is_array($f) && isset($f['key']),
        ));
    }

    public static function parseMoment(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
