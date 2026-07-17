<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

/**
 * The immutable form definition: a questionnaire and its own chrome.
 *
 * Carries what is asked (the panel tree), how the answers are namespaced, and
 * the form-specific chrome that frames it - its start banner and submit/cancel
 * buttons. It never describes the global TUI runtime (theme, key bindings,
 * colour, language); those are configured on the {@see \DrevOps\Tui\Tui}
 * facade and shared by every form an app runs.
 *
 * @package DrevOps\Tui\Model
 */
final readonly class FormDefinition {

  /**
   * Construct the form definition.
   *
   * @param string $title
   *   The application title.
   * @param string $subject
   *   The subject being configured (e.g. the project name).
   * @param \DrevOps\Tui\Model\Panel[] $panels
   *   The top-level panels.
   * @param \DrevOps\Tui\Model\Fixup[] $fixups
   *   Post-settle fix-up rules, evaluated by the engine.
   * @param string $envPrefix
   *   The prefix namespacing per-question env-variable overrides (empty for
   *   the facade default).
   * @param string $banner
   *   The start banner (logo) shown before the interactive TUI (optional).
   * @param \DrevOps\Tui\Model\Buttons $buttons
   *   The submit/cancel buttons the interactive TUI shows on the root panel.
   * @param list<int> $layout
   *   The top-level panel grid: one entry per visual row naming how many
   *   panels sit side by side in it, consumed in declaration order. Empty
   *   renders the panels as today's row list.
   */
  public function __construct(
    public string $title,
    public string $subject,
    public array $panels = [],
    public array $fixups = [],
    public string $envPrefix = '',
    public string $banner = '',
    public Buttons $buttons = new Buttons(),
    public array $layout = [],
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
   * @return \DrevOps\Tui\Model\Field[]
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
   * @param \DrevOps\Tui\Model\Panel[] $panels
   *   Panels to walk.
   * @param \DrevOps\Tui\Model\Field[] $fields
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
