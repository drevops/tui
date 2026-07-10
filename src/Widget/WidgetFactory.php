<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyMapManager;

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
   *
   * @return \DrevOps\Tui\Widget\WidgetInterface
   *   The widget.
   */
  public function create(Field $field, mixed $current): WidgetInterface {
    $labels = $this->labels($field);

    $widget = match ($field->type) {
      FieldType::Confirm => new ConfirmWidget((bool) $current),
      FieldType::Toggle => new ToggleWidget($labels, is_string($current) ? $current : ''),
      FieldType::Select => new SelectWidget($labels, is_string($current) ? $current : ''),
      FieldType::MultiSelect => new MultiSelectWidget($labels, $this->toList($current)),
      FieldType::MultiSearch => new MultiSearchWidget($labels, $this->toList($current)),
      FieldType::Suggest => new SuggestWidget(array_keys($labels), is_string($current) ? $current : ''),
      FieldType::Search => new SearchWidget($labels, is_string($current) ? $current : ''),
      FieldType::FilePicker => new FilePickerWidget($field->pickerStart, is_string($current) ? $current : '', $field->pickerMode, $field->pickerExtensions, $field->pickerShowHidden),
      FieldType::MultiFilePicker => new FilePickerWidget($field->pickerStart, $this->toList($current), $field->pickerMode, $field->pickerExtensions, $field->pickerShowHidden, multiple: TRUE),
      FieldType::Number => new NumberWidget(is_int($current) || is_float($current) ? (string) (int) $current : '', bounds: $field->bounds),
      FieldType::Textarea => new TextareaWidget(is_string($current) ? $current : '', externalEdit: $field->externalEditor && $this->externalEditorAvailable),
      FieldType::Password => new PasswordWidget(is_string($current) ? $current : '', revealable: $field->revealable, confirm: $field->confirm),
      FieldType::Pause => new PauseWidget(),
      default => new TextWidget(is_string($current) ? $current : ''),
    };

    return $widget->setKeys($this->keymap->forField($field->type));
  }

  /**
   * The option value => label map for a field.
   *
   * @param \DrevOps\Tui\Config\Field $field
   *   The field.
   *
   * @return array<string,string>
   *   The labels keyed by value.
   */
  protected function labels(Field $field): array {
    $out = [];

    foreach ($field->options as $option) {
      $out[$option->value] = $option->label;
    }

    return $out;
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
