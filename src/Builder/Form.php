<?php

declare(strict_types=1);

namespace DrevOps\Tui\Builder;

use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Model\Fixup;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\Panel;

/**
 * A fluent builder declaring a form: its panels, fields and own chrome.
 *
 * Declares only what belongs to a specific questionnaire - its title, panels
 * and fields, its start banner and submit/cancel buttons, its fix-ups and
 * env-variable prefix. The global TUI runtime (theme, key bindings, colour,
 * language) is configured on the {@see \DrevOps\Tui\Tui} facade, not here.
 *
 * @package DrevOps\Tui\Builder
 */
final class Form {

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
   * The prefix namespacing per-question env-variable overrides.
   */
  protected string $envPrefix = '';

  /**
   * The post-settle fix-up rules.
   *
   * @var \DrevOps\Tui\Model\Fixup[]
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
   * @param \DrevOps\Tui\Model\Fixup $fixup
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
   * Build the immutable form definition.
   *
   * @return \DrevOps\Tui\Model\FormDefinition
   *   The form definition.
   */
  public function build(): FormDefinition {
    $panels = array_map(static fn(PanelBuilder $panel): Panel => $panel->build(), $this->panels);

    $form = new FormDefinition(
      $this->title,
      $this->subject,
      $panels,
      $this->fixups,
      $this->envPrefix,
      $this->banner,
      $this->buttons,
      $this->submitLabel,
      $this->cancelLabel,
    );

    $this->assertUniqueFieldIds($form);
    $this->assertToggleOptions($form);
    $this->assertReorderOptions($form);

    return $form;
  }

  /**
   * Assert that every field id is unique across the panel tree.
   *
   * @param \DrevOps\Tui\Model\FormDefinition $form
   *   The built form definition.
   */
  protected function assertUniqueFieldIds(FormDefinition $form): void {
    $seen = [];

    foreach ($form->fields() as $field) {
      if (isset($seen[$field->id])) {
        throw new FormException(sprintf('Duplicate field id "%s".', $field->id));
      }

      $seen[$field->id] = TRUE;
    }
  }

  /**
   * Assert that every toggle field declares exactly two options.
   *
   * @param \DrevOps\Tui\Model\FormDefinition $form
   *   The built form definition.
   */
  protected function assertToggleOptions(FormDefinition $form): void {
    foreach ($form->fields() as $field) {
      if ($field->type !== FieldType::Toggle) {
        continue;
      }

      if (count($field->options) !== 2) {
        throw new FormException(sprintf('Toggle field "%s" must have exactly two options, %d given.', $field->id, count($field->options)));
      }

      // A dynamic default is a closure resolved at runtime; every literal
      // default - whatever its type - must be one of the two option values,
      // otherwise the widget would silently coerce it and select the first.
      if ($field->default instanceof \Closure) {
        continue;
      }

      $values = array_map(static fn(Option $option): string => $option->value, $field->options);

      if (!is_string($field->default) || !in_array($field->default, $values, TRUE)) {
        throw new FormException(sprintf('Toggle field "%s" default must be one of: %s.', $field->id, implode(', ', $values)));
      }
    }
  }

  /**
   * Assert that every reorder field declares at least two plain options.
   *
   * A ranking arranges a flat list, so headings, separators and disabled rows
   * have no place in it, and fewer than two items is nothing to reorder.
   *
   * @param \DrevOps\Tui\Model\FormDefinition $form
   *   The built form definition.
   */
  protected function assertReorderOptions(FormDefinition $form): void {
    foreach ($form->fields() as $field) {
      if ($field->type !== FieldType::Reorder) {
        continue;
      }

      foreach ($field->options as $option) {
        if (!$option->selectable()) {
          throw new FormException(sprintf('Reorder field "%s" allows only plain options - no headings, separators or disabled rows.', $field->id));
        }
      }

      if (count($field->options) < 2) {
        throw new FormException(sprintf('Reorder field "%s" must have at least two options, %d given.', $field->id, count($field->options)));
      }
    }
  }

}
