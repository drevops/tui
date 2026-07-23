<?php

declare(strict_types=1);

namespace DrevOps\Tui\Feedback;

use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Theme\Sgr;

/**
 * A determinate progress bar: a filling bar with a step count and a label.
 *
 * The work has a known length, so each advance() fills the bar by one step of
 * the total and may update the trailing label. On finish the bar settles on its
 * own line, so the completed state persists after the work returns.
 *
 * @package DrevOps\Tui\Feedback
 */
final class ProgressBar extends Feedback {

  /**
   * The bar width in cells.
   */
  protected const int WIDTH = 24;

  /**
   * The total number of steps; never negative.
   */
  protected int $total;

  /**
   * The number of completed steps.
   */
  protected int $current = 0;

  /**
   * The label shown after the bar.
   */
  protected string $label = '';

  /**
   * Construct a progress bar.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal the line is drawn on.
   * @param bool $active
   *   Whether to draw control sequences (TRUE) or stay plain (FALSE).
   * @param bool $color
   *   Whether to colour the filled portion.
   * @param bool $unicode
   *   Whether to use Unicode glyphs; FALSE falls back to ASCII.
   * @param string $caption
   *   The caption shown before the bar.
   * @param int $total
   *   The number of steps the bar fills through; negatives clamp to zero.
   */
  public function __construct(Terminal $terminal, bool $active, bool $color, bool $unicode, string $caption, int $total) {
    parent::__construct($terminal, $active, $color, $unicode, $caption);
    $this->total = max(0, $total);
  }

  /**
   * Advance the bar by one step, optionally replacing the label.
   *
   * @param string $label
   *   The new label, or empty to keep the current one.
   */
  public function advance(string $label = ''): void {
    $this->current = min($this->current + 1, $this->total);

    if ($label !== '') {
      $this->label = $label;
    }

    if ($this->active) {
      $this->draw($this->compose());
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function initialFrame(): string {
    return $this->compose();
  }

  /**
   * {@inheritdoc}
   */
  protected function finish(): void {
    $this->draw($this->compose());
    $this->terminal->write("\n");
  }

  /**
   * Compose the bar: caption, filled/empty cells, the step count and label.
   *
   * @return string
   *   The composed bar.
   */
  protected function compose(): string {
    [$fill, $track] = $this->glyphs();

    $ratio = $this->total > 0 ? $this->current / $this->total : 1.0;
    $filled = (int) round($ratio * self::WIDTH);

    $bar = ($filled > 0 ? $this->paint(str_repeat($fill, $filled), Sgr::Green) : '') . str_repeat($track, self::WIDTH - $filled);
    $line = ($this->caption === '' ? '' : $this->caption . ' ') . '[' . $bar . '] ' . $this->current . '/' . $this->total;

    return $this->label === '' ? $line : $line . ' ' . $this->label;
  }

  /**
   * The [fill, track] glyphs for the resolved glyph mode.
   *
   * @return array{string,string}
   *   The filled-cell and empty-cell glyphs.
   */
  protected function glyphs(): array {
    return $this->unicode ? ['█', '░'] : ['#', '-'];
  }

}
