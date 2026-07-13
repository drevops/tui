<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A single-choice list with fuzzy type-to-filter over the option labels.
 *
 * The select widget plus a query line: printable characters narrow the list,
 * ranked by fuzzy relevance, and the matched characters are highlighted.
 *
 * @package DrevOps\Tui\Widget
 */
class SearchWidget extends SelectWidget {

  /**
   * The current type-to-filter text.
   */
  protected string $filter = '';

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function handle(Key $key): void {
    $keys = $this->keys();

    if ($keys->matches($key, Action::DeleteBack)) {
      $this->filter = substr($this->filter, 0, -1);
      $this->resetFilterCursor();

      return;
    }

    if ($keys->matches($key, Action::InsertSpace)) {
      $this->filter .= ' ';
      $this->resetFilterCursor();

      return;
    }

    if ($key->isChar()) {
      $this->filter .= $key->char ?? '';
      $this->resetFilterCursor();

      return;
    }

    parent::handle($key);
  }

  /**
   * Land the cursor on the first match and reset paging on a query change.
   */
  protected function resetFilterCursor(): void {
    $this->cursor = $this->firstSelectable($this->visible());
    $this->offset = 0;
  }

  /**
   * {@inheritdoc}
   *
   * With no filter every row shows in declared order; once filtering, only
   * matching options show, ranked by fuzzy relevance - structural headings and
   * separators drop away so the result reads as a flat relevance list.
   */
  #[\Override]
  protected function visible(): array {
    if ($this->filter === '') {
      return $this->options;
    }

    return $this->matcher()->rankOptions($this->options, $this->filter);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function view(ThemeInterface $theme): string {
    return $this->filter . $theme->caret() . "\n" . parent::view($theme);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function renderOptionRow(ThemeInterface $theme, Option $option, bool $current): string {
    if ($option->disabled) {
      return $theme->radio(FALSE) . ' ' . $this->renderDisabledLabel($theme, $option);
    }

    return $theme->radio($current) . ' ' . $this->renderMatchedLabel($theme, $option->label, $this->matchPositions($option->label), $current);
  }

  /**
   * The matched-character positions in a label under the current filter.
   *
   * @param string $label
   *   The option label.
   *
   * @return list<int>
   *   The matched indices, or an empty list when not filtering.
   */
  protected function matchPositions(string $label): array {
    return $this->filter === '' ? [] : $this->matcher()->positions($label, $this->filter);
  }

}
