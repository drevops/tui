<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
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
  protected int $cursor = -1;

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
   * @param int|null $page_size
   *   The number of suggestions shown at once before the list pages; NULL uses
   *   the default.
   */
  public function __construct(protected array $values, protected string $buffer = '', ?\Closure $validate = NULL, ?\Closure $transform = NULL, ?int $page_size = NULL) {
    parent::__construct($validate, $transform);
    $this->pageSize = $this->resolvePageSize($page_size);
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
      $this->cursor = min(count($this->visible()) - 1, $this->cursor + 1);

      return;
    }

    if ($keys->matches($key, Action::MoveUp)) {
      $this->cursor = max(-1, $this->cursor - 1);

      return;
    }

    if ($keys->matches($key, Action::DeleteBack)) {
      $this->buffer = mb_substr($this->buffer, 0, -1, 'UTF-8');
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
    $this->cursor = -1;
    $this->offset = 0;
  }

  /**
   * The suggestions matching the current buffer, ranked by fuzzy relevance.
   *
   * @return list<string>
   *   The matching suggestion values, most relevant first.
   */
  protected function visible(): array {
    if ($this->buffer === '') {
      return $this->values;
    }

    return $this->matcher()->rankValues($this->values, $this->buffer);
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    if ($this->cursor >= 0) {
      $visible = $this->visible();

      return $visible[$this->cursor] ?? $this->buffer;
    }

    return $this->buffer;
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    $visible = $this->visible();
    $viewport = $this->pageViewport(count($visible), $this->cursor);

    $rows = [];

    foreach (array_slice($visible, $viewport->offset, $this->pageSize) as $slot => $value) {
      $current = $viewport->offset + $slot === $this->cursor;
      $rows[] = $theme->marker($current) . ' ' . $this->renderMatchedLabel($theme, $value, $this->matchPositions($value), $current);
    }

    return implode("\n", [$this->buffer . $theme->caret(), ...$this->wrapScrolled($theme, $rows, $viewport)]);
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
  protected function matchPositions(string $value): array {
    return $this->buffer === '' ? [] : $this->matcher()->positions($value, $this->buffer);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hints(): array {
    return [new Hint('move', Action::MoveUp, Action::MoveDown), ...parent::hints()];
  }

}
