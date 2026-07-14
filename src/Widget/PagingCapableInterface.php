<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

/**
 * A widget that windows a long list to a page that follows the cursor.
 *
 * {@see PageableTrait} carries the default implementation.
 *
 * @package DrevOps\Tui\Widget
 */
interface PagingCapableInterface {

  /**
   * The number of rows shown at once before the list pages.
   *
   * @return int
   *   The effective page size.
   */
  public function pageSize(): int;

}
