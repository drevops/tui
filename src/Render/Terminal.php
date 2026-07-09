<?php

declare(strict_types=1);

namespace DrevOps\Tui\Render;

use Symfony\Component\Console\Terminal as ConsoleTerminal;

/**
 * Thin terminal I/O: raw mode, alternate screen, mouse, render and restore.
 *
 * The raw-mode toggling and input reading touch the real TTY and are excluded
 * from coverage; the output writing is stream-injectable and testable.
 *
 * @package DrevOps\Tui\Render
 */
class Terminal {

  /**
   * The output stream.
   *
   * @var resource
   */
  protected $output;

  /**
   * The input stream.
   *
   * @var resource
   */
  protected $input;

  /**
   * Construct a terminal.
   *
   * @param mixed $output
   *   The output stream (defaults to STDOUT).
   * @param mixed $input
   *   The input stream (defaults to STDIN).
   */
  public function __construct(mixed $output = NULL, mixed $input = NULL) {
    $this->output = is_resource($output) ? $output : STDOUT;
    $this->input = is_resource($input) ? $input : STDIN;
  }

  /**
   * Write text to the output.
   *
   * @param string $text
   *   The text.
   */
  public function write(string $text): void {
    fwrite($this->output, $text);
  }

  /**
   * Clear the screen and write a frame.
   *
   * @param string $frame
   *   The frame.
   */
  public function render(string $frame): void {
    $this->write(TerminalControl::clear() . $frame);
  }

  /**
   * Clear the screen.
   */
  public function clear(): void {
    $this->write(TerminalControl::clear());
  }

  /**
   * Enter the full-screen raw-input mode.
   */
  public function setup(): void {
    // @codeCoverageIgnoreStart
    $this->stty('-echo -icanon');
    $this->write(TerminalControl::altScreenOn() . TerminalControl::hideCursor() . TerminalControl::mouseOn());
    // @codeCoverageIgnoreEnd
  }

  /**
   * Restore the terminal to its normal mode.
   */
  public function restore(): void {
    // @codeCoverageIgnoreStart
    $this->write(TerminalControl::restore());
    $this->stty('sane');
    // @codeCoverageIgnoreEnd
  }

  /**
   * Read raw bytes from the input.
   *
   * @param int $bytes
   *   The maximum number of bytes to read.
   *
   * @return string
   *   The bytes read.
   */
  public function read(int $bytes = 32): string {
    // @codeCoverageIgnoreStart
    $data = fread($this->input, max(1, $bytes));

    return $data === FALSE ? '' : $data;
    // @codeCoverageIgnoreEnd
  }

  /**
   * The terminal height in rows.
   *
   * @return int
   *   The number of rows available for rendering.
   */
  public function height(): int {
    return (new ConsoleTerminal())->getHeight();
  }

  /**
   * Query the terminal background colour via OSC 11.
   *
   * Writes the query and reads the reply under a brief, bounded raw-mode
   * timeout, so a terminal that does not answer cannot block. Leaves the
   * terminal on the main screen.
   *
   * @return string|null
   *   The raw reply bytes, or NULL when the input is not a TTY or no reply
   *   arrived.
   */
  public function queryBackground(): ?string {
    // @codeCoverageIgnoreStart
    if (!stream_isatty($this->input)) {
      return NULL;
    }

    $this->stty('-echo -icanon min 0 time 1');
    $response = '';

    try {
      $this->write(TerminalControl::queryBackground());
      fflush($this->output);

      for ($read = 0; $read < 3; $read++) {
        $chunk = fread($this->input, 64);
        if (!is_string($chunk)) {
          continue;
        }
        if ($chunk === '') {
          continue;
        }

        $response .= $chunk;

        if (str_contains($response, "\007") || str_contains($response, Ansi::ESC . '\\')) {
          break;
        }
      }
    }
    finally {
      $this->stty('sane');
    }

    return $response === '' ? NULL : $response;
    // @codeCoverageIgnoreEnd
  }

  /**
   * Run stty with the given arguments.
   *
   * @param string $args
   *   The stty arguments.
   */
  protected function stty(string $args): void {
    // @codeCoverageIgnoreStart
    exec('stty ' . $args . ' 2>/dev/null');
    // @codeCoverageIgnoreEnd
  }

}
