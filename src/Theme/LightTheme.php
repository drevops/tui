<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A theme for light terminals: the dark theme's scheme in darker shades.
 *
 * Mirrors the dark theme role for role, with foregrounds dark enough to read
 * on a light background: values stay green as in the dark theme, while the
 * cyan and yellow accents - which wash out on white - become their darker
 * blue and magenta analogs.
 *
 * @package DrevOps\Tui\Theme
 */
class LightTheme extends AbstractTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function defineStyles(): array {
    return [
      'title' => '1;34',
      'value' => '32',
      'marker' => '1;34',
      'indicator' => '35',
      'highlight' => '1;34',
    ] + parent::defineStyles();
  }

}
