<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget\Capability;

use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Multi-choice behaviour: a toggleable selection set collected as a list.
 *
 * Composes with {@see OptionsCapableTrait} and {@see FilterCapableTrait}: the
 * cursor moves over the visible rows, Space toggles the highlighted option,
 * Right/Left select or deselect all visible options, and accepting commits
 * the selected values in display order.
 *
 * @package DrevOps\Tui\Widget\Capability
 */
trait MultiSelectionCapableTrait {

  /**
   * The selected values as a set (value => TRUE).
   *
   * @var array<string,bool>
   */
  protected array $selected = [];

  /**
   * The highlighted index within the visible rows.
   */
  protected int $cursor = 0;

  /**
   * The matched-character positions in a label, for highlighting.
   *
   * @param string $label
   *   The option label.
   *
   * @return list<int>
   *   The matched indices.
   */
  abstract protected function matchPositions(string $label): array;

  /**
   * Seed the option rows and the selection set from the default values.
   *
   * @param array<int|string,\DrevOps\Tui\Config\Option|string> $options
   *   Option rows in display order - a list of options or the value => label
   *   shorthand map.
   * @param list<string> $default
   *   The initially selected values (non-selectable values are ignored).
   */
  protected function initMultiChoice(array $options, array $default): void {
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
    if ($this->handleMultiChoiceKey($key)) {
      return;
    }

    $this->handleFilterKey($key);
  }

  /**
   * Handle cancel, acceptance, cursor movement and selection toggles.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to handle.
   *
   * @return bool
   *   TRUE when the key was consumed.
   */
  protected function handleMultiChoiceKey(Key $key): bool {
    $keys = $this->keys();

    if ($this->handleCancel($key)) {
      return TRUE;
    }

    if ($keys->matches($key, Action::Accept)) {
      $this->accept($this->liveValue());

      return TRUE;
    }

    if ($keys->matches($key, Action::MoveUp)) {
      $this->cursor = $this->stepCursor($this->visible(), $this->cursor, -1);

      return TRUE;
    }

    if ($keys->matches($key, Action::MoveDown)) {
      $this->cursor = $this->stepCursor($this->visible(), $this->cursor, 1);

      return TRUE;
    }

    if ($keys->matches($key, Action::Toggle)) {
      $this->toggleCurrent();

      return TRUE;
    }

    if ($keys->matches($key, Action::SelectAll)) {
      $this->setAllVisible(TRUE);

      return TRUE;
    }

    if ($keys->matches($key, Action::SelectNone)) {
      $this->setAllVisible(FALSE);

      return TRUE;
    }

    return FALSE;
  }

  /**
   * The selectable option values, in display order.
   *
   * @return list<string>
   *   The selectable option values.
   */
  public function selectableValues(): array {
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
  public function toggleCurrent(): void {
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
  public function setAllVisible(bool $selected): void {
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
   * Render one option row: marker, checkbox and the (possibly disabled) label.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Config\Option $option
   *   The option row.
   * @param bool $current
   *   Whether the row holds the cursor.
   *
   * @return string
   *   The rendered row.
   */
  public function renderOptionRow(ThemeInterface $theme, Option $option, bool $current): string {
    if ($option->disabled) {
      return $theme->marker(FALSE) . ' ' . $theme->check(FALSE) . ' ' . $this->renderDisabledLabel($theme, $option);
    }

    return $theme->marker($current) . ' ' . $theme->check(isset($this->selected[$option->value])) . ' ' . $this->renderMatchedLabel($theme, $option->label, $this->matchPositions($option->label), $current);
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
      ...parent::hints(),
    ];
  }

}
