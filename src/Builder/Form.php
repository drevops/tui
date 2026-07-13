<?php

declare(strict_types=1);

namespace DrevOps\Tui\Builder;

use DrevOps\Tui\Config\Config;
use DrevOps\Tui\Config\ConfigException;
use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Config\Fixup;
use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Config\Panel;
use DrevOps\Tui\Input\KeyMapManager;

/**
 * A fluent builder declaring a form: its panels, fields and TUI options.
 *
 * @package DrevOps\Tui\Builder
 */
final class Form {

  /**
   * The theme name or class (empty for the default).
   */
  protected string $theme = '';

  /**
   * Display options passed to the interactive theme.
   *
   * @var array<string,mixed>
   */
  protected array $themeOptions = [];

  /**
   * The key-map preset name or class (empty for the default).
   */
  protected string $keymap = '';

  /**
   * Bindings overriding the preset, applied on top of it.
   *
   * @var list<\DrevOps\Tui\Input\Binding>
   */
  protected array $keymapOverrides = [];

  /**
   * The start banner (logo).
   */
  protected string $banner = '';

  /**
   * Whether the interactive TUI shows submit and cancel buttons.
   */
  protected bool $buttons = TRUE;

  /**
   * The submit button label.
   */
  protected string $submitLabel = 'Submit';

  /**
   * The cancel button label.
   */
  protected string $cancelLabel = 'Cancel';

  /**
   * Whether to clear the screen when the interactive TUI exits.
   */
  protected bool $clearOnExit = TRUE;

  /**
   * Whether the interactive TUI shows the contextual key-hint footer.
   */
  protected bool $footer = TRUE;

  /**
   * Force ANSI colour on/off; NULL auto-detects.
   */
  protected ?bool $color = NULL;

  /**
   * Force Unicode/ASCII glyphs; NULL auto-detects.
   */
  protected ?bool $unicode = NULL;

  /**
   * The prefix namespacing per-question env-variable overrides.
   */
  protected string $envPrefix = '';

  /**
   * The post-settle fix-up rules.
   *
   * @var \DrevOps\Tui\Config\Fixup[]
   */
  protected array $fixups = [];

  /**
   * The top-level panel builders, in declaration order.
   *
   * @var \DrevOps\Tui\Builder\PanelBuilder[]
   */
  protected array $panels = [];

  /**
   * Construct a form builder.
   *
   * @param string $title
   *   The application title.
   * @param string $subject
   *   The subject being configured.
   */
  protected function __construct(protected string $title, protected string $subject) {
  }

  /**
   * Create a form builder.
   *
   * @param string $title
   *   The application title.
   * @param string $subject
   *   The subject being configured.
   *
   * @return self
   *   The builder.
   */
  public static function create(string $title, string $subject = ''): self {
    return new self($title, $subject);
  }

  /**
   * Set the theme name or class.
   *
   * @param string $theme
   *   The theme name or class. Empty (or "auto") auto-detects light/dark from
   *   the terminal background.
   * @param array<string,mixed> $options
   *   Display options for the theme, keyed by name - e.g.
   *   `['spacing' => ThemeInterface::SPACING_PADDED, 'border' =>
   *   ThemeInterface::BORDER_ROUNDED]` - plus any a custom theme reads.
   *
   * @return $this
   *   The builder.
   */
  public function theme(string $theme, array $options = []): self {
    $this->theme = $theme;
    $this->themeOptions = $options;

    return $this;
  }

  /**
   * Set the key-binding preset and optional overrides.
   *
   * The preset names the base bindings ("default", "vim", a registered name, or
   * a preset class); the overrides retune individual bindings on top of it,
   * each a {@see \DrevOps\Tui\Input\Binding} naming a scope, an action and its
   * keys. Conflicting, un-typeable or malformed bindings throw when the form is
   * built, not mid-session.
   *
   * @param string $preset
   *   The preset name or class. Empty selects the default preset.
   * @param list<\DrevOps\Tui\Input\Binding> $overrides
   *   Bindings applied on top of the preset.
   *
   * @return $this
   *   The builder.
   */
  public function keys(string $preset = '', array $overrides = []): self {
    $this->keymap = $preset;
    $this->keymapOverrides = $overrides;

    return $this;
  }

  /**
   * Set the start banner.
   *
   * @param string $banner
   *   The banner (logo).
   *
   * @return $this
   *   The builder.
   */
  public function banner(string $banner): self {
    $this->banner = $banner;

    return $this;
  }

  /**
   * Configure the submit and cancel buttons.
   *
   * @param bool $show
   *   Whether to show the buttons.
   * @param string $submit_label
   *   The submit button label.
   * @param string $cancel_label
   *   The cancel button label.
   *
   * @return $this
   *   The builder.
   */
  public function buttons(bool $show, string $submit_label = 'Submit', string $cancel_label = 'Cancel'): self {
    $this->buttons = $show;
    $this->submitLabel = $submit_label;
    $this->cancelLabel = $cancel_label;

    return $this;
  }

