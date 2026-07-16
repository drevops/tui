<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Model;

use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\OptionKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the option model, option kinds and the field option helpers.
 */
#[CoversClass(Option::class)]
#[CoversClass(OptionKind::class)]
#[CoversClass(FieldType::class)]
#[CoversClass(Field::class)]
#[Group('model')]
final class OptionTest extends TestCase {

  public function testListFromMap(): void {
    $options = Option::list(['a' => 'Apple', 'b' => 'Banana']);

    $this->assertCount(2, $options);
    $this->assertSame('a', $options[0]->value);
    $this->assertSame('Apple', $options[0]->label);
    $this->assertSame(OptionKind::Option, $options[0]->kind);
    $this->assertTrue($options[0]->selectable());
  }

  public function testListLabelDefaultsToValue(): void {
    $options = Option::list(['a' => '']);

    $this->assertSame('a', $options[0]->label);
  }

  public function testListFromOptionsPassesThrough(): void {
    $sep = new Option('', '', '', OptionKind::Separator);
    $options = Option::list([new Option('a', 'Apple'), $sep]);

    $this->assertSame('Apple', $options[0]->label);
    $this->assertSame($sep, $options[1]);
  }

  public function testListMixed(): void {
    $options = Option::list(['a' => 'Apple', new Option('b', 'Banana', '', OptionKind::Option, TRUE, 'nope')]);

    $this->assertSame('a', $options[0]->value);
    $this->assertTrue($options[1]->disabled);
    $this->assertSame('nope', $options[1]->disabledReason);
  }

  #[DataProvider('dataProviderSelectable')]
  public function testSelectable(Option $option, bool $expected): void {
    $this->assertSame($expected, $option->selectable());
  }

  public static function dataProviderSelectable(): \Iterator {
    yield 'plain option' => [new Option('a', 'A'), TRUE];
    yield 'disabled option' => [new Option('a', 'A', '', OptionKind::Option, TRUE), FALSE];
    yield 'separator' => [new Option('', '', '', OptionKind::Separator), FALSE];
    yield 'heading' => [new Option('', 'Group', '', OptionKind::Heading), FALSE];
  }

  #[DataProvider('dataProviderConstrainsToOptions')]
  public function testConstrainsToOptions(FieldType $type, bool $expected): void {
    $this->assertSame($expected, $type->constrainsToOptions());
  }

  public static function dataProviderConstrainsToOptions(): \Iterator {
    yield [FieldType::Select, TRUE];
    yield [FieldType::Search, TRUE];
    yield [FieldType::MultiSelect, TRUE];
    yield [FieldType::MultiSearch, TRUE];
    yield [FieldType::Reorder, TRUE];
    yield [FieldType::Suggest, FALSE];
    yield [FieldType::Text, FALSE];
    yield [FieldType::Confirm, FALSE];
  }

  #[DataProvider('dataProviderIsMultiChoice')]
  public function testIsMultiChoice(FieldType $type, bool $expected): void {
    $this->assertSame($expected, $type->isMultiChoice());
  }

  public static function dataProviderIsMultiChoice(): \Iterator {
    yield [FieldType::MultiSelect, TRUE];
    yield [FieldType::MultiSearch, TRUE];
    yield [FieldType::Reorder, TRUE];
    yield [FieldType::MultiFilePicker, FALSE];
    yield [FieldType::Select, FALSE];
    yield [FieldType::Search, FALSE];
    yield [FieldType::Text, FALSE];
  }

  #[DataProvider('dataProviderCollectsList')]
  public function testCollectsList(FieldType $type, bool $expected): void {
    $this->assertSame($expected, $type->collectsList());
  }

  public static function dataProviderCollectsList(): \Iterator {
    yield [FieldType::MultiSelect, TRUE];
    yield [FieldType::MultiSearch, TRUE];
    yield [FieldType::MultiFilePicker, TRUE];
    yield [FieldType::Reorder, TRUE];
    yield [FieldType::Select, FALSE];
    yield [FieldType::FilePicker, FALSE];
    yield [FieldType::Text, FALSE];
  }

  public function testFieldOptionScan(): void {
    $field = $this->selectField();

    $this->assertSame('Standard', $field->option('standard')?->label);
    // A disabled option is still found by value.
    $this->assertTrue($field->option('demo')?->disabled);
    // Missing values and structural rows are not returned.
    $this->assertNotInstanceOf(Option::class, $field->option('missing'));
    $this->assertNotInstanceOf(Option::class, $field->option(''));
  }

