<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Widget\Capability\CompletionCapableInterface;
use DrevOps\Tui\Widget\Capability\CompletionCapableTrait;
use DrevOps\Tui\Widget\Capability\TextEditCapableInterface;
use DrevOps\Tui\Widget\Capability\TextEditCapableTrait;

/**
 * Single-line text input with a movable cursor and optional ghost-text.
 *
 * @package DrevOps\Tui\Widget
 */
class TextWidget extends AbstractWidget implements TextEditCapableInterface, CompletionCapableInterface {

  use TextEditCapableTrait;
  use CompletionCapableTrait;

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
  public function __construct(string $buffer = '', ?\Closure $validate = NULL, ?\Closure $transform = NULL, protected array $completions = []) {
    parent::__construct($validate, $transform);
    $this->initTextBuffer($buffer);
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

    // At the line's end, Right accepts the ghost-text like Tab; elsewhere it
    // falls through to the plain caret move.
    if ($keys->matches($key, Action::MoveRight) && $this->bestMatch() !== NULL) {
      $this->applyCompletion();

      return;
    }

    $this->handleTextEditKey($key);
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

    return $this->renderCaretLine($theme) . $ghost;
  }

}
