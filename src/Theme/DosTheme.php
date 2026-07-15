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
 * declares its colours by overriding the appearance atoms directly and defaults
 * to a double-line border, and inherits the default theme's layout and glyphs.
 *
 * @package DrevOps\Tui\Theme
 */
class DosTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function title(string $text): string {
    return $this->paint($this->isDark ? '1;97' : '34', $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function value(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize($this->isDark ? '96' : '36', $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function indicator(string $text): string {
    return $this->paint($this->isDark ? '93' : '33', $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function highlight(string $text): string {
    return $this->paint($this->isDark ? '1;97' : '34', $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function highlightMatch(string $text): string {
    return $this->paint($this->isDark ? '93' : '33', $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function border(string $text): string {
    return $this->paint($this->isDark ? '97' : '34', $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function marker(bool $selected): string {
    return $selected ? $this->paint($this->isDark ? '1;97' : '34', $this->unicode ? '❯' : '>') : ' ';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function radio(bool $on): string {
    return $on ? $this->paint($this->isDark ? '1;97' : '34', $this->unicode ? '●' : '(*)') : ($this->unicode ? '○' : '( )');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function caret(): string {
    return $this->paint($this->isDark ? '1;97' : '34', $this->unicode ? '█' : '|');
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

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function background(): ?string {
    // Paint the classic DOS blue screen on a dark terminal; a light terminal
    // keeps its own surface, where the darker CGA tones stay legible.
    return $this->isDark ? '#0000aa' : NULL;
  }

}
