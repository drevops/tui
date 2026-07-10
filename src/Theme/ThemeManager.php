<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * The theme registry and factory.
 *
 * Themes are self-contained classes; this manager is how a config's theme name
 * becomes an instance. The built-in theme is pre-registered ("default"), a
 * consumer registers its own under a short name with {@see register()}, and
 * {@see create()} also accepts a fully-qualified theme class name directly, so
 * a one-off theme needs no registration at all. Terminal-capability detection
 * (colour, Unicode, dark/light background) lives on
 * {@see \DrevOps\Tui\Render\Terminal}.
 *
 * @package DrevOps\Tui\Theme
 */
final class ThemeManager {

  /**
   * The name => theme-class registry.
   *
   * @var array<string,class-string<\DrevOps\Tui\Theme\DefaultTheme>>
   */
  protected static array $registry = [
    'default' => DefaultTheme::class,
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
   *   When the class is not a DefaultTheme (or a subclass) - registration fails
   *   early rather than at the later create() call.
   */
  public static function register(string $name, string $class): void {
    if (!is_a($class, DefaultTheme::class, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Theme class "%s" must extend %s.', $class, DefaultTheme::class));
    }

    self::$registry[$name] = $class;
  }

  /**
   * Create a theme by name.
   *
   * Lowest friction first: a fully-qualified theme class name is instantiated
   * directly, so a config can point at a consumer's own theme class with no
   * registration. Otherwise the name is looked up in the registry ("default" or
   * a name passed to {@see register()}). An unknown name fails loudly - a typo
   * should not silently render the default theme. Colour, Unicode and the
   * dark/light mode are display options carried in $options.
   *
   * @param string $name
   *   A registered name, a theme class name, or "" for the default theme.
   * @param int $width
   *   The frame width.
   * @param array<string,mixed> $options
   *   Display options passed to the theme (e.g. "mode", "color", "unicode",
   *   "spacing", "border").
   *
   * @return \DrevOps\Tui\Theme\DefaultTheme
   *   The theme instance.
   *
   * @throws \InvalidArgumentException
   *   When the name is neither registered nor a theme class name.
   */
  public static function create(string $name = 'default', int $width = 76, array $options = []): DefaultTheme {
    $name = $name === '' ? 'default' : $name;

    $class = self::$registry[$name] ?? (is_a($name, DefaultTheme::class, TRUE) ? $name : NULL);

    if ($class === NULL) {
      throw new \InvalidArgumentException(sprintf('Unknown theme "%s". Use a registered name (%s), register one with ThemeManager::register(), or pass a DefaultTheme subclass name.', $name, implode(', ', array_keys(self::$registry))));
    }

    return new $class($width, $options);
  }

}
