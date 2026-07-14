<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

/**
 * A widget whose visible rows narrow under a typed query.
 *
 * The match strategy (substring, fuzzy, per-directory) is the widget's own;
 * {@see ChoiceFilterTrait} carries the default implementation for the choice
 * widgets.
 *
 * @package DrevOps\Tui\Widget
 */
interface FilterCapableInterface {

  /**
   * The current type-to-filter text.
   *
   * @return string
   *   The live query, empty when not filtering.
   */
  public function filter(): string;

  /**
   * Land the cursor on the first match and reset paging on a query change.
   */
  public function resetFilterCursor(): void;

}
