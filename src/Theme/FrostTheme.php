<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A calm, arctic theme: frost-blue accents, sage values, sand highlights.
 *
 * A curated 256-colour palette selectable by name ("frost"). It overrides only
 * the palette accessors and inherits the default theme's layout, glyphs and
 * dark/light mode, so it renders across every widget and degrades to plain text
 * when colour is off.
 *
 * @package DrevOps\Tui\Theme
 */
class FrostTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function accentSgr(): string {
    return $this->isDark ? '1;38;5;117' : '1;38;5;25';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function valueSgr(): string {
    return $this->isDark ? '38;5;150' : '38;5;65';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function indicatorSgr(): string {
    return $this->isDark ? '38;5;222' : '38;5;136';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function matchSgr(): string {
    return $this->isDark ? '38;5;222' : '38;5;136';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function borderSgr(): string {
    return $this->isDark ? '38;5;109' : '38;5;66';
  }

}
