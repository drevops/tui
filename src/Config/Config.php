<?php

declare(strict_types=1);

namespace DrevOps\Tui\Config;

use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Translation\Translator;

/**
 * The root configuration model: title, subject and a tree of panels.
 *
 * @package DrevOps\Tui\Config
 */
final readonly class Config {

  /**
   * Construct the root config.
   *
   * @param string $title
   *   The application title.
   * @param string $subject
   *   The subject being configured (e.g. the project name).
   * @param \DrevOps\Tui\Config\Panel[] $panels
   *   The top-level panels.
   * @param \DrevOps\Tui\Config\Fixup[] $fixups
   *   Post-settle fix-up rules, evaluated by the engine.
   * @param string $theme
   *   The theme name or class for the interactive TUI. Empty (or "auto")
   *   auto-detects light/dark from the terminal background.
   * @param string $banner
   *   The start banner (logo) shown before the interactive TUI (optional).
   * @param bool $buttons
   *   Whether the interactive TUI shows submit and cancel buttons.
   * @param string $submitLabel
   *   The label of the submit button.
   * @param string $cancelLabel
   *   The label of the cancel button.
   * @param bool $clearOnExit
   *   Whether to clear the screen when the interactive TUI exits.
   * @param bool|null $color
   *   Force ANSI colour on/off in the interactive TUI; NULL auto-detects.
   * @param bool|null $unicode
   *   Force Unicode/ASCII glyphs in the interactive TUI; NULL auto-detects.
   * @param string $envPrefix
   *   The prefix namespacing per-question env-variable overrides (empty for
   *   the facade default).
   * @param array<string,mixed> $themeOptions
   *   Display options passed to the interactive theme, keyed by name (e.g.
   *   "spacing" and "border"; see ThemeInterface constants).
   * @param \DrevOps\Tui\Input\KeyMap|null $keymap
   *   The resolved key bindings for the interactive TUI; NULL uses the default
   *   preset. The Form builder resolves and validates this at build time.
   * @param bool $footer
   *   Whether the interactive TUI shows the contextual key-hint footer.
   * @param \DrevOps\Tui\Translation\Translator|null $translator
   *   The translator localizing chrome and questions; NULL leaves every string
   *   in its English source.
   */
  public function __construct(
    public string $title,
    public string $subject,
    public array $panels = [],
    public array $fixups = [],
    public string $theme = '',
    public string $banner = '',
    public bool $buttons = TRUE,
    public string $submitLabel = 'Submit',
    public string $cancelLabel = 'Cancel',
    public bool $clearOnExit = TRUE,
    public ?bool $color = NULL,
    public ?bool $unicode = NULL,
    public string $envPrefix = '',
    public array $themeOptions = [],
    public ?KeyMap $keymap = NULL,
    public bool $footer = TRUE,
    public ?Translator $translator = NULL,
  ) {
  }

  /**
   * Find a field by id anywhere in the panel tree.
   *
   * @param string $id
   *   The field id to find.
   */
  public function field(string $id): ?Field {
    foreach ($this->fields() as $field) {
      if ($field->id === $id) {
        return $field;
      }
    }

    return NULL;
  }

  /**
   * All fields flattened across the panel tree, in declaration order.
   *
   * @return \DrevOps\Tui\Config\Field[]
   *   The fields.
   */
  public function fields(): array {
    $fields = [];
    $this->collectFields($this->panels, $fields);

    return $fields;
  }

  /**
   * Recursively flatten fields from panels into an accumulator.
   *
   * @param \DrevOps\Tui\Config\Panel[] $panels
   *   Panels to walk.
   * @param \DrevOps\Tui\Config\Field[] $fields
   *   Accumulator, populated in place.
   */
  protected function collectFields(array $panels, array &$fields): void {
    foreach ($panels as $panel) {
      foreach ($panel->fields as $field) {
        $fields[] = $field;
      }

      $this->collectFields($panel->panels, $fields);
    }
  }

}
