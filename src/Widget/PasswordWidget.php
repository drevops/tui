<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Theme\AbstractTheme;

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
  public function view(AbstractTheme $theme): string {
    $mask = $theme->glyph('mask');
    $line = str_repeat($mask, $this->cursor) . $theme->style('marker', $theme->glyph('caret')) . str_repeat($mask, strlen($this->buffer) - $this->cursor);

    return $this->error === NULL ? $line : $line . "\n" . $theme->style('error', $this->error);
  }

}
