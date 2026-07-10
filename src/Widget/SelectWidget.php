<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\OptionKind;
use DrevOps\Tui\Input\Action;
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

    if ($keys->matches($key, Action::MoveUp)) {
      $this->cursor = $this->stepCursor($this->options, $this->cursor, -1);

      return;
    }

    if ($keys->matches($key, Action::MoveDown)) {
      $this->cursor = $this->stepCursor($this->options, $this->cursor, 1);

      return;
    }

    if ($keys->matches($key, Action::Accept) && $this->currentSelectable()) {
      $this->accept($this->liveValue());
    }
  }

  /**
   * Whether the highlighted row is a selectable option.
   *
   * @return bool
   *   TRUE when the cursor rests on a selectable option.
   */
  protected function currentSelectable(): bool {
    return ($this->options[$this->cursor] ?? NULL)?->selectable() ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return $this->currentSelectable() ? $this->options[$this->cursor]->value : '';
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    $lines = [];

    $viewport = $this->pageViewport(count($this->options), $this->cursor);

    if ($viewport->has_above) {
      $lines[] = $theme->indicator('  ' . $theme->indicatorUp());
    }

    foreach (array_slice($this->options, $viewport->offset, $this->pageSize) as $slot => $option) {
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

      $lines[] = $this->renderRadioRow($theme, $option->label, $index === $this->cursor);
    }

    if ($viewport->has_below) {
      $lines[] = $theme->indicator('  ' . $theme->indicatorDown());
    }

    return implode("\n", $lines);
  }

}
