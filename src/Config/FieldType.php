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
   *   TRUE for the list-collecting types.
   */
  public function collectsList(): bool {
    return in_array($this, [self::MultiSelect, self::MultiSearch, self::MultiFilePicker, self::Reorder], TRUE);
  }

  /**
   * Whether the field is a multi-selection over its declared option set.
   *
   * Narrower than {@see collectsList()}: the multi file picker collects a
   * list too, but its entries come from the filesystem, not the options.
   *
   * @return bool
   *   TRUE for the option-backed multi-choice types.
   */
  public function isMultiChoice(): bool {
    return in_array($this, [self::MultiSelect, self::MultiSearch, self::Reorder], TRUE);
  }

  /**
   * Whether a headless value has the shape this field type collects.
   *
   * @param mixed $value
   *   The candidate value.
   *
   * @return bool
   *   TRUE when the value's type matches the field type.
   */
  public function acceptsValue(mixed $value): bool {
    return match (TRUE) {
      $this === self::Confirm, $this === self::Pause => is_bool($value),
      $this->collectsList() => is_array($value),
      $this === self::Number => is_int($value) || is_float($value),
      // An empty string is an unset date, left to the required check; any
      // other value must be a strict `Y-m-d` calendar date.
      $this === self::Calendar => is_string($value) && ($value === '' || DateBounds::parse($value) instanceof \DateTimeImmutable),
      default => is_string($value),
    };
  }

  /**
   * The human name of the value shape this type collects, translated.
   *
   * @return string
   *   The value-kind fragment (e.g. "a string", "a list").
   */
  public function valueKind(): string {
    return match (TRUE) {
      $this === self::Confirm, $this === self::Pause => Translator::t('a boolean'),
      $this->collectsList() => Translator::t('a list'),
      $this === self::Number => Translator::t('a number'),
      $this === self::Calendar => Translator::t('a date (YYYY-MM-DD)'),
      default => Translator::t('a string'),
    };
  }

}
