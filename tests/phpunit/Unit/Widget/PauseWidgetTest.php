<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\WidgetRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\AbstractWidget;
use DrevOps\Tui\Widget\PauseWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the pause widget.
 */
#[CoversClass(PauseWidget::class)]
#[CoversClass(AbstractWidget::class)]
#[Group('widget')]
final class PauseWidgetTest extends TestCase {

  public function testEnterAcknowledges(): void {
    $widget = new PauseWidget();

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertTrue($value);
    $this->assertTrue($widget->isComplete());
  }

  public function testSpaceAcknowledges(): void {
    $widget = new PauseWidget();

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Space)));

    $this->assertTrue($value);
  }

  public function testOtherKeysIgnored(): void {
    $widget = new PauseWidget();

    $widget->handle(Key::char('x'));
    $widget->handle(Key::named(KeyName::Down));

    $this->assertFalse($widget->isComplete());
    $this->assertFalse($widget->value());
  }

  public function testCancelAndView(): void {
    $widget = new PauseWidget();

    // The prompt key glyph is drawn from the live binding (Enter by default).
    $view = $widget->view(new DefaultTheme());
    $this->assertStringContainsString('Press ', $view);
    $this->assertStringContainsString('to continue', $view);
    $this->assertStringContainsString('↵', $view);

    $widget->handle(Key::named(KeyName::Escape));
    $this->assertTrue($widget->isCancelled());
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new PauseWidget())->hints());

    $this->assertSame(['continue', 'cancel'], $labels);
  }

}
