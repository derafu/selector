<?php

declare(strict_types=1);

/**
 * Derafu: Selector - Elegant Data Structure Navigation for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Selector\Types;

use Derafu\Selector\Contract\SelectorTypeInterface;
use Derafu\Selector\Exception\SelectorException;
use Derafu\Selector\Exception\UnsupportedOperationException;
use Derafu\Selector\Parser\SelectorParser;
use Derafu\Selector\Selector;
use Stringable;

/**
 * Implements composite selector type that handles multiple selector parts.
 *
 * This selector type manages expressions that combine multiple selectors
 * with operations like OR and concatenation.
 */
final class CompositeSelector implements SelectorTypeInterface
{
    private readonly SelectorParser $parser;

    /**
     * @param array<array-key, array<string, string>> $tokens Array of tokenized selector parts.
     */
    public function __construct(
        private array $tokens
    ) {
        $this->parser = Selector::getParser();
    }

    /**
     * Reads a value by evaluating all parts and combining them according to
     * operations.
     *
     * @param array<array-key, mixed> $data The data structure to read from.
     * @return mixed The final combined value.
     */
    public function read(array $data): mixed
    {
        $finalValue = null;

        foreach ($this->tokens as $token) {
            if ($token['type'] === 'string') {
                $finalValue = self::concatenateValues(
                    $finalValue,
                    $token['value']
                );
            } elseif (in_array($token['type'], ['selector', 'if'])) {
                // Possible OR operators are searched for and processed.
                $subSelectors = explode('||', $token['id']);
                $orValueFound = false;
                foreach ($subSelectors as $subSelector) {
                    $value = $this->parser->parse($subSelector)->read($data);
                    if ($value !== null && $value !== '') {
                        $finalValue = self::concatenateValues(
                            $finalValue,
                            $value
                        );
                        $orValueFound = true;
                        break;
                    }
                }
                // If no valid value was found, the last value found is used.
                if (!$orValueFound) {
                    if (str_contains($token['id'], '||')) {
                        $value = null;
                    }
                    $finalValue = self::concatenateValues($finalValue, $value);
                }
            } elseif ($token['type'] === 'operator' && $token['value'] === 'or') {
                if ($finalValue !== null) {
                    break;
                }
            }
        }

        return $finalValue;
    }

    /**
     * Writing is not supported for composite selectors.
     *
     * @param array<array-key, mixed> $data The data structure to modify.
     * @param mixed $value The value to write.
     * @throws UnsupportedOperationException Always throws as operation is not supported.
     */
    public function write(array &$data, mixed $value): void
    {
        throw new UnsupportedOperationException(
            'Write not supported for composite selectors.'
        );
    }

    /**
     * Clearing is not supported for composite selectors.
     *
     * @param array<array-key, mixed> $data The data structure to modify.
     * @throws UnsupportedOperationException Always throws as operation is not supported.
     */
    public function clear(array &$data): void
    {
        throw new UnsupportedOperationException(
            'Clear not supported for composite selectors.'
        );
    }

    /**
     * Concatenates two values, handling special cases and type conversions.
     *
     * @param mixed $currentValue The current accumulated value.
     * @param mixed $newValue The new value to concatenate.
     * @return mixed The concatenated result.
     * @throws SelectorException If a value that needs conversion has an unsupported type.
     */
    private function concatenateValues(mixed $currentValue, mixed $newValue): mixed
    {
        if (is_array($newValue)) {
            $elements = [];
            foreach ($newValue as $element) {
                $element = $this->requireConvertibleValue($element);
                $elements[] = match (true) {
                    $element === null => 'null',
                    is_bool($element) => $element ? 'true' : 'false',
                    default => (string) $element,
                };
            }
            $newValue = '[' . implode(', ', $elements) . ']';
        }

        if ($currentValue === null) {
            return $newValue;
        }

        if (is_string($currentValue)) {
            if ($newValue === null) {
                $newValue = 'null';
            } elseif (is_bool($newValue)) {
                $newValue = $newValue ? 'true' : 'false';
            }

            return $currentValue . (string) $this->requireConvertibleValue($newValue);
        }

        if (is_bool($currentValue)) {
            $currentValue = $currentValue ? 'true' : 'false';
        }

        if (is_bool($newValue)) {
            $newValue = $newValue ? 'true' : 'false';
        }

        return (string) $this->requireConvertibleValue($currentValue)
            . (string) $this->requireConvertibleValue($newValue);
    }

    /**
     * Validates a value only when it needs conversion to text.
     *
     * @param mixed $value The value to validate.
     * @return bool|int|float|string|Stringable|null The value without conversion.
     * @throws SelectorException If the value has an unsupported type.
     */
    private function requireConvertibleValue(mixed $value): bool|int|float|string|Stringable|null
    {
        if ($value !== null && !is_scalar($value) && !$value instanceof Stringable) {
            throw new SelectorException([
                'The value to concatenate must be a bool, int, float, string, Stringable, or null; {type} given.',
                'type' => get_debug_type($value),
            ]);
        }

        return $value;
    }
}
