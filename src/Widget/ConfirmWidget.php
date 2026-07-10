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
 * A yes/no toggle.
 *
 * @package DrevOps\Tui\Widget
 */
class ConfirmWidget extends AbstractWidget {

  /**
   * Construct a confirm widget.
   *
   * @param bool $current
   *   The initial choice.
   * @param \Closure|null $validate
   *   Optional validator (see AbstractWidget).
   * @param \Closure|null $transform
   *   Optional transformer (see AbstractWidget).
   */
  public function __construct(protected bool $current = FALSE, ?\Closure $validate = NULL, ?\Closure $transform = NULL) {
    parent::__construct($validate, $transform);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Confirm);
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
      $this->accept($this->current);

      return;
    }

    if ($keys->matches($key, Action::Toggle)) {
      $this->current = !$this->current;

      return;
    }

    if ($keys->matches($key, Action::Yes)) {
      $this->current = TRUE;

      return;
    }

    if ($keys->matches($key, Action::No)) {
      $this->current = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return $this->current;
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    $marker_on = $theme->radio(TRUE);
    $marker_off = $theme->radio(FALSE);
    $yes_label = $this->highlightLabel($theme, 'Yes', $this->current);
    $no_label = $this->highlightLabel($theme, 'No', !$this->current);

    return $this->current ? $marker_on . ' ' . $yes_label . '  ' . $marker_off . ' ' . $no_label : $marker_off . ' ' . $yes_label . '  ' . $marker_on . ' ' . $no_label;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hints(): array {
    return [new Hint('yes/no', Action::Yes, Action::No), new Hint('toggle', Action::Toggle), new Hint('accept', Action::Accept), new Hint('cancel', Action::Cancel)];
  }

}
