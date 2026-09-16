<?php

namespace App\Support\Forms;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Evaluates the calculation a calculated field declares (PRD FRM-04). An
 * expression is a number, a reference, or an operation over expressions:
 *
 *   { "op": "/", "args": [ { "field": "weight" },
 *                          { "op": "^", "args": [ { "op": "/", "args": [ { "field": "height" }, 100 ] }, 2 ] } ] }
 *
 * A missing input makes the whole result missing rather than a wrong
 * number. The same JSON is evaluated on the client for live display.
 */
final class Calculations
{
    /**
     * @var list<string>
     */
    public const OPERATORS = ['+', '-', '*', '/', '^', 'round', 'abs', 'min', 'max', 'minutes_between', 'days_between', 'years_between', 'if'];

    /**
     * @param  callable(string $kind, string $name): mixed  $lookup
     */
    public static function evaluate(mixed $expression, callable $lookup): int|float|null
    {
        if ($expression === null || $expression === '') {
            return null;
        }

        if (is_int($expression) || is_float($expression)) {
            return $expression;
        }

        if (is_numeric($expression)) {
            return (float) $expression;
        }

        if (! is_array($expression)) {
            return null;
        }

        if (array_key_exists('field', $expression)) {
            return self::number($lookup('field', (string) $expression['field']));
        }

        if (array_key_exists('context', $expression)) {
            return self::number($lookup('context', (string) $expression['context']));
        }

        $op = $expression['op'] ?? null;
        $args = is_array($expression['args'] ?? null) ? array_values($expression['args']) : [];

        if ($op === 'if') {
            $branch = Conditions::evaluate($args[0] ?? null, $lookup) ? ($args[1] ?? null) : ($args[2] ?? null);

            return self::evaluate($branch, $lookup);
        }

        if (in_array($op, ['minutes_between', 'days_between', 'years_between'], true)) {
            return self::between((string) $op, $args, $lookup);
        }

        $values = [];

        foreach ($args as $arg) {
            $value = self::evaluate($arg, $lookup);

            if ($value === null) {
                return null;
            }

            $values[] = $value;
        }

        return match ($op) {
            '+' => array_sum($values),
            '-' => count($values) === 1 ? -$values[0] : self::fold($values, fn ($a, $b) => $a - $b),
            '*' => array_product($values),
            '/' => self::fold($values, fn ($a, $b) => $b == 0 ? null : $a / $b),
            '^' => count($values) === 2 ? $values[0] ** $values[1] : null,
            'round' => count($values) >= 1 ? round($values[0], (int) ($values[1] ?? 0)) : null,
            'abs' => count($values) === 1 ? abs($values[0]) : null,
            'min' => $values === [] ? null : min($values),
            'max' => $values === [] ? null : max($values),
            default => null,
        };
    }

    /**
     * The field keys an expression reads, for definition checks.
     *
     * @return list<string>
     */
    public static function fieldReferences(mixed $expression): array
    {
        if (! is_array($expression)) {
            return [];
        }

        if (isset($expression['field'])) {
            return [(string) $expression['field']];
        }

        $refs = [];

        if (($expression['op'] ?? null) === 'if') {
            $refs = Conditions::fieldReferences($expression['args'][0] ?? null);
            $rest = array_slice(is_array($expression['args'] ?? null) ? $expression['args'] : [], 1);
        } else {
            $rest = is_array($expression['args'] ?? null) ? $expression['args'] : [];
        }

        foreach ($rest as $arg) {
            $refs = [...$refs, ...self::fieldReferences($arg)];
        }

        return array_values(array_unique($refs));
    }

    /**
     * @return list<string> problems found, empty when well-formed
     */
    public static function problems(mixed $expression, string $where): array
    {
        if ($expression === null || is_int($expression) || is_float($expression) || is_numeric($expression)) {
            return [];
        }

        if (! is_array($expression)) {
            return ["{$where}: a calculation must be a number, a reference or an operation."];
        }

        if (array_key_exists('field', $expression) || array_key_exists('context', $expression)) {
            return [];
        }

        $op = $expression['op'] ?? null;

        if (! is_string($op) || ! in_array($op, self::OPERATORS, true)) {
            return ["{$where}: unknown operation \"".(is_scalar($op) ? $op : '?').'".'];
        }

        $args = $expression['args'] ?? null;

        if (! is_array($args)) {
            return ["{$where}: \"{$op}\" needs an \"args\" list."];
        }

        $problems = [];

        if ($op === 'if') {
            $problems = Conditions::problems($args[0] ?? null, $where);
            $args = array_slice($args, 1);
        }

        foreach ($args as $arg) {
            $problems = [...$problems, ...self::problems($arg, $where)];
        }

        return $problems;
    }

    /**
     * @param  list<int|float>  $values
     * @param  callable(int|float, int|float): (int|float|null)  $step
     */
    private static function fold(array $values, callable $step): int|float|null
    {
        if ($values === []) {
            return null;
        }

        $acc = $values[0];

        foreach (array_slice($values, 1) as $value) {
            $acc = $step($acc, $value);

            if ($acc === null) {
                return null;
            }
        }

        return $acc;
    }

    /**
     * @param  list<mixed>  $args
     * @param  callable(string $kind, string $name): mixed  $lookup
     */
    private static function between(string $op, array $args, callable $lookup): ?float
    {
        $from = self::moment($args[0] ?? null, $lookup);
        $to = self::moment($args[1] ?? null, $lookup);

        if ($from === null || $to === null) {
            return null;
        }

        return match ($op) {
            'minutes_between' => round($from->diffInMinutes($to, false), 2),
            'days_between' => round($from->diffInDays($to, false), 2),
            'years_between' => round($from->diffInYears($to, false), 2),
            default => null,
        };
    }

    /**
     * @param  callable(string $kind, string $name): mixed  $lookup
     */
    private static function moment(mixed $expression, callable $lookup): ?Carbon
    {
        $value = $expression;

        if (is_array($expression) && array_key_exists('field', $expression)) {
            $value = $lookup('field', (string) $expression['field']);
        } elseif (is_array($expression) && array_key_exists('context', $expression)) {
            $value = $lookup('context', (string) $expression['context']);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private static function number(mixed $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
