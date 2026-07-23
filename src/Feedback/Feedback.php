<?php

declare(strict_types=1);

namespace DrevOps\Tui\Feedback;

use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Render\TerminalControl;
use DrevOps\Tui\Theme\Sgr;

/**
 * Shared machinery for the transient one-line feedback indicators.
 *
 * A feedback wraps a callback and shows progress on a single rewritten line
 * while it runs, passing the callback's return value straight back. On an
 * interactive terminal it hides the cursor, redraws the line in place on each
 * update and restores the cursor when the callback returns - even if it throws.
 * Off a TTY it emits no control sequences at all: it prints the caption once as
 * a plain line and every update renders nothing. The callback receives the
 * feedback so it can drive the updates as it works.
 *
 * @package DrevOps\Tui\Feedback
 */
abstract class Feedback {

  /**
   * Construct a feedback.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal the line is drawn on.
   * @param bool $active
   *   Whether to draw control sequences (TRUE) or stay plain (FALSE).
   * @param bool $color
   *   Whether to colour the indicator.
   * @param bool $unicode
   *   Whether to use Unicode glyphs; FALSE falls back to ASCII.
   * @param string $caption
   *   The caption shown beside the indicator.
   */
  public function __construct(
    protected Terminal $terminal,
    protected bool $active,
    protected bool $color,
    protected bool $unicode,
    protected string $caption,
  ) {
  }

  /**
   * Run the callback, showing feedback while it works.
   *
   * @param callable(static): TReturn $work
   *   The work to run; it receives this feedback so it can drive the updates.
   *
   * @return TReturn
   *   The callback's return value.
   *
   * @template TReturn
   */
  public function run(callable $work): mixed {
    if (!$this->active) {
      // Off a TTY the indicator is invisible chrome, so a single plain caption
      // line is the whole trace - no cursor or colour sequences to corrupt a
      // redirected log. Flush it so a block-buffered stream shows the caption
      // before the work runs, not after.
      $this->terminal->write($this->caption . "\n");
      $this->terminal->flush();

      return $work($this);
    }

    $this->terminal->write(TerminalControl::hideCursor());
    $this->draw($this->initialFrame());

    try {
      return $work($this);
    }
    finally {
      // A throw in the work must still leave a clean line and a visible cursor.
      $this->finish();
      $this->terminal->write(TerminalControl::showCursor());
      $this->terminal->flush();
    }
  }

  /**
   * Redraw the line in place with a new frame.
   *
   * @param string $frame
   *   The frame text.
   */
  protected function draw(string $frame): void {
    // Return to column zero, paint the frame, then erase any tail a shorter
    // frame would leave behind.
    $this->terminal->write("\r" . $frame . TerminalControl::eraseToLineEnd());
    $this->terminal->flush();
  }

  /**
   * Style text with an SGR palette when colour is on, otherwise leave it plain.
   *
   * @param string $text
   *   The text.
   * @param \DrevOps\Tui\Theme\Sgr ...$parts
   *   The palette parts.
   *
   * @return string
   *   The styled or plain text.
   */
  protected function paint(string $text, Sgr ...$parts): string {
    return $this->color ? Ansi::style($text, Sgr::of(...$parts)) : $text;
  }

  /**
   * The first frame drawn before the callback starts.
   *
   * @return string
   *   The frame text.
   */
  abstract protected function initialFrame(): string;

  /**
   * Settle the line once the callback returns.
   */
  abstract protected function finish(): void;

}
