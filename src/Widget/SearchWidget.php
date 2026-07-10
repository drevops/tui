<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\OptionKind;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A single-choice list with fuzzy type-to-filter over the option labels.
 *
 * @package DrevOps\Tui\Widget
 */
class SearchWidget extends AbstractWidget {

  use ChoiceListTrait;

  /**
   * The current type-to-filter text.
   */
  protected string $filter = '';

  /**
   * The highlighted index within the visible (filtered) options.
   */
  protected int $cursor = 0;

  /**
   * Construct a search widget.
   *
   * @param array<int|string,\DrevOps\Tui\Config\Option|string> $options
   *   Option rows in display order - a list of options or the value => label
   *   shorthand map.
   * @param string $default
   *   The initially highlighted value.
   * @param \Closure|null $validate
   *   Optional validator (see AbstractWidget).
   * @param \Closure|null $transform
   *   Optional transformer (see AbstractWidget).
   * @param int|null $pageSize
   *   The number of option rows shown at once before the list pages; NULL uses
   *   the default.
   */
  public function __construct(array $options, string $default = '', ?\Closure $validate = NULL, ?\Closure $transform = NULL, ?int $pageSize = NULL) {
    parent::__construct($validate, $transform);
    $this->initOptions($options);
    $this->cursor = $this->cursorForDefault($this->options, $default);
    $this->pageSize = $pageSize ?? self::DEFAULT_PAGE_SIZE;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
    $keys = $this->keys();

    if ($this->handleCancel($key)) {
      return;
    }

    if ($keys->matches($key, Action::Accept)) {
      if ($this->currentSelectable()) {
        $this->accept($this->liveValue());
      }

      return;
    }

    if ($keys->matches($key, Action::MoveUp)) {
      $this->cursor = $this->stepCursor($this->visible(), $this->cursor, -1);

      return;
    }

    if ($keys->matches($key, Action::MoveDown)) {
      $this->cursor = $this->stepCursor($this->visible(), $this->cursor, 1);

      return;
    }

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
    }
  }

  /**
   * Land the cursor on the first match and rewind paging when the query changes.
   */
  protected function resetFilterCursor(): void {
    $this->cursor = $this->firstSelectable($this->visible());
    $this->offset = 0;
  }

  /**
   * The rows currently visible under the filter.
   *
   * With no filter every row shows in declared order; once filtering, only
   * matching options show, ranked by fuzzy relevance - structural headings and
   * separators drop away so the result reads as a flat relevance list.
   *
   * @return list<\DrevOps\Tui\Config\Option>
   *   The visible rows.
   */
  protected function visible(): array {
    if ($this->filter === '') {
      return $this->options;
    }

    return $this->matcher()->rankOptions($this->options, $this->filter);
  }

  /**
   * Whether the highlighted visible row is a selectable option.
   *
   * @return bool
   *   TRUE when the cursor rests on a selectable option.
   */
  protected function currentSelectable(): bool {
    return ($this->visible()[$this->cursor] ?? NULL)?->selectable() ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return $this->currentSelectable() ? $this->visible()[$this->cursor]->value : '';
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    $lines = [$this->filter . $theme->caret()];

    $visible = $this->visible();
    $viewport = $this->pageViewport(count($visible), $this->cursor);

    if ($viewport->has_above) {
      $lines[] = $theme->indicator('  ' . $theme->indicatorUp());
    }

    foreach (array_slice($visible, $viewport->offset, $this->pageSize) as $slot => $option) {
      $index = $viewport->offset + $slot;

      if ($option->kind === OptionKind::Heading) {
        $lines[] = $this->renderHeadingRow($theme, $option);

        continue;
      }

      if ($option->kind === OptionKind::Separator) {
        $lines[] = $this->renderSeparatorRow($theme);

        continue;
      }

      if ($option->disabled) {
        $lines[] = $theme->radio(FALSE) . ' ' . $this->renderDisabledLabel($theme, $option);

        continue;
      }

      $current = $index === $this->cursor;
      $lines[] = $theme->radio($current) . ' ' . $this->renderMatchedLabel($theme, $option->label, $this->positionsFor($option->label), $current);
    }

    if ($viewport->has_below) {
      $lines[] = $theme->indicator('  ' . $theme->indicatorDown());
    }

    return implode("\n", $lines);
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
  protected function positionsFor(string $label): array {
    return $this->filter === '' ? [] : $this->matcher()->positions($label, $this->filter);
  }

}
