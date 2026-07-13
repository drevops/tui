<?php

declare(strict_types=1);

namespace DrevOps\Tui\Config;

use DrevOps\Tui\Condition\ConditionInterface;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Discovery\DiscoverInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * A single question in the configuration model.
 *
 * @package DrevOps\Tui\Config
 */
final readonly class Field {

  /**
   * The option rows for choice-based fields, in display order.
   *
   * @var list<\DrevOps\Tui\Config\Option>
   */
  public array $options;

  /**
   * Construct a field.
   *
   * @param string $id
   *   The unique field id.
   * @param string $label
   *   The human-readable label.
   * @param string $description
   *   The help text.
   * @param \DrevOps\Tui\Config\FieldType $type
   *   The widget type.
   * @param mixed $default
   *   The declared default value, or a `fn (Context): mixed` closure computing
   *   a dynamic default from the run context.
   * @param array<int|string,\DrevOps\Tui\Config\Option|string> $options
   *   Option rows for choice-based fields, in display order - a list of
   *   {@see Option} rows or the value => label shorthand map (normalized via
   *   {@see Option::list()}).
   * @param bool $required
   *   Whether a value is required.
   * @param \DrevOps\Tui\Condition\ConditionInterface|null $when
   *   The conditional-visibility rule, evaluated by the engine.
   * @param \DrevOps\Tui\Derive\Derive|null $derive
   *   The derive rule, evaluated by the engine.
   * @param \DrevOps\Tui\Discovery\DiscoverInterface|\Closure|null $discover
   *   The discovery rule - or a custom `fn (Context): mixed` detector -
   *   evaluated by the engine in update mode.
   * @param \Closure|null $validate
   *   A declared validator `fn (mixed $value): ?string` returning an error
   *   message, or NULL when the value is valid.
   * @param \Closure|null $transform
   *   A declared transformer `fn (mixed $value): mixed` normalizing an
   *   accepted value.
   * @param int $weight
   *   The processing weight: lower runs earlier. Fields of equal weight process
   *   in reverse declaration order, so specific replacements run before generic
   *   ones without any weights at all.
   * @param bool $revealable
   *   Password only: whether the editor offers a reveal/hide toggle.
   * @param bool $confirm
   *   Password only: whether the editor prompts for the value twice and rejects
   *   a mismatch before accepting.
   * @param bool $externalEditor
   *   Whether the field may hand off to the user's $EDITOR for composing its
   *   value. Honoured by the textarea widget; ignored by other types.
   * @param \DrevOps\Tui\Config\NumberBounds|null $bounds
   *   Number only: optional min/max/step bounds; NULL for a plain integer
   *   entry with no range or keyboard stepping.
   * @param \DrevOps\Tui\Config\FilePickerMode $pickerMode
   *   File picker only: which entries may be selected (any, files or
   *   directories); ignored by other types.
   * @param string $pickerStart
   *   File picker only: the directory the browser opens at and cannot ascend
   *   above; empty falls back to the current working directory.
   * @param list<string> $pickerExtensions
   *   File picker only: the file extensions selectable files are limited to
   *   (dot-less, case-insensitive); empty allows every extension.
   * @param bool $pickerShowHidden
   *   File picker only: whether dot-entries are shown when the browser opens.
   * @param int|null $pageSize
   *   Choice widgets only: how many option rows show at once before the list
   *   pages; NULL uses the widget default. A purely visual bound - it does not
   *   constrain a headless value, so it is absent from the machine schema.
   * @param list<string>|\Closure $completion
   *   Text only: the inline ghost-text completion source - a list of candidate
   *   strings, or a `fn (array<string,mixed> $answers): list<string>` closure
   *   over the answers collected so far. Empty disables ghost-text; ignored by
   *   other types.
   * @param \DrevOps\Tui\Config\DateBounds|null $dateBounds
   *   Date only: the min/max range and week-start day; NULL for non-date
   *   fields.
   */
  public function __construct(
    public string $id,
    public string $label,
    public string $description,
    public FieldType $type,
    public mixed $default,
    array $options = [],
    public bool $required = FALSE,
    public ?ConditionInterface $when = NULL,
    public ?Derive $derive = NULL,
    public DiscoverInterface|\Closure|null $discover = NULL,
    public ?\Closure $validate = NULL,
    public ?\Closure $transform = NULL,
    public int $weight = 0,
    public bool $revealable = FALSE,
    public bool $confirm = FALSE,
    public bool $externalEditor = FALSE,
    public ?NumberBounds $bounds = NULL,
    public FilePickerMode $pickerMode = FilePickerMode::Any,
    public string $pickerStart = '',
    public array $pickerExtensions = [],
    public bool $pickerShowHidden = FALSE,
    public ?int $pageSize = NULL,
    public array|\Closure $completion = [],
    public ?DateBounds $dateBounds = NULL,
  ) {
    $this->options = Option::list($options);
  }

  /**
   * Get a selectable-or-disabled option by its value.
   *
   * Structural rows (separators, headings) carry no value and are never
   * returned.
   */
  public function option(string $value): ?Option {
    foreach ($this->options as $option) {
      if ($option->kind === OptionKind::Option && $option->value === $value) {
        return $option;
      }
    }

    return NULL;
  }

  /**
   * The values of the selectable options, in display order.
   *
   * @return list<string>
   *   The selectable option values (excludes separators, headings and disabled
   *   options).
   */
  public function selectableValues(): array {
    $out = [];

    foreach ($this->options as $option) {
      if ($option->selectable()) {
        $out[] = $option->value;
      }
    }

    return $out;
  }

  /**
   * Validate a supplied value against the field's selectable options.
   *
   * Handles both a single-choice scalar and a multi-choice list, returning the
   * first offending item. The message is a caller-agnostic fragment so the
   * engine and the schema validator can each frame it their own way.
   *
   * @param mixed $value
   *   The candidate value - a scalar for single-choice, a list for multi.
   *
   * @return string|null
   *   An error fragment when an item is not a selectable option, or NULL when
   *   the field is unconstrained or every item is allowed.
   */
  public function optionError(mixed $value): ?string {
    if (!$this->type->constrainsToOptions() || $this->options === []) {
      return NULL;
    }

    if ($this->type->isMulti()) {
      if (!is_array($value)) {
        return Translator::t('value must be a list');
      }

      $items = $value;
    }
    else {
      $items = [$value];
    }

    foreach ($items as $item) {
      $error = $this->scalarOptionError(is_scalar($item) ? (string) $item : '');
      if ($error !== NULL) {
        return $error;
      }
    }

    if ($this->type === FieldType::Reorder) {
      return $this->rankingError($items);
    }

    return NULL;
  }

  /**
   * Classify a single scalar value against the selectable options.
   *
   * @param string $value
   *   The candidate value.
   *
   * @return string|null
   *   A fragment naming the value when it is disabled or unknown, or NULL when
   *   it is a selectable option.
   */
  protected function scalarOptionError(string $value): ?string {
    if (in_array($value, $this->selectableValues(), TRUE)) {
      return NULL;
    }

    $option = $this->option($value);
    if ($option instanceof Option && $option->disabled) {
      return $option->disabledReason !== ''
        ? Translator::t('option "@value" is disabled: @reason', ['@value' => $value, '@reason' => $option->disabledReason])
        : Translator::t('option "@value" is disabled', ['@value' => $value]);
    }

    return Translator::t('value "@value" is not one of: @options', ['@value' => $value, '@options' => implode(', ', $this->selectableValues())]);
  }

  /**
   * Check that a ranking lists every selectable option exactly once.
   *
   * Membership is verified by the caller, so a value that is a full
   * permutation has the same length as the option set with no repeats.
   *
   * @param array<array-key,mixed> $items
   *   The supplied ranking, already confirmed to hold only selectable values.
   *
   * @return string|null
   *   An error fragment when the ranking omits or repeats an option, or NULL
   *   when it is a complete permutation.
   */
  protected function rankingError(array $items): ?string {
    $selectable = $this->selectableValues();

    $seen = [];
    foreach ($items as $item) {
      $seen[is_scalar($item) ? (string) $item : ''] = TRUE;
    }

    if (count($items) === count($selectable) && count($seen) === count($items)) {
      return NULL;
    }

    return sprintf('must rank every option exactly once (%s)', implode(', ', $selectable));
  }

  /**
   * Order a set of values, completing and de-duplicating a desired ordering.
   *
   * The desired values that belong to the allowed set come first - in the
   * given order, de-duplicated - then every allowed value the desired list
   * omits is appended in its declared order. The result is always a full
   * permutation of the allowed values, so a partial or dirty ordering still
   * resolves to a complete one.
   *
   * @param list<string> $allowed
   *   The full set of values, in declared order.
   * @param list<string> $desired
   *   The requested ordering; values outside the allowed set are ignored and
   *   repeats collapsed.
   *
   * @return list<string>
   *   The allowed values in the resolved order.
   */
  public static function canonicalOrder(array $allowed, array $desired): array {
    $set = array_fill_keys($allowed, TRUE);

    $order = [];
    $seen = [];
    foreach ($desired as $value) {
      if (isset($set[$value]) && !isset($seen[$value])) {
        $order[] = $value;
        $seen[$value] = TRUE;
      }
    }

    foreach ($allowed as $value) {
      if (!isset($seen[$value])) {
        $order[] = $value;
        $seen[$value] = TRUE;
      }
    }

    return $order;
  }

}
