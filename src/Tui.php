<?php

declare(strict_types=1);

namespace DrevOps\Tui;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Engine\Engine;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Resolver\InputResolver;
use DrevOps\Tui\Schema\AgentHelp;
use DrevOps\Tui\Schema\SchemaGenerator;
use DrevOps\Tui\Schema\SchemaValidator;
use DrevOps\Tui\Render\PanelController;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\ThemeManager;
use DrevOps\Tui\Translation\Translator;

/**
 * The one-class entry point for collecting a form's answers.
 *
 * Wraps the engine, input resolver, schema tools and panel TUI so a consumer
 * can collect answers - headlessly or interactively - in a single call. It also
 * owns the global TUI runtime shared by every form: the theme, key bindings,
 * colour and glyph forcing, the key-hint footer, screen clearing and the active
 * language, each set through a fluent setter. Those internals stay reachable
 * via form(), engine() and registry() when a consumer wants finer control.
 *
 * @package DrevOps\Tui
 */
final class Tui {

  /**
   * The handler registry.
   */
  protected HandlerRegistry $registry;

  /**
   * The engine.
   */
  protected Engine $engine;

  /**
   * The effective env-variable prefix for per-question overrides.
   */
  protected string $envPrefix;

  /**
   * The form definition.
   */
  protected FormDefinition $form;

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
   * The resolved key bindings; NULL uses the default preset.
   */
  protected ?KeyMap $keymap = NULL;

  /**
   * Force ANSI colour on/off; NULL auto-detects.
   */
  protected ?bool $color = NULL;

  /**
   * Force Unicode/ASCII glyphs; NULL auto-detects.
   */
  protected ?bool $unicode = NULL;

  /**
   * Expand the interactive TUI to the whole terminal; NULL leaves the option
   * to the theme options.
   */
  protected ?bool $fullscreen = NULL;

  /**
   * Whether the interactive TUI shows the contextual key-hint footer.
   */
  protected bool $footer = TRUE;

  /**
   * Whether to clear the screen when the interactive TUI exits.
   */
  protected bool $clearOnExit = TRUE;

  /**
   * The translator localizing chrome and questions (NULL leaves English).
   */
  protected ?Translator $translator = NULL;

  /**
   * Construct a TUI.
   *
   * @param \DrevOps\Tui\Model\FormDefinition|\DrevOps\Tui\Builder\Form $form
   *   The form: a Form builder (built internally) or its built definition.
   * @param string[] $handler_namespaces
   *   Namespaces searched, in order, for per-field consumer classes offering
   *   reusable static validate()/transform() behaviour.
   * @param string $env_prefix
   *   The env-variable prefix for per-question overrides; wins over the
   *   form-declared prefix, which wins over the "TUI_" default.
   */
  public function __construct(FormDefinition|Form $form, array $handler_namespaces = [], string $env_prefix = '') {
    $this->form = $form instanceof Form ? $form->build() : $form;
    $this->envPrefix = $env_prefix !== '' ? $env_prefix : ($this->form->envPrefix !== '' ? $this->form->envPrefix : 'TUI_');
    $this->registry = new HandlerRegistry($handler_namespaces);
    $this->engine = new Engine($this->form, $this->registry);
  }

  /**
   * Set the interactive theme name and its display options.
   *
   * @param string $theme
   *   The theme name or class. Empty (or "auto") auto-detects light/dark from
   *   the terminal background.
   * @param array<string,mixed> $options
   *   Display options for the theme, keyed by name - e.g.
   *   `['spacing' => Spacing::Padded, 'border' => Border::Rounded]` - plus any
   *   a custom theme reads.
   *
   * @return $this
   *   The facade.
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
   * a preset class); each override is a {@see \DrevOps\Tui\Input\Binding}
   * naming a scope, an action and its keys, retuning it on top of the preset.
   * Conflicting, un-typeable or malformed bindings throw here, not mid-session.
   *
   * @param string $preset
   *   The preset name or class. Empty selects the default preset.
   * @param list<\DrevOps\Tui\Input\Binding> $overrides
   *   Bindings applied on top of the preset.
   *
   * @return $this
   *   The facade.
   */
  public function keys(string $preset = '', array $overrides = []): self {
    $this->keymap = KeyMapManager::create($preset, $overrides);

    return $this;
  }

