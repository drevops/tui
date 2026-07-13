<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Translation\Translator;

/**
 * Builds the widget for a field, seeded with the field's current value.
 *
 * @package DrevOps\Tui\Widget
 */
class WidgetFactory {

  /**
   * The resolved key bindings to inject into each widget.
   */
  protected KeyMap $keymap;

  /**
   * Construct a widget factory.
   *
   * @param \DrevOps\Tui\Input\KeyMap|null $keymap
   *   The resolved key bindings; NULL uses the default preset.
   * @param bool $externalEditorAvailable
   *   Whether an external editor is launchable here. A textarea field opts in
   *   per-field; the handoff shows only when one is also available.
   */
  public function __construct(?KeyMap $keymap = NULL, protected bool $externalEditorAvailable = FALSE) {
    $this->keymap = $keymap ?? KeyMapManager::create();
  }

  /**
   * Create a widget for a field, wired with its scope's key bindings.
   *
   * @param \DrevOps\Tui\Config\Field $field
   *   The field.
   * @param mixed $current
   *   The current value to seed the widget with.
   * @param array<string,mixed> $answers
   *   The answers collected so far, passed to a text completion closure.
   *
   * @return \DrevOps\Tui\Widget\WidgetInterface
   *   The widget.
   */
  public function create(Field $field, mixed $current, array $answers = []): WidgetInterface {
    $widget = match ($field->type) {
      FieldType::Confirm => new ConfirmWidget((bool) $current),
      FieldType::Toggle => new ToggleWidget($this->labels($field), is_string($current) ? $current : ''),
      FieldType::Select => new SelectWidget($this->options($field), is_string($current) ? $current : '', pageSize: $field->pageSize),
      FieldType::MultiSelect => new MultiSelectWidget($this->options($field), $this->toList($current), pageSize: $field->pageSize),
      FieldType::MultiSearch => new MultiSearchWidget($this->options($field), $this->toList($current), pageSize: $field->pageSize),
      FieldType::Reorder => new ReorderWidget($this->options($field), $this->toList($current), pageSize: $field->pageSize),
      FieldType::Suggest => new SuggestWidget($field->selectableValues(), is_string($current) ? $current : '', pageSize: $field->pageSize),
      FieldType::Search => new SearchWidget($this->options($field), is_string($current) ? $current : '', pageSize: $field->pageSize),
      FieldType::FilePicker => new FilePickerWidget($field->pickerStart, is_string($current) ? $current : '', $field->pickerMode, $field->pickerExtensions, $field->pickerShowHidden),
      FieldType::MultiFilePicker => new FilePickerWidget($field->pickerStart, $this->toList($current), $field->pickerMode, $field->pickerExtensions, $field->pickerShowHidden, multiple: TRUE),
      FieldType::Number => new NumberWidget(is_int($current) || is_float($current) ? (string) (int) $current : '', bounds: $field->bounds),
      FieldType::Calendar => new CalendarWidget(is_string($current) ? $current : '', bounds: $field->dateBounds),
      FieldType::Textarea => new TextareaWidget(is_string($current) ? $current : '', externalEdit: $field->externalEditor && $this->externalEditorAvailable),
      FieldType::Password => new PasswordWidget(is_string($current) ? $current : '', revealable: $field->revealable, confirm: $field->confirm),
      FieldType::Pause => new PauseWidget(),
      default => new TextWidget(is_string($current) ? $current : '', completions: $this->completionsFor($field, $answers)),
    };

    return $widget->setKeys($this->keymap->forField($field->type));
  }

  /**
   * Resolve a text field's completion source to a concrete candidate list.
   *
   * A closure source is called with the answers collected so far; the result is
   * coerced to a list of strings, so a mistyped source degrades to no
   * completion rather than erroring.
   *
   * @param \DrevOps\Tui\Config\Field $field
   *   The field.
   * @param array<string,mixed> $answers
   *   The answers collected so far.
   *
   * @return list<string>
   *   The candidate strings; empty when the field declares no completion.
   */
  protected function completionsFor(Field $field, array $answers): array {
    $source = $field->completion instanceof \Closure ? ($field->completion)($answers) : $field->completion;

    $out = [];
    foreach (is_array($source) ? $source : [] as $item) {
      if (is_string($item)) {
        $out[] = $item;
      }
    }

    return $out;
  }

  /**
   * The selectable value => label map for a field's options.
   *
   * @param \DrevOps\Tui\Config\Field $field
   *   The field.
   *
   * @return array<string,string>
   *   The labels keyed by value, for widgets that take a flat option map.
   */
  protected function labels(Field $field): array {
    $out = [];

    foreach ($field->options as $option) {
      if ($option->selectable()) {
        $out[$option->value] = Translator::t($option->label);
      }
    }

    return $out;
  }

  /**
   * A field's options with their labels and disabled reasons translated.
   *
   * Translating once here, rather than at each widget draw, keeps the list a
   * widget searches identical to the list it shows, so a match runs against the
   * same text the user reads.
   *
   * @param \DrevOps\Tui\Config\Field $field
   *   The field.
   *
   * @return list<\DrevOps\Tui\Config\Option>
   *   The options in display order, localized to the active language.
   */
  protected function options(Field $field): array {
    return array_map(static fn(Option $option): Option => new Option(
      $option->value,
      Translator::t($option->label),
      $option->description,
      $option->kind,
      $option->disabled,
      $option->disabledReason !== '' ? Translator::t($option->disabledReason) : '',
    ), $field->options);
  }

  /**
   * Coerce a value to a list of strings.
   *
   * @param mixed $value
   *   The value.
   *
   * @return list<string>
   *   The list of strings.
   */
  protected function toList(mixed $value): array {
    if (!is_array($value)) {
      return [];
    }

    $out = [];
    foreach ($value as $item) {
      if (is_string($item)) {
        $out[] = $item;
      }
    }

    return $out;
  }

}
