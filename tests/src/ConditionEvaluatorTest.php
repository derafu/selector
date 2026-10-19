<?php

declare(strict_types=1);

/**
 * Derafu: Selector - Elegant Data Structure Navigation for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSelector;

use Derafu\Selector\Evaluator\ConditionEvaluator;
use Derafu\Selector\Exception\EvaluatorException;
use Derafu\Selector\Exception\SelectorException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(ConditionEvaluator::class)]
#[UsesClass(EvaluatorException::class)]
#[UsesClass(SelectorException::class)]
final class ConditionEvaluatorTest extends TestCase
{
    /** @return array<string, array{mixed, mixed, bool}> */
    public static function equalityCases(): array
    {
        return [
            'different strings' => ['first', 'second', false],
            'quoted string' => ['"value"', 'value', true],
            'spaces are preserved' => [' value ', 'value', false],
            'single quotes are preserved' => ["'value'", 'value', false],
            'null and empty string' => [null, '', true],
            'true' => [true, 'true', true],
            'false' => [false, 'false', true],
            'false is not empty string' => [false, '', false],
            'zero' => [0, '0', true],
            'zero is not empty string' => [0, '', false],
            'leading zeros are preserved' => [1, '01', false],
            'integer' => [12345, '12345', true],
            'float' => [-1.5, '-1.5', true],
            'stringable' => [new ConditionString('"value"'), 'value', true],
        ];
    }

    #[DataProvider('equalityCases')]
    public function testEqualityAndInequalityKeepTheirNormalization(mixed $left, mixed $right, bool $equal): void
    {
        $evaluator = new ConditionEvaluator();
        foreach (['=' => $equal, '==' => $equal, '!=' => !$equal, '<>' => !$equal] as $operator => $expected) {
            $this->assertSame($expected, $evaluator->evaluate($left, $operator, $right));
            $this->assertSame($expected, $evaluator->evaluate($right, $operator, $left));
        }
    }

    /** @return array<string, array{mixed, string, mixed, bool}> */
    public static function operationCases(): array
    {
        return [
            'numeric greater' => ['10', '>', 2, true],
            'numeric greater false' => [2, '>', '10', false],
            'numeric greater or equal' => [2.5, '>=', '2.5', true],
            'numeric greater or equal false' => [1, '>=', 2, false],
            'numeric less' => ['2', '<', '10', true],
            'numeric less false' => [2, '<', 2, false],
            'numeric less or equal' => ['2', '<=', 2, true],
            'numeric less or equal false' => [3, '<=', 2, false],
            'lexical comparison' => ['beta', '>', 'alpha', true],
            'stringable left comparison' => [new ConditionString('beta'), '>', 'alpha', true],
            'stringable right comparison' => ['alpha', '<', new ConditionString('beta'), true],
            'boolean comparison' => [true, '>=', 'true', true],
            'null comparison' => [null, '<', 'alpha', true],
            'string contains' => ['alphabet', 'contains', 'pha', true],
            'string does not contain' => ['alphabet', 'contains', 'xyz', false],
            'numeric input contains' => [12345, 'contains', 234, true],
            'stringable input contains' => [new ConditionString('alphabet'), 'contains', 'pha', true],
            'stringable needle' => ['alphabet', 'contains', new ConditionString('pha'), true],
            'boolean needle keeps empty string conversion' => ['abc', 'contains', false, true],
            'boolean input keeps word conversion' => [false, 'contains', 'alse', true],
            'null contains empty string' => [null, 'contains', null, true],
            'numeric array needle' => [[1, 2, 3], 'contains', 2, true],
            'float array needle' => [[1, 2.5, 3], 'contains', 2.5, true],
            'numeric string array needle' => [[1, '2', 3], 'contains', '2', true],
            'array missing needle' => [[1, 2, 3], 'contains', 4, false],
            'empty collection' => [[], 'contains', 'value', false],
            'array null element' => [[null], 'contains', null, true],
            'array boolean element' => [[true], 'contains', true, true],
            'stringable array element' => [['named' => new ConditionString('value')], 'contains', 'value', true],
            'array quotes are preserved' => [[new ConditionString('"value"')], 'contains', 'value', false],
            'array quoted needle' => [['"value"'], 'contains', '"value"', true],
            'string length' => ['abc', 'length', '3', true],
            'integer expected length' => ['abc', 'length', 3, true],
            'float expected length' => ['abc', 'length', 3.5, true],
            'quoted expected length' => ['abc', 'length', '"3"', true],
            'stringable expected length' => ['abc', 'length', new ConditionString('3'), true],
            'wrong length' => ['abc', 'length', 2, false],
            'array length counts without converting elements' => [[new stdClass(), ['nested']], 'length', 2, true],
            'null expected length' => [[], 'length', null, true],
            'false expected length' => ['', 'length', false, true],
            'true expected length' => ['a', 'length', true, true],
            'boolean input length keeps word conversion' => [false, 'length', 5, true],
            'unsupported length input' => [123, 'length', 3, false],
            'stringable is not a length input' => [new ConditionString('abc'), 'length', 3, false],
            'object is not a length input' => [new stdClass(), 'length', 1, false],
            'null is null' => [null, 'is', 'null', true],
            'null is None' => [null, 'is', 'None', true],
            'quoted null condition' => [null, 'is', '"null"', true],
            'literal null string is not null' => ['null', 'is', 'null', false],
            'array is not null' => [[new stdClass()], 'is', 'null', false],
            'object is not null' => [new stdClass(), 'is', 'null', false],
            'stringable null condition' => [null, 'is', new ConditionString('null'), true],
            'other is condition preserves false result' => ['null', 'is', 'other', false],
            'other is condition preserves true result' => ['value', 'is', 'other', true],
            'null condition uses existing fallback' => [null, 'is', null, true],
            'boolean condition uses existing fallback' => ['value', 'is', false, true],
            'numeric condition uses existing fallback' => ['value', 'is', 0, true],
        ];
    }

    #[DataProvider('operationCases')]
    public function testOperatorsAcceptTheirSupportedOperands(mixed $left, string $operator, mixed $right, bool $expected): void
    {
        $this->assertSame($expected, (new ConditionEvaluator())->evaluate($left, $operator, $right));
    }

    /** @return array<string, array{mixed}> */
    public static function invalidOperands(): array
    {
        return [
            'empty array' => [[]],
            'array' => [['key' => 'value']],
            'object' => [new stdClass()],
            'closure' => [static fn (): string => 'value'],
        ];
    }

    private function translator(): Translator
    {
        $translator = new Translator('es');
        $translator->addLoader('php', new PhpFileLoader());
        $translator->addResource(
            'php',
            dirname(__DIR__, 2) . '/resources/translations/errors+intl-icu.es.php',
            'es',
            'errors+intl-icu'
        );

        return $translator;
    }

    private function assertInvalidOperand(mixed $left, string $operator, mixed $right, string $type): void
    {
        try {
            (new ConditionEvaluator())->evaluate($left, $operator, $right);
            $this->fail('An unsupported operand must produce an evaluator exception.');
        } catch (EvaluatorException $exception) {
            $this->assertSame(
                'Evaluator error: The condition operand must be a bool, int, float, string, Stringable, or null; ' . $type . ' given.',
                $exception->getMessage()
            );
            $this->assertSame(
                'Error de evaluación: El operando de la condición debe ser bool, int, float, string, Stringable o null; se recibió ' . $type . '.',
                $exception->trans($this->translator())
            );
        }
    }

    #[DataProvider('invalidOperands')]
    public function testEachOperatorRejectsInvalidOperandsWhereConversionIsRequired(mixed $value): void
    {
        $type = get_debug_type($value);
        foreach (['=', '==', '!=', '<>', '>', '>=', '<', '<='] as $operator) {
            $this->assertInvalidOperand($value, $operator, 'value', $type);
            $this->assertInvalidOperand('value', $operator, $value, $type);
        }
        foreach (['contains', 'length', 'is'] as $operator) {
            $this->assertInvalidOperand('value', $operator, $value, $type);
        }
        if (!is_array($value)) {
            $this->assertInvalidOperand($value, 'contains', 'value', $type);
        }
    }

    #[DataProvider('invalidOperands')]
    public function testContainsValidatesEveryArrayElementEvenAfterAMatchingValue(mixed $value): void
    {
        $collection = ['matching' => 'value', 7 => $value];
        $this->assertInvalidOperand($collection, 'contains', 'value', get_debug_type($value));
        $this->assertSame(['matching' => 'value', 7 => $value], $collection);
    }

    public function testResourcesAreRejectedWithoutClosingThemAndRemainValidForNonConvertingOperations(): void
    {
        $resource = fopen('php://memory', 'r+');
        $this->assertIsResource($resource);
        try {
            $this->testEachOperatorRejectsInvalidOperandsWhereConversionIsRequired($resource);
            $this->testContainsValidatesEveryArrayElementEvenAfterAMatchingValue($resource);
            $evaluator = new ConditionEvaluator();
            $this->assertFalse($evaluator->evaluate($resource, 'length', '1'));
            $this->assertFalse($evaluator->evaluate($resource, 'is', 'null'));
            $this->assertSame(2, fwrite($resource, 'ok'));
        } finally {
            fclose($resource);
        }
        $this->assertInvalidOperand($resource, '=', 'value', 'resource (closed)');
    }

    public function testAnUnknownOperatorIsReportedBeforeCheckingOperandTypes(): void
    {
        try {
            (new ConditionEvaluator())->evaluate(new stdClass(), 'unknown', []);
            $this->fail('An unknown operator must fail.');
        } catch (EvaluatorException $exception) {
            $this->assertSame('Evaluator error: Operator not implemented: unknown', $exception->getMessage());
            $this->assertSame('Error de evaluación: Operador no implementado: unknown', $exception->trans($this->translator()));
        }
    }
}

final class ConditionString implements Stringable
{
    public function __construct(private readonly string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
