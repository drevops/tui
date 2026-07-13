<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Render\PanelController;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Testing\BufferedTerminal;
use DrevOps\Tui\Tests\Traits\ResetsTranslator;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;
use DrevOps\Tui\Tui;
use DrevOps\Tui\Engine\Engine;
use DrevOps\Tui\Handler\HandlerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Tui facade.
 */
#[CoversClass(Tui::class)]
#[Group('tui')]
final class TuiTest extends TestCase {

  use ResetsTranslator;

  public function testActivatesTranslator(): void {
    $this->assertNull(Translator::shared());

    $translator = new Translator('es', [dirname(__DIR__) . '/Fixtures/translations']);
    $form = Form::create('Demo')
      ->translator($translator)
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('name');
      });

    new Tui($form);

    $this->assertSame($translator, Translator::shared());
  }

  public function testTranslatorClearedWhenFormHasNone(): void {
    // A translated form activates its translator.
    $translated = Form::create('Demo')
      ->translator(new Translator('es', [dirname(__DIR__) . '/Fixtures/translations']))
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('name');
      });
    new Tui($translated);
    $this->assertInstanceOf(Translator::class, Translator::shared());

    // A later translator-less form clears it, so its language does not leak.
    $plain = Form::create('Demo')
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('name');
      });
    new Tui($plain);
    $this->assertNull(Translator::shared());
  }

  public function testCollect(): void {
    $answers = $this->tui()->collect('{"name":"Acme"}', 'dir', FALSE, '1.0');

    $this->assertSame('Acme', $answers->value('name'));
    // "machine" is derived from "name".
    $this->assertSame('acme', $answers->value('machine'));
  }

  public function testRun(): void {
    // Supplied prompts force the headless path regardless of the TTY.
    $answers = $this->tui()->run('{"name":"Acme"}', '1.0');
    $this->assertSame('Acme', $answers->value('name'));

    // An explicit FALSE forces headless collection from the defaults.
    $answers = $this->tui()->run('', '', '', FALSE);
    $this->assertSame('', $answers->value('name'));
  }

  public function testSchema(): void {
    $this->assertArrayHasKey('prompts', $this->tui()->schema());
  }

  public function testAgentHelp(): void {
    $this->assertStringContainsString('name', $this->tui()->agentHelp());
  }

  public function testEnvPrefix(): void {
    $config = Form::create('Demo')
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('name');
      })
      ->build();

    // No prefix anywhere falls back to the package default.
    $this->assertStringContainsString('TUI_<ID>', (new Tui($config))->agentHelp());
    // A constructor prefix wins.
    $this->assertStringContainsString('ARG_<ID>', (new Tui($config, [], 'ARG_'))->agentHelp());

    $config = Form::create('Demo')
      ->envPrefix('FORM_')
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('name');
      })
      ->build();

    // The form-declared prefix is used unless the constructor overrides it.
    $this->assertStringContainsString('FORM_<ID>', (new Tui($config))->agentHelp());
    $this->assertStringContainsString('ARG_<ID>', (new Tui($config, [], 'ARG_'))->agentHelp());
  }

  public function testValidate(): void {
    $this->assertSame([], $this->tui()->validate(['name' => 'Acme']));
    $this->assertNotSame([], $this->tui()->validate(['bogus' => 'x']));
  }

  public function testAccessors(): void {
    $tui = $this->tui();

    $this->assertSame('Demo', $tui->config()->title);
    $this->assertInstanceOf(Engine::class, $tui->engine());
    $this->assertInstanceOf(HandlerRegistry::class, $tui->registry());
  }

  public function testController(): void {
    $controller = $this->tui()->controller(['color' => FALSE, 'unicode' => TRUE, 'mode' => ThemeInterface::MODE_DARK]);

    $this->assertInstanceOf(PanelController::class, $controller);
    // The engine's resolved answers seed the controller.
    $this->assertSame('', $controller->answers()->value('name'));
  }

  public function testInteractDrivesScriptedTerminal(): void {
    // The Demo hub lists the panel, then Submit and Cancel: Down reaches
    // Submit, Enter activates it. "\033[B" is Down, "\r" is Enter.
    $terminal = new BufferedTerminal(["\033[B", "\r"]);

    $answers = $this->tui()->interact(terminal: $terminal);

    $this->assertInstanceOf(Answers::class, $answers);
    $this->assertStringContainsString('Demo', $terminal->output());
  }

  #[DataProvider('dataProviderResolveTheme')]
  public function testResolveTheme(string $config_theme, string $theme, string $expected): void {
    $tui = $this->themedTui($config_theme);
    $resolved = (new \ReflectionMethod($tui, 'resolveTheme'))->invoke($tui, $theme);

    $this->assertSame($expected, $resolved);
  }

  public static function dataProviderResolveTheme(): \Iterator {
    // The argument wins over the config theme.
    yield 'argument wins over config' => ['ocean', 'reef', 'reef'];
    // An empty argument falls back to the config theme.
    yield 'config theme used' => ['ocean', '', 'ocean'];
    // Empty or the "auto" sentinel selects the default theme; the dark/light
    // mode is a separate option now, not a theme choice.
    yield 'empty is default' => ['', '', 'default'];
    yield 'auto argument is default' => ['', 'auto', 'default'];
    yield 'auto config is default' => ['auto', '', 'default'];
  }

  #[DataProvider('dataProviderResolveThemeOptionsDetectsMode')]
  public function testResolveThemeOptionsDetectsMode(bool $color, ?string $osc, string $expected_mode): void {
    $restore = getenv('COLORFGBG');
    putenv('COLORFGBG');

    try {
      $tui = $this->colouredTui($color);
      $options = (array) (new \ReflectionMethod($tui, 'resolveThemeOptions'))->invoke($tui, $this->terminalReturning($osc));

      $this->assertSame($color, $options['color']);
      $this->assertTrue($options['unicode']);
      $this->assertSame($expected_mode, $options['mode']);
    }
    finally {
      is_string($restore) ? putenv('COLORFGBG=' . $restore) : putenv('COLORFGBG');
    }
  }

  public static function dataProviderResolveThemeOptionsDetectsMode(): \Iterator {
    // With colour on, the mode follows the terminal background.
    yield 'colour on detects light' => [TRUE, "\033]11;rgb:ffff/ffff/ffff\007", 'light'];
    yield 'colour on detects dark' => [TRUE, "\033]11;rgb:0000/0000/0000\007", 'dark'];
    yield 'colour on no reply defaults dark' => [TRUE, NULL, 'dark'];
    // With colour off, the background query is skipped and mode is dark.
    yield 'colour off skips detection' => [FALSE, "\033]11;rgb:ffff/ffff/ffff\007", 'dark'];
  }

  public function testResolveThemeOptionsRespectsConsumerOptions(): void {
    $form = Form::create('Demo')
      ->theme('', ['mode' => 'light', 'color' => FALSE, 'unicode' => FALSE])
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('name');
      });
    $tui = new Tui($form);

    // A consumer's explicit options win over detection.
    $options = (array) (new \ReflectionMethod($tui, 'resolveThemeOptions'))->invoke($tui, $this->terminalReturning("\033]11;rgb:0000/0000/0000\007"));

    $this->assertSame('light', $options['mode']);
    $this->assertFalse($options['color']);
    $this->assertFalse($options['unicode']);
  }

  /**
   * A TUI over a small in-memory form.
   */
  protected function tui(): Tui {
    $form = Form::create('Demo')
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('name')->required();
        $panel->text('machine')->derive(new Derive('{{name}}', 'machine'));
      });

    return new Tui($form, [], 'TEST_');
  }

  /**
   * A TUI whose config declares the given theme.
   */
  protected function themedTui(string $theme): Tui {
    $form = Form::create('Demo')
      ->theme($theme)
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('name');
      });

    return new Tui($form);
  }

  /**
   * A TUI whose config forces colour on or off (and Unicode on).
   */
  protected function colouredTui(bool $color): Tui {
    $form = Form::create('Demo')
      ->color($color)
      ->unicode(TRUE)
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('name');
      });

    return new Tui($form);
  }

  /**
   * A terminal whose background query yields a fixed reply.
   */
  protected function terminalReturning(?string $response): Terminal {
    return new class($response) extends Terminal {

      public function __construct(protected ?string $response) {
        parent::__construct();
      }

      #[\Override]
      public function queryBackground(): ?string {
        return $this->response;
      }

    };
  }

}
