<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit;

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Render\Terminal;
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

  #[DataProvider('dataProviderResolveTheme')]
  public function testResolveTheme(string $config_theme, string $theme, bool $color, ?string $osc, string $expected): void {
    $restore = getenv('COLORFGBG');
    putenv('COLORFGBG');

    try {
      $tui = $this->themedTui($config_theme);
      $resolved = (new \ReflectionMethod($tui, 'resolveTheme'))->invoke($tui, $theme, $color, $this->terminalReturning($osc));

      $this->assertSame($expected, $resolved);
    }
    finally {
      is_string($restore) ? putenv('COLORFGBG=' . $restore) : putenv('COLORFGBG');
    }
  }

  public static function dataProviderResolveTheme(): \Iterator {
    // The argument wins over the config, with no detection.
    yield 'argument wins over config' => ['ocean', 'light', TRUE, 'rgb:0000/0000/0000', 'light'];
    // An empty argument falls back to the config theme.
    yield 'config theme used' => ['ocean', '', TRUE, 'rgb:0000/0000/0000', 'ocean'];
    // Colour off skips detection and defaults to dark.
    yield 'colour off defaults dark' => ['', '', FALSE, "\033]11;rgb:ffff/ffff/ffff\007", 'dark'];
    // An empty theme with colour on detects from the background.
    yield 'empty detects light' => ['', '', TRUE, "\033]11;rgb:ffff/ffff/ffff\007", 'light'];
    yield 'empty detects dark' => ['', '', TRUE, "\033]11;rgb:0000/0000/0000\007", 'dark'];
    // The explicit "auto" sentinel triggers detection over a config theme.
    yield 'auto sentinel detects' => ['dark', 'auto', TRUE, "\033]11;rgb:ffff/ffff/ffff\007", 'light'];
    // No terminal reply falls back to dark.
    yield 'no reply defaults dark' => ['', '', TRUE, NULL, 'dark'];
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
