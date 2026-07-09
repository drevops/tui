<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A theme for light terminals: darker, higher-contrast foregrounds.
 *
 * Bright cyan and yellow wash out on a light background, so the palette leans
 * on blue and magenta instead.
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
      'value' => '34',
      'marker' => '1;34',
      'indicator' => '35',
      'highlight' => '1;34',
    ] + parent::defineStyles();
  }

}
