<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\ArrayKeyStream;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\AbstractWidget;
use DrevOps\Tui\Widget\ReorderWidget;
use DrevOps\Tui\Widget\WidgetRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the reorder widget.
 */
#[CoversClass(ReorderWidget::class)]
#[CoversClass(AbstractWidget::class)]
#[Group('widget')]
final class ReorderWidgetTest extends TestCase {

  /**
   * The three-item fixture used across most cases.
   *
   * @return array<string,string>
   *   The value => label option map.
   */
  protected static function options(): array {
    return ['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry'];
  }

  public function testGrabAndMoveDownAccepts(): void {
    $widget = new ReorderWidget(self::options());

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['b', 'c', 'a'], $value);
  }

  public function testNavigateThenGrabMoveUp(): void {
    $widget = new ReorderWidget(self::options());

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Up),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'c', 'b'], $value);
  }

  public function testDefaultOrder(): void {
    $widget = new ReorderWidget(self::options(), ['c', 'a']);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['c', 'a', 'b'], $value);
  }

  public function testDefaultCompletesAndCleans(): void {
    // A partial default with an unknown ("x") and a duplicate ("b") still
    // resolves to a full ranking: known values first, remainder appended.
    $widget = new ReorderWidget(self::options(), ['b', 'x', 'b']);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['b', 'a', 'c'], $value);
  }

  public function testCancelReturnsNull(): void {
    $widget = new ReorderWidget(self::options());

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertNull($value);
    $this->assertTrue($widget->isCancelled());
  }

  public function testGrabbedClampsAtTop(): void {
    $widget = new ReorderWidget(self::options());

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Up),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'b', 'c'], $value);
  }

  public function testGrabbedClampsAtBottom(): void {
    $widget = new ReorderWidget(self::options());

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'b', 'c'], $value);
  }

  public function testNavigationClampsAtTop(): void {
    $widget = new ReorderWidget(self::options());

    // Up at the top is a no-op; grabbing then moving down still works.
    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Up),
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['b', 'a', 'c'], $value);
  }

  public function testNavigationClampsAtBottom(): void {
    $widget = new ReorderWidget(self::options());

    // A third Down stays on the last row; grabbing then moving up still works.
    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Up),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'c', 'b'], $value);
  }

  public function testGrabTogglesOffThenNavigates(): void {
    $widget = new ReorderWidget(self::options());

    // Grab then drop: the following Down navigates rather than moving the item.
    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'b', 'c'], $value);
  }

  public function testLiveValueReflectsMovesBeforeAccept(): void {
    $widget = new ReorderWidget(self::options());

    $widget->handle(Key::named(KeyName::Space));
    $widget->handle(Key::named(KeyName::Down));

    $this->assertSame(['b', 'a', 'c'], $widget->value());
  }

  public function testViewMarkersDegradeWithUnicodeMode(): void {
    $widget = new ReorderWidget(self::options());

    $before = Ansi::strip($widget->view(new DefaultTheme()));
    $this->assertStringContainsString('❯', $before);
    $this->assertStringNotContainsString('↑↓', $before);

    $widget->handle(Key::named(KeyName::Space));

    $grabbed = Ansi::strip($widget->view(new DefaultTheme()));
    $this->assertStringContainsString('↑↓', $grabbed);

    $ascii = Ansi::strip($widget->view(new DefaultTheme(76, ['unicode' => FALSE])));
    $this->assertStringContainsString('^v', $ascii);
  }

  public function testHints(): void {
    $hints = array_map(static fn(Hint $hint): array => [$hint->label, $hint->actions], (new ReorderWidget(self::options()))->hints());

    $this->assertSame([
      ['move', [Action::MoveUp, Action::MoveDown]],
      ['grab', [Action::Grab]],
      ['accept', [Action::Accept]],
      ['cancel', [Action::Cancel]],
    ], $hints);
  }

  public function testHintsWhileHoldingItem(): void {
    $widget = new ReorderWidget(self::options());
    $widget->handle(Key::named(KeyName::Space));

    $hints = array_map(static fn(Hint $hint): array => [$hint->label, $hint->actions], $widget->hints());

    // Holding an item swaps to reorder/drop labels and drops the accept hint -
    // the form cannot be accepted while an item is held.
    $this->assertSame([
      ['reorder', [Action::MoveUp, Action::MoveDown]],
      ['drop', [Action::Grab]],
      ['cancel', [Action::Cancel]],
    ], $hints);
  }

  public function testEnterDropsHeldItemInsteadOfAccepting(): void {
    $widget = new ReorderWidget(self::options());

    $widget->handle(Key::named(KeyName::Space));
    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Enter));

    // Enter dropped the held item rather than accepting the form.
    $this->assertFalse($widget->isComplete());
    $this->assertSame(['b', 'a', 'c'], $widget->value());

    // A second Enter, with nothing held, accepts.
    $widget->handle(Key::named(KeyName::Enter));
    $this->assertTrue($widget->isComplete());
  }

  public function testRejectsNonPositivePageSize(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Page size must be a positive integer, -3 given.');

    new ReorderWidget(self::options(), pageSize: -3);
  }

  public function testPagesLongList(): void {
    $widget = new ReorderWidget(['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry', 'd' => 'Date'], pageSize: 2);

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
