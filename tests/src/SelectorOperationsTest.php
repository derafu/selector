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
use Derafu\Selector\Evaluator\SelectorResolver;
use Derafu\Selector\Operations\Cleaner;
use Derafu\Selector\Operations\Reader;
use Derafu\Selector\Operations\Writer;
use Derafu\Selector\Parser\SelectorParser;
use Derafu\Selector\Parser\SelectorTokenizer;
use Derafu\Selector\Selector;
use Derafu\Selector\Types\ArraySelector;
use Derafu\Selector\Types\ConditionalSelector;
use Derafu\Selector\Types\LiteralSelector;
use Derafu\Selector\Types\SimpleSelector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversClass(Selector::class)]
#[CoversClass(Reader::class)]
#[CoversClass(Writer::class)]
#[CoversClass(Cleaner::class)]
#[CoversClass(SelectorParser::class)]
#[CoversClass(SelectorTokenizer::class)]
#[CoversClass(SelectorResolver::class)]
#[CoversClass(ConditionEvaluator::class)]
#[CoversClass(ArraySelector::class)]
#[CoversClass(SimpleSelector::class)]
#[CoversClass(ConditionalSelector::class)]
#[CoversClass(LiteralSelector::class)]
final class SelectorOperationsTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFacadeInitializesAndReusesItsComponents(): void
    {
        $parser = Selector::getParser();
        $this->assertSame($parser, Selector::getParser());

        $data = ['keep' => 'untouched'];
        Selector::set($data, 'user.name', 'Ada');
        Selector::set($data, 'user.role', 'admin');
        $this->assertSame('Ada', Selector::get($data, 'user.name'));
        $this->assertSame('admin', Selector::get($data, 'user.role'));
        Selector::clear($data, 'user.name');
        $this->assertSame(['keep' => 'untouched', 'user' => ['role' => 'admin']], $data);
        Selector::clear($data, 'user.role');
        $this->assertSame(['keep' => 'untouched'], $data);
        $this->assertSame($parser, Selector::getParser());
    }

    public function testHasDistinguishesNullAndMissingFromFalseAndEmptyValues(): void
    {
        $data = ['null' => null, 'false' => false, 'zero' => 0, 'empty' => '', 'array' => []];
        $this->assertFalse(Selector::has($data, 'missing'));
        $this->assertFalse(Selector::has($data, 'null'));
        foreach (['false', 'zero', 'empty', 'array'] as $key) {
            $this->assertTrue(Selector::has($data, $key));
        }
        $this->assertSame('fallback', Selector::get($data, 'null', 'fallback'));
        $this->assertFalse(Selector::get($data, 'false', 'fallback'));
    }

    public function testEmptySelectorDoesNotReadOrMutateData(): void
    {
        $data = ['keep' => ['value' => 1]];
        $this->assertNull(Selector::get($data, ''));
        $this->assertSame('fallback', Selector::get($data, '', 'fallback'));
        $this->assertFalse(Selector::has($data, ''));
        Selector::set($data, '', 'replacement');
        $this->assertSame(['keep' => ['value' => 1]], $data);
        Selector::clear($data, '');
        $this->assertSame(['keep' => ['value' => 1]], $data);
    }

    public function testParserHandlesEmptyInnerPathsAndParenthesizedLiterals(): void
    {
        $parser = new SelectorParser(new SelectorTokenizer(), new SelectorResolver());
        foreach (['', ' ', 'user.', '()', '( )', '(user.)'] as $expression) {
            $parsed = $parser->parse($expression);
            $this->assertInstanceOf(LiteralSelector::class, $parsed);
            $this->assertNull($parsed->read(['user' => 'must not be returned']));
        }
        $literal = $parser->parse('("literal text")');
        $this->assertInstanceOf(LiteralSelector::class, $literal);
        $this->assertSame('literal text', $literal->read(['literal text' => 'not the literal']));
        $this->assertSame(' ', $parser->parse('(" ")')->read([]));
    }

    public function testMissingArrayIndexReturnsTheDefaultWithoutChangingData(): void
    {
        $data = ['items' => [2 => 'existing']];
        $this->assertSame('fallback', Selector::get($data, 'items[0]', 'fallback'));
        $this->assertSame(['items' => [2 => 'existing']], $data);
    }

    public function testArrayWritesCreateNestedPathsAndPreserveOtherIndexes(): void
    {
        $data = ['keep' => true];
        Selector::set($data, 'items[2].name', 'Ada');
        $this->assertSame(['keep' => true, 'items' => [2 => ['name' => 'Ada']]], $data);
        Selector::set($data, 'items[5]', 'other');
        Selector::set($data, 'items[2].role', 'admin');
        $this->assertSame([
            'keep' => true,
            'items' => [2 => ['name' => 'Ada', 'role' => 'admin'], 5 => 'other'],
        ], $data);
    }

    public function testDirectArrayWritesMergeArraysAndReplaceScalars(): void
    {
        $data = ['items' => [0 => ['name' => 'old', 4 => 'first'], 3 => 'keep']];
        Selector::set($data, 'items[0]', ['name' => 'new', 9 => 'second']);
        $this->assertSame(['items' => [0 => ['name' => 'new', 0 => 'first', 1 => 'second'], 3 => 'keep']], $data);
        Selector::set($data, 'items[0]', false);
        $this->assertSame(['items' => [0 => false, 3 => 'keep']], $data);
        Selector::set($data, 'items[0]', ['restored']);
        $this->assertSame(['items' => [0 => ['restored'], 3 => 'keep']], $data);
    }

    public function testSimpleWritesMergeArraysWithoutLosingSiblingValues(): void
    {
        $data = ['settings' => ['name' => 'old', 5 => 'first'], 'keep' => true];
        Selector::set($data, 'settings', ['name' => 'new', 9 => 'second']);
        $this->assertSame(['settings' => ['name' => 'new', 0 => 'first', 1 => 'second'], 'keep' => true], $data);
    }

    /** @return array<string, array{array<array-key, mixed>, string, array<array-key, mixed>}> */
    public static function clearCases(): array
    {
        return [
            'missing simple key' => [['keep' => 1], 'missing', ['keep' => 1]],
            'direct simple key' => [['remove' => null, 'keep' => 1], 'remove', ['keep' => 1]],
            'nested simple key with sibling' => [['user' => ['name' => 'Ada', 'role' => 'admin']], 'user.name', ['user' => ['role' => 'admin']]],
            'empty simple parents' => [['user' => ['profile' => ['name' => 'Ada']], 'keep' => 1], 'user.profile.name', ['keep' => 1]],
            'missing nested key' => [['user' => ['role' => 'admin']], 'user.name', ['user' => ['role' => 'admin']]],
            'missing array key' => [['keep' => 1], 'items[0]', ['keep' => 1]],
            'non-array key' => [['items' => 'keep'], 'items[0]', ['items' => 'keep']],
            'null array key' => [['items' => null], 'items[0]', ['items' => null]],
            'missing array index' => [['items' => [2 => 'keep']], 'items[0]', ['items' => [2 => 'keep']]],
            'direct array index preserves keys' => [['items' => [0 => 'remove', 2 => 'keep']], 'items[0]', ['items' => [2 => 'keep']]],
            'last array index removes parent' => [['items' => ['remove'], 'keep' => 1], 'items[0]', ['keep' => 1]],
            'nested array value preserves siblings' => [['items' => [['name' => 'Ada', 'role' => 'admin']]], 'items[0].name', ['items' => [['role' => 'admin']]]],
            'empty array element removed without reindexing' => [['items' => [0 => ['name' => 'Ada'], 2 => ['name' => 'keep']]], 'items[0].name', ['items' => [2 => ['name' => 'keep']]]],
            'empty array parents removed' => [['items' => [['name' => 'Ada']], 'keep' => 1], 'items[0].name', ['keep' => 1]],
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('clearCases')]
    public function testClearRemovesOnlyTheSelectedValueAndEmptyParents(array $data, string $path, array $expected): void
    {
        Selector::clear($data, $path);
        $this->assertSame($expected, $data);
        Selector::clear($data, $path);
        $this->assertSame($expected, $data);
    }

    /** @return array<string, array{string, string, string}> */
    public static function conditionalCases(): array
    {
        return [
            'true branch' => ['admin', 'admin_role', 'basic_role'],
            'false branch' => ['member', 'basic_role', 'admin_role'],
        ];
    }

    #[DataProvider('conditionalCases')]
    public function testConditionalWritesAndClearsAffectOnlyTheChosenBranch(string $type, string $selected, string $other): void
    {
        $path = '((type) = "admin" ? (admin_role) : (basic_role))';
        $data = ['type' => $type, 'admin_role' => 'original admin', 'basic_role' => 'original basic'];
        $original = $data;
        Selector::set($data, $path, 'updated');
        $this->assertSame('updated', $data[$selected]);
        $this->assertSame($original[$other], $data[$other]);
        $this->assertSame($type, $data['type']);
        Selector::clear($data, $path);
        unset($original[$selected]);
        $this->assertSame($original, $data);
    }
}
