<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget\Capability;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Character-buffer editing: a movable cursor, insertion and deletion.
 *
 * All arithmetic is by character, not byte, so multibyte input edits and
 * renders correctly.
 *
 * @package DrevOps\Tui\Widget\Capability
 */
trait TextEditCapableTrait {

  /**
   * The live input buffer.
   */
  protected string $buffer = '';

  /**
   * The cursor offset within the buffer, in characters.
   */
  protected int $cursor = 0;

  /**
   * Seed the buffer and land the cursor at its end.
   *
   * @param string $buffer
   *   The initial value (and live input buffer).
   */
  protected function initTextBuffer(string $buffer): void {
    $this->buffer = $buffer;
    $this->cursor = mb_strlen($this->buffer, 'UTF-8');
  }

  /**
   * The live input buffer.
   *
   * @return string
   *   The buffer.
   */
  public function buffer(): string {
    return $this->buffer;
  }

  /**
   * Handle a buffer edit: deletion, cursor movement, insertion.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to handle.
   *
   * @return bool
   *   TRUE when the key edited the buffer or moved the cursor.
   */
  protected function handleTextEditKey(Key $key): bool {
    $keys = $this->keys();

    if ($keys->matches($key, Action::DeleteBack)) {
      $this->backspace();

      return TRUE;
    }

    if ($keys->matches($key, Action::MoveLeft)) {
      $this->cursor = max(0, $this->cursor - 1);

      return TRUE;
    }

    if ($keys->matches($key, Action::MoveRight)) {
      $this->cursor = min(mb_strlen($this->buffer, 'UTF-8'), $this->cursor + 1);

      return TRUE;
    }

    if ($keys->matches($key, Action::InsertSpace)) {
      $this->insert(' ');

      return TRUE;
    }

    if ($key->isChar()) {
      $this->insert($key->char ?? '');

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Insert text at the cursor.
   *
   * @param string $text
   *   The text to insert.
   */
  public function insert(string $text): void {
    $this->buffer = mb_substr($this->buffer, 0, $this->cursor, 'UTF-8') . $text . mb_substr($this->buffer, $this->cursor, NULL, 'UTF-8');
    $this->cursor += mb_strlen($text, 'UTF-8');
  }

  /**
   * Delete the character before the cursor.
   */
  public function backspace(): void {
    if ($this->cursor > 0) {
      $this->buffer = mb_substr($this->buffer, 0, $this->cursor - 1, 'UTF-8') . mb_substr($this->buffer, $this->cursor, NULL, 'UTF-8');
      $this->cursor--;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return $this->buffer;
  }

  /**
   * Render the buffer split by the caret at the cursor position.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme supplying the caret glyph.
   *
   * @return string
   *   The input line.
   */
  protected function renderCaretLine(ThemeInterface $theme): string {
    return mb_substr($this->buffer, 0, $this->cursor, 'UTF-8') . $theme->caret() . mb_substr($this->buffer, $this->cursor, NULL, 'UTF-8');
  }

}
