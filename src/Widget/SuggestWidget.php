<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * An autocomplete text input fuzzy-filtering a fixed option set.
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
   * @param int|null $pageSize
   *   The number of suggestions shown at once before the list pages; NULL uses
   *   the default.
   */
  public function __construct(protected array $values, protected string $buffer = '', ?\Closure $validate = NULL, ?\Closure $transform = NULL, ?int $pageSize = NULL) {
    parent::__construct($validate, $transform);
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
      $this->resetFilterCursor();

      return;
    }

    if ($keys->matches($key, Action::InsertSpace)) {
      $this->buffer .= ' ';
      $this->resetFilterCursor();

      return;
    }

    if ($key->isChar()) {
      $this->buffer .= $key->char ?? '';
      $this->resetFilterCursor();
    }
  }

  /**
   * Reset the highlight and paging when the query changes.
   */
  protected function resetFilterCursor(): void {
    $this->highlight = -1;
    $this->offset = 0;
  }

  /**
   * The suggestions matching the current buffer, ranked by fuzzy relevance.
   *
   * @return list<string>
   *   The matching suggestion values, most relevant first.
   */
  protected function matches(): array {
    if ($this->buffer === '') {
      return $this->values;
    }

    return $this->matcher()->rankValues($this->values, $this->buffer);
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

    $matches = $this->matches();
    $viewport = $this->pageViewport(count($matches), $this->highlight);

    if ($viewport->has_above) {
      $lines[] = $theme->indicator('  ' . $theme->indicatorUp());
    }

    foreach (array_slice($matches, $viewport->offset, $this->pageSize) as $slot => $value) {
      $index = $viewport->offset + $slot;
      $current = $index === $this->highlight;
      $lines[] = $theme->marker($current) . ' ' . $this->renderMatchedLabel($theme, $value, $this->positionsFor($value), $current);
    }

    if ($viewport->has_below) {
      $lines[] = $theme->indicator('  ' . $theme->indicatorDown());
    }

    return implode("\n", $lines);
  }

  /**
   * The matched-character positions in a suggestion under the current buffer.
   *
   * @param string $value
   *   The suggestion value.
   *
   * @return list<int>
   *   The matched indices, or an empty list when not filtering.
   */
  protected function positionsFor(string $value): array {
    return $this->buffer === '' ? [] : $this->matcher()->positions($value, $this->buffer);
  }

}
