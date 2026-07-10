<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Input\ArrayKeyStream;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\TextareaWidget;
use DrevOps\Tui\Widget\WidgetRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the textarea widget.
 */
#[CoversClass(TextareaWidget::class)]
#[Group('widget')]
final class TextareaWidgetTest extends TestCase {

  public function testEnterInsertsNewlineAndTabAccepts(): void {
    $widget = new TextareaWidget();

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('one', Key::named(KeyName::Enter), 'two', Key::named(KeyName::Tab)));

    $this->assertSame("one\ntwo", $value);
    $this->assertTrue($widget->isComplete());
  }

  public function testUpAndDownMoveAcrossLines(): void {
    $widget = new TextareaWidget("ab\ncd");

    // The cursor starts at the end of "cd"; Up keeps the column on "ab".
    $widget->handle(Key::named(KeyName::Up));
    $widget->handle(Key::char('X'));

    $this->assertSame("abX\ncd", $widget->value());

    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::char('Y'));

    $this->assertSame("abX\ncdY", $widget->value());
  }

  public function testUpClampsAtFirstLineAndDownAtLast(): void {
    $widget = new TextareaWidget('solo');

    $widget->handle(Key::named(KeyName::Up));
    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Tab));

    $this->assertSame('solo', $widget->value());
  }

  public function testUpFromLongerLineClampsColumn(): void {
    $widget = new TextareaWidget("a\nlonger");

    $widget->handle(Key::named(KeyName::Up));
    $widget->handle(Key::char('Z'));

    $this->assertSame("aZ\nlonger", $widget->value());
  }

  public function testViewShowsHintAndError(): void {
    $widget = new TextareaWidget('x', FALSE, fn(mixed $value): string => 'Nope.');

    $view = $widget->view(new DefaultTheme());
    $this->assertStringContainsString('tab accept', $view);

    $widget->handle(Key::named(KeyName::Tab));
    $this->assertStringContainsString('Nope.', $widget->view(new DefaultTheme()));
  }

  public function testCancel(): void {
    $widget = new TextareaWidget('x');

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($widget->isCancelled());
    $this->assertNull($value);
  }

  public function testRendersOwnHint(): void {
    // Its view already shows "enter newline / tab accept", so the editor chrome
    // must not add the generic "enter accept" hint on top.
    $this->assertTrue((new TextareaWidget('x'))->rendersHint());
  }

  public function testCtrlERequestsExternalEditWhenEnabled(): void {
    $widget = new TextareaWidget('draft', TRUE);

    $widget->handle(Key::char("\x05"));

    $this->assertTrue($widget->wantsExternalEdit());
    // The buffer is untouched until the captured value is applied.
    $this->assertSame('draft', $widget->value());
    $this->assertFalse($widget->isComplete());
  }

  public function testCtrlESwallowedWhenDisabled(): void {
    $widget = new TextareaWidget('draft');

    $widget->handle(Key::char("\x05"));

    $this->assertFalse($widget->wantsExternalEdit());
    // The control key is swallowed, never inserted as a raw byte.
    $this->assertSame('draft', $widget->value());
  }

  public function testApplyExternalEditReplacesBufferAndAccepts(): void {
    $widget = new TextareaWidget('old', TRUE);
    $widget->handle(Key::char("\x05"));

    $widget->applyExternalEdit("new\ntext");

    $this->assertSame("new\ntext", $widget->value());
    $this->assertTrue($widget->isComplete());
    $this->assertFalse($widget->wantsExternalEdit());
  }

  public function testApplyExternalEditNullKeepsBufferAndStaysEditing(): void {
    $widget = new TextareaWidget('keep', TRUE);
    $widget->handle(Key::char("\x05"));

    $widget->applyExternalEdit(NULL);

    $this->assertSame('keep', $widget->value());
    $this->assertFalse($widget->isComplete());
    $this->assertFalse($widget->wantsExternalEdit());
  }

  public function testApplyExternalEditRunsValidator(): void {
    $widget = new TextareaWidget('x', TRUE, fn(mixed $value): string => 'Nope.');

    $widget->applyExternalEdit('bad');

    $this->assertFalse($widget->isComplete());
    $this->assertStringContainsString('Nope.', $widget->view(new DefaultTheme()));
  }

  public function testViewShowsEditorHintOnlyWhenEnabled(): void {
    $this->assertStringContainsString('ctrl-e editor', (new TextareaWidget('x', TRUE))->view(new DefaultTheme()));
    $this->assertStringNotContainsString('ctrl-e editor', (new TextareaWidget('x'))->view(new DefaultTheme()));
  }

}
