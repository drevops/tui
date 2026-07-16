<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget\Capability;

use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A widget that presents a list of option rows.
 *
 * The rows are {@see Option} objects, so headings, separators and disabled
 * options travel with the selectable ones; {@see OptionsCapableTrait} carries
 * the default implementation.
 *
 * @package DrevOps\Tui\Widget\Capability
 */
interface OptionsCapableInterface {

  /**
   * The rows the widget currently shows.
   *
   * @return list<\DrevOps\Tui\Model\Option>
   *   The visible rows.
   */
  public function visible(): array;

  /**
   * Render one option row.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Model\Option $option
   *   The option row.
   * @param bool $current
   *   Whether the row holds the cursor.
   *
   * @return string
   *   The rendered row.
   */
  public function renderOptionRow(ThemeInterface $theme, Option $option, bool $current): string;

}
