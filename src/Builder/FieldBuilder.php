<?php

declare(strict_types=1);

namespace DrevOps\Tui\Builder;

use DrevOps\Tui\Condition\ConditionInterface;
use DrevOps\Tui\Config\ConfigException;
use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Config\FilePickerMode;
use DrevOps\Tui\Config\NumberBounds;
use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Discovery\DiscoverInterface;

/**
 * A fluent builder for a single Field.
 *
 * @package DrevOps\Tui\Builder
 */
final class FieldBuilder {

  /**
   * The help text.
   */
  protected string $description = '';

  /**
   * Whether an explicit default was set (otherwise the type default is used).
   */
  protected bool $hasDefault = FALSE;

  /**
   * The explicit default value, when set.
   */
  protected mixed $default = NULL;

  /**
   * The options, keyed by value.
   *
   * @var array<string,\DrevOps\Tui\Config\Option>
   */
  protected array $options = [];

  /**
   * Whether a value is required.
   */
  protected bool $required = FALSE;

  /**
   * The conditional-visibility rule.
   */
  protected ?ConditionInterface $when = NULL;

  /**
   * The derive rule.
   */
  protected ?Derive $derive = NULL;

  /**
   * The discovery rule, or a custom detector closure.
   */
  protected DiscoverInterface|\Closure|null $discover = NULL;

  /**
   * The declared validator.
   */
  protected ?\Closure $validate = NULL;

  /**
   * The declared transformer.
   */
  protected ?\Closure $transform = NULL;

  /**
   * The processing weight.
   */
  protected int $weight = 0;

  /**
   * Whether a password editor offers a reveal/hide toggle.
   */
  protected bool $revealable = FALSE;

  /**
   * Whether a password editor prompts for the value twice.
   */
  protected bool $confirm = FALSE;

  /**
   * Whether the field may hand off to the user's $EDITOR.
   */
  protected bool $externalEditor = FALSE;

  /**
   * The number field's inclusive minimum, when declared.
   */
  protected ?int $min = NULL;

  /**
   * The number field's inclusive maximum, when declared.
   */
  protected ?int $max = NULL;

  /**
   * The number field's Up/Down increment, when declared.
   */
  protected ?int $step = NULL;

  /**
   * File picker only: which entries may be selected.
   */
  protected FilePickerMode $pickerMode = FilePickerMode::Any;

  /**
   * File picker only: the directory the browser opens at and cannot ascend
   * above.
   */
  protected string $pickerStart = '';

  /**
   * File picker only: the extensions selectable files are limited to.
   *
   * @var list<string>
   */
  protected array $pickerExtensions = [];

  /**
   * File picker only: whether dot-entries are shown when the browser opens.
   */
  protected bool $pickerShowHidden = FALSE;

  /**
   * Construct a field builder.
   *
   * @param string $id
   *   The unique field id.
   * @param string $label
   *   The human-readable label.
   * @param \DrevOps\Tui\Config\FieldType $fieldType
   *   The widget type.
   */
  public function __construct(protected string $id, protected string $label, protected FieldType $fieldType) {
  }

  /**
   * Set the help text.
   *
   * @param string $description
   *   The help text.
   *
   * @return $this
   *   The builder.
   */
  public function description(string $description): self {
    $this->description = $description;

    return $this;
  }

  /**
   * Set the default value.
   *
   * @param mixed $default
   *   The default value, or a `fn (Context): mixed` closure computing a
   *   dynamic default from the run context.
   *
   * @return $this
   *   The builder.
   */
  public function default(mixed $default): self {
    $this->hasDefault = TRUE;
    $this->default = $default;

    return $this;
  }

  /**
   * Mark the field required.
   *
   * @param bool $required
   *   Whether a value is required.
   *
   * @return $this
   *   The builder.
   */
  public function required(bool $required = TRUE): self {
    $this->required = $required;

    return $this;
  }

  /**
   * Set the processing weight.
   *
   * @param int $weight
   *   The weight; lower runs earlier.
   *
   * @return $this
   *   The builder.
   */
  public function weight(int $weight): self {
    $this->weight = $weight;

    return $this;
  }

  /**
   * Offer a reveal/hide toggle in a password editor.
   *
   * @param bool $revealable
   *   Whether the toggle is enabled.
   *
   * @return $this
   *   The builder.
   */
  public function revealable(bool $revealable = TRUE): self {
    $this->revealable = $revealable;

    return $this;
  }

