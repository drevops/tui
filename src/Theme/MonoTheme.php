<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A hue-free theme: contrast from weight, grey levels and reverse video.
 *
 * A monochrome palette selectable by name ("mono"). Accents are bold, matches
 * invert, and values and the border sit on the 256-colour grey ramp - so the
 * chrome reads on any background without relying on colour perception. The
 * semantic red error is inherited on purpose. It declares its colours by
 * overriding the appearance atoms directly and inherits the default theme's
 * layout, glyphs and dark/light mode.
 *
 * @package DrevOps\Tui\Theme
 */
class MonoTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function title(string $text): string {
    return $this->paint($this->isDark ? '1;97' : '1;30', $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function value(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize($this->isDark ? '38;5;250' : '38;5;240', $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function indicator(string $text): string {
    return $this->paint('1', $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function highlight(string $text): string {
    return $this->paint($this->isDark ? '1;97' : '1;30', $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function highlightMatch(string $text): string {
    return $this->paint('7', $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function border(string $text): string {
    return $this->paint($this->isDark ? '38;5;244' : '38;5;246', $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function marker(bool $selected): string {
    return $selected ? $this->paint($this->isDark ? '1;97' : '1;30', $this->unicode ? '❯' : '>') : ' ';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function radio(bool $on): string {
    return $on ? $this->paint($this->isDark ? '1;97' : '1;30', $this->unicode ? '●' : '(*)') : ($this->unicode ? '○' : '( )');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function caret(): string {
    return $this->paint($this->isDark ? '1;97' : '1;30', $this->unicode ? '█' : '|');
  }

}
