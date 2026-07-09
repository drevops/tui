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
  protected function defineStyles(): array {
    $styles = parent::defineStyles();

    $styles['value'] = match ($this->option('accent', 'cool')) {
      'warm' => '33',
      'mono' => '90',
      default => $styles['value'],
    };

    return $styles;
  }

}