  /**
   * Prompt for a password twice and reject a mismatch before accepting.
   *
   * @param bool $confirm
   *   Whether confirmation mode is enabled.
   *
   * @return $this
   *   The builder.
   */
  public function confirm(bool $confirm = TRUE): self {
    $this->confirm = $confirm;

    return $this;
  }

  /**
   * Allow the field to hand off to the user's $EDITOR.
   *
   * Honoured by the textarea widget: an available $EDITOR (or $VISUAL) can be
   * launched to compose the value, falling back to inline editing otherwise.
   *
   * @param bool $enabled
   *   Whether the external-editor handoff is offered.
   *
   * @return $this
   *   The builder.
   */
  public function externalEditor(bool $enabled = TRUE): self {
    $this->externalEditor = $enabled;

    return $this;
  }

  /**
   * Number only: set the inclusive minimum accepted value.
   *
   * @param int $min
   *   The minimum.
   *
   * @return $this
   *   The builder.
   */
  public function min(int $min): self {
    $this->min = $min;

    return $this;
  }

  /**
   * Number only: set the inclusive maximum accepted value.
   *
   * @param int $max
   *   The maximum.
   *
   * @return $this
   *   The builder.
   */
  public function max(int $max): self {
    $this->max = $max;

    return $this;
  }

  /**
   * Number only: set the Up/Down increment.
   *
   * @param int $step
   *   The step; must be positive.
   *
   * @return $this
   *   The builder.
   */
  public function step(int $step): self {
    $this->step = $step;

    return $this;
  }

  /**
   * File picker only: set the directory the browser opens at.
   *
   * The browser cannot ascend above this directory, so it also bounds where a
   * path may be chosen. An empty value falls back to the current working
   * directory at collection time.
   *
   * @param string $directory
   *   The start directory.
   *
   * @return $this
   *   The builder.
   */
  public function start(string $directory): self {
    $this->pickerStart = $directory;

    return $this;
  }

  /**
   * File picker only: allow only files to be selected.
   *
   * Directories stay navigable so files beneath them remain reachable.
   *
   * @return $this
   *   The builder.
   */
  public function filesOnly(): self {
    $this->pickerMode = FilePickerMode::File;

    return $this;
  }

  /**
   * File picker only: allow only directories to be selected.
   *
   * @return $this
   *   The builder.
   */
  public function directoriesOnly(): self {
    $this->pickerMode = FilePickerMode::Directory;

    return $this;
  }

  /**
   * File picker only: limit selectable files to the given extensions.
   *
   * @param list<string> $extensions
   *   The allowed extensions (dot-less, case-insensitive); empty allows every
   *   extension.
   *
   * @return $this
   *   The builder.
   */
  public function extensions(array $extensions): self {
    $this->pickerExtensions = $extensions;

    return $this;
  }

  /**
   * File picker only: show dot-entries when the browser opens.
   *
   * @param bool $show
   *   Whether hidden entries are shown initially.
   *
   * @return $this
   *   The builder.
   */
  public function showHidden(bool $show = TRUE): self {
    $this->pickerShowHidden = $show;

    return $this;
  }

  /**
   * Set the conditional-visibility rule.
   *
   * @param \DrevOps\Tui\Condition\ConditionInterface $condition
   *   The condition gating the field, evaluated by the engine.
   *
   * @return $this
   *   The builder.
   */
  public function when(ConditionInterface $condition): self {
    $this->when = $condition;

    return $this;
  }

  /**
   * Set the derive rule.
   *
   * @param \DrevOps\Tui\Derive\Derive $derive
   *   The derive rule, evaluated by the engine.
   *
   * @return $this
   *   The builder.
   */
  public function derive(Derive $derive): self {
    $this->derive = $derive;

    return $this;
  }

  /**
   * Set the discovery rule.
   *
   * @param \DrevOps\Tui\Discovery\DiscoverInterface|\Closure $discover
   *   The discovery rule - or a custom `fn (Context): mixed` detector -
   *   evaluated by the engine in update mode.
   *
   * @return $this
   *   The builder.
   */
  public function discover(DiscoverInterface|\Closure $discover): self {
    $this->discover = $discover;

    return $this;
  }

