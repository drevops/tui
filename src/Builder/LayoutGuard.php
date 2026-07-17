<?php

declare(strict_types=1);

namespace DrevOps\Tui\Builder;

use DrevOps\Tui\Model\FormException;

/**
 * Validates a panel-grid layout declaration at build time.
 *
 * A layout names, per visual row, how many panels sit side by side; the
 * declaration is only usable when every row is at least one column wide and
 * the slots exactly cover the panels they arrange. Shared by the form and
 * panel builders so a mistake fails loudly when the form is declared, never
 * mid-session.
 *
 * @package DrevOps\Tui\Builder
 */
final class LayoutGuard {

  /**
   * Assert a layout declaration matches the panels it arranges.
   *
   * @param list<int> $layout
   *   The layout rows (empty declares no grid and always passes).
   * @param int $panel_count
   *   The number of panels the layout must cover.
   * @param string $owner
   *   The declaring panel or form id, for the error message.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When a row is below one column or the slots do not match the panels.
   */
  public static function assert(array $layout, int $panel_count, string $owner): void {
    if ($layout === []) {
      return;
    }

    foreach ($layout as $columns) {
      if ($columns < 1) {
        throw new FormException(sprintf('Every layout row of "%s" must hold at least one panel.', $owner));
      }
    }

    if (array_sum($layout) !== $panel_count) {
      throw new FormException(sprintf('The layout of "%s" declares %d slot(s) for %d panel(s).', $owner, array_sum($layout), $panel_count));
    }
  }

}
