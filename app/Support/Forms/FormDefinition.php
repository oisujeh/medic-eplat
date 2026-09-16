<?php

namespace App\Support\Forms;

use App\Enums\FormFieldType;

/**
 * A form definition as JSON (PRD FRM-01): pages of sections of fields, a
 * section optionally repeating. This class reads the structure, checks it
 * is well-formed, and resolves the concept every field binds to.
 *
 * Shape:
 *
 *   { "pages": [ { "key", "title", "show_when"?, "sections": [
 *       { "key", "title"?, "description"?, "concept"?, "repeat"?: { "min"?, "max"?, "add_label"? },
 *         "show_when"?, "fields": [
 *           { "key", "type", "label", "concept" | "concept_id", "required"?, "help"?, "unit"?,
 *             "min"?, "max"?, "integer"?, "max_length"?, "rows"?, "options"?, "multiple"?,
 *             "default"?, "not_future"?, "show_when"?, "calc"?, "validate"?: [ { "when", "message" } ] }
 *   ] } ] } ] }
 *
 * A label may be a string or a map of locale to text (FRM-12). Keys share
 * one namespace across the whole form so a reference is never ambiguous.
 */
final class FormDefinition
{
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,59}$/';

    /**
     * @var list<string>
     */
    public const CONTEXT_KEYS = ['age', 'age_days', 'sex', 'encounter_at', 'today'];

    /**
     * @param  array<string, mixed>  $raw
     */
    private function __construct(private readonly array $raw) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self($raw);
    }

    /**
     * The definition a new form starts from.
     *
     * @return array<string, mixed>
     */
    public static function blank(): array
    {
        return [
            'pages' => [[
                'key' => 'page_1',
                'title' => 'Page 1',
                'sections' => [[
                    'key' => 'section_1',
                    'title' => 'Section 1',
                    'fields' => [],
                ]],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pages(): array
    {
        $pages = $this->raw['pages'] ?? [];

        return is_array($pages) ? array_values(array_filter($pages, 'is_array')) : [];
    }

    /**
     * Every section with the page it sits on.
     *
     * @return list<array<string, mixed>>
     */
    public function sections(): array
    {
        $sections = [];

        foreach ($this->pages() as $page) {
            foreach (is_array($page['sections'] ?? null) ? $page['sections'] : [] as $section) {
                if (is_array($section)) {
                    $sections[] = [...$section, '_page' => $page['key'] ?? null];
                }
            }
        }

        return $sections;
    }

    /**
     * Every field with the page and section it sits in and whether that
     * section repeats.
     *
     * @return list<array<string, mixed>>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->sections() as $section) {
            foreach (is_array($section['fields'] ?? null) ? $section['fields'] : [] as $field) {
                if (is_array($field)) {
                    $fields[] = [
                        ...$field,
                        '_page' => $section['_page'],
                        '_section' => $section['key'] ?? null,
                        '_repeating' => self::isRepeating($section),
                    ];
                }
            }
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function field(string $key): ?array
    {
        foreach ($this->fields() as $field) {
            if (($field['key'] ?? null) === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $section
     */
    public static function isRepeating(array $section): bool
    {
        return is_array($section['repeat'] ?? null);
    }

    /**
     * The text to show for a page, section, field or option, in a locale.
     *
     * @param  array<string, mixed>  $entity
     */
    public static function label(array $entity, string $locale = 'en', string $attribute = 'label'): string
    {
        $label = $entity[$attribute] ?? $entity['title'] ?? $entity['key'] ?? '';

        if (is_array($label)) {
            $label = $label[$locale] ?? $label['en'] ?? (reset($label) ?: '');
        }

        return (string) $label;
    }

    /**
     * What is wrong with the definition, in the words a form author needs.
     * Empty when the form can be published.
     *
     * @return list<string>
     */
    public function issues(): array
    {
        $issues = [];
        $pages = $this->raw['pages'] ?? null;

        if (! is_array($pages) || $pages === [] || ! array_is_list($pages)) {
            return ['A form needs a "pages" list with at least one page.'];
        }

        $keys = [];
        $fieldsByKey = [];
        $registerKey = function (mixed $key, string $what) use (&$keys, &$issues): ?string {
            if (! is_string($key) || ! preg_match(self::KEY_PATTERN, $key)) {
                $issues[] = "Every {$what} needs a \"key\": lowercase letters, digits and underscores, starting with a letter.";

                return null;
            }

            if (isset($keys[$key])) {
                $issues[] = "The key \"{$key}\" is used more than once; keys must be unique across the whole form.";

                return null;
            }

            $keys[$key] = $what;

            return $key;
        };

        foreach ($pages as $page) {
            if (! is_array($page)) {
                $issues[] = 'Every page must be an object.';

                continue;
            }

            $pageKey = $registerKey($page['key'] ?? null, 'page');
            $sections = $page['sections'] ?? null;

            if (! is_array($sections) || ! array_is_list($sections)) {
                $issues[] = 'Page "'.($pageKey ?? '?').'" needs a "sections" list.';

                continue;
            }

            $issues = [...$issues, ...Conditions::problems($page['show_when'] ?? null, 'Page "'.($pageKey ?? '?').'" show_when')];

            foreach ($sections as $section) {
                if (! is_array($section)) {
                    $issues[] = 'Every section must be an object.';

                    continue;
                }

                $sectionKey = $registerKey($section['key'] ?? null, 'section');
                $where = 'Section "'.($sectionKey ?? '?').'"';
                $fields = $section['fields'] ?? null;

                if (! is_array($fields) || ! array_is_list($fields)) {
                    $issues[] = "{$where} needs a \"fields\" list.";

                    continue;
                }

                if (array_key_exists('repeat', $section) && ! is_array($section['repeat'])) {
                    $issues[] = "{$where}: \"repeat\" must be an object (it may be empty).";
                }

                $issues = [...$issues, ...Conditions::problems($section['show_when'] ?? null, "{$where} show_when")];

                foreach ($fields as $field) {
                    if (! is_array($field)) {
                        $issues[] = "{$where}: every field must be an object.";

                        continue;
                    }

                    $fieldKey = $registerKey($field['key'] ?? null, 'field');

                    if ($fieldKey !== null) {
                        $fieldsByKey[$fieldKey] = [...$field, '_section' => $sectionKey, '_repeating' => self::isRepeating($section)];
                    }

                    $issues = [...$issues, ...$this->fieldIssues($field, $fieldKey ?? '?')];
                }
            }
        }

        // References: a condition or calculation may only read fields that
        // exist, and a field outside a repeating group cannot read one
        // inside it (which repeat would it mean?).
        foreach ($fieldsByKey as $key => $field) {
            $refs = [
                ...Conditions::fieldReferences($field['show_when'] ?? null),
                ...Calculations::fieldReferences($field['calc'] ?? null),
            ];

            foreach (is_array($field['validate'] ?? null) ? $field['validate'] : [] as $rule) {
                $refs = [...$refs, ...Conditions::fieldReferences(is_array($rule) ? ($rule['when'] ?? null) : null)];
            }

            foreach (array_unique($refs) as $ref) {
                if (! isset($fieldsByKey[$ref])) {
                    $issues[] = "Field \"{$key}\" refers to \"{$ref}\", which is not a field on this form.";

                    continue;
                }

                $target = $fieldsByKey[$ref];

                if ($target['_repeating'] && (! $field['_repeating'] || $target['_section'] !== $field['_section'])) {
                    $issues[] = "Field \"{$key}\" refers to \"{$ref}\" inside repeating group \"{$target['_section']}\"; only fields in the same repeat can.";
                }
            }
        }

        return array_values(array_unique($issues));
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private function fieldIssues(array $field, string $key): array
    {
        $issues = [];
        $where = "Field \"{$key}\"";
        $type = FormFieldType::tryFrom((string) ($field['type'] ?? ''));

        if ($type === null) {
            $issues[] = "{$where}: unknown type \"".(is_scalar($field['type'] ?? null) ? $field['type'] : '?').'".';
        }

        if (self::label($field) === '') {
            $issues[] = "{$where} needs a label.";
        }

        // Every field binds to a concept (FRM-03).
        $hasConcept = (isset($field['concept']) && is_string($field['concept']) && trim($field['concept']) !== '')
            || (isset($field['concept_id']) && is_int($field['concept_id']));

        if (! $hasConcept) {
            $issues[] = "{$where} must bind to a dictionary concept (\"concept\": its local code).";
        }

        if ($type === FormFieldType::Coded) {
            $options = $field['options'] ?? null;

            if (! is_array($options) || $options === [] || ! array_is_list($options)) {
                $issues[] = "{$where}: a coded field needs an \"options\" list.";
            } else {
                $values = [];

                foreach ($options as $option) {
                    if (! is_array($option) || ! isset($option['value']) || ! is_scalar($option['value']) || self::label($option) === '') {
                        $issues[] = "{$where}: every option needs a \"value\" and a \"label\".";

                        continue;
                    }

                    if (in_array((string) $option['value'], $values, true)) {
                        $issues[] = "{$where}: option value \"{$option['value']}\" appears twice.";
                    }

                    $values[] = (string) $option['value'];
                }
            }
        }

        if ($type === FormFieldType::Numeric
            && isset($field['min'], $field['max'])
            && is_numeric($field['min']) && is_numeric($field['max'])
            && (float) $field['min'] > (float) $field['max']) {
            $issues[] = "{$where}: \"min\" is greater than \"max\".";
        }

        if ($type === FormFieldType::Calculated) {
            if (! isset($field['calc'])) {
                $issues[] = "{$where}: a calculated field needs a \"calc\" expression.";
            } else {
                $issues = [...$issues, ...Calculations::problems($field['calc'], "{$where} calc")];
            }
        } elseif (isset($field['calc'])) {
            $issues[] = "{$where}: only a calculated field may carry \"calc\".";
        }

        $issues = [...$issues, ...Conditions::problems($field['show_when'] ?? null, "{$where} show_when")];

        if (array_key_exists('validate', $field)) {
            if (! is_array($field['validate']) || ! array_is_list($field['validate'])) {
                $issues[] = "{$where}: \"validate\" must be a list of { when, message } rules.";
            } else {
                foreach ($field['validate'] as $rule) {
                    if (! is_array($rule) || ! isset($rule['when']) || ! is_string($rule['message'] ?? null) || trim($rule['message']) === '') {
                        $issues[] = "{$where}: every validate rule needs a \"when\" condition and a \"message\".";

                        continue;
                    }

                    $issues = [...$issues, ...Conditions::problems($rule['when'], "{$where} validate")];
                }
            }
        }

        return $issues;
    }

    /**
     * The local concept codes the definition names but has not resolved.
     *
     * @return list<string>
     */
    public function conceptCodes(): array
    {
        $codes = [];

        $collect = function (array $entity) use (&$codes): void {
            if (isset($entity['concept']) && is_string($entity['concept']) && ! isset($entity['concept_id'])) {
                $codes[] = trim($entity['concept']);
            }
        };

        foreach ($this->sections() as $section) {
            $collect($section);
        }

        foreach ($this->fields() as $field) {
            $collect($field);

            foreach (is_array($field['options'] ?? null) ? $field['options'] : [] as $option) {
                if (is_array($option)) {
                    $collect($option);
                }
            }
        }

        return array_values(array_unique(array_filter($codes)));
    }

    /**
     * A copy with every concept code resolved to its id, ready to publish.
     * Codes the resolver does not know are left as they are.
     *
     * @param  callable(string $code): ?int  $resolver
     */
    public function resolveConcepts(callable $resolver): self
    {
        $resolve = function (array $entity) use ($resolver): array {
            if (isset($entity['concept']) && is_string($entity['concept']) && ! isset($entity['concept_id'])) {
                $id = $resolver(trim($entity['concept']));

                if ($id !== null) {
                    $entity['concept_id'] = $id;
                }
            }

            return $entity;
        };

        $raw = $this->raw;

        foreach ($raw['pages'] ?? [] as $p => $page) {
            foreach ($page['sections'] ?? [] as $s => $section) {
                $section = $resolve($section);

                foreach ($section['fields'] ?? [] as $f => $field) {
                    $field = $resolve($field);

                    foreach ($field['options'] ?? [] as $o => $option) {
                        $field['options'][$o] = is_array($option) ? $resolve($option) : $option;
                    }

                    $section['fields'][$f] = $field;
                }

                $raw['pages'][$p]['sections'][$s] = $section;
            }
        }

        return new self($raw);
    }

    /**
     * A copy with resolved concept ids dropped, so the definition travels
     * between installations by code rather than by this database's ids.
     */
    public function withoutConceptIds(): self
    {
        $strip = function (array $entity): array {
            if (isset($entity['concept'])) {
                unset($entity['concept_id']);
            }

            return $entity;
        };

        $raw = $this->raw;

        foreach ($raw['pages'] ?? [] as $p => $page) {
            foreach ($page['sections'] ?? [] as $s => $section) {
                $section = $strip($section);

                foreach ($section['fields'] ?? [] as $f => $field) {
                    $field = $strip($field);

                    foreach ($field['options'] ?? [] as $o => $option) {
                        $field['options'][$o] = is_array($option) ? $strip($option) : $option;
                    }

                    $section['fields'][$f] = $field;
                }

                $raw['pages'][$p]['sections'][$s] = $section;
            }
        }

        return new self($raw);
    }
}
