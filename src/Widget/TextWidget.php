<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Single-line text input with a movable cursor and optional ghost-text.
 *
 * @package DrevOps\Tui\Widget
 */
class TextWidget extends AbstractWidget {

  /**
   * The cursor offset within the buffer.
   */
  protected int $cursor;

  /**
   * Construct a text widget.
   *
   * @param string $buffer
   *   The initial value (and live input buffer).
   * @param \Closure|null $validate
   *   Optional validator (see AbstractWidget).
   * @param \Closure|null $transform
   *   Optional transformer (see AbstractWidget).
   * @param list<string> $completions
   *   Inline ghost-text candidates: the buffer is completed to the first
   *   candidate it is a prefix of. Empty leaves a plain text field.
   */
  public function __construct(protected string $buffer = '', ?\Closure $validate = NULL, ?\Closure $transform = NULL, protected array $completions = []) {
    parent::__construct($validate, $transform);
    $this->cursor = strlen($this->buffer);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Text);
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

    if ($keys->matches($key, Action::Complete)) {
      $this->applyCompletion();

      return;
    }

    if ($keys->matches($key, Action::DeleteBack)) {
      $this->backspace();

      return;
    }

    if ($keys->matches($key, Action::MoveLeft)) {
      $this->cursor = max(0, $this->cursor - 1);

      return;
    }

    if ($keys->matches($key, Action::MoveRight)) {
      // At the line's end, Right accepts the ghost-text like Tab; elsewhere
      // it just advances the caret.
      if ($this->bestMatch() !== NULL) {
        $this->applyCompletion();
      }
      else {
        $this->cursor = min(strlen($this->buffer), $this->cursor + 1);
      }

      return;
    }

    if ($keys->matches($key, Action::InsertSpace)) {
      $this->insert(' ');

      return;
    }

    if ($key->isChar()) {
      $this->insert($key->char ?? '');
    }
  }

  /**
   * Insert text at the cursor.
   *
   * @param string $char
   *   The text to insert.
   */
  protected function insert(string $char): void {
    $this->buffer = substr($this->buffer, 0, $this->cursor) . $char . substr($this->buffer, $this->cursor);
    $this->cursor += strlen($char);
  }

  /**
   * Delete the character before the cursor.
   */
  protected function backspace(): void {
    if ($this->cursor > 0) {
      $this->buffer = substr($this->buffer, 0, $this->cursor - 1) . substr($this->buffer, $this->cursor);
      $this->cursor--;
    }
  }

  /**
   * The best completion candidate for the current buffer, if any.
   *
   * A candidate qualifies only when the caret sits at the end of a non-empty
   * buffer and the buffer is a case-insensitive prefix of a strictly longer
   * candidate; the first such candidate in declared order wins. Returns NULL
   * when nothing completes, so the field behaves as a plain text input.
   *
   * @return string|null
   *   The full candidate string, or NULL.
   */
  protected function bestMatch(): ?string {
    if ($this->buffer === '' || $this->cursor !== strlen($this->buffer)) {
      return NULL;
    }

    // Fold and measure by character, not byte, so non-ASCII candidates match
    // case-insensitively and the suffix never splits mid-character.
    $needle = mb_strtolower($this->buffer);
    $length = mb_strlen($this->buffer);

    foreach ($this->completions as $completion) {
      if (mb_strlen($completion) > $length && str_starts_with(mb_strtolower($completion), $needle)) {
        return $completion;
      }
    }

    return NULL;
  }

  /**
   * The ghost-text suffix shown after the caret, or an empty string when none.
   *
   * @return string
   *   The suffix of the best candidate beyond the typed buffer.
   */
  protected function ghostSuffix(): string {
    $match = $this->bestMatch();

    return $match === NULL ? '' : mb_substr($match, mb_strlen($this->buffer));
  }

  /**
   * Fill the buffer with the current completion candidate, when one applies.
   */
  protected function applyCompletion(): void {
    $match = $this->bestMatch();

    if ($match !== NULL) {
      $this->buffer = $match;
      $this->cursor = strlen($match);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return $this->buffer;
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    return $this->withError($theme, $this->caretLine($theme));
  }

  /**
   * Render the input line with the caret and any inline ghost-text.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme supplying the caret glyph and the ghost styling.
   *
   * @return string
   *   The input line.
   */
  protected function caretLine(ThemeInterface $theme): string {
    $suffix = $this->ghostSuffix();
    $ghost = $suffix === '' ? '' : $theme->ghost($suffix);

    return substr($this->buffer, 0, $this->cursor) . $theme->caret() . substr($this->buffer, $this->cursor) . $ghost;
  }

}
