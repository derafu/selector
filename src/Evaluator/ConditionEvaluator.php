<?php

declare(strict_types=1);

/**
 * Derafu: Selector - Elegant Data Structure Navigation for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Selector\Evaluator;

use Derafu\Selector\Exception\EvaluatorException;
use Stringable;

/**
 * Evaluates conditions for the conditional selector.
 *
 * This class provides the logic for evaluating different types of conditions
 * such as equality, comparison, containment, etc. It normalizes values for
 * comparison and handles type conversion where appropriate.
 */
final class ConditionEvaluator
{
    /**
     * Evaluates a condition using the specified operator and values.
     *
     * @param mixed $valueA First value to compare.
     * @param string $operator The comparison operator.
     * @param mixed $valueB Second value to compare.
     * @return bool Result of the condition evaluation.
     * @throws EvaluatorException If the operator or an operand type is not supported.
     */
    public function evaluate(mixed $valueA, string $operator, mixed $valueB): bool
    {
        // Convert boolean values to strings for consistent comparison.
        if (is_bool($valueA)) {
            $valueA = $valueA ? 'true' : 'false';
        }

        return match ($operator) {
            // Equality operators.
            '=', '==' => $this->evaluateEquality(
                $this->requireConvertibleValue($valueA),
                $this->requireConvertibleValue($valueB)
            ),
            '!=', '<>' => !$this->evaluateEquality(
                $this->requireConvertibleValue($valueA),
                $this->requireConvertibleValue($valueB)
            ),

            // Comparison operators.
            '>', '>=', '<', '<=' => $this->evaluateComparison(
                $this->requireConvertibleValue($valueA),
                $this->requireConvertibleValue($valueB),
                $operator
            ),

            // Special operators.
            'contains' => $this->evaluateContains(
                $this->requireContainsValue($valueA),
                $this->requireConvertibleValue($valueB)
            ),
            'length' => $this->evaluateLength($valueA, $this->requireConvertibleValue($valueB)),
            'is' => $this->evaluateIs($valueA, $this->requireConvertibleValue($valueB)),

            default => throw new EvaluatorException(
                [
                    'Operator not implemented: {operator}',
                    'operator' => $operator,
                ]
            )
        };
    }

    /**
     * Validates an operand without converting or normalizing it.
     *
     * @param mixed $value The operand to validate.
     * @return bool|int|float|string|Stringable|null The validated operand.
     * @throws EvaluatorException If the operand cannot be converted to string.
     */
    private function requireConvertibleValue(mixed $value): bool|int|float|string|Stringable|null
    {
        if ($value !== null && !is_scalar($value) && !$value instanceof Stringable) {
            throw new EvaluatorException([
                'The condition operand must be a bool, int, float, string, Stringable, or null; {type} given.',
                'type' => get_debug_type($value),
            ]);
        }

        return $value;
    }

    /**
     * Validates a contains operand, including each element of an array.
     *
     * @param mixed $value The operand to validate.
     * @return array<array-key, bool|int|float|string|Stringable|null>|bool|int|float|string|Stringable|null The validated operand.
     * @throws EvaluatorException If the operand or an array element has an unsupported type.
     */
    private function requireContainsValue(mixed $value): array|bool|int|float|string|Stringable|null
    {
        if (!is_array($value)) {
            return $this->requireConvertibleValue($value);
        }

        $validated = [];
        foreach ($value as $key => $element) {
            $validated[$key] = $this->requireConvertibleValue($element);
        }

        return $validated;
    }

    /**
     * Evaluates equality between two values.
     *
     * @param bool|int|float|string|Stringable|null $valueA First value.
     * @param bool|int|float|string|Stringable|null $valueB Second value.
     * @return bool True if values are equal.
     */
    private function evaluateEquality(
        bool|int|float|string|Stringable|null $valueA,
        bool|int|float|string|Stringable|null $valueB
    ): bool {
        $a = $this->normalizeValue($valueA);
        $b = $this->normalizeValue($valueB);

        return $a === $b;
    }

    /**
     * Normalizes values for comparison.
     *
     * Handles special cases like boolean values and strings with quotes.
     * Boolean values are converted to 'true'/'false' strings.
     * String values have their surrounding quotes removed.
     *
     * @param bool|int|float|string|Stringable|null $value The value to normalize.
     * @return string The normalized value as a string.
     */
    private function normalizeValue(
        bool|int|float|string|Stringable|null $value
    ): string {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return trim((string)$value, '"');
    }

    /**
     * Evaluates numeric comparison between two values.
     *
     * @param bool|int|float|string|Stringable|null $valueA First value.
     * @param bool|int|float|string|Stringable|null $valueB Second value.
     * @param string $operator Comparison operator.
     * @return bool Result of the comparison.
     */
    private function evaluateComparison(
        bool|int|float|string|Stringable|null $valueA,
        bool|int|float|string|Stringable|null $valueB,
        string $operator
    ): bool {
        $a = is_numeric($valueA) ? (float)$valueA : (string)$valueA;
        $b = is_numeric($valueB) ? (float)$valueB : (string)$valueB;

        return match ($operator) {
            '>' => $a > $b,
            '>=' => $a >= $b,
            '<' => $a < $b,
            '<=' => $a <= $b,
            default => false
        };
    }

    /**
     * Evaluates if one value contains another.
     *
     * Works with both arrays and strings.
     *
     * @param array<array-key, bool|int|float|string|Stringable|null>|bool|int|float|string|Stringable|null $valueA Value to search in.
     * @param bool|int|float|string|Stringable|null $valueB Value to search for.
     * @return bool True if valueA contains valueB.
     */
    private function evaluateContains(
        array|bool|int|float|string|Stringable|null $valueA,
        bool|int|float|string|Stringable|null $valueB
    ): bool {
        if (is_array($valueA)) {
            $valueB = is_numeric($valueB)
                ? (float) trim((string) $valueB, '"')
                : (string) $valueB
            ;

            return in_array(
                $valueB,
                array_map(
                    fn ($v) => is_numeric($v) ? (float)$v : (string)$v,
                    $valueA
                )
            );
        }

        return str_contains((string)$valueA, (string)$valueB);
    }

    /**
     * Evaluates if a value has the specified length.
     *
     * Works with both arrays and strings.
     *
     * @param mixed $valueA Value to check length of.
     * @param bool|int|float|string|Stringable|null $valueB Expected length.
     * @return bool True if the length matches.
     */
    private function evaluateLength(
        mixed $valueA,
        bool|int|float|string|Stringable|null $valueB
    ): bool {
        if (!is_array($valueA) && !is_string($valueA)) {
            return false;
        }

        $length = is_array($valueA) ? count($valueA) : strlen($valueA);

        $expected = trim((string) $valueB, '"');

        return $length === (int)$expected;
    }

    /**
     * Evaluates special "is" conditions.
     *
     * Currently supports null checks.
     *
     * @param mixed $valueA Value to check.
     * @param bool|int|float|string|Stringable|null $valueB Condition to check against.
     * @return bool Result of the evaluation.
     */
    private function evaluateIs(
        mixed $valueA,
        bool|int|float|string|Stringable|null $valueB
    ): bool {
        $valueB = trim((string) $valueB, '"');
        if ($valueB === 'None' || $valueB === 'null') {
            return $valueA === null;
        }

        return $valueA !== 'null';
    }
}
