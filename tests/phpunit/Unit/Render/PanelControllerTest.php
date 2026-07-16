<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Render;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\ExternalEditor;
use DrevOps\Tui\Render\PanelController;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Render\TerminalControl;
use DrevOps\Tui\Testing\BufferedTerminal;
use DrevOps\Tui\Testing\KeyEncoder;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\DosTheme;
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
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('a', 'A');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE]), NULL, TRUE, TRUE, ['a' => 'x'], []);

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

  public function testInlineEditExpandsWidgetInsideThePanel(): void {
    $controller = $this->controller();
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());

    $frame = Ansi::strip($controller->frame(12));

    // The editor expands in place: the breadcrumb and the sibling row stay
    // visible, the field's view (its caret input) shows under the label, and
    // the footer switches to the widget's hints - no full-screen editor header.
    $this->assertStringContainsString('General', $frame);
    $this->assertStringContainsString('❯ Name', $frame);
    $this->assertStringContainsString('Acme', $frame);
    $this->assertStringContainsString('Advanced', $frame);
    $this->assertStringContainsString('accept', $frame);
    $this->assertStringNotContainsString('────', $frame);
  }

  public function testInlineEditRendersChoiceListInThePanel(): void {
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('main', 'Main', function (PanelBuilder $p): void {
        $p->select('env', 'Env')->default('dev')->options(['dev' => 'Development', 'prod' => 'Production']);
        $p->text('note', 'Note');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE]), NULL, TRUE, TRUE, ['env' => 'dev', 'note' => 'n'], []);

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));

    $frame = Ansi::strip($controller->frame(12));

    // A multi-line widget view renders inline too: the select's own radio list
    // shows in the panel, with the sibling field still visible below it.
    $this->assertStringContainsString('Development', $frame);
    $this->assertStringContainsString('Production', $frame);
    $this->assertStringContainsString('Note', $frame);
  }

  public function testInlineEditKeepsTheFieldDescription(): void {
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('main', 'Main', function (PanelBuilder $p): void {
        $p->confirm('cdn', 'Serve via CDN?')->description('Cache assets at the edge.');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(50, ['color' => FALSE]), NULL, TRUE, TRUE, ['cdn' => TRUE], []);

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));

    // The field's help text stays visible while its editor is open in the row.
    $this->assertStringContainsString('Cache assets at the edge.', Ansi::strip($controller->frame(12)));
  }

  public function testStandaloneEditTakesTheFullScreen(): void {
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name')->standalone();
        $p->text('other', 'Other');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE]), NULL, TRUE, TRUE, ['name' => 'Acme', 'other' => 'x'], []);

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());

    $frame = Ansi::strip($controller->frame(12));

    // A standalone field opens full-screen: the underlined label header and the
    // widget's hints, with none of the other panel rows around it.
    $this->assertStringContainsString("Name\n────", $frame);
    $this->assertStringContainsString('accept', $frame);
    $this->assertStringContainsString('esc cancel', $frame);
    $this->assertStringNotContainsString('Other', $frame);
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
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('a', 'A');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE]), NULL, FALSE, TRUE, ['a' => 'x'], []);

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

  public function testRunPaintsTheThemeBackground(): void {
    $config = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      })
      ->build();
    $keys = [KeyEncoder::encode(Key::named(KeyName::Enter))];

    // The dos theme washes the screen blue: run() hands its background to the
    // terminal, which fills every rendered frame with it.
    $dos = new PanelController($config, new DosTheme(40), NULL, TRUE, TRUE, ['name' => 'Acme'], []);
    $painted = new BufferedTerminal($keys);
    $dos->run($painted);
    $this->assertSame('44', $painted->paintedBackground);
    $this->assertStringContainsString("\033[44m", $painted->output());

    // A theme with no background leaves the terminal's own surface untouched.
    $plain = new PanelController($config, new DefaultTheme(40), NULL, TRUE, TRUE, ['name' => 'Acme'], []);
    $blank = new BufferedTerminal($keys);
    $plain->run($blank);
    $this->assertNull($blank->paintedBackground);
    $this->assertStringNotContainsString("\033[44m", $blank->output());
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

  public function testRunInterruptStopsBeforeHandlingMoreKeys(): void {
    $controller = $this->controller();
    // Ctrl-C arrives first; the Down queued after it must never be handled.
    $terminal = new BufferedTerminal(["\x03", KeyEncoder::encode(Key::named(KeyName::Down))]);

    $controller->run($terminal);

    $this->assertTrue($controller->isInterrupted());
    // An interrupt is neither a quit nor a cancel-button finish.
    $this->assertFalse($controller->isDone());
    $this->assertFalse($controller->isCancelled());
    // The loop broke on the interrupt, so the trailing Down never
    // moved the cursor.
    $this->assertSame(0, $controller->cursor());
  }

  public function testRunInterruptClearsEvenWhenClearOnExitOff(): void {
    $config = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      })
      ->build();
    $theme = new DefaultTheme(40, ['color' => FALSE]);

    // An interrupt renders the frame once (one clear) and then forces a second
    // clear at teardown despite clearOnExit (the fifth argument) being off.
    $interrupted = new PanelController($config, $theme, NULL, TRUE, FALSE, ['name' => 'Acme'], []);
    $terminal = new BufferedTerminal(["\x03"]);
    $interrupted->run($terminal);
    $this->assertTrue($interrupted->isInterrupted());
    $this->assertSame(2, substr_count($terminal->output(), TerminalControl::clear()));

    // Exhausting the input renders the same single frame but adds no teardown
    // clear - so the interrupt's extra clear is what wipes the screen.
    $exhausted = new PanelController($config, $theme, NULL, TRUE, FALSE, ['name' => 'Acme'], []);
    $quiet = new BufferedTerminal([]);
    $exhausted->run($quiet);
    $this->assertFalse($exhausted->isInterrupted());
    $this->assertSame(1, substr_count($quiet->output(), TerminalControl::clear()));
  }

  public function testRunInterruptAtBannerAbortsBeforeTheForm(): void {
    $config = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      })
      ->build();
    $controller = new PanelController($config, new DefaultTheme(40, ['color' => FALSE]), NULL, TRUE, TRUE, ['name' => 'Acme'], [], 'WELCOME', '2.0');
    // Ctrl-C at the "press any key" banner aborts instead of entering the form.
    $terminal = new BufferedTerminal(["\x03"]);

    $controller->run($terminal);

    $this->assertTrue($controller->isInterrupted());
    $this->assertStringContainsString('Press any key to continue', Ansi::strip($terminal->output()));
    // The loop was skipped entirely, so the form body never rendered.
    $this->assertStringNotContainsString('Name', Ansi::strip($terminal->output()));
  }

  public function testRunRendersBannerThenTheForm(): void {
    $builder = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE]), NULL, TRUE, TRUE, ['name' => 'Acme'], [], 'WELCOME', '2.0');
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
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->textarea('notes', 'Notes')->externalEditor();
      });

    return new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE]), NULL, TRUE, TRUE, ['notes' => 'seeded'], [], '', '', $editor);
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
   * A controller over a two-panel form seeded with answers.
   */
  protected function controller(): PanelController {
    $builder = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
        $p->panel('adv', 'Advanced', function (PanelBuilder $sp): void {
          $sp->confirm('debug', 'Debug');
        });
      })
      ->panel('drupal', 'Drupal', function (PanelBuilder $p): void {
        $p->text('profile', 'Profile');
      });
    $theme = new DefaultTheme(40, ['color' => FALSE]);

    return new PanelController($builder->build(), $theme, NULL, TRUE, TRUE, ['name' => 'Acme', 'debug' => FALSE, 'profile' => 'standard'], []);
  }

}
