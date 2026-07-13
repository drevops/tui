<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A single-choice radio list.
 *
 * @package DrevOps\Tui\Widget
 */
class SelectWidget extends AbstractWidget {

  use ChoiceListTrait;

  /**
   * The highlighted option index.
   */
  protected int $cursor = 0;

  /**
   * Construct a select widget.
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
    $this->pageSize = $this->resolvePageSize($pageSize);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
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
   * The rows currently shown.
   *
   * The base select shows every declared row; a filtering subclass narrows
   * this to the rows matching its query.
   *
   * @return list<\DrevOps\Tui\Config\Option>
   *   The visible rows.
   */
  protected function visible(): array {
    return $this->options;
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
    $visible = $this->visible();
    $viewport = $this->pageViewport(count($visible), $this->cursor);

    return implode("\n", $this->renderListRows($theme, $visible, $viewport, fn(Option $option, int $index): string => $this->renderOptionRow($theme, $option, $index === $this->cursor)));
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
  protected function renderOptionRow(ThemeInterface $theme, Option $option, bool $current): string {
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
