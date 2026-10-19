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
use Derafu\Selector\Types\DependentSelector;
use Derafu\Selector\Types\SimpleSelector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(DependentSelector::class)]
#[UsesClass(Selector::class)]
#[UsesClass(SelectorParser::class)]
#[UsesClass(SelectorTokenizer::class)]
#[UsesClass(SelectorResolver::class)]
#[UsesClass(SimpleSelector::class)]
#[UsesClass(SelectorException::class)]
final class DependentSelectorTest extends TestCase
{
    public function testNonArrayEntriesAreSkippedWhenReadingWritingAndClearingAMatch(): void
    {
        $selector = new DependentSelector('items', 'id', 'target', 'value');
        $data = ['items' => [null, false, 7, 'text', ['id' => 'other'], ['id' => 'target', 'value' => 'old']]];

        $this->assertSame('old', $selector->read($data));
        $selector->write($data, 'new');
        $this->assertSame(['items' => [null, false, 7, 'text', ['id' => 'other'], ['id' => 'target', 'value' => 'new']]], $data);
        $this->assertSame('new', $selector->read($data));

        $selector->clear($data);
        $this->assertSame(['items' => [null, false, 7, 'text', ['id' => 'other'], ['id' => 'target']]], $data);
        $this->assertNull($selector->read($data));
    }

    public function testANewMatchInitializesItsNestedPathAndKeepsOtherEntries(): void
    {
        $selector = new DependentSelector('items', 'id', 'target', 'profile', 'name');
        $data = ['items' => [null, ['id' => 'other', 'profile' => ['name' => 'original']]]];

        $selector->write($data, 'new');

        $this->assertSame(['items' => [
            null,
            ['id' => 'other', 'profile' => ['name' => 'original']],
            ['id' => 'target', 'profile' => ['name' => 'new']],
        ]], $data);
        $this->assertSame('new', $selector->read($data));
    }

    /** @return array<string, array{mixed}> */
    public static function directValues(): array
    {
        return [
            'null' => [null],
            'false' => [false],
            'zero' => [0],
            'string' => ['text'],
            'array' => [['nested' => 'value']],
        ];
    }

    #[DataProvider('directValues')]
    public function testANewMatchWithoutANestedPathPreservesTheValue(mixed $value): void
    {
        $selector = new DependentSelector('items', 'id', 'target', 'value');
        $data = [];

        $selector->write($data, $value);

        $this->assertSame(['items' => [['id' => 'target', 'value' => $value]]], $data);
        $this->assertSame($value, $selector->read($data));
    }

    public function testMissingNonArrayAndUnmatchedDictionariesAreNotChangedByClear(): void
    {
        $selector = new DependentSelector('items', 'id', 'target', 'value');
        foreach ([[], ['items' => 7], ['items' => [['id' => 'other']]]] as $original) {
            $data = $original;
            $this->assertNull($selector->read($data));
            $selector->clear($data);
            $this->assertSame($original, $data);
        }
    }

    public function testAnExistingMatchCanInitializeAndUpdateItsNestedPath(): void
    {
        $selector = new DependentSelector('items', 'id', 'target', 'profile', 'name');
        $data = ['items' => [['id' => 'target']]];

        $selector->write($data, 'first');
        $this->assertSame(['items' => [['id' => 'target', 'profile' => ['name' => 'first']]]], $data);
        $selector->write($data, 'second');
        $this->assertSame(['items' => [['id' => 'target', 'profile' => ['name' => 'second']]]], $data);

        $selector->clear($data);
        $this->assertSame(['items' => [['id' => 'target']]], $data);
    }

    public function testWritingAnArrayMergesTheExistingDependentArray(): void
    {
        $selector = new DependentSelector('items', 'id', 'target', 'profile');
        $data = ['items' => [['id' => 'target', 'profile' => ['name' => 'old', 'keep' => true]]]];

        $selector->write($data, ['name' => 'new', 'extra' => 7]);

        $this->assertSame(['items' => [['id' => 'target', 'profile' => ['name' => 'new', 'keep' => true, 'extra' => 7]]]], $data);
    }

    public function testClearingANestedPathKeepsOtherFieldsAndIgnoresNonArrayParents(): void
    {
        $selector = new DependentSelector('items', 'id', 'target', 'profile', 'name');
        $data = ['items' => [['id' => 'target', 'profile' => ['name' => 'old', 'keep' => true]]]];

        $selector->clear($data);
        $this->assertSame(['items' => [['id' => 'target', 'profile' => ['keep' => true]]]], $data);

        $data = ['items' => [['id' => 'target', 'profile' => 7]]];
        $selector->clear($data);
        $this->assertSame(['items' => [['id' => 'target', 'profile' => 7]]], $data);
    }