  /**
   * Set whether to clear the screen when the TUI exits.
   *
   * @param bool $clear
   *   Whether to clear on exit.
   *
   * @return $this
   *   The builder.
   */
  public function clearOnExit(bool $clear): self {
    $this->clearOnExit = $clear;

    return $this;
  }

  /**
   * Set whether the contextual key-hint footer is shown.
   *
   * @param bool $show
   *   Whether to show the footer.
   *
   * @return $this
   *   The builder.
   */
  public function footer(bool $show): self {
    $this->footer = $show;

    return $this;
  }

  /**
   * Force ANSI colour on or off.
   *
   * @param bool|null $color
   *   TRUE/FALSE to force, NULL to auto-detect.
   *
   * @return $this
   *   The builder.
   */
  public function color(?bool $color): self {
    $this->color = $color;

    return $this;
  }

  /**
   * Force Unicode or ASCII glyphs.
   *
   * @param bool|null $unicode
   *   TRUE/FALSE to force, NULL to auto-detect.
   *
   * @return $this
   *   The builder.
   */
  public function unicode(?bool $unicode): self {
    $this->unicode = $unicode;

    return $this;
  }

  /**
   * Set the prefix namespacing per-question env-variable overrides.
   *
   * @param string $prefix
   *   The env-variable prefix (e.g. "MYAPP_"); empty for the facade default.
   *
   * @return $this
   *   The builder.
   */
  public function envPrefix(string $prefix): self {
    $this->envPrefix = $prefix;

    return $this;
  }

  /**
   * Add a post-settle fix-up rule.
   *
   * @param \DrevOps\Tui\Config\Fixup $fixup
   *   The fix-up, evaluated by the engine.
   *
   * @return $this
   *   The builder.
   */
  public function fixup(Fixup $fixup): self {
    $this->fixups[] = $fixup;

    return $this;
  }

  /**
   * Add a top-level panel.
   *
   * @param string $id
   *   The panel id.
   * @param string $title
   *   The panel title.
   * @param \Closure $build
   *   The callback receiving the panel builder.
   *
   * @return $this
   *   The builder.
   */
  public function panel(string $id, string $title, \Closure $build): self {
    $panel = new PanelBuilder($id, $title);
    $build($panel);
    $this->panels[] = $panel;

    return $this;
  }

  /**
   * Build the immutable Config model.
   *
   * @return \DrevOps\Tui\Config\Config
   *   The config.
   */
  public function build(): Config {
    $panels = array_map(static fn(PanelBuilder $panel): Panel => $panel->build(), $this->panels);

    $config = new Config(
      $this->title,
      $this->subject,
      $panels,
      $this->fixups,
      $this->theme,
      $this->banner,
      $this->buttons,
      $this->submitLabel,
      $this->cancelLabel,
      $this->clearOnExit,
      $this->color,
      $this->unicode,
      $this->envPrefix,
      $this->themeOptions,
      KeyMapManager::create($this->keymap, $this->keymapOverrides),
      $this->footer,
    );

    $this->assertUniqueFieldIds($config);
    $this->assertToggleOptions($config);

    return $config;
  }

  /**
   * Assert that every field id is unique across the panel tree.
   *
   * @param \DrevOps\Tui\Config\Config $config
   *   The built config.
   */
  protected function assertUniqueFieldIds(Config $config): void {
    $seen = [];

    foreach ($config->fields() as $field) {
      if (isset($seen[$field->id])) {
        throw new ConfigException(sprintf('Duplicate field id "%s".', $field->id));
      }

      $seen[$field->id] = TRUE;
    }
  }

  /**
   * Assert that every toggle field declares exactly two options.
   *
   * @param \DrevOps\Tui\Config\Config $config
   *   The built config.
   */
  protected function assertToggleOptions(Config $config): void {
    foreach ($config->fields() as $field) {
      if ($field->type !== FieldType::Toggle) {
        continue;
      }

      if (count($field->options) !== 2) {
        throw new ConfigException(sprintf('Toggle field "%s" must have exactly two options, %d given.', $field->id, count($field->options)));
      }

      // A dynamic default is a closure resolved at runtime; every literal
      // default - whatever its type - must be one of the two option values,
      // otherwise the widget would silently coerce it and select the first.
      if ($field->default instanceof \Closure) {
        continue;
      }

      $values = array_map(static fn(Option $option): string => $option->value, $field->options);

      if (!is_string($field->default) || !in_array($field->default, $values, TRUE)) {
        throw new ConfigException(sprintf('Toggle field "%s" default must be one of: %s.', $field->id, implode(', ', $values)));
      }
    }
  }

}
