<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A retro MS-DOS theme: the bright 16-colour CGA palette in a bordered window.
 *
 * The look of EDIT.COM, QBasic and Norton Commander - bright white headings,
 * cyan values and yellow highlights inside a double-line box, period-correct in
 * the classic 16-colour SGR set rather than 256-colour. It is built for a blue
 * terminal background (its previews are shown on the DOS blue); on a light
 * terminal it falls back to the darker CGA tones so it stays legible. It
 * overrides the five palette accessors and defaults to a double-line border,
 * and inherits the default theme's layout and glyphs.
 *
 * @package DrevOps\Tui\Theme
 */
class DosTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function accentSgr(): string {
    return $this->isDark ? '1;97' : '34';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function valueSgr(): string {
    return $this->isDark ? '96' : '36';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function indicatorSgr(): string {
    return $this->isDark ? '93' : '33';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function matchSgr(): string {
    return $this->isDark ? '93' : '33';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function borderSgr(): string {
    return $this->isDark ? '97' : '34';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function borderStyle(): Border {
    // The MS-DOS look is a bordered window (EDIT.COM / Norton Commander), so
    // default to a double-line box when the form declares no border of its own.
    if (!isset($this->options['border'])) {
      return Border::Double;
    }

    return parent::borderStyle();
  }

}