  /**
   * Force ANSI colour on or off.
   *
   * @param bool|null $color
   *   TRUE/FALSE to force, NULL to auto-detect.
   *
   * @return $this
   *   The facade.
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
   *   The facade.
   */
  public function unicode(?bool $unicode): self {
    $this->unicode = $unicode;

    return $this;
  }

  /**
   * Expand the interactive TUI to the whole terminal screen.
   *
   * Sugar for the "fullscreen" theme option: the frame stretches to the
   * terminal, bounded by the "max_width"/"max_height" options, and the content
   * anchors to the "halign"/"valign" alignments. Below the "min_width" (by
   * default measured from the content) or "min_height" options the TUI shows a
   * resize notice instead of a broken layout. Headless collection is
   * unaffected.
   *
   * @param bool $fullscreen
   *   Whether to expand to the whole terminal.
   *
   * @return $this
   *   The facade.
   */
  public function fullscreen(bool $fullscreen = TRUE): self {
    $this->fullscreen = $fullscreen;

    return $this;
  }

  /**
   * Set whether the contextual key-hint footer is shown.
   *
   * @param bool $show
   *   Whether to show the footer.
   *
   * @return $this
   *   The facade.
   */
  public function footer(bool $show): self {
    $this->footer = $show;

    return $this;
  }

  /**
   * Set whether to clear the screen when the TUI exits.
   *
   * @param bool $clear
   *   Whether to clear on exit.
   *
   * @return $this
   *   The facade.
   */
  public function clearOnExit(bool $clear): self {
    $this->clearOnExit = $clear;

    return $this;
  }

  /**
   * Set the translator localizing chrome and questions.
   *
   * The translator carries the active language and catalog directories and is
   * activated process-wide so `t()` resolves during a run. Without one, every
   * string renders in its English source.
   *
   * @param \DrevOps\Tui\Translation\Translator $translator
   *   The translator.
   *
   * @return $this
   *   The facade.
   */
  public function translator(Translator $translator): self {
    $this->translator = $translator;

    return $this;
  }

  /**
   * Collect answers, interactively on a terminal or headlessly otherwise.
   *
   * Routes to interact() when no prompts are supplied and standard input is a
   * TTY, and to collect() otherwise. Pass $interactive to force a mode - for
   * example from a console framework's own interactivity detection.
   *
   * @param string $prompts
   *   Answers as a JSON string (or a path to a JSON file), empty for none.
   * @param string $version
   *   The version stamped into the context (and shown below the banner).
   * @param string $directory
   *   The target directory (defaults to the current working directory).
   * @param bool|null $interactive
   *   TRUE/FALSE to force the mode; NULL auto-detects from the prompts and
   *   the standard-input TTY.
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The collected answers.
   *
   * @throws \DrevOps\Tui\Engine\EngineException
   *   When the engine cannot process the configuration or answers.
   * @throws \DrevOps\Tui\InterruptException
   *   When the user aborts the interactive session with the interrupt key.
   */
  public function run(string $prompts = '', string $version = '', string $directory = '', ?bool $interactive = NULL): Answers {
    $interactive ??= $prompts === '' && defined('STDIN') && stream_isatty(STDIN);

    return $interactive ? $this->interact(version: $version, directory: $directory) : $this->collect($prompts, $directory, FALSE, $version);
  }

