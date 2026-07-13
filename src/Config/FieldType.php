<?php

declare(strict_types=1);

namespace DrevOps\Tui\Config;

/**
 * The set of supported field (widget) types.
 *
 * @package DrevOps\Tui\Config
 */
enum FieldType: string {

  case Text = 'text';
  case Select = 'select';
  case MultiSelect = 'multiselect';
  case Confirm = 'confirm';
  case Toggle = 'toggle';
  case Suggest = 'suggest';
  case Number = 'number';
  case Date = 'date';
  case Textarea = 'textarea';
  case Password = 'password';
  case Search = 'search';
  case MultiSearch = 'multisearch';
  case Reorder = 'reorder';
  case FilePicker = 'filepicker';
  case MultiFilePicker = 'multifilepicker';
  case Pause = 'pause';

  /**
   * Whether a supplied value must be one of the field's selectable options.
   *
   * Suggest is excluded: its options are autocomplete hints, not a closed set.
   *
   * @return bool
   *   TRUE for the option-constrained choice types.
   */
  public function constrainsToOptions(): bool {
    return in_array($this, [
      self::Select,
      self::Search,
      self::Toggle,
      self::MultiSelect,
      self::MultiSearch,
      self::Reorder,
    ], TRUE);
  }

  /**
   * Whether the field collects a list of values rather than a single value.
   *
   * @return bool
   *   TRUE for the multi-choice types.
   */
  public function isMulti(): bool {
    return in_array($this, [self::MultiSelect, self::MultiSearch, self::Reorder], TRUE);
  }

}
