<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Config\OptionKind;
use DrevOps\Tui\Input\Action;
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
   */
  public function __construct(array $options, array $default = [], ?\Closure $validate = NULL, ?\Closure $transform = NULL) {
    parent::__construct($validate, $transform);
    $this->initOptions($options);

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
      $this->cursor = $this->firstSelectable($this->visible());

      return;
    }

    if ($key->isChar()) {
      $this->filter .= $key->char ?? '';
      $this->cursor = $this->firstSelectable($this->visible());
    }
  }

  /**
   * The rows currently visible under the filter.
   *
   * With no filter every row shows in declared order; once filtering, only
   * matching options show - structural headings and separators drop away so the
   * result reads as a flat relevance list.
   *
   * @return list<\DrevOps\Tui\Config\Option>
   *   The visible rows.
   */
  protected function visible(): array {
    if ($this->filter === '') {
      return $this->options;
    }

    $needle = strtolower($this->filter);

    return array_values(array_filter($this->options, fn(Option $option): bool => $option->kind === OptionKind::Option && str_contains(strtolower($option->label), $needle)));
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

    foreach ($this->visible() as $index => $option) {
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
      $lines[] = $theme->marker($current) . ' ' . $theme->check(isset($this->selected[$option->value])) . ' ' . $this->highlightLabel($theme, $option->label, $current);
    }

    $lines[] = $this->hint($theme);

    return implode("\n", $lines);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function rendersHint(): bool {
    return TRUE;
  }

  /**
   * Build the key-hint line shown beneath the option list.
   *
   * Toggle is the non-obvious action here - nothing else signals that a key
   * toggles the highlighted option - so it leads, followed by the remaining
   * bindings. Every glyph is drawn from the live bindings, so the line stays
   * truthful when the keys are remapped.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The themed, dot-joined hint line.
   */
  protected function hint(ThemeInterface $theme): string {
    $fragments = array_filter([
      $theme->keysHint($this->keys(), 'select', Action::Toggle),
      $theme->keysHint($this->keys(), 'move', Action::MoveUp, Action::MoveDown),
      $theme->keysHint($this->keys(), 'none/all', Action::SelectNone, Action::SelectAll),
      $theme->keysHint($this->keys(), 'accept', Action::Accept),
      $theme->keysHint($this->keys(), 'cancel', Action::Cancel),
    ]);

    return $theme->footer(implode(' ' . $theme->dot() . ' ', $fragments));
  }

}
