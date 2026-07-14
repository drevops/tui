<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Config\NumberBounds;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Translation\Translator;

/**
 * Integer input: digits with an optional leading minus, accepted as an int.
 *
 * With bounds supplied, Up/Down adjust the value by the step clamped to the
 * range and an out-of-range entry is rejected inline; without bounds the widget
 * is a plain integer text entry with the arrow keys inert.
 *
 * @package DrevOps\Tui\Widget
 */
class NumberWidget extends TextWidget {

  /**
   * Construct a number widget.
   *
   * @param string $buffer
   *   The initial value (and live input buffer).
   * @param \Closure|null $validate
   *   Optional validator (see AbstractWidget).
   * @param \Closure|null $transform
   *   Optional transformer (see AbstractWidget).
   * @param \DrevOps\Tui\Config\NumberBounds|null $bounds
   *   Optional bounds and step; NULL for a plain integer entry.
   */
  public function __construct(string $buffer = '', ?\Closure $validate = NULL, ?\Closure $transform = NULL, protected ?NumberBounds $bounds = NULL) {
    parent::__construct($buffer, $validate, $transform);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Number);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function handle(Key $key): void {
    $bounds = $this->bounds;

    if ($bounds instanceof NumberBounds) {
      $keys = $this->keys();

      if ($keys->matches($key, Action::Increment)) {
        $this->adjust($bounds, 1);

        return;
      }

      if ($keys->matches($key, Action::Decrement)) {
        $this->adjust($bounds, -1);

        return;
      }
    }

    parent::handle($key);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function insert(string $char): void {
    if ($char === '-') {
      if ($this->cursor !== 0 || str_contains($this->buffer, '-')) {
        return;
      }
    }
    elseif (!ctype_digit($char)) {
      return;
    }

    parent::insert($char);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function liveValue(): mixed {
    return (int) $this->buffer;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function accept(mixed $value): bool {
    $violation = $this->bounds?->violation($value);
    if ($violation !== NULL) {
      $this->error = Translator::t('Enter a number @constraint.', ['@constraint' => $violation]);

      return FALSE;
    }

    return parent::accept($value);
  }

  /**
   * Step the value in a direction, clamped to the bounds, and show the result.
   *
   * @param \DrevOps\Tui\Config\NumberBounds $bounds
   *   The bounds driving the step and clamp.
   * @param int $direction
   *   Either 1 to increment or -1 to decrement.
   */
  protected function adjust(NumberBounds $bounds, int $direction): void {
    $this->buffer = (string) $bounds->step((int) $this->buffer, $direction);
    $this->cursor = mb_strlen($this->buffer, 'UTF-8');
    $this->error = NULL;
  }

  /**
   * {@inheritdoc}
   *
   * The step keys are the non-obvious binding here - nothing else signals that
   * they adjust the value - so they lead when bounds are set.
   */
  #[\Override]
  public function hints(): array {
    if (!$this->bounds instanceof NumberBounds) {
      return parent::hints();
    }

    return [new Hint('adjust', Action::Increment, Action::Decrement), ...parent::hints()];
  }

}
