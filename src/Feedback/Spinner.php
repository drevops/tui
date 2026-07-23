<?php

declare(strict_types=1);

namespace DrevOps\Tui\Feedback;

use DrevOps\Tui\Render\TerminalControl;
use DrevOps\Tui\Theme\Sgr;

/**
 * An indeterminate spinner: a glyph beside a caption, cycled one frame a tick.
 *
 * The work has no known length, so the glyph animates rather than filling: each
 * tick() advances it to the next frame. On finish the line is wiped, since the
 * spinner reports nothing to keep once the work is done.
 *
 * @package DrevOps\Tui\Feedback
 */
final class Spinner extends Feedback {

  /**
   * The Unicode animation frames, one glyph per tick.
   */
  protected const array UNICODE_FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

  /**
   * The ASCII animation frames used when Unicode is off.
   */
  protected const array ASCII_FRAMES = ['|', '/', '-', '\\'];

  /**
   * The current frame index.
   */
  protected int $frame = 0;

  /**
   * Advance the spinner by one animation frame.
   */
  public function tick(): void {
    if (!$this->active) {
      return;
    }

    $frames = $this->frames();
    $this->frame = ($this->frame + 1) % count($frames);
    $this->draw($this->compose($frames[$this->frame]));
  }

  /**
   * {@inheritdoc}
   */
  protected function initialFrame(): string {
    return $this->compose($this->frames()[0]);
  }

  /**
   * {@inheritdoc}
   */
  protected function finish(): void {
    $this->terminal->write("\r" . TerminalControl::eraseLine());
  }

  /**
   * Compose a frame: the styled glyph, then the caption when there is one.
   *
   * @param string $glyph
   *   The spinner glyph.
   *
   * @return string
   *   The composed frame.
   */
  protected function compose(string $glyph): string {
    $indicator = $this->paint($glyph, Sgr::Bold, Sgr::Cyan);

    return $this->caption === '' ? $indicator : $indicator . ' ' . $this->caption;
  }

  /**
   * The frame set for the resolved glyph mode.
   *
   * @return list<string>
   *   The frames.
   */
  protected function frames(): array {
    return $this->unicode ? self::UNICODE_FRAMES : self::ASCII_FRAMES;
  }

}