    public function testClearingTheOnlyFieldRemovesTheEmptyElementAndDictionary(): void
    {
        $selector = new DependentSelector('items', 'id', 'target', 'id');
        $data = ['items' => [['id' => 'target']]];

        $selector->clear($data);

        $this->assertSame([], $data);
    }

    /** @return array<string, array{mixed, string}> */
    public static function comparisonValues(): array
    {
        return [
            'true keeps numeric representation' => [true, '1'],
            'false keeps empty representation' => [false, ''],
            'zero' => [0, '0'],
            'integer' => [12345, '12345'],
            'float' => [1.5, '1.5'],
            'leading zeros' => ['001', '001'],
            'stringable' => [new DependentComparisonString('001'), '001'],
        ];
    }

    #[DataProvider('comparisonValues')]
    public function testSupportedComparisonKeysWorkForReadWriteAndClear(mixed $key, string $required): void
    {
        $selector = new DependentSelector('items', 'id', $required, 'value');
        $data = ['items' => [['id' => $key, 'value' => 'old']]];

        $this->assertSame('old', $selector->read($data));
        $selector->write($data, 'new');
        $this->assertSame(['items' => [['id' => $key, 'value' => 'new']]], $data);
        $selector->clear($data);
        $this->assertSame(['items' => [['id' => $key]]], $data);
    }

    public function testNullAndMissingComparisonKeysDoNotMatchAnEmptyString(): void
    {
        $selector = new DependentSelector('items', 'id', '', 'value');
        $data = ['items' => [['id' => null, 'value' => 'null'], ['value' => 'missing']]];
        $original = $data;

        $this->assertNull($selector->read($data));
        $selector->clear($data);
        $this->assertSame($original, $data);
        $selector->write($data, 'new');
        $this->assertSame(['items' => [
            ['id' => null, 'value' => 'null'],
            ['value' => 'missing'],
            ['id' => '', 'value' => 'new'],
        ]], $data);
    }

    public function testLeadingZerosAreNotDiscardedDuringComparison(): void
    {
        $selector = new DependentSelector('items', 'id', '1', 'value');
        $data = ['items' => [['id' => '001', 'value' => 'keep'], ['id' => 1, 'value' => 'match']]];

        $this->assertSame('match', $selector->read($data));
        $selector->write($data, 'new');
        $this->assertSame(['items' => [['id' => '001', 'value' => 'keep'], ['id' => 1, 'value' => 'new']]], $data);
        $selector->clear($data);
        $this->assertSame(['items' => [['id' => '001', 'value' => 'keep'], ['id' => 1]]], $data);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidComparisonValues(): array
    {
        return [
            'empty array' => [[]],
            'array' => [['nested' => 'value']],
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

    private function assertInvalidComparisonForAllOperations(mixed $key): void
    {
        $selector = new DependentSelector('items', 'id', 'target', 'value');
        foreach (['read', 'write', 'clear'] as $operation) {
            $data = ['items' => [
                ['id' => $key, 'value' => 'invalid'],
                ['id' => 'target', 'value' => 'valid'],
            ]];
            $original = $data;
            try {
                if ($operation === 'read') {
                    $selector->read($data);
                } elseif ($operation === 'write') {
                    $selector->write($data, 'new');
                } else {
                    $selector->clear($data);
                }
                $this->fail('An invalid comparison key must fail instead of being skipped.');
            } catch (SelectorException $exception) {
                $type = get_debug_type($key);
                $this->assertSame(
                    'The comparison key "id" must contain a bool, int, float, string, or Stringable; ' . $type . ' given.',
                    $exception->getMessage()
                );
                $this->assertSame(
                    'La clave de comparación "id" debe contener bool, int, float, string o Stringable; se recibió ' . $type . '.',
                    $exception->trans($this->translator())
                );
                $this->assertSame($original, $data);
            }
        }
    }

    #[DataProvider('invalidComparisonValues')]
    public function testInvalidComparisonKeysProduceATranslatableError(mixed $key): void
    {
        $this->assertInvalidComparisonForAllOperations($key);
    }

    public function testOpenAndClosedResourcesAreRejectedWithoutClosingThem(): void
    {
        $resource = fopen('php://memory', 'r+');
        $this->assertIsResource($resource);
        try {
            $this->assertInvalidComparisonForAllOperations($resource);
            $this->assertSame(2, fwrite($resource, 'ok'));
        } finally {
            fclose($resource);
        }
        $this->assertInvalidComparisonForAllOperations($resource);
    }
}

final class DependentComparisonString implements Stringable
{
    public function __construct(private readonly string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
