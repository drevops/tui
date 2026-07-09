<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Theme\AbstractTheme;

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
  public function handle(Key $key): void {
    if ($this->handleCancel($key)) {
      return;
    }

    if ($key->is(KeyName::Enter)) {
      $this->accept($this->current);

      return;
    }

    if ($this->isToggle($key)) {
      $this->current = !$this->current;

      return;
    }

    if ($key->isChar()) {
      $this->applyChar($key->char ?? '');
    }
  }

  /**
   * Whether the key toggles the choice.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to test.
   *
   * @return bool
   *   TRUE when the key toggles.
   */
  protected function isToggle(Key $key): bool {
    return in_array($key->name, [KeyName::Left, KeyName::Right, KeyName::Space, KeyName::Up, KeyName::Down], TRUE);
  }

  /**
   * Set the choice from a typed character (y/n).
   *
   * @param string $char
   *   The typed character.
   */
  protected function applyChar(string $char): void {
    $char = strtolower($char);

    if ($char === 'y') {
      $this->current = TRUE;
    }
    elseif ($char === 'n') {
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
  public function view(AbstractTheme $theme): string {
    $marker_on = $theme->style('marker', $theme->glyph('radio_on'));
    $marker_off = $theme->glyph('radio_off');
    $yes_label = $this->highlightLabel($theme, 'Yes', $this->current);
    $no_label = $this->highlightLabel($theme, 'No', !$this->current);

    return $this->current ? $marker_on . ' ' . $yes_label . '  ' . $marker_off . ' ' . $no_label : $marker_off . ' ' . $yes_label . '  ' . $marker_on . ' ' . $no_label;
  }

}
