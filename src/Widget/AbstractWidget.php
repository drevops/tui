<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Shared widget behaviour: accept/cancel, validation and transformation.
 *
 * @package DrevOps\Tui\Widget
 */
abstract class AbstractWidget implements WidgetInterface {

  /**
   * The resolved key bindings for this widget's scope.
   *
   * Injected by the widget factory; when a widget is constructed directly (for
   * a test or a one-off), it falls back to the default preset for its scope.
   */
  protected ?ScopedKeyMap $scoped = NULL;

  /**
   * Whether a valid value has been accepted.
   */
  protected bool $complete = FALSE;

  /**
   * Whether the widget was cancelled.
   */
  protected bool $cancelled = FALSE;

  /**
   * The current validation error, if any.
   */
  protected ?string $error = NULL;

  /**
   * The accepted, transformed value once complete.
   */
  protected mixed $accepted = NULL;

  /**
   * Construct a widget.
   *
   * @param \Closure|null $validate
   *   Optional validator `fn(mixed $value): ?string` returning an error message
   *   or NULL when the value is valid.
   * @param \Closure|null $transform
   *   Optional transformer `fn(mixed $value): mixed` applied on accept.
   */
  public function __construct(
    protected ?\Closure $validate = NULL,
    protected ?\Closure $transform = NULL,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function isComplete(): bool {
    return $this->complete;
  }

  /**
   * {@inheritdoc}
   */
  public function isCancelled(): bool {
    return $this->cancelled;
  }

  /**
   * {@inheritdoc}
   */
  public function error(): ?string {
    return $this->error;
  }

  /**
   * {@inheritdoc}
   */
  public function value(): mixed {
    return $this->complete ? $this->accepted : $this->liveValue();
  }

  /**
   * {@inheritdoc}
   */
  public function rendersHint(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setKeys(ScopedKeyMap $keys): static {
    $this->scoped = $keys;

    return $this;
  }

  /**
   * The in-progress value before acceptance.
   *
   * @return mixed
   *   The current, not-yet-accepted value.
   */
  abstract protected function liveValue(): mixed;

  /**
   * The scope whose default bindings apply when none are injected.
   *
   * Widgets whose bindings differ from the base defaults override this; the
   * base scope is the right fallback for the rest.
   *
   * @return \DrevOps\Tui\Input\Scope
   *   The widget's binding scope.
   */
  protected function keyScope(): Scope {
    return Scope::base();
  }

  /**
   * The resolved bindings for this widget, defaulting to the built-in preset.
   *
   * @return \DrevOps\Tui\Input\ScopedKeyMap
   *   The scoped bindings.
   */
  protected function keys(): ScopedKeyMap {
    return $this->scoped ??= KeyMapManager::create()->scope($this->keyScope());
  }

  /**
   * Cancel the widget when the key triggers the cancel action.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to test.
   *
   * @return bool
   *   TRUE when the key cancelled the widget.
   */
  protected function handleCancel(Key $key): bool {
    if ($this->keys()->matches($key, Action::Cancel)) {
      $this->cancelled = TRUE;

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Style an option label, highlighted when its row holds the cursor.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param string $label
   *   The option label.
   * @param bool $current
   *   Whether the option's row holds the cursor.
   *
   * @return string
   *   The label, highlight-styled when current.
   */
  protected function highlightLabel(ThemeInterface $theme, string $label, bool $current): string {
    return $current ? $theme->highlight($label) : $label;
  }

  /**
   * Render a radio option row: the radio glyph plus the highlighted label.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param string $label
   *   The option label.
   * @param bool $current
   *   Whether the option's row holds the cursor.
   *
   * @return string
   *   The rendered row.
   */
  protected function renderRadioRow(ThemeInterface $theme, string $label, bool $current): string {
    return $theme->radio($current) . ' ' . $this->highlightLabel($theme, $label, $current);
  }

  /**
   * Validate and, when valid, transform a value and complete the widget.
   *
   * @param mixed $value
   *   The candidate value.
   *
   * @return bool
   *   TRUE when the value was accepted; FALSE when validation failed.
   */
  protected function accept(mixed $value): bool {
    $error = $this->validate instanceof \Closure ? ($this->validate)($value) : NULL;
    if (is_string($error) && $error !== '') {
      $this->error = $error;

      return FALSE;
    }

    $this->error = NULL;
    $this->accepted = $this->transform instanceof \Closure ? ($this->transform)($value) : $value;
    $this->complete = TRUE;

    return TRUE;
  }

}
