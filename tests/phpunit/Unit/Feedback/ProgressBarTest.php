<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Feedback;

use DrevOps\Tui\Feedback\Feedback;
use DrevOps\Tui\Feedback\ProgressBar;
use DrevOps\Tui\Testing\BufferedTerminal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the determinate progress bar and its shared feedback base.
 */
#[CoversClass(ProgressBar::class)]
#[CoversClass(Feedback::class)]
#[Group('tui')]
final class ProgressBarTest extends TestCase {

  public function testReturnsTheCallbackResult(): void {
    $terminal = new BufferedTerminal();
    $bar = new ProgressBar($terminal, TRUE, TRUE, TRUE, 'Packing', 3);

    $result = $bar->run(static fn(): array => ['ok']);

    $this->assertSame(['ok'], $result);
  }

  public function testActiveRunFillsAndSettlesOnItsOwnLine(): void {
    $terminal = new BufferedTerminal();
    $bar = new ProgressBar($terminal, TRUE, TRUE, TRUE, 'Packing', 2);

    $bar->run(static function (ProgressBar $b): void {
      $b->advance('apples');
      $b->advance('pears');
    });

    $output = $terminal->output();

    $this->assertStringContainsString("\033[?25l", $output);
    $this->assertStringContainsString("\033[?25h", $output);
    $this->assertStringContainsString('Packing', $output);
    // The step count reaches the total and the last label shows.
    $this->assertStringContainsString('2/2', $output);
    $this->assertStringContainsString('pears', $output);
    // The completed bar settles on its own line.
    $this->assertStringContainsString("\n", $output);
    // The filled cell uses the Unicode block.
    $this->assertStringContainsString('█', $output);
  }

  public function testAsciiFallbackUsesHashAndDash(): void {
    $terminal = new BufferedTerminal();
    $bar = new ProgressBar($terminal, TRUE, FALSE, FALSE, 'Packing', 4);

    $bar->run(static function (ProgressBar $b): void {
      $b->advance();
    });

    $output = $terminal->output();

    $this->assertStringContainsString('#', $output);
    $this->assertStringContainsString('-', $output);
    $this->assertStringNotContainsString('█', $output);
  }

  public function testColorStylesTheFilledPortion(): void {
    $terminal = new BufferedTerminal();
    $bar = new ProgressBar($terminal, TRUE, TRUE, TRUE, 'Packing', 2);

    $bar->run(static function (ProgressBar $b): void {
      $b->advance();
    });

    // Green wraps the filled cells when colour is on.
    $this->assertStringContainsString("\033[32m", $terminal->output());
  }

  public function testNoColorLeavesTheBarPlain(): void {
    $terminal = new BufferedTerminal();
    $bar = new ProgressBar($terminal, TRUE, FALSE, TRUE, 'Packing', 2);

    $bar->run(static function (ProgressBar $b): void {
      $b->advance();
    });

    $this->assertStringNotContainsString("\033[32m", $terminal->output());
  }

  public function testAdvanceBeyondTotalClampsTheCount(): void {
    $terminal = new BufferedTerminal();
    $bar = new ProgressBar($terminal, TRUE, TRUE, TRUE, 'Packing', 1);

    $bar->run(static function (ProgressBar $b): void {
      $b->advance();
      $b->advance();
      $b->advance();
    });

    $output = $terminal->output();

    $this->assertStringContainsString('1/1', $output);
    $this->assertStringNotContainsString('2/1', $output);
  }

  public function testZeroTotalRendersFullBar(): void {
    $terminal = new BufferedTerminal();
    $bar = new ProgressBar($terminal, TRUE, TRUE, TRUE, 'Packing', 0);

    $bar->run(static fn(): null => NULL);

    $output = $terminal->output();

    $this->assertStringContainsString('0/0', $output);
    // A zero-step bar is already complete, so it fills.
    $this->assertStringContainsString('█', $output);
  }

  public function testNegativeTotalClampsToZero(): void {
    $terminal = new BufferedTerminal();
    $bar = new ProgressBar($terminal, TRUE, TRUE, TRUE, 'Packing', -5);

    $bar->run(static fn(): null => NULL);

    $this->assertStringContainsString('0/0', $terminal->output());
  }

  public function testKeepsThePreviousLabelWhenAdvancedWithoutOne(): void {
    $terminal = new BufferedTerminal();
    $bar = new ProgressBar($terminal, TRUE, FALSE, TRUE, 'Packing', 3);

    $bar->run(static function (ProgressBar $b): void {
      $b->advance('plums');
      $b->advance();
    });

    // The label set on the first advance survives an unlabelled advance.
    $this->assertStringContainsString('plums', $terminal->output());
  }

  public function testInactiveRunPrintsOnePlainLineAndNoControlSequences(): void {
    $terminal = new BufferedTerminal();
    $bar = new ProgressBar($terminal, FALSE, TRUE, TRUE, 'Packing', 2);

    $result = $bar->run(static function (ProgressBar $b): string {
      // Advances are silent off a TTY.
      $b->advance('apples');

      return 'value';
    });

    $output = $terminal->output();

    $this->assertSame('value', $result);
    $this->assertSame("Packing\n", $output);
    $this->assertStringNotContainsString("\033", $output);
  }

}
