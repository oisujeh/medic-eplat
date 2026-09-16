<?php

namespace App\Support\Forms;

/**
 * Evaluates the declarative conditions a form definition uses for skip
 * logic, show/hide and cross-field validation (PRD FRM-04).
 *
 * A condition is one of:
 *
 *   { "field": "pregnant", "op": "eq", "value": "yes" }
 *   { "all": [ ...conditions ] }
 *   { "any": [ ...conditions ] }
 *   { "not": condition }
 *
 * `value` may be a literal, another field ({ "field": "start" }) or a
 * patient context value ({ "context": "age" }). The same JSON is evaluated
 * on the client, so the two sides always agree.
 */
final class Conditions
{
    /**
     * @var list<string>
     */
    public const OPERATORS = ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'empty', 'not_empty', 'contains'];

    /**
     * @param  callable(string $kind, string $name): mixed  $lookup  resolves "field" and "context" references
     */
    public static function evaluate(mixed $condition, callable $lookup): bool
    {
        if ($condition === null || $condition === [] || $condition === true) {
            return true;
        }

        if ($condition === false) {
            return false;
        }

        if (! is_array($condition)) {
            return false;
        }

        if (array_key_exists('all', $condition)) {
            foreach ((array) $condition['all'] as $child) {
                if (! self::evaluate($child, $lookup)) {
                    return false;
                }
            }

            return true;
        }

        if (array_key_exists('any', $condition)) {
            foreach ((array) $condition['any'] as $child) {
                if (self::evaluate($child, $lookup)) {
                    return true;
                }
            }

            return false;
        }

        if (array_key_exists('not', $condition)) {
            return ! self::evaluate($condition['not'], $lookup);
        }

        if (! isset($condition['field']) || ! isset($condition['op'])) {
            return false;
        }

        $actual = $lookup('field', (string) $condition['field']);
        $expected = self::operand($condition['value'] ?? null, $lookup);

        return self::compare((string) $condition['op'], $actual, $expected);
    }

    /**
     * The field keys a condition reads, for definition checks.
     *
     * @return list<string>
     */
    public static function fieldReferences(mixed $condition): array
    {
        if (! is_array($condition)) {
            return [];
        }

        $refs = [];

        foreach (['all', 'any'] as $group) {
            if (isset($condition[$group]) && is_array($condition[$group])) {
                foreach ($condition[$group] as $child) {
                    $refs = [...$refs, ...self::fieldReferences($child)];
                }
            }
        }

        if (isset($condition['not'])) {
            $refs = [...$refs, ...self::fieldReferences($condition['not'])];
        }

        if (isset($condition['field'])) {
            $refs[] = (string) $condition['field'];
        }

        if (isset($condition['value']) && is_array($condition['value']) && isset($condition['value']['field'])) {
            $refs[] = (string) $condition['value']['field'];
        }

        return array_values(array_unique($refs));
    }

    /**
     * Whether a condition is well-formed: known operators and groups only.
     *
     * @return list<string> problems found, empty when well-formed
     */
    public static function problems(mixed $condition, string $where): array
    {
        if ($condition === null || $condition === []) {
            return [];
        }

        if (! is_array($condition)) {
            return ["{$where}: a condition must be an object."];
        }

        $problems = [];

        foreach (['all', 'any'] as $group) {
            if (array_key_exists($group, $condition)) {
                if (! is_array($condition[$group])) {
                    return ["{$where}: \"{$group}\" must be a list of conditions."];
                }

                foreach ($condition[$group] as $child) {
                    $problems = [...$problems, ...self::problems($child, $where)];
                }

                return $problems;
            }
        }

        if (array_key_exists('not', $condition)) {
            return self::problems($condition['not'], $where);
        }

        if (! isset($condition['field']) || ! is_string($condition['field'])) {
            $problems[] = "{$where}: a condition needs a \"field\".";
        }

        if (! isset($condition['op']) || ! in_array($condition['op'], self::OPERATORS, true)) {
            $problems[] = "{$where}: unknown operator \"".(is_scalar($condition['op'] ?? null) ? $condition['op'] : '?').'".';
        }

        return $problems;
    }

    /**
     * @param  callable(string $kind, string $name): mixed  $lookup
     */
    private static function operand(mixed $value, callable $lookup): mixed
    {
        if (is_array($value) && array_key_exists('field', $value) && count($value) === 1) {
            return $lookup('field', (string) $value['field']);
        }

        if (is_array($value) && array_key_exists('context', $value) && count($value) === 1) {
            return $lookup('context', (string) $value['context']);
        }

        return $value;
    }

    private static function compare(string $op, mixed $actual, mixed $expected): bool
    {
        return match ($op) {
            'empty' => self::isEmpty($actual),
            'not_empty' => ! self::isEmpty($actual),
            'eq' => self::equals($actual, $expected),
            'ne' => ! self::equals($actual, $expected),
            'gt' => self::ordered($actual, $expected) === 1,
            'gte' => in_array(self::ordered($actual, $expected), [0, 1], true),
            'lt' => self::ordered($actual, $expected) === -1,
            'lte' => in_array(self::ordered($actual, $expected), [0, -1], true),
            'in' => is_array($expected) && self::inList($actual, $expected),
            'not_in' => is_array($expected) && ! self::inList($actual, $expected),
            'contains' => self::contains($actual, $expected),
            default => false,
        };
    }

    public static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private static function equals(mixed $a, mixed $b): bool
    {
        if (self::isEmpty($a) && self::isEmpty($b)) {
            return true;
        }

        if (is_bool($a) || is_bool($b)) {
            return self::truthy($a) === self::truthy($b);
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        if (is_array($a) || is_array($b)) {
            return $a == $b;
        }

        return (string) $a === (string) $b;
    }

    /**
     * -1, 0 or 1 when both sides are comparable; null otherwise.
     */
    private static function ordered(mixed $a, mixed $b): ?int
    {
        if (self::isEmpty($a) || self::isEmpty($b)) {
            return null;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a <=> (float) $b;
        }

        if (is_string($a) && is_string($b)) {
            return strcmp($a, $b) <=> 0;
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $list
     */
    private static function inList(mixed $value, array $list): bool
    {
        foreach ($list as $candidate) {
            if (self::equals($value, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private static function contains(mixed $haystack, mixed $needle): bool
    {
        if (is_array($haystack)) {
            return self::inList($needle, array_values($haystack));
        }

        if (is_string($haystack) && is_scalar($needle)) {
            return $needle !== '' && str_contains(mb_strtolower($haystack), mb_strtolower((string) $needle));
        }

        return false;
    }

    private static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(mb_strtolower($value), ['1', 'true', 'yes', 'y'], true);
        }

        return (bool) $value;
    }
}
