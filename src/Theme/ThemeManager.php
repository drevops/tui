<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * The theme registry and factory, plus terminal capability detection.
 *
 * Themes are self-contained classes; this manager is how a config's theme
 * name becomes an instance. Built-in themes are pre-registered ("dark",
 * "light"), a consumer registers its own under a short name with
 * {@see register()}, and {@see create()} also accepts a fully-qualified
 * theme class name directly, so a one-off theme needs no registration at all.
 *
 * @package DrevOps\Tui\Theme
 */
final class ThemeManager {

  /**
   * The name => theme-class registry.
   *
   * @var array<string,class-string<\DrevOps\Tui\Theme\AbstractTheme>>
   */
  protected static array $registry = [
    'dark' => DarkTheme::class,
    'light' => LightTheme::class,
  ];

  /**
   * Register a theme class under a name so a config can select it.
   *
   * @param string $name
   *   The theme name.
   * @param string $class
   *   The theme class name.
   *
   * @throws \InvalidArgumentException
   *   When the class is not an AbstractTheme subclass - registration fails
   *   early rather than at the later create() call.
   */
  public static function register(string $name, string $class): void {
    if (!is_subclass_of($class, AbstractTheme::class)) {
      throw new \InvalidArgumentException(sprintf('Theme class "%s" must extend %s.', $class, AbstractTheme::class));
    }

    self::$registry[$name] = $class;
  }

  /**
   * Create a theme by name.
   *
   * Lowest friction first: a fully-qualified theme class name is instantiated
   * directly, so a config can point at a consumer's own theme class with no
   * registration. Otherwise the name is looked up in the registry ("dark",
   * "light", "default", or a name passed to {@see register()}). An unknown
   * name fails loudly - a typo should not silently render the default theme.
   *
   * @param string $name
   *   A registered name, a theme class name, or "default" for dark.
   * @param bool $color
   *   Whether colour is enabled.
   * @param int $width
   *   The frame width.
   * @param bool $unicode
   *   Whether Unicode glyphs are used (FALSE falls back to ASCII).
   *
   * @return \DrevOps\Tui\Theme\AbstractTheme
   *   The theme instance.
   *
   * @throws \InvalidArgumentException
   *   When the name is neither registered nor a theme class name.
   */
  public static function create(string $name = 'default', bool $color = TRUE, int $width = 76, bool $unicode = TRUE): AbstractTheme {
    $name = $name === '' || $name === 'default' ? 'dark' : $name;

    $class = self::$registry[$name] ?? (is_subclass_of($name, AbstractTheme::class) ? $name : NULL);

    if ($class === NULL) {
      throw new \InvalidArgumentException(sprintf('Unknown theme "%s". Use a registered name (%s), register one with ThemeManager::register(), or pass an AbstractTheme subclass name.', $name, implode(', ', array_keys(self::$registry))));
    }

    return new $class($color, $width, $unicode);
  }

  /**
   * Detect whether the environment advertises a Unicode-capable locale.
   *
   * The first set of LC_ALL, LC_CTYPE or LANG decides, and a "UTF" locale
   * enables Unicode. An unset locale falls back to ASCII.
   *
   * @return bool
   *   TRUE when a UTF locale is advertised.
   */
  public static function detectUnicode(): bool {
    foreach (['LC_ALL', 'LC_CTYPE', 'LANG'] as $var) {
      $value = getenv($var);
      if (is_string($value) && $value !== '') {
        return stripos($value, 'utf') !== FALSE;
      }
    }

    return FALSE;
  }

  /**
   * Detect whether the environment supports ANSI colour.
   *
   * Honours the NO_COLOR convention and the "dumb" terminal.
   *
   * @return bool
   *   TRUE unless NO_COLOR is set or TERM is "dumb".
   */
  public static function detectColor(): bool {
    if (getenv('NO_COLOR') !== FALSE) {
      return FALSE;
    }

    return getenv('TERM') !== 'dumb';
  }

  /**
   * Detect the theme name that best matches the terminal background.
   *
   * Resolves in order: the OSC 11 background reply (when one was captured),
   * then the COLORFGBG environment variable, then a dark default. Always
   * returns a built-in theme name.
   *
   * @param string|null $osc_response
   *   The raw OSC 11 reply bytes, or NULL when the terminal was not queried or
   *   did not answer.
   *
   * @return string
   *   "dark" or "light".
   */
  public static function detectTheme(?string $osc_response = NULL): string {
    if (is_string($osc_response)) {
      $name = self::themeFromOsc($osc_response);

      if ($name !== NULL) {
        return $name;
      }
    }

    return self::themeFromColorFgBg() ?? 'dark';
  }

  /**
   * Derive a theme name from an OSC 11 background-colour reply.
   *
   * A terminal answers with a payload such as "rgb:rrrr/gggg/bbbb" whose three
   * channels each carry 1 to 4 hex digits (an "rgba:" prefix is also seen).
   * The background's relative luminance selects the theme.
   *
   * @param string $response
   *   The raw reply bytes.
   *
   * @return string|null
   *   "dark" or "light", or NULL when the reply holds no parseable colour.
   */
  protected static function themeFromOsc(string $response): ?string {
    if (preg_match('#rgba?:([0-9a-f]{1,4})/([0-9a-f]{1,4})/([0-9a-f]{1,4})#i', $response, $matches) !== 1) {
      return NULL;
    }

    $is_dark = self::luminanceIsDark(self::channel($matches[1]), self::channel($matches[2]), self::channel($matches[3]));

    return $is_dark ? 'dark' : 'light';
  }

  /**
   * Scale a 1-4 hex-digit colour channel to the 0-255 range.
   *
   * @param string $hex
   *   The channel as 1 to 4 hexadecimal digits.
   *
   * @return int
   *   The channel value, 0-255.
   */
  protected static function channel(string $hex): int {
    $max = (2 ** (4 * strlen($hex))) - 1;

    return (int) round((int) hexdec($hex) / $max * 255);
  }

  /**
   * Whether an RGB colour reads as a dark background.
   *
   * Uses the Rec. 709 relative-luminance coefficients; a colour below the
   * midpoint of the range is dark.
   *
   * @param int $r
   *   Red, 0-255.
   * @param int $g
   *   Green, 0-255.
   * @param int $b
   *   Blue, 0-255.
   *
   * @return bool
   *   TRUE when the colour is dark.
   */
  protected static function luminanceIsDark(int $r, int $g, int $b): bool {
    return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) < 128;
  }

  /**
   * Derive a theme name from the COLORFGBG environment variable.
   *
   * Some terminals export "fg;bg" (or "fg;decoration;bg") as palette indices.
   * The background is the last field; indices 0-6 and 8 are the dark half of
   * the standard sixteen-colour palette, the rest light.
   *
   * @return string|null
   *   "dark" or "light", or NULL when COLORFGBG is unset or its background is
   *   not a palette index.
   */
  protected static function themeFromColorFgBg(): ?string {
    $value = getenv('COLORFGBG');

    if (!is_string($value) || $value === '') {
      return NULL;
    }

    $parts = explode(';', $value);
    $background = (string) end($parts);

    if (!ctype_digit($background)) {
      return NULL;
    }

    return in_array((int) $background, [0, 1, 2, 3, 4, 5, 6, 8], TRUE) ? 'dark' : 'light';
  }

}
