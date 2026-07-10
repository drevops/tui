<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Config\OptionKind;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A single-choice list with type-to-filter over the option labels.
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
   */
  public function __construct(array $options, string $default = '', ?\Closure $validate = NULL, ?\Closure $transform = NULL) {
    parent::__construct($validate, $transform);
    $this->initOptions($options);
    $this->cursor = $this->cursorForDefault($this->options, $default);
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
      $this->cursor = $this->firstSelectable($this->visible());

      return;
    }

    if ($keys->matches($key, Action::InsertSpace)) {
      $this->filter .= ' ';
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
        $lines[] = $theme->radio(FALSE) . ' ' . $this->renderDisabledLabel($theme, $option);

        continue;
      }

      $lines[] = $this->renderRadioRow($theme, $option->label, $index === $this->cursor);
    }

    return implode("\n", $lines);
  }

}
