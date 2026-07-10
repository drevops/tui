<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Shared option-list behaviour for the choice widgets.
 *
 * Holds the ordered option rows and centralizes the two things every choice
 * widget must agree on: the cursor only ever rests on a selectable row (so
 * separators, headings and disabled options are skipped), and those
 * non-selectable rows render as visual-only structure.
 *
 * @package DrevOps\Tui\Widget
 */
trait ChoiceList {

  /**
   * The option rows in display order.
   *
   * @var list<\DrevOps\Tui\Config\Option>
   */
  protected array $options = [];

  /**
   * Normalize and store the option rows.
   *
   * @param array<int|string,\DrevOps\Tui\Config\Option|string> $options
   *   A list of options or the value => label shorthand map.
   */
  protected function initOptions(array $options): void {
    $this->options = Option::list($options);
  }

  /**
   * The index of the first selectable row, or 0 when none is selectable.
   *
   * @param list<\DrevOps\Tui\Config\Option> $rows
   *   The rows to scan.
   *
   * @return int
   *   The first selectable index.
   */
  protected function firstSelectable(array $rows): int {
    foreach ($rows as $index => $row) {
      if ($row->selectable()) {
        return $index;
      }
    }

    return 0;
  }

  /**
   * The cursor for a default value: its selectable row, else the first one.
   *
   * @param list<\DrevOps\Tui\Config\Option> $rows
   *   The rows to scan.
   * @param string $default
   *   The default value to land on.
   *
   * @return int
   *   The resolved cursor index.
   */
  protected function cursorForDefault(array $rows, string $default): int {
    foreach ($rows as $index => $row) {
      if ($row->selectable() && $row->value === $default) {
        return $index;
      }
    }

    return $this->firstSelectable($rows);
  }

  /**
   * Step the cursor to the next selectable row, skipping non-selectable rows.
   *
   * @param list<\DrevOps\Tui\Config\Option> $rows
   *   The rows to move over.
   * @param int $from
   *   The current cursor index.
   * @param int $dir
   *   The direction: +1 down, -1 up.
   *
   * @return int
   *   The next selectable index, or $from when none lies in that direction.
   */
  protected function stepCursor(array $rows, int $from, int $dir): int {
    $index = $from + $dir;

    while ($index >= 0 && $index < count($rows)) {
      if ($rows[$index]->selectable()) {
        return $index;
      }

      $index += $dir;
    }

    return $from;
  }

  /**
   * Render a group heading row.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Config\Option $option
   *   The heading row.
   *
   * @return string
   *   The rendered row.
   */
  protected function renderHeadingRow(ThemeInterface $theme, Option $option): string {
    return $theme->heading($option->label);
  }

  /**
   * Render a separator row.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The rendered row.
   */
  protected function renderSeparatorRow(ThemeInterface $theme): string {
    return $theme->divider();
  }

  /**
   * Render a disabled option's label, appending its reason when present.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Config\Option $option
   *   The disabled option row.
   *
   * @return string
   *   The dimmed label (with reason).
   */
  protected function renderDisabledLabel(ThemeInterface $theme, Option $option): string {
    $text = $option->label;

    if ($option->disabledReason !== '') {
      $text .= ' (' . $option->disabledReason . ')';
    }

    return $theme->disabled($text);
  }

}