  /**
   * Collect answers non-interactively from a JSON payload and the environment.
   *
   * @param string $prompts
   *   Answers as a JSON string (or empty to rely on defaults and environment).
   * @param string $directory
   *   The target directory (defaults to the current working directory).
   * @param bool $update
   *   Whether to enable discovery against an existing project.
   * @param string $version
   *   The version stamped into the context.
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The collected answers.
   */
  public function collect(string $prompts = '', string $directory = '', bool $update = FALSE, string $version = ''): Answers {
    // Restore this facade's language at the operation boundary: another facade
    // constructed or configured meanwhile may have replaced the shared one.
    Translator::setShared($this->translator);
    $inputs = (new InputResolver($this->envPrefix))->resolve($this->form->fields(), $prompts, getenv());

    return $this->engine->collect($inputs, $this->context($directory, $update, $version));
  }

  /**
   * Collect answers interactively through the panel TUI.
   *
   * @param string $theme
   *   The theme name or class. Empty falls back to the facade's theme; an empty
   *   facade theme (or "auto") auto-detects light/dark from the terminal
   *   background.
   * @param string $banner
   *   An optional start banner.
   * @param string $version
   *   An optional version shown below the banner and stamped into the context.
   * @param string $directory
   *   The target directory (defaults to the current working directory).
   * @param \DrevOps\Tui\Render\Terminal|null $terminal
   *   The terminal to drive (defaults to a real one).
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The collected answers.
   *
   * @throws \DrevOps\Tui\Engine\EngineException
   *   When the engine cannot process the configuration or answers.
   * @throws \DrevOps\Tui\InterruptException
   *   When the user aborts the interactive session with the interrupt key.
   */
  public function interact(string $theme = '', string $banner = '', string $version = '', string $directory = '', ?Terminal $terminal = NULL): Answers {
    if (!$terminal instanceof Terminal) {
      // @codeCoverageIgnoreStart
      $terminal = new Terminal();
      // @codeCoverageIgnoreEnd
    }

    // The theme's display options (colour, Unicode, mode) come from the facade
    // when set, otherwise they are auto-detected from the terminal.
    $options = $this->resolveThemeOptions($terminal);

    // A fullscreen frame lays out to the terminal's width (the theme caps it
    // with "max_width"); the width is fixed for the session, like the theme.
    $width = ($options['fullscreen'] ?? FALSE) === TRUE ? $terminal->width() : DefaultTheme::DEFAULT_WIDTH;

    $controller = $this->controller($options, $theme, $banner, $version, $directory, $width);

    $answers = $controller->run($terminal);

    // An interrupt is an abort, not a submit: surface it so the partial answers
    // collected before the abort are never mistaken for a completed form.
    if ($controller->isInterrupted()) {
      throw new InterruptException('The interactive session was interrupted.');
    }

    return $answers;
  }

  /**
   * Build the interactive panel controller for the resolved display options.
   *
   * Shared by interact() and the test harness: it settles the engine's answers,
   * resolves the theme and banner, and wires the controller - so a caller that
   * supplies its own terminal (a real one, or a scripted one for tests) can run
   * the interactive loop against it.
   *
   * @param array<string,mixed> $options
   *   The resolved theme display options (colour, Unicode, mode).
   * @param string $theme
   *   The theme name or class; empty falls back to the facade's theme.
   * @param string $banner
   *   An optional start banner; empty falls back to the form's banner.
   * @param string $version
   *   An optional version shown below the banner and stamped into the context.
   * @param string $directory
   *   The target directory (defaults to the current working directory).
   * @param int $width
   *   The frame width the theme lays out to (the terminal width when
   *   fullscreen is on).
   *
   * @return \DrevOps\Tui\Render\PanelController
   *   The controller, ready to run against a terminal.
   *
   * @internal
   *   Public for the {@see \DrevOps\Tui\Testing\TuiTester} harness; consumers
   *   collect through run(), collect() or interact().
   */
  public function controller(array $options, string $theme = '', string $banner = '', string $version = '', string $directory = '', int $width = DefaultTheme::DEFAULT_WIDTH): PanelController {
    // Restore this facade's language before rendering (see collect()).
    Translator::setShared($this->translator);
    $answers = $this->engine->collect([], $this->context($directory, FALSE, $version));

    $banner_text = $banner !== '' ? $banner : $this->form->banner;

    return new PanelController(
      $this->form,
      ThemeManager::create($this->resolveTheme($theme), $width, $options),
      $this->keymap ?? KeyMapManager::create(),
      $this->footer,
      $this->clearOnExit,
      $answers->values,
      $answers->provenance,
      $banner_text,
      $version,
    );
  }

