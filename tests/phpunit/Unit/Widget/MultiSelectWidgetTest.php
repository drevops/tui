<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\WidgetRunner;
use DrevOps\Tui\Tests\Traits\AssertsPagingTrait;
use DrevOps\Tui\Tests\Traits\MixedOptionsTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\AbstractWidget;
use DrevOps\Tui\Widget\Capability\FilterCapableTrait;
use DrevOps\Tui\Widget\Capability\OptionsCapableTrait;
use DrevOps\Tui\Widget\Capability\MultiSelectionCapableTrait;
use DrevOps\Tui\Widget\MultiSelectWidget;
use DrevOps\Tui\Widget\Capability\PagingCapableTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the multiselect widget.
 */
#[CoversClass(MultiSelectWidget::class)]
#[CoversClass(AbstractWidget::class)]
#[CoversClass(OptionsCapableTrait::class)]
#[CoversClass(MultiSelectionCapableTrait::class)]
#[CoversClass(FilterCapableTrait::class)]
#[CoversClass(PagingCapableTrait::class)]
#[Group('widget')]
final class MultiSelectWidgetTest extends TestCase {

  use AssertsPagingTrait;
  use MixedOptionsTrait;

  public function testToggleAndAccept(): void {
    $widget = new MultiSelectWidget(['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry']);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'b'], $value);
  }

  public function testDefaultSelected(): void {
    $widget = new MultiSelectWidget(['a' => 'A', 'b' => 'B'], ['b']);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['b'], $value);
  }

  public function testFilterNarrowsThenToggles(): void {
    $widget = new MultiSelectWidget(['apple' => 'Apple', 'apricot' => 'Apricot', 'banana' => 'Banana']);

    $widget->handle(Key::char('b'));
    $widget->handle(Key::char('a'));
    $widget->handle(Key::char('n'));
    $this->assertStringContainsString('Banana', $widget->view(new DefaultTheme()));
    $this->assertStringNotContainsString('Apple', $widget->view(new DefaultTheme()));

    $widget->handle(Key::named(KeyName::Space));
    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['banana'], $value);
  }

  public function testFilterBackspaceRestoresList(): void {
    $widget = new MultiSelectWidget(['apple' => 'Apple', 'banana' => 'Banana']);

    $widget->handle(Key::char('b'));
    $this->assertStringNotContainsString('Apple', $widget->view(new DefaultTheme()));

    $widget->handle(Key::named(KeyName::Backspace));
    $this->assertStringContainsString('Apple', $widget->view(new DefaultTheme()));
  }

  public function testSelectAllAndNone(): void {
    $widget = new MultiSelectWidget(['a' => 'A', 'b' => 'B', 'c' => 'C']);

    $widget->handle(Key::named(KeyName::Right));
    $this->assertSame(['a', 'b', 'c'], $widget->value());

    $widget->handle(Key::named(KeyName::Left));
    $this->assertSame([], $widget->value());
  }

  public function testCancel(): void {
    $widget = new MultiSelectWidget(['a' => 'A', 'b' => 'B']);

    WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($widget->isCancelled());
  }

  public function testUpMovesCursorBack(): void {
    $widget = new MultiSelectWidget(['a' => 'A', 'b' => 'B']);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Up),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a'], $value);
  }

  public function testToggleOffDeselects(): void {
    $widget = new MultiSelectWidget(['a' => 'A', 'b' => 'B'], ['b']);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame([], $value);
  }

  public function testToggleWithNoMatchesIsNoop(): void {
    $widget = new MultiSelectWidget(['a' => 'Apple']);

    $widget->handle(Key::char('z'));
    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame([], $value);
  }

  public function testHints(): void {
    $hints = array_map(static fn(Hint $hint): array => [$hint->label, $hint->actions], (new MultiSelectWidget(['a' => 'A']))->hints());

    $this->assertSame([
      ['select', [Action::Toggle]],
      ['move', [Action::MoveUp, Action::MoveDown]],
      ['none/all', [Action::SelectNone, Action::SelectAll]],
      ['accept', [Action::Accept]],
      ['cancel', [Action::Cancel]],
    ], $hints);
  }

  public function testSpaceSkipsDisabledAndTogglesSelectable(): void {
    $widget = new MultiSelectWidget($this->mixedOptions());

    // Toggle Apple, skip the heading to Banana and toggle it, skip the
    // separator and the disabled Cherry to Date and toggle it.
    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'b', 'd'], $value);
  }

  public function testSelectAllSkipsDisabled(): void {
    $widget = new MultiSelectWidget($this->mixedOptions());

    $widget->handle(Key::named(KeyName::Right));

    $this->assertSame(['a', 'b', 'd'], $widget->value());
  }

  public function testDefaultExcludesDisabled(): void {
    $widget = new MultiSelectWidget($this->mixedOptions(), ['c', 'a']);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['a'], $value);
  }

  public function testFilterDropsHeadingsAndSeparators(): void {
    $widget = new MultiSelectWidget($this->mixedOptions());

    $widget->handle(Key::char('b'));
    $widget->handle(Key::char('a'));
    $widget->handle(Key::char('n'));
    $view = Ansi::strip($widget->view(new DefaultTheme()));

    $this->assertStringContainsString('Banana', $view);
    $this->assertStringNotContainsString('Fruits', $view);
    $this->assertStringNotContainsString('Apple', $view);
    $this->assertStringNotContainsString('──', $view);
  }

  public function testDisabledMatchingFilterIsShownButNotToggleable(): void {
    $widget = new MultiSelectWidget($this->mixedOptions());

    $widget->handle(Key::char('e'));
    $widget->handle(Key::char('r'));
    $widget->handle(Key::char('r'));
    $this->assertStringContainsString('Cherry (out of stock)', Ansi::strip($widget->view(new DefaultTheme())));

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame([], $value);
  }

  public function testRendersHeadingSeparatorAndDisabled(): void {
    $view = Ansi::strip((new MultiSelectWidget($this->mixedOptions()))->view(new DefaultTheme()));

    $this->assertStringContainsString('Fruits', $view);
    $this->assertStringContainsString('Cherry (out of stock)', $view);
    $this->assertStringContainsString('──', $view);
  }

  public function testFilterStaysSubstringNotFuzzy(): void {
    $widget = new MultiSelectWidget(['banana' => 'Banana', 'apple' => 'Apple']);

    // "bn" is a subsequence of "Banana" but not a substring, so the checkbox
    // list - which stays substring-only - narrows it away.
    $widget->handle(Key::char('b'));
    $widget->handle(Key::char('n'));

    $this->assertStringNotContainsString('Banana', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testRejectsNonPositivePageSize(): void {
    $this->assertRejectsNonPositivePageSize(static fn(int $size): MultiSelectWidget => new MultiSelectWidget(['a' => 'A'], page_size: $size), -3);
  }

  public function testPagesLongOptionList(): void {
    $this->assertPagesAndFollowsCursor(static fn(int $size): MultiSelectWidget => new MultiSelectWidget(self::pagingOptions(), page_size: $size));
  }

}
