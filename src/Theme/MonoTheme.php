<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A hue-free theme: contrast from weight, grey levels and reverse video.
 *
 * A monochrome palette selectable by name ("mono"). Accents are bold, matches
 * invert, and values and the border sit on the 256-colour grey ramp - so the
 * chrome reads on any background without relying on colour perception. The
 * semantic red error is inherited on purpose: an error should stand out even
 * here. It overrides only the palette accessors and inherits the default
 * theme's layout, glyphs and dark/light mode.
 *
 * @package DrevOps\Tui\Theme
 */
class MonoTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function accentSgr(): string {
    return $this->isDark ? '1;97' : '1;30';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function valueSgr(): string {
    return $this->isDark ? '38;5;250' : '38;5;240';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function indicatorSgr(): string {
    return '1';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function matchSgr(): string {
    return '7';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function borderSgr(): string {
    return $this->isDark ? '38;5;244' : '38;5;246';
  }

}