  public function testSelectableValues(): void {
    $this->assertSame(['standard', 'minimal'], $this->selectField()->selectableValues());
  }

  #[DataProvider('dataProviderOptionError')]
  public function testOptionError(FieldType $type, array $options, mixed $value, ?string $expected): void {
    $field = new Field('f', 'F', '', $type, $type->isMultiChoice() ? [] : '', $options);

    $this->assertSame($expected, $field->optionError($value));
  }

  public static function dataProviderOptionError(): \Iterator {
    $options = [
      new Option('standard', 'Standard'),
      new Option('minimal', 'Minimal'),
      new Option('demo', 'Demo', '', OptionKind::Option, TRUE, 'unavailable'),
      new Option('legacy', 'Legacy', '', OptionKind::Option, TRUE),
      new Option('', '', '', OptionKind::Separator),
    ];
    yield 'selectable value' => [FieldType::Select, $options, 'standard', NULL];
    yield 'disabled with reason' => [FieldType::Select, $options, 'demo', 'option "demo" is disabled: unavailable'];
    yield 'disabled without reason' => [FieldType::Select, $options, 'legacy', 'option "legacy" is disabled'];
    yield 'unknown value' => [FieldType::Select, $options, 'bogus', 'value "bogus" is not one of: standard, minimal'];
    yield 'unconstrained type' => [FieldType::Suggest, $options, 'bogus', NULL];
    yield 'no options' => [FieldType::Select, [], 'bogus', NULL];
    yield 'multi valid' => [FieldType::MultiSelect, $options, ['standard', 'minimal'], NULL];
    yield 'multi disabled item' => [FieldType::MultiSelect, $options, ['standard', 'demo'], 'option "demo" is disabled: unavailable'];
    yield 'multi non-array' => [FieldType::MultiSelect, $options, 'standard', 'value must be a list'];
    yield 'reorder full permutation' => [FieldType::Reorder, $options, ['minimal', 'standard'], NULL];
    yield 'reorder partial' => [FieldType::Reorder, $options, ['standard'], 'must rank every option exactly once (standard, minimal)'];
    yield 'reorder duplicate' => [FieldType::Reorder, $options, ['standard', 'standard'], 'must rank every option exactly once (standard, minimal)'];
    yield 'reorder unknown item' => [FieldType::Reorder, $options, ['standard', 'bogus'], 'value "bogus" is not one of: standard, minimal'];
    yield 'reorder non-array' => [FieldType::Reorder, $options, 'standard', 'value must be a list'];
  }

  /**
   * Tests completing and de-duplicating a desired ordering.
   *
   * @param list<string> $allowed
   *   The full set of values, in declared order.
   * @param list<string> $desired
   *   The requested ordering.
   * @param list<string> $expected
   *   The resolved permutation.
   */
  #[DataProvider('dataProviderCanonicalOrder')]
  public function testCanonicalOrder(array $allowed, array $desired, array $expected): void {
    $this->assertSame($expected, Field::canonicalOrder($allowed, $desired));
  }

  /**
   * Data provider for testCanonicalOrder().
   *
   * @return \Iterator<string, array{list<string>, list<string>, list<string>}>
   *   The allowed values, desired order and resolved permutation.
   */
  public static function dataProviderCanonicalOrder(): \Iterator {
    yield 'empty desired keeps declared order' => [['a', 'b', 'c'], [], ['a', 'b', 'c']];
    yield 'full desired preserved' => [['a', 'b', 'c'], ['c', 'b', 'a'], ['c', 'b', 'a']];
    yield 'partial desired completed' => [['a', 'b', 'c'], ['c'], ['c', 'a', 'b']];
    yield 'unknown desired dropped' => [['a', 'b', 'c'], ['x', 'b'], ['b', 'a', 'c']];
    yield 'duplicate desired collapsed' => [['a', 'b', 'c'], ['b', 'b', 'a'], ['b', 'a', 'c']];
    yield 'no allowed values' => [[], ['a'], []];
  }

  /**
   * A select field mixing selectable, disabled and structural rows.
   */
  protected function selectField(): Field {
    return new Field('profile', 'Profile', '', FieldType::Select, '', [
      new Option('standard', 'Standard'),
      new Option('', 'Group', '', OptionKind::Heading),
      new Option('minimal', 'Minimal'),
      new Option('', '', '', OptionKind::Separator),
      new Option('demo', 'Demo', '', OptionKind::Option, TRUE, 'unavailable'),
    ]);
  }

}
