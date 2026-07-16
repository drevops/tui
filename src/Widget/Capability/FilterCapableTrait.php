<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget\Capability;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;

/**
 * Type-to-filter behaviour over a choice widget's option rows.
 *
 * Printable characters narrow the visible rows to the matching options and
 * Backspace widens them again; the match strategy itself (substring, fuzzy)
 * is the widget's own.
 *
 * @package DrevOps\Tui\Widget\Capability
 */
trait FilterCapableTrait {

  /**
   * The current type-to-filter text.
   */
  protected string $filter = '';

  /**
   * The current type-to-filter text.
   *
   * @return string
   *   The live query, empty when not filtering.
   */
  public function filter(): string {
    return $this->filter;
  }

  /**
   * Filter the option rows to those matching the query.
   *
   * @param string $needle
   *   The query.
   *
   * @return list<\DrevOps\Tui\Model\Option>
   *   The matching option rows.
   */
  abstract protected function filterOptions(string $needle): array;

  /**
   * Handle a filter edit: Backspace deletes a character, a printable appends.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to handle.
   *
   * @return bool
   *   TRUE when the key edited the filter.
   */
  protected function handleFilterKey(Key $key): bool {
    if ($this->keys()->matches($key, Action::DeleteBack)) {
      $this->filter = mb_substr($this->filter, 0, -1, 'UTF-8');
      $this->resetFilterCursor();

      return TRUE;
    }

    if ($key->isChar()) {
      $this->filter .= $key->char ?? '';
      $this->resetFilterCursor();

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Land the cursor on the first match and reset paging on a query change.
   */
  public function resetFilterCursor(): void {
    $this->cursor = $this->firstSelectable($this->visible());
    $this->offset = 0;
  }

  /**
   * The rows currently visible under the filter.
   *
   * With no filter every row shows in declared order; once filtering, only
   * matching options show - structural headings and separators drop away so
   * the result reads as a flat list.
   *
   * @return list<\DrevOps\Tui\Model\Option>
   *   The visible rows.
   */
  public function visible(): array {
    if ($this->filter === '') {
      return $this->options;
    }

    return $this->filterOptions($this->filter);
  }

}
