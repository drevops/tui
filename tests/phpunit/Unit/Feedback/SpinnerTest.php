<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Feedback;

use DrevOps\Tui\Feedback\Feedback;
use DrevOps\Tui\Feedback\Spinner;
use DrevOps\Tui\Testing\BufferedTerminal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the indeterminate spinner and its shared feedback base.
 */
#[CoversClass(Spinner::class)]
#[CoversClass(Feedback::class)]
#[Group('tui')]
final class SpinnerTest extends TestCase {

  public function testReturnsTheCallbackResult(): void {
    $terminal = new BufferedTerminal();
    $spinner = new Spinner($terminal, TRUE, TRUE, TRUE, 'Scanning');

    $result = $spinner->run(static fn(): string => 'done');

    $this->assertSame('done', $result);
  }

  public function testActiveRunHidesAndRestoresTheCursor(): void {
    $terminal = new BufferedTerminal();
    $spinner = new Spinner($terminal, TRUE, TRUE, TRUE, 'Scanning');

    $spinner->run(static function (Spinner $s): void {
      $s->tick();
      $s->tick();
    });

    $output = $terminal->output();

    // The cursor is hidden while the spinner runs and shown again at the end.
    $this->assertStringContainsString("\033[?25l", $output);
    $this->assertStringContainsString("\033[?25h", $output);
    // The line is redrawn from column zero and wiped on finish.
    $this->assertStringContainsString("\r", $output);
    $this->assertStringContainsString("\033[2K", $output);
    // The caption travels with the frames.
    $this->assertStringContainsString('Scanning', $output);
  }

  public function testActiveRunCyclesUnicodeFrames(): void {
    $terminal = new BufferedTerminal();
    $spinner = new Spinner($terminal, TRUE, FALSE, TRUE, 'Scanning');

    $spinner->run(static function (Spinner $s): void {
      $s->tick();
    });

    $output = $terminal->output();

    // The first frame is drawn before the work, the second on the tick.
    $this->assertStringContainsString('⠋', $output);
    $this->assertStringContainsString('⠙', $output);
  }

  public function testActiveRunFallsBackToAsciiFrames(): void {
    $terminal = new BufferedTerminal();
    $spinner = new Spinner($terminal, TRUE, FALSE, FALSE, 'Scanning');

    $spinner->run(static function (Spinner $s): void {
      $s->tick();
    });

    $output = $terminal->output();

    $this->assertStringContainsString('|', $output);
    $this->assertStringContainsString('/', $output);
    // No Unicode glyph leaks into the ASCII frame set.
    $this->assertStringNotContainsString('⠋', $output);
  }

  public function testColorStylesTheGlyph(): void {
    $terminal = new BufferedTerminal();
    $spinner = new Spinner($terminal, TRUE, TRUE, TRUE, 'Scanning');

    $spinner->run(static fn() => NULL);

    // Bold cyan wraps the glyph when colour is on.
    $this->assertStringContainsString("\033[1;36m", $terminal->output());
  }

  public function testNoColorLeavesTheGlyphPlain(): void {
    $terminal = new BufferedTerminal();
    $spinner = new Spinner($terminal, TRUE, FALSE, TRUE, 'Scanning');

    $spinner->run(static fn() => NULL);

    $this->assertStringNotContainsString("\033[1;36m", $terminal->output());
  }

  public function testEmptyCaptionDrawsOnlyTheGlyph(): void {
    $terminal = new BufferedTerminal();
    $spinner = new Spinner($terminal, TRUE, FALSE, TRUE, '');

    $spinner->run(static fn() => NULL);

    $output = $terminal->output();

    $this->assertStringContainsString('⠋', $output);
    // No stray separator space follows a captionless glyph.
    $this->assertStringNotContainsString('⠋ ', $output);
  }

  public function testInactiveRunPrintsOnePlainLineAndNoControlSequences(): void {
    $terminal = new BufferedTerminal();
    $spinner = new Spinner($terminal, FALSE, TRUE, TRUE, 'Scanning');

    $result = $spinner->run(static function (Spinner $s): string {
      // Ticks are silent off a TTY.
      $s->tick();

      return 'value';
    });

    $output = $terminal->output();

    $this->assertSame('value', $result);
    $this->assertSame("Scanning\n", $output);
    $this->assertStringNotContainsString("\033", $output);
    $this->assertStringNotContainsString("\r", $output);
  }

  public function testCleansUpWhenTheWorkThrows(): void {
    $terminal = new BufferedTerminal();
    $spinner = new Spinner($terminal, TRUE, TRUE, TRUE, 'Scanning');

    $message = '';

    try {
      $spinner->run(static function (): void {
        throw new \RuntimeException('boom');
      });
    }
    catch (\RuntimeException $exception) {
      $message = $exception->getMessage();
    }

    // The exception propagates - its message reaches here - and the cursor is
    // restored even though the work threw.
    $this->assertSame('boom', $message);
    $this->assertStringContainsString("\033[?25h", $terminal->output());
  }

}
