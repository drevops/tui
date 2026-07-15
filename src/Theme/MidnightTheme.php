<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A cool, vivid theme: violet accents, green values, pink highlights.
 *
 * A curated 256-colour palette selectable by name ("midnight"). It declares its
 * colours by overriding the appearance atoms directly and inherits the default
 * theme's layout, glyphs and dark/light mode, so it renders across every widget
 * and degrades to plain text when colour is off.
 *
 * @package DrevOps\Tui\Theme
 */
class MidnightTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function title(string $text): string {
    return $this->paint($this->isDark ? '1;38;5;141' : '1;38;5;54', $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function value(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize($this->isDark ? '38;5;114' : '38;5;28', $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function indicator(string $text): string {
    return $this->paint($this->isDark ? '38;5;212' : '38;5;162', $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function highlight(string $text): string {
    return $this->paint($this->isDark ? '1;38;5;141' : '1;38;5;54', $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function highlightMatch(string $text): string {
    return $this->paint($this->isDark ? '38;5;212' : '38;5;162', $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function border(string $text): string {
    return $this->paint($this->isDark ? '38;5;97' : '38;5;61', $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function marker(bool $selected): string {
    return $selected ? $this->paint($this->isDark ? '1;38;5;141' : '1;38;5;54', $this->unicode ? '❯' : '>') : ' ';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function radio(bool $on): string {
    return $on ? $this->paint($this->isDark ? '1;38;5;141' : '1;38;5;54', $this->unicode ? '●' : '(*)') : ($this->unicode ? '○' : '( )');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function caret(): string {
    return $this->paint($this->isDark ? '1;38;5;141' : '1;38;5;54', $this->unicode ? '█' : '|');
  }

}
