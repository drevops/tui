<?php

declare(strict_types=1);

namespace Playground\FieldBehaviour;

/**
 * The consumer's handler class for the "order_code" field.
 *
 * The engine maps a field id to a PascalCase class name (order_code ->
 * OrderCode) and looks it up in the namespaces given to the Tui facade. A
 * public static validate() and transform() found there become the field's
 * behaviour - reusable across every form that has an "order_code" field. A
 * closure declared on the field itself always wins over the class.
 */
class OrderCode {

  /**
   * Reject codes that are not exactly six letters or digits.
   *
   * @param mixed $value
   *   The value to validate.
   *
   * @return string|null
   *   An error message, or NULL when the value is acceptable.
   */
  public static function validate(mixed $value): ?string {
    return is_string($value) && preg_match('/^[a-z0-9]{6}$/i', $value) === 1 ? NULL : 'An order code is exactly six letters or digits.';
  }

  /**
   * Normalize the accepted code to lowercase.
   *
   * @param mixed $value
   *   The accepted value.
   *
   * @return mixed
   *   The normalized value.
   */
  public static function transform(mixed $value): mixed {
    return is_string($value) ? strtolower($value) : $value;
  }

}
