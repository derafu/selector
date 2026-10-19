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

use Derafu\Selector\Evaluator\SelectorResolver;
use Derafu\Selector\Exception\SelectorException;
use Derafu\Selector\Parser\SelectorParser;
use Derafu\Selector\Parser\SelectorTokenizer;
use Derafu\Selector\Selector;
use Derafu\Selector\Types\CompositeSelector;
use Derafu\Selector\Types\SimpleSelector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(CompositeSelector::class)]
#[UsesClass(SelectorException::class)]
#[UsesClass(Selector::class)]
#[UsesClass(SelectorParser::class)]
#[UsesClass(SelectorTokenizer::class)]
#[UsesClass(SelectorResolver::class)]
#[UsesClass(SimpleSelector::class)]
final class CompositeSelectorTest extends TestCase
{
    /** @return array<string, array{array<array-key, mixed>, string}> */
    public static function arrayValues(): array
    {
        return [
            'booleans and null' => [[true, false, null], '[true, false, null]'],
            'mixed values' => [['text', 123, true, false, null, 1.5], '[text, 123, true, false, null, 1.5]'],
            'empty array' => [[], '[]'],
            'keys do not change the representation' => [['first' => true, 7 => false, 'last' => null], '[true, false, null]'],
            'stringable keeps quotes and spaces' => [[new CompositeString(' "value" ')], '[ "value" ]'],
        ];
    }

    /** @param array<array-key, mixed> $values */
    #[DataProvider('arrayValues')]
    public function testArrayElementsHaveAnExplicitTextRepresentation(array $values, string $expected): void
    {
        $selector = new CompositeSelector([
            ['type' => 'string', 'value' => 'values: '],
            ['type' => 'selector', 'id' => 'items'],
        ]);
        $data = ['items' => $values];

        $this->assertSame('values: ' . $expected, $selector->read($data));
        $this->assertSame(['items' => $values], $data);

        $selector = new CompositeSelector([
            ['type' => 'selector', 'id' => 'items'],
            ['type' => 'string', 'value' => ' suffix'],
        ]);
        $this->assertSame($expected . ' suffix', $selector->read($data));
    }

    /** @return array<string, array{mixed, mixed, string}> */
    public static function concatenationCases(): array
    {
        return [
            'string and true' => ['value:', true, 'value:true'],
            'string and false' => ['value:', false, 'value:false'],
            'string and null' => ['value:', null, 'value:null'],
            'number and null keeps empty conversion' => [7, null, '7'],
            'number and true' => [7, true, '7true'],
            'true and false' => [true, false, 'truefalse'],
            'false and number' => [false, 7, 'false7'],
            'two numbers' => [1, 2.5, '12.5'],
            'string and stringable' => ['prefix:', new CompositeString(' "value" '), 'prefix: "value" '],
            'stringable and string' => [new CompositeString(' "value" '), ':suffix', ' "value" :suffix'],
        ];
    }

    #[DataProvider('concatenationCases')]
    public function testScalarAndStringableConcatenationKeepsItsExistingRules(mixed $left, mixed $right, string $expected): void
    {
        $selector = new CompositeSelector([
            ['type' => 'selector', 'id' => 'left'],
            ['type' => 'selector', 'id' => 'right'],
        ]);

        $this->assertSame($expected, $selector->read(['left' => $left, 'right' => $right]));
    }

    public function testOrReturnsAnObjectWithoutTryingToConvertIt(): void
    {
        $selector = new CompositeSelector([
            ['type' => 'selector', 'id' => 'left'],
            ['type' => 'operator', 'value' => 'or'],
            ['type' => 'selector', 'id' => 'right'],
        ]);
        $object = new stdClass();

        $this->assertSame($object, $selector->read(['left' => $object, 'right' => 'fallback']));
        $this->assertFalse($selector->read(['left' => false, 'right' => 'fallback']));
        $this->assertSame('fallback', $selector->read(['left' => null, 'right' => 'fallback']));
    }

    /** @return array<string, array{mixed}> */
    public static function invalidValues(): array
    {
        return [
            'nested array' => [['nested' => 'value']],
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

    /** @param array<string, mixed> $data */
    private function assertInvalidConversion(array $data, string $type): void
    {
        $selector = new CompositeSelector([
            ['type' => 'selector', 'id' => 'left'],
            ['type' => 'selector', 'id' => 'right'],
        ]);
        try {
            $selector->read($data);
            $this->fail('A value with an unsupported type must not be converted to text.');
        } catch (SelectorException $exception) {
            $this->assertSame(
                'The value to concatenate must be a bool, int, float, string, Stringable, or null; ' . $type . ' given.',
                $exception->getMessage()
            );
            $this->assertSame(
                'El valor a concatenar debe ser bool, int, float, string, Stringable o null; se recibió ' . $type . '.',
                $exception->trans($this->translator())
            );
        }
    }

    #[DataProvider('invalidValues')]
    public function testArrayElementsWithUnsupportedTypesProduceATranslatableError(mixed $value): void
    {
        $this->assertInvalidConversion(['left' => 'prefix:', 'right' => [true, $value]], get_debug_type($value));
    }

    public function testAnObjectIsRejectedOnlyWhenConcatenationRequiresItsText(): void
    {
        $object = new stdClass();
        $this->assertInvalidConversion(['left' => 'prefix:', 'right' => $object], 'stdClass');
        $this->assertInvalidConversion(['left' => 7, 'right' => $object], 'stdClass');
        $this->assertInvalidConversion(['left' => $object, 'right' => 'suffix'], 'stdClass');
    }

    public function testResourcesAreRejectedOnlyWhenConvertedAndAreNotClosed(): void
    {
        $resource = fopen('php://memory', 'r+');
        $this->assertIsResource($resource);
        try {
            $selector = new CompositeSelector([
                ['type' => 'selector', 'id' => 'left'],
                ['type' => 'operator', 'value' => 'or'],
                ['type' => 'selector', 'id' => 'right'],
            ]);
            $this->assertSame($resource, $selector->read(['left' => $resource, 'right' => 'fallback']));
            $this->assertInvalidConversion(['left' => 'prefix:', 'right' => [$resource]], 'resource (stream)');
            $this->assertInvalidConversion(['left' => 'prefix:', 'right' => $resource], 'resource (stream)');
            $this->assertInvalidConversion(['left' => 7, 'right' => $resource], 'resource (stream)');
            $this->assertInvalidConversion(['left' => $resource, 'right' => 'suffix'], 'resource (stream)');
            $this->assertSame(2, fwrite($resource, 'ok'));
        } finally {
            fclose($resource);
        }
        $this->assertInvalidConversion(['left' => 'prefix:', 'right' => $resource], 'resource (closed)');
    }
}

final class CompositeString implements Stringable
{
    public function __construct(private readonly string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
