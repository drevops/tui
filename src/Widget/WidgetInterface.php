<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A single interactive field collector driven one key at a time.
 *
 * @package DrevOps\Tui\Widget
 */
interface WidgetInterface {

  /**
   * Process one key press, mutating the widget state.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to process.
   */
  public function handle(Key $key): void;

  /**
   * Give the widget the resolved bindings for its scope.
   *
   * @param \DrevOps\Tui\Input\ScopedKeyMap $keys
   *   The scoped key bindings.
   *
   * @return static
   *   The widget, for chaining.
   */
  public function setKeys(ScopedKeyMap $keys): static;

  /**
   * Whether a valid value has been accepted.
   */
  public function isComplete(): bool;

  /**
   * Whether the widget was cancelled (Escape).
   */
  public function isCancelled(): bool;

  /**
   * The current value.
   *
   * @return mixed
   *   The typed value (string, string[] or bool depending on the widget).
   */
  public function value(): mixed;

  /**
   * The current validation error, if any.
   *
   * @return string|null
   *   The error message, or NULL when there is none.
   */
  public function error(): ?string;

  /**
   * A rendering of the current state, using the theme's glyphs.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme supplying Unicode or ASCII glyphs.
   *
   * @return string
   *   The rendered view.
   */
  public function view(ThemeInterface $theme): string;

  /**
   * Whether view() renders its own key-hint line.
   *
   * @return bool
   *   TRUE when the view already includes a hint line of its own.
   */
  public function rendersHint(): bool;

}
