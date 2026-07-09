<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * The default theme: one palette per colour mode, dark or light.
 *
 * Collapses the two terminal-background variants into a single theme whose
 * "mode" option selects the palette - bright foregrounds on a dark ground,
 * darker foregrounds on a light ground. A consumer never picks a separate
 * "dark" or "light" theme; the mode is an option, auto-detected from the
 * terminal background. A custom theme extends this to inherit both palettes, or
 * {@see AbstractTheme} for a neutral start.
 *
 * @package DrevOps\Tui\Theme
 */
class DefaultTheme extends AbstractTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function defineStyles(): array {
    $palette = $this->mode() === self::MODE_LIGHT ? $this->lightStyles() : $this->darkStyles();

    return $palette + parent::defineStyles();
  }

  /**
   * The dark-mode palette: bright accents for a dark background.
   *
   * @return array<string,string>
   *   The role => style-code overrides.
   */
  protected function darkStyles(): array {
    return [
      'title' => '1;36',
      'value' => '32',
      'marker' => '1;36',
      'indicator' => '1;33',
      'highlight' => '1;36',
      'border' => '36',
    ];
  }

  /**
   * The light-mode palette: darker accents that read on a light background.
   *
   * Mirrors the dark palette role for role, with the cyan and yellow accents -
   * which wash out on white - replaced by their darker blue and magenta analogs;
   * values stay green, as in dark mode.
   *
   * @return array<string,string>
   *   The role => style-code overrides.
   */
  protected function lightStyles(): array {
    return [
      'title' => '1;34',
      'value' => '32',
      'marker' => '1;34',
      'indicator' => '35',
      'highlight' => '1;34',
      'border' => '34',
    ];
  }

}
