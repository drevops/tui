<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
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
   * The fuzzy matcher, created on first use.
   */
  protected ?Matcher $matcher = NULL;

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
  public function hints(): array {
    return [new Hint('accept', Action::Accept), new Hint('cancel', Action::Cancel)];
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
   * Accept the live value when the key triggers the accept action.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to test.
   *
   * @return bool
   *   TRUE when the key triggered the accept - it is consumed whether or not
   *   the value passed validation.
   */
  protected function handleAccept(Key $key): bool {
    if ($this->keys()->matches($key, Action::Accept)) {
      $this->accept($this->liveValue());

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
   * The shared fuzzy matcher.
   *
   * @return \DrevOps\Tui\Widget\Matcher
   *   The matcher.
   */
  protected function matcher(): Matcher {
    return $this->matcher ??= new Matcher();
  }

  /**
   * Style an option label, emphasising the query-matched characters.
   *
   * The label is split into runs of matched and unmatched characters, each run
   * styled on its own so no SGR code nests inside another: matched runs get the
   * match colour, and on the cursor row the rest keeps the highlight colour.
   * With no matched positions this is exactly {@see highlightLabel()}.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param string $label
   *   The option label.
   * @param list<int> $positions
   *   The zero-based indices of the matched characters.
   * @param bool $current
   *   Whether the option's row holds the cursor.
   *
   * @return string
   *   The styled label.
   */
  protected function renderMatchedLabel(ThemeInterface $theme, string $label, array $positions, bool $current): string {
    if ($positions === []) {
      return $this->highlightLabel($theme, $label, $current);
    }

    $matched = array_fill_keys($positions, TRUE);
    $out = '';
    $run = '';
    $run_matched = FALSE;

    foreach (mb_str_split($label, 1, 'UTF-8') as $index => $char) {
      $is_matched = isset($matched[$index]);

      if ($run !== '' && $is_matched !== $run_matched) {
        $out .= $this->styleRun($theme, $run, $run_matched, $current);
        $run = '';
      }

      $run .= $char;
      $run_matched = $is_matched;
    }

    return $out . $this->styleRun($theme, $run, $run_matched, $current);
  }

  /**
   * Style one run of same-kind characters for {@see renderMatchedLabel()}.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param string $run
   *   The run of characters.
   * @param bool $matched
   *   Whether the run's characters matched the query.
   * @param bool $current
   *   Whether the option's row holds the cursor.
   *
   * @return string
   *   The styled run.
   */
  protected function styleRun(ThemeInterface $theme, string $run, bool $matched, bool $current): string {
    if ($matched) {
      return $theme->highlightMatch($run);
    }

    return $current ? $theme->highlight($run) : $run;
  }

  /**
   * Append the themed validation error line to a view, when an error is set.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param string $view
   *   The rendered view.
   *
   * @return string
   *   The view, with the error line appended when an error is present.
   */
  protected function withError(ThemeInterface $theme, string $view): string {
    return $this->error === NULL ? $view : $view . "\n" . $theme->error($this->error);
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
