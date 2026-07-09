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
   * @param class-string<\DrevOps\Tui\Theme\AbstractTheme> $class
   *   The theme class.
   */
  public static function register(string $name, string $class): void {
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

}
