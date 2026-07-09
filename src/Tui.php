<?php

declare(strict_types=1);

namespace DrevOps\Tui;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Config\Config;
use DrevOps\Tui\Engine\Engine;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Resolver\InputResolver;
use DrevOps\Tui\Schema\AgentHelp;
use DrevOps\Tui\Schema\SchemaGenerator;
use DrevOps\Tui\Schema\SchemaValidator;
use DrevOps\Tui\Render\PanelController;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Theme\ThemeManager;

/**
 * The one-class entry point for collecting a form's answers.
 *
 * Wraps the engine, input resolver, schema tools and panel TUI so a consumer
 * can collect answers - headlessly or interactively - in a single call,
 * without touching the internals. Those internals stay reachable via
 * config(), engine() and registry() when a consumer does want finer control.
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
   * The configuration.
   */
  protected Config $config;

  /**
   * Construct a TUI.
   *
   * @param \DrevOps\Tui\Config\Config|\DrevOps\Tui\Builder\Form $form
   *   The form: a Form builder (built internally) or its built Config.
   * @param string[] $handler_namespaces
   *   Namespaces searched, in order, for per-field consumer classes offering
   *   reusable static validate()/transform() behaviour.
   * @param string $env_prefix
   *   The env-variable prefix for per-question overrides; wins over the
   *   form-declared prefix, which wins over the "TUI_" default.
   */
  public function __construct(Config|Form $form, array $handler_namespaces = [], string $env_prefix = '') {
    $this->config = $form instanceof Form ? $form->build() : $form;
    $this->envPrefix = $env_prefix !== '' ? $env_prefix : ($this->config->envPrefix !== '' ? $this->config->envPrefix : 'TUI_');
    $this->registry = new HandlerRegistry($handler_namespaces);
    $this->engine = new Engine($this->config, $this->registry);
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
    $inputs = (new InputResolver($this->envPrefix))->resolve($this->config->fields(), $prompts, getenv());
    $this->engine->collect($inputs, $this->context($directory, $update, $version));

    return $this->engine->answers();
  }

  /**
   * Collect answers interactively through the panel TUI.
   *
   * @param string $theme
   *   The theme name or class. Empty falls back to the config's theme; an
   *   empty config theme (or "auto") auto-detects light/dark from the terminal
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
   */
  public function interact(string $theme = '', string $banner = '', string $version = '', string $directory = '', ?Terminal $terminal = NULL): Answers {
    // @codeCoverageIgnoreStart
    $this->engine->collect([], $this->context($directory, FALSE, $version));

    $terminal ??= new Terminal();

    // The banner comes from the argument, then the config. The theme's display
    // options (colour, Unicode, mode) come from the config when set, otherwise
    // they are auto-detected from the terminal.
    $banner_text = $banner !== '' ? $banner : $this->config->banner;

    $theme_name = $this->resolveTheme($theme);
    $options = $this->resolveThemeOptions($terminal);

    $controller = new PanelController(
      $this->config,
      ThemeManager::create($theme_name, 76, $options),
      $this->engine->answers()->values,
      $this->engine->answers()->provenance,
      $banner_text,
      $version,
    );

    return $controller->run($terminal);
    // @codeCoverageIgnoreEnd
  }

  /**
   * The JSON schema describing the questions.
   *
   * @return array<string,mixed>
   *   The schema.
   */
  public function schema(): array {
    return (new SchemaGenerator($this->config))->generate();
  }

  /**
   * Agent-facing help for driving the form non-interactively.
   *
   * @return string
   *   The help text.
   */
  public function agentHelp(): string {
    return (new AgentHelp($this->config, $this->envPrefix))->generate();
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
    return (new SchemaValidator($this->config))->validate($answers);
  }

  /**
   * The configuration.
   *
   * @return \DrevOps\Tui\Config\Config
   *   The configuration.
   */
  public function config(): Config {
    return $this->config;
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
   * The argument wins over the config theme; an empty result or the explicit
   * "auto" sentinel selects the default theme. The dark/light mode is a display
   * option resolved separately, no longer a theme choice.
   *
   * @param string $theme
   *   The theme argument (empty to fall back to the config theme).
   *
   * @return string
   *   The resolved theme name.
   */
  protected function resolveTheme(string $theme): string {
    $name = $theme !== '' ? $theme : $this->config->theme;

    return $name === '' || $name === 'auto' ? 'default' : $name;
  }

  /**
   * Build the theme's display options, auto-detecting what the consumer omits.
   *
   * The config's options win; anything unset for colour, Unicode and the
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
    $options = $this->config->themeOptions;

    if (!isset($options['color'])) {
      $options['color'] = $this->config->color ?? Terminal::detectColor();
    }

    if (!isset($options['unicode'])) {
      $options['unicode'] = $this->config->unicode ?? Terminal::detectUnicode();
    }

    if (!isset($options['mode'])) {
      $options['mode'] = $options['color'] ? Terminal::detectMode($terminal->queryBackground()) : ThemeInterface::MODE_DARK;
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
