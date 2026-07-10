<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Multi-line text input: Enter inserts a newline, Tab accepts.
 *
 * @package DrevOps\Tui\Widget
 */
class TextareaWidget extends TextWidget {

  /**
   * Whether the external-editor handoff has been requested.
   */
  protected bool $externalEditRequested = FALSE;

  /**
   * Construct a textarea widget.
   *
   * @param string $buffer
   *   The initial value (and live input buffer).
   * @param \Closure|null $validate
   *   Optional validator (see AbstractWidget).
   * @param \Closure|null $transform
   *   Optional transformer (see AbstractWidget).
   * @param bool $externalEdit
   *   Whether the external-editor handoff is offered (an available $EDITOR).
   */
  public function __construct(string $buffer = '', ?\Closure $validate = NULL, ?\Closure $transform = NULL, protected bool $externalEdit = FALSE) {
    parent::__construct($buffer, $validate, $transform);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Textarea);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function handle(Key $key): void {
    $keys = $this->keys();

    if ($keys->matches($key, Action::ExternalEdit)) {
      // Only act when the handoff is offered; either way the bound key is
      // swallowed rather than inserting a raw control byte into the buffer.
      if ($this->externalEdit) {
        $this->externalEditRequested = TRUE;
      }

      return;
    }

    if ($keys->matches($key, Action::NewLine)) {
      $this->insert("\n");

      return;
    }

    if ($keys->matches($key, Action::MoveUp)) {
      $this->moveLine(-1);

      return;
    }

    if ($keys->matches($key, Action::MoveDown)) {
      $this->moveLine(1);

      return;
    }

    // The parent handles the rest, including Accept, which this scope binds to
    // Tab rather than Enter.
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

    return $this->error === NULL ? $text : $text . "\n" . $theme->error($this->error);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hints(): array {
    $hints = [new Hint('newline', Action::NewLine), new Hint('accept', Action::Accept), new Hint('cancel', Action::Cancel)];

    if ($this->externalEdit) {
      $hints[] = new Hint('editor', Action::ExternalEdit);
    }

    return $hints;
  }

}
