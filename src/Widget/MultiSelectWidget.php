<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\FieldType;
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

  /**
   * The option values in display order.
   *
   * @var list<string>
   */
  protected array $values;

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
   * @param array<string,string> $labels
   *   Options as value => label, in display order.
   * @param list<string> $default
   *   The initially selected values.
   * @param \Closure|null $validate
   *   Optional validator (see AbstractWidget).
   * @param \Closure|null $transform
   *   Optional transformer (see AbstractWidget).
   */
  public function __construct(protected array $labels, array $default = [], ?\Closure $validate = NULL, ?\Closure $transform = NULL) {
    parent::__construct($validate, $transform);
    $this->values = array_keys($this->labels);

    foreach ($default as $value) {
      $this->selected[$value] = TRUE;
    }
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
      $this->cursor = max(0, $this->cursor - 1);

      return;
    }

    if ($keys->matches($key, Action::MoveDown)) {
      $this->cursor = min(count($this->visible()) - 1, $this->cursor + 1);

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
      $this->cursor = 0;

      return;
    }

    if ($key->isChar()) {
      $this->filter .= $key->char ?? '';
      $this->cursor = 0;
    }
  }

  /**
   * The options currently visible under the filter.
   *
   * @return list<string>
   *   The visible option values.
   */
  protected function visible(): array {
    if ($this->filter === '') {
      return $this->values;
    }

    $needle = strtolower($this->filter);

    return array_values(array_filter($this->values, fn(string $value): bool => str_contains(strtolower($this->labels[$value] ?? $value), $needle)));
  }

  /**
   * Toggle the highlighted option.
   */
  protected function toggleCurrent(): void {
    $visible = $this->visible();
    $value = $visible[$this->cursor] ?? NULL;

    if ($value === NULL) {
      return;
    }

    if (isset($this->selected[$value])) {
      unset($this->selected[$value]);
    }
    else {
      $this->selected[$value] = TRUE;
    }
  }

  /**
   * Select or deselect all visible options.
   *
   * @param bool $selected
   *   TRUE to select, FALSE to deselect.
   */
  protected function setAllVisible(bool $selected): void {
    foreach ($this->visible() as $value) {
      if ($selected) {
        $this->selected[$value] = TRUE;
      }
      else {
        unset($this->selected[$value]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return array_values(array_filter($this->values, fn(string $value): bool => isset($this->selected[$value])));
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    $lines = [];

    foreach ($this->visible() as $index => $value) {
      $box = $theme->check(isset($this->selected[$value]));
      $current = $index === $this->cursor;
      $marker = $theme->marker($current);
      $lines[] = $marker . ' ' . $box . ' ' . $this->highlightLabel($theme, $this->labels[$value] ?? $value, $current);
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
