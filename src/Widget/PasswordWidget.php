<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Single-line text input rendered masked; the accepted value stays plain.
 *
 * @package DrevOps\Tui\Widget
 */
class PasswordWidget extends TextWidget {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function view(ThemeInterface $theme): string {
    $mask = $theme->mask();
    $line = str_repeat($mask, $this->cursor) . $theme->caret() . str_repeat($mask, strlen($this->buffer) - $this->cursor);

    return $this->error === NULL ? $line : $line . "\n" . $theme->error($this->error);
  }

}
