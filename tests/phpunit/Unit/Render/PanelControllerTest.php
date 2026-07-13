<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Render;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyEncoder;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\ExternalEditor;
use DrevOps\Tui\Render\PanelController;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Testing\BufferedTerminal;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the interactive panel controller.
 */
#[CoversClass(PanelController::class)]
#[Group('tui')]
final class PanelControllerTest extends TestCase {

  public function testDrillIntoPanelAndBack(): void {
    $controller = $this->controller();

    $controller->handle(Key::named(KeyName::Enter));
    $this->assertSame('General', $controller->currentPanel()->title);

    $controller->handle(Key::named(KeyName::Escape));
    $this->assertSame('Demo', $controller->currentPanel()->title);
  }

  public function testNavigateCursorClamps(): void {
    $controller = $this->controller();
    $this->assertSame(0, $controller->cursor());

    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(1, $controller->cursor());

    // The root holds 2 panels plus the Submit and Cancel buttons (4 items).
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(3, $controller->cursor());

    $controller->handle(Key::named(KeyName::Up));
    $this->assertSame(2, $controller->cursor());
  }

  public function testSubmitButton(): void {
    $controller = $this->controller();

    // Move past the 2 panels to Submit (index 2), then activate it.
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertTrue($controller->isDone());
    $this->assertFalse($controller->isCancelled());
  }

  public function testCancelButton(): void {
    $controller = $this->controller();

    // Move to Cancel (index 3), then activate it.
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertTrue($controller->isDone());
    $this->assertTrue($controller->isCancelled());
  }

  public function testButtonsRenderByDefault(): void {
    $controller = $this->controller();

    // Submit and Cancel render inline on one row.
    $this->assertStringContainsString('[ Submit ]  [ Cancel ]', Ansi::strip($controller->frame(12)));

    // Select a button and re-render (covers the button cursor-line branch).
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $this->assertStringContainsString('[ Submit ]', Ansi::strip($controller->frame(12)));
  }

