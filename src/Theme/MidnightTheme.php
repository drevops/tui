<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A cool, vivid theme: violet accents, green values, pink highlights.
 *
 * A curated 256-colour palette selectable by name ("midnight"). It overrides
 * only the palette accessors and inherits the default theme's layout, glyphs
 * and dark/light mode, so it renders across every widget and degrades to plain
 * text when colour is off.
 *
 * @package DrevOps\Tui\Theme
 */
class MidnightTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function accentSgr(): string {
    return $this->isDark ? '1;38;5;141' : '1;38;5;54';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function valueSgr(): string {
    return $this->isDark ? '38;5;114' : '38;5;28';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function indicatorSgr(): string {
    return $this->isDark ? '38;5;212' : '38;5;162';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function matchSgr(): string {
    return $this->isDark ? '38;5;212' : '38;5;162';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function borderSgr(): string {
    return $this->isDark ? '38;5;97' : '38;5;61';
  }

}
