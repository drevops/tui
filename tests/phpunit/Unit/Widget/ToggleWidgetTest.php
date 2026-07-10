<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Input\ArrayKeyStream;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\AbstractWidget;
use DrevOps\Tui\Widget\ToggleWidget;
use DrevOps\Tui\Widget\WidgetRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the toggle widget.
 */
#[CoversClass(ToggleWidget::class)]
#[CoversClass(AbstractWidget::class)]
#[Group('widget')]
final class ToggleWidgetTest extends TestCase {

  public function testDefaultAndFlip(): void {
    $widget = new ToggleWidget(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'enabled');
    $this->assertSame('enabled', $widget->value());
    $this->assertStringContainsString('● Enabled', Ansi::strip($widget->view(new DefaultTheme())));

    $widget->handle(Key::named(KeyName::Space));
    $this->assertSame('disabled', $widget->value());
    $this->assertStringContainsString('● Disabled', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testHonoursExplicitDefault(): void {
    $widget = new ToggleWidget(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'disabled');

    $this->assertSame('disabled', $widget->value());
    $this->assertStringContainsString('● Disabled', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testUnknownDefaultFallsBackToFirst(): void {
    $widget = new ToggleWidget(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'nope');

    $this->assertSame('enabled', $widget->value());
  }

  #[DataProvider('dataProviderFlipKeys')]
  public function testFlipKeys(Key $key): void {
    $widget = new ToggleWidget(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'enabled');

    $widget->handle($key);

    $this->assertSame('disabled', $widget->value());
  }

  /**
   * Data provider for testFlipKeys().
   *
   * @return \Iterator<string, array{\DrevOps\Tui\Input\Key}>
   *   Each key that flips the switch.
   */
  public static function dataProviderFlipKeys(): \Iterator {
    yield 'space' => [Key::named(KeyName::Space)];
    yield 'left' => [Key::named(KeyName::Left)];
    yield 'right' => [Key::named(KeyName::Right)];
    yield 'up' => [Key::named(KeyName::Up)];
    yield 'down' => [Key::named(KeyName::Down)];
  }

  public function testDirectSelectionByFirstLetter(): void {
    $widget = new ToggleWidget(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'enabled');

    $widget->handle(Key::char('d'));
    $this->assertSame('disabled', $widget->value());

    $widget->handle(Key::char('e'));
    $this->assertSame('enabled', $widget->value());

    // Selection is case-insensitive.
    $widget->handle(Key::char('D'));
    $this->assertSame('disabled', $widget->value());

    // A letter matching neither label is a no-op.
    $widget->handle(Key::char('z'));
    $this->assertSame('disabled', $widget->value());
  }

  public function testFirstLetterCollisionSelectsFirstLabel(): void {
    $widget = new ToggleWidget(['public' => 'Public', 'private' => 'Private'], 'private');

    // Both labels start with "p"; the first-declared label wins.
    $widget->handle(Key::char('p'));
    $this->assertSame('public', $widget->value());

    // The colliding label stays reachable by flipping.
    $widget->handle(Key::named(KeyName::Space));
    $this->assertSame('private', $widget->value());
  }

  public function testAccept(): void {
    $widget = new ToggleWidget(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'enabled');

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Space), Key::named(KeyName::Enter)));

    $this->assertSame('disabled', $value);
    $this->assertTrue($widget->isComplete());
  }

  public function testCancel(): void {
    $widget = new ToggleWidget(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'enabled');

    $widget->handle(Key::named(KeyName::Escape));

    $this->assertTrue($widget->isCancelled());
  }

  public function testAsciiRendering(): void {
    $widget = new ToggleWidget(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'enabled');
    $theme = new DefaultTheme(76, ['unicode' => FALSE, 'color' => FALSE]);

    $view = $widget->view($theme);

    $this->assertStringContainsString('(*) Enabled', $view);
    $this->assertStringContainsString('( ) Disabled', $view);
  }

  public function testFlipWithoutOptionsIsSafe(): void {
    $widget = new ToggleWidget([]);

    $widget->handle(Key::named(KeyName::Space));

    $this->assertSame('', $widget->value());
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new ToggleWidget(['on' => 'On', 'off' => 'Off']))->hints());

    $this->assertSame(['toggle', 'accept', 'cancel'], $labels);
  }

}
