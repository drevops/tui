<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * The default theme for dark terminals: bright foregrounds on a dark ground.
 *
 * @package DrevOps\Tui\Theme
 */
class DarkTheme extends AbstractTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function defineStyles(): array {
    return [
      'title' => '1;36',
      'value' => '32',
      'marker' => '1;36',
      'indicator' => '1;33',
      'highlight' => '1;36',
    ] + parent::defineStyles();
  }

}