  /**
   * Set the declared validator.
   *
   * @param \Closure $validator
   *   The validator `fn (mixed $value): ?string` returning an error message,
   *   or NULL when the value is valid.
   *
   * @return $this
   *   The builder.
   */
  public function validate(\Closure $validator): self {
    $this->validate = $validator;

    return $this;
  }

  /**
   * Set the declared transformer.
   *
   * @param \Closure $transformer
   *   The transformer `fn (mixed $value): mixed` normalizing an accepted
   *   value.
   *
   * @return $this
   *   The builder.
   */
  public function transform(\Closure $transformer): self {
    $this->transform = $transformer;

    return $this;
  }

  /**
   * Add a single option.
   *
   * @param string $value
   *   The option value.
   * @param string $label
   *   The option label (defaults to the value).
   * @param string $description
   *   The option description.
   *
   * @return $this
   *   The builder.
   */
  public function option(string $value, string $label = '', string $description = ''): self {
    $this->options[$value] = new Option($value, $label === '' ? $value : $label, $description);

    return $this;
  }

  /**
   * Add several options from a value => label map.
   *
   * @param array<array-key,string> $options
   *   The options, keyed by value with a label.
   *
   * @return $this
   *   The builder.
   */
  public function options(array $options): self {
    foreach ($options as $value => $label) {
      $this->option((string) $value, $label);
    }

    return $this;
  }

  /**
   * Build the immutable Field.
   *
   * @return \DrevOps\Tui\Config\Field
   *   The field.
   */
  public function build(): Field {
    return new Field(
      $this->id,
      $this->label,
      $this->description,
      $this->fieldType,
      $this->resolveDefault(),
      $this->options,
      $this->required,
      $this->when,
      $this->derive,
      $this->discover,
      $this->validate,
      $this->transform,
      $this->weight,
      $this->revealable,
      $this->confirm,
      $this->externalEditor,
      $this->buildBounds(),
      $this->pickerMode,
      $this->pickerStart,
      $this->pickerExtensions,
      $this->pickerShowHidden,
    );
  }

  /**
   * The effective default: the declared one, or the type's implicit default.
   *
   * @return mixed
   *   The default value.
   */
  protected function resolveDefault(): mixed {
    if ($this->hasDefault) {
      return $this->default;
    }

    // A toggle is always in one of its two states, so it defaults to the first
    // option's value rather than an empty value that would not match either.
    // The value is read off the option, not its array key, so a numeric-string
    // value like "0" is not coerced to an int.
    if ($this->fieldType === FieldType::Toggle && $this->options !== []) {
      return reset($this->options)->value;
    }

    return $this->defaultFor($this->fieldType);
  }

  /**
   * Assemble the number bounds from the declared min/max/step, if any.
   *
   * @return \DrevOps\Tui\Config\NumberBounds|null
   *   The bounds, or NULL when none were declared.
   *
   * @throws \DrevOps\Tui\Config\ConfigException
   *   When min exceeds max, or the step is not positive.
   */
  protected function buildBounds(): ?NumberBounds {
    if ($this->min === NULL && $this->max === NULL && $this->step === NULL) {
      return NULL;
    }

    if ($this->min !== NULL && $this->max !== NULL && $this->min > $this->max) {
      throw new ConfigException(sprintf('Field "%s" declares min %d greater than max %d.', $this->id, $this->min, $this->max));
    }

    if ($this->step !== NULL && $this->step < 1) {
      throw new ConfigException(sprintf('Field "%s" declares a non-positive step %d.', $this->id, $this->step));
    }

    return new NumberBounds($this->min, $this->max, $this->step);
  }

  /**
   * The engine default for a field type when none is declared.
   *
   * @param \DrevOps\Tui\Config\FieldType $type
   *   The field type.
   *
   * @return mixed
   *   The type default.
   */
  protected function defaultFor(FieldType $type): mixed {
    return match ($type) {
      FieldType::MultiSelect, FieldType::MultiSearch, FieldType::MultiFilePicker => [],
      FieldType::Confirm => FALSE,
      FieldType::Number => 0,
      // A pause is an interactive acknowledgement; headless runs have nothing
      // to wait for, so it defaults to acknowledged.
      FieldType::Pause => TRUE,
      default => '',
    };
  }

}
