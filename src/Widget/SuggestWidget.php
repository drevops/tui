<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * An autocomplete text input filtering a fixed option set.
 *
 * @package DrevOps\Tui\Widget
 */
class SuggestWidget extends AbstractWidget {

  /**
   * The highlighted suggestion index, or -1 for none.
   */
  protected int $highlight = -1;

  /**
   * Construct a suggest widget.
   *
   * @param list<string> $values
   *   The suggestion values.
   * @param string $buffer
   *   The initial input.
   * @param \Closure|null $validate
   *   Optional validator (see AbstractWidget).
   * @param \Closure|null $transform
   *   Optional transformer (see AbstractWidget).
   */
  public function __construct(protected array $values, protected string $buffer = '', ?\Closure $validate = NULL, ?\Closure $transform = NULL) {
    parent::__construct($validate, $transform);
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

    if ($keys->matches($key, Action::MoveDown)) {
      $this->highlight = min(count($this->matches()) - 1, $this->highlight + 1);

      return;
    }

    if ($keys->matches($key, Action::MoveUp)) {
      $this->highlight = max(-1, $this->highlight - 1);

      return;
    }

    if ($keys->matches($key, Action::DeleteBack)) {
      $this->buffer = substr($this->buffer, 0, -1);
      $this->highlight = -1;

      return;
    }

    if ($keys->matches($key, Action::InsertSpace)) {
      $this->buffer .= ' ';
      $this->highlight = -1;

      return;
    }

    if ($key->isChar()) {
      $this->buffer .= $key->char ?? '';
      $this->highlight = -1;
    }
  }

  /**
   * The suggestions matching the current buffer.
   *
   * @return list<string>
   *   The matching suggestion values.
   */
  protected function matches(): array {
    if ($this->buffer === '') {
      return $this->values;
    }

    $needle = strtolower($this->buffer);

    return array_values(array_filter($this->values, fn(string $value): bool => str_contains(strtolower($value), $needle)));
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    if ($this->highlight >= 0) {
      $matches = $this->matches();

      return $matches[$this->highlight] ?? $this->buffer;
    }

    return $this->buffer;
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    $lines = [$this->buffer . $theme->caret()];

    foreach ($this->matches() as $index => $value) {
      $current = $index === $this->highlight;
      $marker = $theme->marker($current);
      $lines[] = $marker . ' ' . $this->highlightLabel($theme, $value, $current);
    }

    return implode("\n", $lines);
  }

}