  /**
   * The JSON schema describing the questions.
   *
   * @return array<string,mixed>
   *   The schema.
   */
  public function schema(): array {
    return (new SchemaGenerator($this->form))->generate();
  }

  /**
   * Agent-facing help for driving the form non-interactively.
   *
   * @return string
   *   The help text.
   */
  public function agentHelp(): string {
    return (new AgentHelp($this->form, $this->envPrefix))->generate();
  }

  /**
   * Validate an answer set against the schema.
   *
   * @param array<string,mixed> $answers
   *   The answers to validate.
   *
   * @return list<string>
   *   The validation errors (empty when valid).
   */
  public function validate(array $answers): array {
    return (new SchemaValidator($this->form))->validate($answers);
  }

  /**
   * The form definition.
   *
   * @return \DrevOps\Tui\Model\FormDefinition
   *   The form definition.
   */
  public function form(): FormDefinition {
    return $this->form;
  }

  /**
   * The engine.
   *
   * @return \DrevOps\Tui\Engine\Engine
   *   The engine.
   */
  public function engine(): Engine {
    return $this->engine;
  }

  /**
   * The handler registry.
   *
   * @return \DrevOps\Tui\Handler\HandlerRegistry
   *   The handler registry.
   */
  public function registry(): HandlerRegistry {
    return $this->registry;
  }

  /**
   * Resolve the interactive theme name.
   *
   * The argument wins over the facade's theme; an empty result or the explicit
   * "auto" sentinel selects the default theme. The dark/light mode is a display
   * option resolved separately, no longer a theme choice.
   *
   * @param string $theme
   *   The theme argument (empty to fall back to the facade's theme).
   *
   * @return string
   *   The resolved theme name.
   */
  protected function resolveTheme(string $theme): string {
    $name = $theme !== '' ? $theme : $this->theme;

    return $name === '' || $name === 'auto' ? 'default' : $name;
  }

  /**
   * Build the theme's display options, auto-detecting what the consumer omits.
   *
   * The facade's options win; anything unset for colour, Unicode and the
   * dark/light mode is filled from the detected terminal capabilities. The mode
   * follows the background only when colour is on - with colour off the palette
   * is invisible, so the background query is skipped.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal queried for its background during detection.
   *
   * @return array<string,mixed>
   *   The resolved options.
   */
  protected function resolveThemeOptions(Terminal $terminal): array {
    $options = $this->themeOptions;

    if (!isset($options['color'])) {
      $options['color'] = $this->color ?? Terminal::detectColor();
    }

    if (!isset($options['unicode'])) {
      $options['unicode'] = $this->unicode ?? Terminal::detectUnicode();
    }

    if (!isset($options['mode'])) {
      $options['mode'] = $options['color'] ? Terminal::detectMode($terminal->queryBackground()) : Mode::Dark;
    }

    if (!isset($options['fullscreen']) && $this->fullscreen !== NULL) {
      $options['fullscreen'] = $this->fullscreen;
    }

    return $options;
  }

  /**
   * Build a run context for the target directory.
   *
   * @param string $directory
   *   The target directory (empty for the current working directory).
   * @param bool $update
   *   Whether discovery is enabled.
   * @param string $version
   *   The version stamped into the context.
   *
   * @return \DrevOps\Tui\Handler\Context
   *   The context.
   */
  protected function context(string $directory, bool $update, string $version): Context {
    return new Context($directory !== '' ? $directory : (string) getcwd(), [], $update, $version);
  }

}
