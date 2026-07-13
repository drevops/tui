<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Testing;

use DrevOps\Tui\Testing\BufferedTerminal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the scripted-input, output-capturing terminal.
 */
#[CoversClass(BufferedTerminal::class)]
#[Group('testing')]
final class BufferedTerminalTest extends TestCase {

  public function testReadDequeuesOnePerCallThenEof(): void {
    $terminal = new BufferedTerminal(["\r", 'ab']);

    $this->assertSame("\r", $terminal->read());
    $this->assertSame('ab', $terminal->read());
    // Exhausted: every further read reports EOF.
    $this->assertSame('', $terminal->read());
    $this->assertSame('', $terminal->read());
  }

  public function testHeightIsFixed(): void {
    $this->assertSame(24, (new BufferedTerminal())->height());
    $this->assertSame(30, (new BufferedTerminal([], 30))->height());
  }

  public function testSetupAndRestoreProduceNoOutput(): void {
    $terminal = new BufferedTerminal();

    $terminal->setup();
    $terminal->restore();

    $this->assertSame('', $terminal->output());
  }

  public function testCapturesRenderedOutput(): void {
    $terminal = new BufferedTerminal();

    $terminal->render('FRAME');

    $output = $terminal->output();
    $this->assertStringContainsString('FRAME', $output);
    // render() clears the screen before writing the frame.
    $this->assertStringContainsString("\033[2J", $output);
    // The capture is repeatable.
    $this->assertSame($output, $terminal->output());
  }

}
