<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Multi-line text input: Enter inserts a newline, Tab accepts.
 *
 * @package DrevOps\Tui\Widget
 */
class TextareaWidget extends TextWidget {

  /**
   * The key that requests the external-editor handoff (Ctrl-E).
   */
  protected const string EDITOR_KEY = "\x05";

  /**
   * Whether the external-editor handoff has been requested.
   */
  protected bool $externalEditRequested = FALSE;

  /**
   * Construct a textarea widget.
   *
   * @param string $buffer
   *   The initial value (and live input buffer).
   * @param bool $externalEdit
   *   Whether the external-editor handoff is offered (an available $EDITOR).
   * @param \Closure|null $validate
   *   Optional validator (see AbstractWidget).
   * @param \Closure|null $transform
   *   Optional transformer (see AbstractWidget).
   */
  public function __construct(string $buffer = '', protected bool $externalEdit = FALSE, ?\Closure $validate = NULL, ?\Closure $transform = NULL) {
    parent::__construct($buffer, $validate, $transform);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function handle(Key $key): void {
    if ($key->isChar() && $key->char === self::EDITOR_KEY) {
      // Only act when the handoff is offered; otherwise swallow the control key
      // rather than inserting a raw byte into the buffer.
      if ($this->externalEdit) {
        $this->externalEditRequested = TRUE;
      }

      return;
    }

    if ($key->is(KeyName::Enter)) {
      $this->insert("\n");

      return;
    }

    if ($key->is(KeyName::Tab)) {
      $this->accept($this->liveValue());

      return;
    }

    if ($key->is(KeyName::Up)) {
      $this->moveLine(-1);

      return;
    }

    if ($key->is(KeyName::Down)) {
      $this->moveLine(1);

      return;
    }

    parent::handle($key);
  }

  /**
   * Move the cursor to the adjacent line, keeping the column when possible.
   *
   * @param int $delta
   *   The line offset: -1 for up, 1 for down.
   */
  protected function moveLine(int $delta): void {
    $lines = explode("\n", $this->buffer);

    $line = 0;
    $column = $this->cursor;
    foreach ($lines as $index => $text) {
      $length = strlen($text);

      if ($column <= $length) {
        $line = $index;
        break;
      }

      // Skip the line and its trailing newline.
      $column -= $length + 1;
    }

    $target = $line + $delta;

    if ($target < 0 || $target >= count($lines)) {
      return;
    }

    $offset = 0;
    for ($index = 0; $index < $target; $index++) {
      $offset += strlen($lines[$index]) + 1;
    }

    $this->cursor = $offset + min($column, strlen($lines[$target]));
  }

  /**
   * Whether the widget has requested the external-editor handoff.
   *
   * The driver reads this after handling a key and, when TRUE, launches the
   * editor and feeds the result back through applyExternalEdit().
   *
   * @return bool
   *   TRUE when a handoff was requested.
   */
  public function wantsExternalEdit(): bool {
    return $this->externalEditRequested;
  }

  /**
   * Apply the buffer captured from the external editor.
   *
   * Clears the pending request. A non-NULL buffer replaces the value and is
   * accepted, so saving and exiting the editor commits the field. A NULL buffer
   * (the edit was aborted or unavailable) leaves the inline value untouched.
   *
   * @param string|null $content
   *   The captured buffer, or NULL when the edit was aborted.
   */
  public function applyExternalEdit(?string $content): void {
    $this->externalEditRequested = FALSE;

    if ($content === NULL) {
      return;
    }

    $this->buffer = $content;
    $this->cursor = strlen($content);
    $this->accept($content);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function view(ThemeInterface $theme): string {
    $text = substr($this->buffer, 0, $this->cursor) . $theme->caret() . substr($this->buffer, $this->cursor);

    $hint_text = 'enter newline ' . $theme->dot() . ' tab accept';
    if ($this->externalEdit) {
      $hint_text .= ' ' . $theme->dot() . ' ctrl-e editor';
    }

    $out = $text . "\n" . $theme->footer($hint_text);

    return $this->error === NULL ? $out : $out . "\n" . $theme->error($this->error);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function rendersHint(): bool {
    return TRUE;
  }

}
