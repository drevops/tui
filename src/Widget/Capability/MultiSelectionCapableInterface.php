<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget\Capability;

/**
 * A widget that collects a toggleable set of option values as a list.
 *
 * {@see MultiSelectionCapableTrait} carries the default implementation.
 *
 * @package DrevOps\Tui\Widget\Capability
 */
interface MultiSelectionCapableInterface {

  /**
   * Toggle the highlighted option when it is selectable.
   */
  public function toggleCurrent(): void;

  /**
   * Select or deselect all selectable visible options.
   *
   * @param bool $selected
   *   TRUE to select, FALSE to deselect.
   */
  public function setAllVisible(bool $selected): void;

  /**
   * The selectable option values, in display order.
   *
   * @return list<string>
   *   The selectable option values.
   */
  public function selectableValues(): array;

}
