<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Widget\Capability\ExternalEditCapableInterface;
use DrevOps\Tui\Widget\Capability\TextEditCapableInterface;
use DrevOps\Tui\Widget\Capability\TextEditCapableTrait;

/**
 * Multi-line text input: Enter inserts a newline, Tab accepts.
 *
 * @package DrevOps\Tui\Widget
 */
class TextareaWidget extends AbstractWidget implements TextEditCapableInterface, ExternalEditCapableInterface {

  use TextEditCapableTrait;

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
   */
  public function __construct(string $buffer = '', protected bool $externalEdit = FALSE) {
    $this->initTextBuffer($buffer);
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

    if ($this->handleCancel($key)) {
      return;
    }

    // Accept is checked here, after the newline branch, because this scope
    // binds it to Tab rather than Enter.
    if ($this->handleAccept($key)) {
      return;
    }

    $this->handleTextEditKey($key);
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
      $length = mb_strlen($text, 'UTF-8');

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
      $offset += mb_strlen($lines[$index], 'UTF-8') + 1;
    }

    $this->cursor = $offset + min($column, mb_strlen($lines[$target], 'UTF-8'));
  }

  /**
   * {@inheritdoc}
   */
  public function wantsExternalEdit(): bool {
    return $this->externalEditRequested;
  }

  /**
   * {@inheritdoc}
   *
   * Clears the pending request. A non-NULL buffer replaces the value and is
   * accepted, so saving and exiting the editor commits the field. A NULL buffer
   * (the edit was aborted or unavailable) leaves the inline value untouched.
   */
  public function applyExternalEdit(?string $content): void {
    $this->externalEditRequested = FALSE;

    if ($content === NULL) {
      return;
    }

    $this->buffer = $content;
    $this->cursor = mb_strlen($content, 'UTF-8');
    $this->accept($content);
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    return $this->withError($theme, $this->renderCaretLine($theme));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hints(): array {
    $hints = [new Hint('newline', Action::NewLine), ...parent::hints()];

    if ($this->externalEdit) {
      $hints[] = new Hint('editor', Action::ExternalEdit);
    }

    return $hints;
  }

}
