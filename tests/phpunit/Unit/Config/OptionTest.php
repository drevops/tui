<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Config;

use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Config\OptionKind;
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
#[Group('config')]
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
    yield [FieldType::Suggest, FALSE];
    yield [FieldType::Text, FALSE];
    yield [FieldType::Confirm, FALSE];
  }

  #[DataProvider('dataProviderIsMulti')]
  public function testIsMulti(FieldType $type, bool $expected): void {
    $this->assertSame($expected, $type->isMulti());
  }

  public static function dataProviderIsMulti(): \Iterator {
    yield [FieldType::MultiSelect, TRUE];
    yield [FieldType::MultiSearch, TRUE];
    yield [FieldType::Select, FALSE];
    yield [FieldType::Search, FALSE];
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
    $field = new Field('f', 'F', '', $type, $type->isMulti() ? [] : '', $options);

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
