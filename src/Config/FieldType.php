<?php

declare(strict_types=1);

namespace DrevOps\Tui\Config;

use DrevOps\Tui\Translation\Translator;

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
  case Calendar = 'calendar';
  case Textarea = 'textarea';
  case Password = 'password';
  case Search = 'search';
  case MultiSearch = 'multisearch';
  case Reorder = 'reorder';
  case FilePicker = 'filepicker';
  case MultiFilePicker = 'multifilepicker';
  case Pause = 'pause';

  /**
   * The human label in the active language.
   *
   * A literal per case, rather than translating the backing value, so each
   * label is a discoverable chrome key in the catalog template.
   *
   * @return string
   *   The translated label.
   */
  public function label(): string {
    return match ($this) {
      self::Text => Translator::t('Text'),
      self::Select => Translator::t('Select'),
      self::MultiSelect => Translator::t('Multi-select'),
      self::Confirm => Translator::t('Confirm'),
      self::Toggle => Translator::t('Toggle'),
      self::Suggest => Translator::t('Suggest'),
      self::Number => Translator::t('Number'),
      self::Calendar => Translator::t('Calendar'),
      self::Textarea => Translator::t('Textarea'),
      self::Password => Translator::t('Password'),
      self::Search => Translator::t('Search'),
      self::MultiSearch => Translator::t('Multi-search'),
      self::Reorder => Translator::t('Reorder'),
      self::FilePicker => Translator::t('File picker'),
      self::MultiFilePicker => Translator::t('Multi file picker'),
      self::Pause => Translator::t('Pause'),
    };
  }

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
