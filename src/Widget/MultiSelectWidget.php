<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Config\OptionKind;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A checkbox list with type-to-filter and select-all/none.
 *
 * Printable characters narrow the list; Space toggles the highlighted option;
 * Right selects all visible options and Left deselects them.
 *
 * @package DrevOps\Tui\Widget
 */
class MultiSelectWidget extends AbstractWidget {

  use ChoiceListTrait;

  /**
   * The selected values as a set (value => TRUE).
   *
   * @var array<string,bool>
   */
  protected array $selected = [];

  /**
   * The current type-to-filter text.
   */
  protected string $filter = '';

  /**
   * The highlighted index within the visible (filtered) options.
   */
  protected int $cursor = 0;

  /**
   * Construct a multiselect widget.
   *
   * @param array<int|string,\DrevOps\Tui\Config\Option|string> $options
   *   Option rows in display order - a list of options or the value => label
   *   shorthand map.
   * @param list<string> $default
   *   The initially selected values (non-selectable values are ignored).
   * @param \Closure|null $validate
   *   Optional validator (see AbstractWidget).
   * @param \Closure|null $transform
   *   Optional transformer (see AbstractWidget).
   * @param int|null $pageSize
   *   The number of option rows shown at once before the list pages; NULL uses
   *   the default.
   */
  public function __construct(array $options, array $default = [], ?\Closure $validate = NULL, ?\Closure $transform = NULL, ?int $pageSize = NULL) {
    parent::__construct($validate, $transform);
    $this->initOptions($options);
    $this->pageSize = $this->resolvePageSize($pageSize);

    $selectable = array_fill_keys($this->selectableValues(), TRUE);
    foreach ($default as $value) {
      if (isset($selectable[$value])) {
        $this->selected[$value] = TRUE;
      }
    }

    $this->cursor = $this->firstSelectable($this->options);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::MultiSelect);
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
      $this->accept($this->liveValue());

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

    if ($keys->matches($key, Action::Toggle)) {
      $this->toggleCurrent();

      return;
    }

    if ($keys->matches($key, Action::SelectAll)) {
      $this->setAllVisible(TRUE);

      return;
    }

    if ($keys->matches($key, Action::SelectNone)) {
      $this->setAllVisible(FALSE);

      return;
    }

    if ($keys->matches($key, Action::DeleteBack)) {
      $this->filter = substr($this->filter, 0, -1);
      $this->resetFilterCursor();

      return;
    }

    if ($key->isChar()) {
      $this->filter .= $key->char ?? '';
      $this->resetFilterCursor();
    }
  }

  /**
   * Land the cursor on the first match and reset paging on a query change.
   */
  protected function resetFilterCursor(): void {
    $this->cursor = $this->firstSelectable($this->visible());
    $this->offset = 0;
  }

  /**
   * The rows currently visible under the filter.
   *
   * With no filter every row shows in declared order; once filtering, only
   * matching options show - structural headings and separators drop away so the
   * result reads as a flat list.
   *
   * @return list<\DrevOps\Tui\Config\Option>
   *   The visible rows.
   */
  protected function visible(): array {
    if ($this->filter === '') {
      return $this->options;
    }

    return $this->filterOptions($this->filter);
  }

  /**
   * Filter the options to those matching the query.
   *
   * The base checkbox list narrows by case-insensitive substring; the search
   * variant overrides this to rank by fuzzy relevance.
   *
   * @param string $needle
   *   The query.
   *
   * @return list<\DrevOps\Tui\Config\Option>
   *   The matching option rows.
   */
  protected function filterOptions(string $needle): array {
    $lower = mb_strtolower($needle);

    return array_values(array_filter($this->options, static fn(Option $option): bool => $option->kind === OptionKind::Option && str_contains(mb_strtolower($option->label), $lower)));
  }

  /**
   * The matched-character positions in a label, for highlighting.
   *
   * The base checkbox list does not highlight matches; the search variant
   * overrides this to point at the fuzzy-matched characters.
   *
   * @param string $label
   *   The option label.
   *
   * @return list<int>
   *   The matched indices (none by default).
   */
  protected function matchPositions(string $label): array {
    return [];
  }

  /**
   * The selectable values, as a value => label map for filtering and display.
   *
   * @return list<string>
   *   The selectable option values.
   */
  protected function selectableValues(): array {
    $out = [];

    foreach ($this->options as $option) {
      if ($option->selectable()) {
        $out[] = $option->value;
      }
    }

    return $out;
  }

  /**
   * Toggle the highlighted option when it is selectable.
   */
  protected function toggleCurrent(): void {
    $option = $this->visible()[$this->cursor] ?? NULL;

    if (!$option instanceof Option || !$option->selectable()) {
      return;
    }

    if (isset($this->selected[$option->value])) {
      unset($this->selected[$option->value]);
    }
    else {
      $this->selected[$option->value] = TRUE;
    }
  }

  /**
   * Select or deselect all selectable visible options.
   *
   * @param bool $selected
   *   TRUE to select, FALSE to deselect.
   */
  protected function setAllVisible(bool $selected): void {
    foreach ($this->visible() as $option) {
      if (!$option->selectable()) {
        continue;
      }

      if ($selected) {
        $this->selected[$option->value] = TRUE;
      }
      else {
        unset($this->selected[$option->value]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    $out = [];

    foreach ($this->options as $option) {
      if ($option->selectable() && isset($this->selected[$option->value])) {
        $out[] = $option->value;
      }
    }

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    $lines = [];

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
        $lines[] = $theme->marker(FALSE) . ' ' . $theme->check(FALSE) . ' ' . $this->renderDisabledLabel($theme, $option);

        continue;
      }

      $current = $index === $this->cursor;
      $lines[] = $theme->marker($current) . ' ' . $theme->check(isset($this->selected[$option->value])) . ' ' . $this->renderMatchedLabel($theme, $option->label, $this->matchPositions($option->label), $current);
    }

    if ($viewport->has_below) {
      $lines[] = $theme->indicator('  ' . $theme->indicatorDown());
    }

    return implode("\n", $lines);
  }

  /**
   * {@inheritdoc}
   *
   * Toggle is the non-obvious action here - nothing else signals that a key
   * toggles the highlighted option - so it leads, followed by the rest.
   */
  #[\Override]
  public function hints(): array {
    return [
      new Hint('select', Action::Toggle),
      new Hint('move', Action::MoveUp, Action::MoveDown),
      new Hint('none/all', Action::SelectNone, Action::SelectAll),
      new Hint('accept', Action::Accept),
      new Hint('cancel', Action::Cancel),
    ];
  }

}
