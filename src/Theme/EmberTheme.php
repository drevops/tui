<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A warm, retro theme: burnt-orange accents, olive values, gold highlights.
 *
 * A curated 256-colour palette selectable by name ("ember"). It overrides only
 * the palette accessors and inherits the default theme's layout, glyphs and
 * dark/light mode, so it renders across every widget and degrades to plain text
 * when colour is off.
 *
 * @package DrevOps\Tui\Theme
 */
class EmberTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function accentSgr(): string {
    return $this->isDark ? '1;38;5;208' : '1;38;5;166';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function valueSgr(): string {
    return $this->isDark ? '38;5;142' : '38;5;100';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function indicatorSgr(): string {
    return $this->isDark ? '38;5;214' : '38;5;172';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function matchSgr(): string {
    return $this->isDark ? '38;5;214' : '38;5;172';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function borderSgr(): string {
    return $this->isDark ? '38;5;130' : '38;5;94';
  }

}
