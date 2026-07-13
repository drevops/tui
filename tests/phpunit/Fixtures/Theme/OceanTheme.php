<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Fixtures\Theme;

use DrevOps\Tui\Theme\DefaultTheme;

/**
 * Test fixture: a custom theme selectable by class through ThemeManager.
 *
 * Overrides a single atom so it is visibly distinct from the default, while
 * inheriting the (width, options) constructor so it works with the standard
 * theme factory.
 *
 * @package DrevOps\Tui\Tests\Fixtures\Theme
 */
class OceanTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function title(string $text): string {
    return $this->paint('1;96', $text);
  }

}
