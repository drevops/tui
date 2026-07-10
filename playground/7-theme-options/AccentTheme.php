<?php

declare(strict_types=1);

namespace Playground\ThemeOptions;

use DrevOps\Tui\Theme\DefaultTheme;

/**
 * The default theme plus one theme-invented option: a value "accent" colour.
 *
 * The whole recipe for a custom option: declare it in optionSchema() - so an
 * unknown key or a bad value still throws - and read it with option(). A
 * consumer sets it with the same plain-string options array as the built-ins
 * (`['accent' => 'warm']`); no enum, no separate options class, no import.
 */
class AccentTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function optionSchema(): array {
    return ['accent' => ['cool', 'warm', 'mono']] + parent::optionSchema();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function value(string $text, bool $selected = FALSE): string {
    return match ($this->option('accent', 'cool')) {
      'warm' => $this->paint($this->emphasize('33', $selected), $text),
      'mono' => $this->paint($this->emphasize('90', $selected), $text),
      default => parent::value($text, $selected),
    };
  }

}
