<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A single-choice list with type-to-filter over the option labels.
 *
 * @package DrevOps\Tui\Widget
 */
class SearchWidget extends AbstractWidget {

  /**
   * The option values in display order.
   *
   * @var list<string>
   */
  protected array $values;

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
   * @param array<string,string> $labels
   *   Options as value => label, in display order.
   * @param string $default
   *   The initially highlighted value.
   * @param \Closure|null $validate
   *   Optional validator (see AbstractWidget).
   * @param \Closure|null $transform
   *   Optional transformer (see AbstractWidget).
   */
  public function __construct(protected array $labels, string $default = '', ?\Closure $validate = NULL, ?\Closure $transform = NULL) {
    parent::__construct($validate, $transform);
    $this->values = array_keys($this->labels);
    $index = array_search($default, $this->values, TRUE);
    $this->cursor = $index === FALSE ? 0 : $index;
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
      if ($this->visible() !== []) {
        $this->accept($this->liveValue());
      }

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

    if ($keys->matches($key, Action::DeleteBack)) {
      $this->filter = substr($this->filter, 0, -1);
      $this->cursor = 0;

      return;
    }

    if ($keys->matches($key, Action::InsertSpace)) {
      $this->filter .= ' ';
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
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    $visible = $this->visible();

    return $visible[$this->cursor] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    $lines = [$this->filter . $theme->caret()];

    foreach ($this->visible() as $index => $value) {
      $lines[] = $this->renderRadioRow($theme, $this->labels[$value] ?? $value, $index === $this->cursor);
    }

    return implode("\n", $lines);
  }

}
