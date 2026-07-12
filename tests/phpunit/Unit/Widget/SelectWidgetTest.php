<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Config\OptionKind;
use DrevOps\Tui\Input\ArrayKeyStream;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Tests\Traits\MixedOptionsTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\AbstractWidget;
use DrevOps\Tui\Widget\ChoiceListTrait;
use DrevOps\Tui\Widget\SelectWidget;
use DrevOps\Tui\Widget\WidgetRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the select widget.
 */
#[CoversClass(SelectWidget::class)]
#[CoversClass(AbstractWidget::class)]
#[CoversClass(ChoiceListTrait::class)]
#[Group('widget')]
final class SelectWidgetTest extends TestCase {

  use MixedOptionsTrait;

  public function testNavigatesAndSelects(): void {
    $widget = new SelectWidget(['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry'], 'a');

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Up),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('b', $value);
    $this->assertStringContainsString('●', $widget->view(new DefaultTheme()));
  }

  public function testDefaultHighlight(): void {
    $widget = new SelectWidget(['a' => 'A', 'b' => 'B'], 'b');

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame('b', $value);
  }

  public function testBoundsClamp(): void {
    $widget = new SelectWidget(['a' => 'A', 'b' => 'B']);

    $widget->handle(Key::named(KeyName::Up));
    $this->assertSame('a', $widget->value());

    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Down));
    $this->assertSame('b', $widget->value());
  }

  public function testCancel(): void {
    $widget = new SelectWidget(['a' => 'A', 'b' => 'B']);

    $widget->handle(Key::named(KeyName::Escape));

    $this->assertTrue($widget->isCancelled());
  }

  public function testSetKeysInjectsBindings(): void {
    // An injected scope map takes over from the lazy default: the vim select
    // scope binds j to move-down, which the default preset does not.
    $widget = (new SelectWidget(['a' => 'A', 'b' => 'B'], 'a'))
      ->setKeys(KeyMapManager::create('vim')->forField(FieldType::Select));

    $widget->handle(Key::char('j'));

    $this->assertSame('b', $widget->value());
  }

  public function testNavigationSkipsHeadingsSeparatorsAndDisabled(): void {
    $widget = new SelectWidget($this->mixedOptions());

    // From Apple (0): Down skips the heading to Banana (2); Down skips the
    // separator and the disabled Cherry to Date (5).
    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('d', $value);
  }

  public function testUpSkipsBackOverDisabled(): void {
    $widget = new SelectWidget($this->mixedOptions());

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Up),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('b', $value);
  }

  public function testDefaultOnDisabledFallsBackToFirstSelectable(): void {
    $widget = new SelectWidget($this->mixedOptions(), 'c');

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame('a', $value);
  }

  public function testRendersHeadingSeparatorAndDisabledReason(): void {
    $view = Ansi::strip((new SelectWidget($this->mixedOptions()))->view(new DefaultTheme()));

    $this->assertStringContainsString('Fruits', $view);
    $this->assertStringContainsString('Cherry (out of stock)', $view);
    $this->assertStringContainsString('──', $view);
  }

  public function testNoSelectableRowYieldsNoValue(): void {
    $widget = new SelectWidget([new Option('', 'Group', '', OptionKind::Heading)]);

    $widget->handle(Key::named(KeyName::Enter));

    $this->assertFalse($widget->isComplete());
    $this->assertSame('', $widget->value());
  }

  public function testRejectsNonPositivePageSize(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Page size must be a positive integer, 0 given.');

    new SelectWidget(['a' => 'A'], pageSize: 0);
  }

  public function testPagesLongOptionList(): void {
    $widget = new SelectWidget(['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry', 'd' => 'Date'], pageSize: 2);

    $view = Ansi::strip($widget->view(new DefaultTheme()));

    $this->assertStringContainsString('Apple', $view);
    $this->assertStringContainsString('Banana', $view);
    $this->assertStringNotContainsString('Cherry', $view);
    $this->assertStringContainsString('▼', $view);

    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Down));
    $scrolled = Ansi::strip($widget->view(new DefaultTheme()));

    $this->assertStringContainsString('Cherry', $scrolled);
    $this->assertStringContainsString('▲', $scrolled);
    $this->assertStringNotContainsString('Apple', $scrolled);
  }

}
