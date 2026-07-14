<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

/**
 * A widget whose cursor picks exactly one option as its value.
 *
 * {@see SingleChoiceTrait} carries the default implementation.
 *
 * @package DrevOps\Tui\Widget
 */
interface SelectionCapableInterface {

  /**
   * Whether the highlighted visible row is a selectable option.
   *
   * @return bool
   *   TRUE when the cursor rests on a selectable option.
   */
  public function currentSelectable(): bool;

}
