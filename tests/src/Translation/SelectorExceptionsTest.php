<?php

declare(strict_types=1);

/**
 * Derafu: Selector - Elegant Data Structure Navigation for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSelector\Translation;

use Derafu\Selector\Contract\SelectorTypeInterface;
use Derafu\Selector\Evaluator\ConditionEvaluator;
use Derafu\Selector\Evaluator\SelectorResolver;
use Derafu\Selector\Exception\EvaluatorException;
use Derafu\Selector\Exception\ParserException;
use Derafu\Selector\Exception\SelectorException;
use Derafu\Selector\Exception\TypeException;
use Derafu\Selector\Exception\UnsupportedOperationException;
use Derafu\Selector\Operations\Cleaner;
use Derafu\Selector\Operations\Reader;
use Derafu\Selector\Operations\Writer;
use Derafu\Selector\Parser\SelectorParser;
use Derafu\Selector\Parser\SelectorTokenizer;
use Derafu\Selector\Selector;
use Derafu\Selector\Types\ArraySelector;
use Derafu\Selector\Types\CompositeSelector;
use Derafu\Selector\Types\DependentSelector;
use Derafu\Selector\Types\JmesPathSelector;
use Derafu\Selector\Types\JsonPathSelector;
use Derafu\Selector\Types\LiteralSelector;
use Derafu\Selector\Types\SimpleSelector;
use Derafu\Translation\TranslatableMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(SelectorException::class)]
#[CoversClass(EvaluatorException::class)]
#[CoversClass(ParserException::class)]
#[CoversClass(TypeException::class)]
#[CoversClass(UnsupportedOperationException::class)]
#[CoversClass(ConditionEvaluator::class)]
#[CoversClass(Reader::class)]
#[CoversClass(Writer::class)]
#[CoversClass(Cleaner::class)]
#[CoversClass(SimpleSelector::class)]
#[CoversClass(ArraySelector::class)]
#[CoversClass(DependentSelector::class)]
#[CoversClass(JsonPathSelector::class)]
#[CoversClass(JmesPathSelector::class)]
#[CoversClass(LiteralSelector::class)]
#[CoversClass(CompositeSelector::class)]
#[UsesClass(Selector::class)]
#[UsesClass(SelectorParser::class)]
#[UsesClass(SelectorTokenizer::class)]
#[UsesClass(SelectorResolver::class)]
final class SelectorExceptionsTest extends TestCase
{
    private function translator(): Translator
    {
        $translator = new Translator('es');
        $translator->addLoader('php', new PhpFileLoader());
        $translator->addResource(
            'php',
            dirname(__DIR__, 3) . '/resources/translations/errors+intl-icu.es.php',
            'es',
            'errors+intl-icu'
        );

        return $translator;
    }

    /** @return array<string, array{class-string<SelectorException>, string, string}> */
    public static function exceptionClasses(): array
    {
        return [
            'base' => [SelectorException::class, '', ''],
            'evaluator' => [EvaluatorException::class, 'Evaluator error: ', 'Error de evaluación: '],
            'parser' => [ParserException::class, 'Parser error: ', 'Error de análisis: '],
            'type' => [TypeException::class, 'Type error: ', 'Error de tipo: '],
            'unsupported' => [UnsupportedOperationException::class, 'Unsupported operation: ', 'Operación no admitida: '],
        ];
    }

    /** @param class-string<SelectorException> $class */
    #[DataProvider('exceptionClasses')]
    public function testStringMessagesKeepTheirPrefixCodeAndPreviousException(
        string $class,
        string $prefix,
        string $translatedPrefix
    ): void {
        $previous = new RuntimeException('original');
        $exception = new $class('detail', 17, $previous);

        $this->assertSame($prefix . 'detail', $exception->getMessage());
        $this->assertSame($translatedPrefix . 'detail', $exception->trans($this->translator()));
        $this->assertSame(17, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    /** @param class-string<SelectorException> $class */
    #[DataProvider('exceptionClasses')]
    public function testDefaultEmptyMessagesRemainCompatible(
        string $class,
        string $prefix,
        string $translatedPrefix
    ): void {
        $exception = new $class();
        $this->assertSame($prefix, $exception->getMessage());
        $this->assertSame($translatedPrefix, $exception->trans($this->translator()));
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    /** @param class-string<SelectorException> $class */
    #[DataProvider('exceptionClasses')]
    public function testArrayAndTranslatableMessagesTranslateWithoutLosingParameterCharacters(
        string $class,
        string $prefix,
        string $translatedPrefix
    ): void {
        $operator = "invalid'{value}%";
        foreach ([
            ['Operator not implemented: {operator}', 'operator' => $operator],
            new TranslatableMessage('Operator not implemented: {operator}', ['operator' => $operator], 'errors'),
        ] as $message) {
            $exception = new $class($message);
            $this->assertSame($prefix . 'Operator not implemented: ' . $operator, $exception->getMessage());
            $this->assertSame($translatedPrefix . 'Operador no implementado: ' . $operator, $exception->trans($this->translator()));
        }
    }

    public function testAnUnsupportedOperatorTranslatesBothItsPrefixAndDetail(): void
    {
        try {
            (new ConditionEvaluator())->evaluate(1, 'invalid', 2);
            $this->fail('An unsupported operator must fail.');
        } catch (EvaluatorException $exception) {
            $this->assertSame('Evaluator error: Operator not implemented: invalid', $exception->getMessage());
            $this->assertSame('Error de evaluación: Operador no implementado: invalid', $exception->trans($this->translator()));
        }
    }

    /** @return array<string, array{SelectorTypeInterface, array<array-key, mixed>, string, string}> */
    public static function invalidTraversal(): array
    {
        return [
            'simple key' => [new SimpleSelector("a'{x}%", 'child'), ["a'{x}%" => 7],
                "Cannot traverse through non-array value at key 'a'{x}%'",
                "No se puede recorrer un valor que no es un arreglo en la clave 'a'{x}%'"],
            'array key' => [new ArraySelector('items', 0), ['items' => 7],
                "Cannot access index on non-array at key 'items'",
                "No se puede acceder a un índice en un valor que no es un arreglo en la clave 'items'"],
            'array index' => [new ArraySelector('items', 12345, 'child'), ['items' => [12345 => 7]],
                'Cannot traverse through non-array value at index 12345',
                'No se puede recorrer un valor que no es un arreglo en el índice 12345'],
            'dictionary' => [new DependentSelector('users', 'id', '1', 'name'), ['users' => 7],
                "Cannot access array elements on non-array at key 'users'",
                "No se puede acceder a elementos de un arreglo en un valor que no es un arreglo en la clave 'users'"],
            'dependent key' => [new DependentSelector('users', 'id', '1', 'name', 'child'), ['users' => [['id' => '1', 'name' => 7]]],
                "Cannot traverse through non-array value at dependent key 'name'",
                "No se puede recorrer un valor que no es un arreglo en la clave dependiente 'name'"],
        ];
    }

    /** @param array<array-key, mixed> $data */
    #[DataProvider('invalidTraversal')]
    public function testInvalidTraversalPreservesTheEnglishMessageAndTranslatesItsParameters(
        SelectorTypeInterface $selector,
        array $data,
        string $message,
        string $translation
    ): void {
        $original = $data;
        try {
            $selector->write($data, 'new');
            $this->fail('A scalar cannot be traversed.');
        } catch (SelectorException $exception) {
            $this->assertSame($message, $exception->getMessage());
            $this->assertSame($translation, $exception->trans($this->translator()));
            $this->assertSame($original, $data);
        }
    }

    /** @return iterable<string, array{SelectorTypeInterface, string, string, string}> */
    public static function unsupportedOperations(): iterable
    {
        foreach (['write' => 'Write', 'clear' => 'Clear'] as $method => $operation) {
            $translatedOperation = $method === 'write' ? 'escritura' : 'limpieza';
            foreach (['JSONPath' => new JsonPathSelector('$'), 'JMESPath' => new JmesPathSelector('@')] as $type => $selector) {
                yield $type . ':' . $method => [$selector, $method,
                    'Unsupported operation: ' . $operation . ' operations are not supported with ' . $type . ' selectors.',
                    'Operación no admitida: Las operaciones de ' . $translatedOperation . ' no están admitidas con selectores ' . $type . '.'];
            }
            yield 'literal:' . $method => [new LiteralSelector('literal'), $method,
                $operation . ' operations are not supported with literal selectors',
                'Las operaciones de ' . $translatedOperation . ' no están admitidas con selectores literales'];
            yield 'composite:' . $method => [new CompositeSelector([]), $method,
                'Unsupported operation: ' . ($method === 'write' ? 'Write' : 'Clear') . ' not supported for composite selectors.',
                'Operación no admitida: La ' . $translatedOperation . ' no está admitida para selectores compuestos.'];
        }
    }

    #[DataProvider('unsupportedOperations')]
    public function testUnsupportedOperationsTranslateAndLeaveDataUntouched(
        SelectorTypeInterface $selector,
        string $method,
        string $message,
        string $translation
    ): void {
        $data = ['keep' => 'value'];
        try {
            if ($method === 'write') {
                $selector->write($data, 'new');
            } else {
                $selector->clear($data);
            }
            $this->fail('This operation is unsupported.');
        } catch (SelectorException $exception) {
            $this->assertSame($message, $exception->getMessage());
            $this->assertSame($translation, $exception->trans($this->translator()));
            $this->assertSame(['keep' => 'value'], $data);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function invalidExpressions(): array
    {
        return ['JSONPath' => ['$.[', 'JSONPath'], 'JMESPath' => ['jmespath:[', 'JMESPath']];
    }

    #[DataProvider('invalidExpressions')]
    public function testReadErrorsTranslateTheNestedExceptionAndPreserveTheExternalCause(string $selector, string $type): void
    {
        $expression = $type === 'JSONPath' ? '$.[' : '[';
        try {
            Selector::get([], $selector);
            $this->fail('An invalid expression must fail.');
        } catch (SelectorException $exception) {
            $this->assertSame("Failed to read using selector '{$selector}': Invalid {$type} expression: {$expression}", $exception->getMessage());
            $this->assertSame("No se pudo leer usando el selector '{$selector}': Expresión {$type} inválida: {$expression}", $exception->trans($this->translator()));
            $previous = $exception->getPrevious();
            $this->assertInstanceOf(SelectorException::class, $previous);
            $this->assertNotNull($previous->getPrevious());
            $this->assertSame($previous, $exception->getTranslatableMessage()->getParameters()['error']);
        }
    }

    /** @return array<string, array{string, string, string}> */
    public static function wrappedOperations(): array
    {
        return ['write' => ['write', 'write', 'escribir'], 'clear' => ['clear', 'clear', 'limpiar']];
    }

    #[DataProvider('wrappedOperations')]
    public function testWriteAndClearErrorsTranslateTheEntireChain(string $method, string $operation, string $translatedOperation): void
    {
        $data = ['keep' => 'value'];
        try {
            if ($method === 'write') {
                Selector::set($data, '$.item', 'new');
            } else {
                Selector::clear($data, '$.item');
            }
            $this->fail('The operation must fail.');
        } catch (SelectorException $exception) {
            $previous = $exception->getPrevious();
            $this->assertInstanceOf(UnsupportedOperationException::class, $previous);
            $this->assertSame("Failed to {$operation} using selector '$.item': " . $previous->getMessage(), $exception->getMessage());
            $this->assertSame("No se pudo {$translatedOperation} usando el selector '$.item': " . $previous->trans($this->translator()), $exception->trans($this->translator()));
            $this->assertSame($previous, $exception->getTranslatableMessage()->getParameters()['error']);
            $this->assertSame(['keep' => 'value'], $data);
        }
    }
}
