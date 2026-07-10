<?php

declare(strict_types=1);

namespace DrevOps\Tui\Input;

use DrevOps\Tui\Config\FieldType;

/**
 * The binding context a key resolves in: base, navigation, or one widget type.
 *
 * Bindings are layered. The base scope holds the defaults shared by every
 * widget; navigation and per-field-type scopes override the base for the few
 * keys that mean something different there (Enter inserts a newline in a
 * textarea, Space toggles an option in a checkbox list, and so on). A scope is
 * a value, not an enum, so it can wrap a {@see FieldType} without duplicating
 * that enum's cases.
 *
 * @package DrevOps\Tui\Input
 */
final readonly class Scope {

  /**
   * The field types whose widgets consume printable characters as input.
   *
   * In these scopes a letter or digit is the value the user is typing, so a
   * binding may not claim a printable character for an action - that would make
   * the character un-typeable. Enforced by {@see consumesText()}.
   */
  protected const array TEXT_ENTRY = [
    FieldType::Text,
    FieldType::Number,
    FieldType::Password,
    FieldType::Textarea,
    FieldType::Search,
    FieldType::Suggest,
    FieldType::MultiSelect,
    FieldType::MultiSearch,
  ];

  /**
   * The token for the base scope, kept distinct from any field-scope token.
   */
  protected const string BASE_TOKEN = '@base';

  /**
   * The token for the navigation scope, distinct from any field-scope token.
   */
  protected const string NAVIGATION_TOKEN = '@navigation';

  /**
   * The prefix for a field-scope token, distinct from the constants above.
   *
   * Uses the field type's case name rather than its serialized value, keeping
   * the token an internal identity separate from any rendering boundary.
   */
  protected const string FIELD_PREFIX = 'field:';

  /**
   * Construct a scope.
   *
   * @param \DrevOps\Tui\Config\FieldType|null $fieldType
   *   The widget type this scope targets, or NULL for the base and navigation
   *   scopes.
   * @param bool $navigation
   *   Whether this is the navigation scope.
   */
  protected function __construct(
    public ?FieldType $fieldType = NULL,
    public bool $navigation = FALSE,
  ) {
  }

  /**
   * The base scope holding the defaults shared by every widget.
   *
   * @return self
   *   The base scope.
   */
  public static function base(): self {
    return new self();
  }

  /**
   * The panel-navigation scope.
   *
   * @return self
   *   The navigation scope.
   */
  public static function navigation(): self {
    return new self(navigation: TRUE);
  }

  /**
   * The scope for a single widget type.
   *
   * @param \DrevOps\Tui\Config\FieldType $type
   *   The field type.
   *
   * @return self
   *   The field-type scope.
   */
  public static function field(FieldType $type): self {
    return new self($type);
  }

  /**
   * A stable token identifying this scope, usable as an array key.
   *
   * @return string
   *   The token.
   */
  public function token(): string {
    if ($this->navigation) {
      return self::NAVIGATION_TOKEN;
    }

    return $this->fieldType instanceof FieldType ? self::FIELD_PREFIX . $this->fieldType->name : self::BASE_TOKEN;
  }

  /**
   * Whether this scope's widget consumes printable characters as typed input.
   *
   * @return bool
   *   TRUE when a printable character is reserved for typing and may not be
   *   bound to an action.
   */
  public function consumesText(): bool {
    return $this->fieldType instanceof FieldType && in_array($this->fieldType, self::TEXT_ENTRY, TRUE);
  }

  /**
   * A human-readable label for this scope, used in error messages.
   *
   * @return string
   *   The label.
   */
  public function label(): string {
    if ($this->navigation) {
      return 'navigation';
    }

    return $this->fieldType instanceof FieldType ? $this->fieldType->value : 'base';
  }

}
