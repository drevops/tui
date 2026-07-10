<?php

declare(strict_types=1);

namespace DrevOps\Tui\Schema;

use DrevOps\Tui\Config\Config;
use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\FieldType;

/**
 * Validates an answer set against the configuration.
 *
 * Checks value types, option membership and required questions, and skips
 * questions whose `when` condition is not met by the answer set. Returns a
 * list of actionable error messages (empty when the set is valid).
 *
 * @package DrevOps\Tui\Schema
 */
class SchemaValidator {

  /**
   * Construct a validator.
   *
   * @param \DrevOps\Tui\Config\Config $config
   *   The configuration to validate against.
   */
  public function __construct(protected Config $config) {
  }

  /**
   * Validate an answer set.
   *
   * @param array<string,mixed> $answers
   *   The answers keyed by question id.
   *
   * @return list<string>
   *   The validation errors; empty when valid.
   */
  public function validate(array $answers): array {
    $errors = [];

    foreach ($this->config->fields() as $field) {
      if ($field->when !== NULL && !$field->when->matches($answers)) {
        continue;
      }

      if (!array_key_exists($field->id, $answers)) {
        if ($field->required) {
          $errors[] = sprintf('Missing required question "%s".', $field->id);
        }

        continue;
      }

      $error = $this->validateValue($field, $answers[$field->id]);
      if ($error !== NULL) {
        $errors[] = $error;
      }
    }

    foreach (array_keys($answers) as $id) {
      if (!$this->config->field((string) $id) instanceof Field) {
        $errors[] = sprintf('Unknown question "%s".', (string) $id);
      }
    }

    return $errors;
  }

  /**
   * Validate a single value against its field.
   *
   * @param \DrevOps\Tui\Config\Field $field
   *   The field.
   * @param mixed $value
   *   The value.
   *
   * @return string|null
   *   The first error, or NULL when valid.
   */
  protected function validateValue(Field $field, mixed $value): ?string {
    if (!$this->isType($field->type, $value)) {
      return sprintf('Question "%s" must be %s.', $field->id, $this->typeName($field->type));
    }

    if ($field->required && $this->isEmpty($value)) {
      return sprintf('Question "%s" is required.', $field->id);
    }

    $bounds_error = $this->checkBounds($field, $value);
    if ($bounds_error !== NULL) {
      return $bounds_error;
    }

    return $this->checkOptions($field, $value);
  }

  /**
   * Check a number value against its declared bounds.
   *
   * @param \DrevOps\Tui\Config\Field $field
   *   The field.
   * @param mixed $value
   *   The value.
   *
   * @return string|null
   *   An error, or NULL when in range (or when the field declares no bounds).
   */
  protected function checkBounds(Field $field, mixed $value): ?string {
    $violation = $field->bounds?->violation($value);

    return $violation === NULL ? NULL : sprintf('Question "%s" must be %s.', $field->id, $violation);
  }

  /**
   * Whether the value matches the field type.
   *
   * @param \DrevOps\Tui\Config\FieldType $type
   *   The field type.
   * @param mixed $value
   *   The value.
   *
   * @return bool
   *   TRUE when the value matches.
   */
  protected function isType(FieldType $type, mixed $value): bool {
    return match ($type) {
      FieldType::Confirm, FieldType::Pause => is_bool($value),
      FieldType::MultiSelect, FieldType::MultiSearch, FieldType::MultiFilePicker => is_array($value),
      FieldType::Number => is_int($value) || is_float($value),
      default => is_string($value),
    };
  }

  /**
   * A human name for a field type.
   *
   * @param \DrevOps\Tui\Config\FieldType $type
   *   The field type.
   *
   * @return string
   *   The human name.
   */
  protected function typeName(FieldType $type): string {
    return match ($type) {
      FieldType::Confirm, FieldType::Pause => 'a boolean',
      FieldType::MultiSelect, FieldType::MultiSearch, FieldType::MultiFilePicker => 'a list',
      FieldType::Number => 'a number',
      default => 'a string',
    };
  }

  /**
   * Whether a value is empty.
   *
   * @param mixed $value
   *   The value.
   *
   * @return bool
   *   TRUE when empty.
   */
  protected function isEmpty(mixed $value): bool {
    return in_array($value, ['', [], NULL], TRUE);
  }

  /**
   * Check option membership for choice fields.
   *
   * Rejects any supplied value that is not a selectable option, telling a
   * disabled option apart from an unknown one.
   *
   * @param \DrevOps\Tui\Config\Field $field
   *   The field.
   * @param mixed $value
   *   The value.
   *
   * @return string|null
   *   An error, or NULL when valid.
   */
  protected function checkOptions(Field $field, mixed $value): ?string {
    $error = $field->optionError($value);

    return $error === NULL ? NULL : sprintf('Question "%s": %s.', $field->id, $error);
  }

}