  public function testButtonsOptOut(): void {
    $config = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('a', 'A');
      })
      ->build();
    $controller = new PanelController($config, new DefaultTheme(40, ['color' => FALSE]), ['a' => 'x'], []);

    $this->assertStringNotContainsString('Submit', Ansi::strip($controller->frame(12)));

    // With buttons off, the single field is the only item: Down clamps at 0.
    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(0, $controller->cursor());
  }

  public function testButtonsOnlyOnRoot(): void {
    $controller = $this->controller();

    // The root panel shows the buttons.
    $this->assertStringContainsString('[ Submit ]', Ansi::strip($controller->frame(12)));

    // Drilling into a sub-panel hides them.
    $controller->handle(Key::named(KeyName::Enter));
    $sub = Ansi::strip($controller->frame(12));
    $this->assertStringNotContainsString('Submit', $sub);
    $this->assertStringNotContainsString('Cancel', $sub);

    // Popping back to the root shows them again.
    $controller->handle(Key::named(KeyName::Escape));
    $this->assertStringContainsString('[ Submit ]', Ansi::strip($controller->frame(12)));
  }

  public function testEditingFrameShowsHeaderAndHints(): void {
    $controller = $this->controller();
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());

    $frame = Ansi::strip($controller->frame(12));

    // The editor shows the field label underlined with the rule glyph, and
    // editing hints instead of the panel status line.
    $this->assertStringContainsString("Name\n────", $frame);
    $this->assertStringContainsString('accept', $frame);
    $this->assertStringContainsString('esc cancel', $frame);
    $this->assertStringNotContainsString('move', $frame);
  }

  public function testButtonsNavigateWithLeftRight(): void {
    $controller = $this->controller();

    // Past the two sub-panels to Submit (index 2).
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(2, $controller->cursor());

    // Right moves to Cancel, Left moves back to Submit.
    $controller->handle(Key::named(KeyName::Right));
    $this->assertSame(3, $controller->cursor());
    $controller->handle(Key::named(KeyName::Left));
    $this->assertSame(2, $controller->cursor());

    // Left on the first button clamps.
    $controller->handle(Key::named(KeyName::Left));
    $this->assertSame(2, $controller->cursor());
  }

  public function testLeftRightIgnoredOffButtons(): void {
    $controller = $this->controller();

    // On a normal item, Left/Right do nothing.
    $controller->handle(Key::named(KeyName::Right));
    $this->assertSame(0, $controller->cursor());
    $controller->handle(Key::named(KeyName::Left));
    $this->assertSame(0, $controller->cursor());
  }

  public function testEditFieldReturnsWithValue(): void {
    $controller = $this->controller();
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());

    $controller->handle(Key::char('!'));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertFalse($controller->isEditing());
    $this->assertSame('Acme!', $controller->answers()->value('name'));
    $this->assertSame(Provenance::Edited, $controller->answers()->provenanceOf('name'));
  }

  public function testEditCancelKeepsValue(): void {
    $controller = $this->controller();
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());

    $controller->handle(Key::named(KeyName::Escape));

    $this->assertFalse($controller->isEditing());
    $this->assertSame('Acme', $controller->answers()->value('name'));
  }

  public function testDrillIntoSubPanel(): void {
    $controller = $this->controller();
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(1, $controller->cursor());

    $controller->handle(Key::named(KeyName::Enter));

    $this->assertSame('Advanced', $controller->currentPanel()->title);
  }

  public function testMouseWheelScrollsWithoutMovingCursor(): void {
    $controller = $this->controller();
    $before = $controller->cursor();

    $controller->handle(Key::named(KeyName::MouseWheelDown));

    $this->assertSame($before, $controller->cursor());
    $this->assertFalse($controller->isEditing());
    $this->assertStringContainsString('Demo', $controller->frame(4));
  }

  public function testMouseWheelUpScrollsBackWithoutMovingCursor(): void {
    $controller = $this->controller();

    $controller->handle(Key::named(KeyName::MouseWheelDown));
    $controller->handle(Key::named(KeyName::MouseWheelUp));

    $this->assertSame(0, $controller->cursor());
    $this->assertStringContainsString('Demo', $controller->frame(4));
  }

  public function testHubShowsPanelValueSummary(): void {
    $controller = $this->controller();

    // At the root hub (before drilling in), each sub-panel shows a one-line
    // summary of its field values - here the "General" panel's name.
    $this->assertStringContainsString('Acme', Ansi::strip($controller->frame(20)));
  }

  public function testFrameShowsSelectionAndValue(): void {
    $controller = $this->controller();
    $controller->handle(Key::named(KeyName::Enter));

    $frame = Ansi::strip($controller->frame(12));

    $this->assertStringContainsString('General', $frame);
    $this->assertStringContainsString('❯ Name', $frame);
    $this->assertStringContainsString('Acme', $frame);
  }

  public function testEditingFrameShowsWidget(): void {
    $controller = $this->controller();
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));

    $frame = $controller->frame(12);

    $this->assertStringContainsString('Name', $frame);
    $this->assertStringContainsString('Acme', $frame);
  }

  public function testQuit(): void {
    $controller = $this->controller();
    $this->assertFalse($controller->isDone());

    $controller->handle(Key::char('q'));

    $this->assertTrue($controller->isDone());
  }

  public function testHubFooterShowsQuitAndHelp(): void {
    $controller = $this->controller();

    // The hub footer is complete: it surfaces quit and the help toggle, not
    // just the move/select/back subset.
    $footer = Ansi::strip($controller->frame(12));
    $this->assertStringContainsString('q quit', $footer);
    $this->assertStringContainsString('? help', $footer);
  }

  public function testHelpOverlayTogglesAndCloses(): void {
    $controller = $this->controller();

    // '?' opens the overlay; the frame becomes the help screen listing the hub
    // and each widget type the form uses (here Text and Confirm).
    $controller->handle(Key::char('?'));
    $this->assertTrue($controller->isShowingHelp());

    $help = Ansi::strip($controller->frame(12));
    $this->assertStringContainsString('Keyboard help', $help);
    $this->assertStringContainsString('Navigation', $help);
    $this->assertStringContainsString('Text', $help);
    $this->assertStringContainsString('Confirm', $help);
    $this->assertStringContainsString('? close', $help);

    // Any key dismisses it, and that key does nothing else (the cursor stays).
    $controller->handle(Key::named(KeyName::Down));
    $this->assertFalse($controller->isShowingHelp());
    $this->assertSame(0, $controller->cursor());
    $this->assertStringContainsString('Demo', Ansi::strip($controller->frame(12)));
  }

  public function testFooterHiddenWhenTurnedOff(): void {
    $config = Form::create('Demo')
      ->footer(FALSE)
      ->buttons(FALSE)
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('a', 'A');
      })
      ->build();
    $controller = new PanelController($config, new DefaultTheme(40, ['color' => FALSE]), ['a' => 'x'], []);

    // The hub footer is gone.
    $this->assertStringNotContainsString('quit', Ansi::strip($controller->frame(12)));

    // And so is the editor's hint line (drill into the panel, then the field).
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());
    $this->assertStringNotContainsString('accept', Ansi::strip($controller->frame(12)));
  }

  public function testRunSubmitsThroughTheInputPipe(): void {
    $controller = $this->controller();
    $terminal = new BufferedTerminal([
      KeyEncoder::encode(Key::named(KeyName::Down)),
      KeyEncoder::encode(Key::named(KeyName::Down)),
      KeyEncoder::encode(Key::named(KeyName::Enter)),
    ]);

    $answers = $controller->run($terminal);

    $this->assertInstanceOf(Answers::class, $answers);
    $this->assertTrue($controller->isDone());
    $this->assertFalse($controller->isCancelled());
    // The loop rendered the hub before submitting.
    $this->assertStringContainsString('Demo', Ansi::strip($terminal->output()));
  }

  public function testRunStopsWhenInputIsExhausted(): void {
    $controller = $this->controller();
    // A single navigation key that does not finish the form; input then ends.
    $terminal = new BufferedTerminal([KeyEncoder::encode(Key::named(KeyName::Down))]);

    $controller->run($terminal);

    // The EOF break ends the loop without the form being submitted.
    $this->assertFalse($controller->isDone());
    $this->assertSame(1, $controller->cursor());
  }

  public function testRunRendersBannerThenTheForm(): void {
    $config = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      })
      ->build();
    $controller = new PanelController($config, new DefaultTheme(40, ['color' => FALSE]), ['name' => 'Acme'], [], 'WELCOME', '2.0');
    // The first key dismisses the banner; input then ends.
    $terminal = new BufferedTerminal([KeyEncoder::encode(Key::named(KeyName::Enter))]);

    $controller->run($terminal);

    $this->assertStringContainsString('Press any key to continue', Ansi::strip($terminal->output()));
    // After the banner is dismissed, the loop renders the form body itself.
    $this->assertStringContainsString('General', Ansi::strip($terminal->output()));
  }

  public function testTextareaExternalEditCommitsCapturedValue(): void {
    $controller = $this->textareaController($this->fixedEditor('FROM EDITOR'));

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());

    $controller->handle(Key::char("\x05"));

    $this->assertFalse($controller->isEditing());
    $this->assertSame('FROM EDITOR', $controller->answers()->value('notes'));
    $this->assertSame(Provenance::Edited, $controller->answers()->provenanceOf('notes'));
  }

  public function testTextareaExternalEditAbortKeepsEditing(): void {
    $controller = $this->textareaController($this->fixedEditor(NULL));

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::char("\x05"));

    // A NULL capture (aborted edit) leaves the field open, value intact.
    $this->assertTrue($controller->isEditing());
    $this->assertSame('seeded', $controller->answers()->value('notes'));
  }

  public function testTextareaEditorHintShownWhenAvailable(): void {
    $controller = $this->textareaController($this->fixedEditor(NULL));

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertStringContainsString('ctrl-e editor', Ansi::strip($controller->frame(12)));
  }

  public function testTextareaEditorHintHiddenAndHandoffInertWhenUnavailable(): void {
    $controller = $this->textareaController($this->unavailableEditor());

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertStringNotContainsString('ctrl-e editor', Ansi::strip($controller->frame(12)));

    // With no editor available the trigger is inert - editing continues.
    $controller->handle(Key::char("\x05"));
    $this->assertTrue($controller->isEditing());
    $this->assertSame('seeded', $controller->answers()->value('notes'));
  }

  /**
   * A single-panel controller whose textarea opts into the editor handoff.
   *
   * @param \DrevOps\Tui\Render\ExternalEditor $editor
   *   The external-editor service to inject.
   */
  protected function textareaController(ExternalEditor $editor): PanelController {
    $config = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->textarea('notes', 'Notes')->externalEditor();
      })
      ->build();

    return new PanelController($config, new DefaultTheme(40, ['color' => FALSE]), ['notes' => 'seeded'], [], '', '', $editor);
  }

  /**
   * An available editor stub returning a fixed capture.
   *
   * @param string|null $result
   *   The value edit() returns (NULL simulates an aborted edit).
   */
  protected function fixedEditor(?string $result): ExternalEditor {
    return new class($result) extends ExternalEditor {

      public function __construct(protected ?string $result) {
      }

      #[\Override]
      public function isAvailable(): bool {
        return TRUE;
      }

      #[\Override]
      public function edit(string $initial, ?Terminal $terminal = NULL): ?string {
        return $this->result;
      }

    };
  }

  /**
   * An editor stub reporting no editor is available.
   */
  protected function unavailableEditor(): ExternalEditor {
    return new class extends ExternalEditor {

      #[\Override]
      public function isAvailable(): bool {
        return FALSE;
      }

      #[\Override]
      public function edit(string $initial, ?Terminal $terminal = NULL): ?string {
        throw new \RuntimeException('the editor must not launch when unavailable');
      }

    };
  }

  /**
   * A controller over a two-panel config seeded with answers.
   */
  protected function controller(): PanelController {
    $config = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
        $p->panel('adv', 'Advanced', function (PanelBuilder $sp): void {
          $sp->confirm('debug', 'Debug');
        });
      })
      ->panel('drupal', 'Drupal', function (PanelBuilder $p): void {
        $p->text('profile', 'Profile');
      })
      ->build();
    $theme = new DefaultTheme(40, ['color' => FALSE]);

    return new PanelController($config, $theme, ['name' => 'Acme', 'debug' => FALSE, 'profile' => 'standard'], []);
  }

}
