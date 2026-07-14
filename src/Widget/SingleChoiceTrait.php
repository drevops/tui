<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Single-choice behaviour: one cursor over the rows, one accepted value.
 *
 * Composes with {@see ChoiceListTrait}: the cursor moves over the visible
 * rows, only ever resting on a selectable one, and accepting commits the
 * highlighted option's value.
 *
 * @package DrevOps\Tui\Widget
 */
trait SingleChoiceTrait {

  /**
   * The highlighted index within the visible rows.
   */
  protected int $cursor = 0;

  /**
   * Seed the option rows and land the cursor on the default value.
   *
   * @param array<int|string,\DrevOps\Tui\Config\Option|string> $options
   *   Option rows in display order - a list of options or the value => label
   *   shorthand map.
   * @param string $default
   *   The initially highlighted value.
   */
  protected function initSingleChoice(array $options, string $default): void {
    $this->initOptions($options);
    $this->cursor = $this->cursorForDefault($this->options, $default);
  }

  /**
   * Handle cancel, cursor movement and acceptance of the highlighted option.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to handle.
   */
  protected function handleSingleChoiceKey(Key $key): void {
    $keys = $this->keys();

    if ($this->handleCancel($key)) {
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

    if ($keys->matches($key, Action::Accept) && $this->currentSelectable()) {
      $this->accept($this->liveValue());
    }
  }

  /**
   * Whether the highlighted visible row is a selectable option.
   *
   * @return bool
   *   TRUE when the cursor rests on a selectable option.
   */
  public function currentSelectable(): bool {
    return ($this->visible()[$this->cursor] ?? NULL)?->selectable() ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return $this->currentSelectable() ? $this->visible()[$this->cursor]->value : '';
  }

  /**
   * Render one option row: the radio glyph and the (possibly disabled) label.
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
      return $theme->radio(FALSE) . ' ' . $this->renderDisabledLabel($theme, $option);
    }

    return $this->renderRadioRow($theme, $option->label, $current);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hints(): array {
    return [new Hint('move', Action::MoveUp, Action::MoveDown), ...parent::hints()];
  }

}
