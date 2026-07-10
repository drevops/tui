<?php

declare(strict_types=1);

namespace DrevOps\Tui\Config;

/**
 * Optional integer bounds and step for a number field.
 *
 * Any of the three may be unset (NULL): an unset min or max leaves that side
 * open, and an unset step increments by one. The range arithmetic and the
 * human range phrase live here once, so the interactive widget, the headless
 * engine and the answer-set validator all agree.
 *
 * @package DrevOps\Tui\Config
 */
final readonly class NumberBounds {

  /**
   * Construct number bounds.
   *
   * @param int|null $min
   *   The inclusive minimum, or NULL for an open lower bound.
   * @param int|null $max
   *   The inclusive maximum, or NULL for an open upper bound.
   * @param int|null $step
   *   The Up/Down increment, or NULL to step by one.
   */
  public function __construct(
    public ?int $min = NULL,
    public ?int $max = NULL,
    public ?int $step = NULL,
  ) {
  }

  /**
   * Whether a value is within the bounds.
   *
   * @param int $value
   *   The value.
   *
   * @return bool
   *   TRUE when the value is within both declared bounds.
   */
  public function contains(int $value): bool {
    if ($this->min !== NULL && $value < $this->min) {
      return FALSE;
    }

    return !($this->max !== NULL && $value > $this->max);
  }

  /**
   * The human range phrase, e.g. "between 1 and 10", "at least 1", "at most 10".
   *
   * @return string
   *   The phrase, or an empty string when neither bound is declared.
   */
  public function describe(): string {
    if ($this->min !== NULL && $this->max !== NULL) {
      return sprintf('between %d and %d', $this->min, $this->max);
    }

    if ($this->min !== NULL) {
      return sprintf('at least %d', $this->min);
    }

    if ($this->max !== NULL) {
      return sprintf('at most %d', $this->max);
    }

    return '';
  }

  /**
   * Clamp a value into the declared bounds.
   *
   * @param int $value
   *   The value.
   *
   * @return int
   *   The value, moved onto the nearest bound it exceeds.
   */
  public function clamp(int $value): int {
    if ($this->min !== NULL && $value < $this->min) {
      return $this->min;
    }

    if ($this->max !== NULL && $value > $this->max) {
      return $this->max;
    }

    return $value;
  }

  /**
   * Step a value by the step in a direction, clamped to the bounds.
   *
   * @param int $value
   *   The current value.
   * @param int $direction
   *   1 to increment, -1 to decrement.
   *
   * @return int
   *   The stepped, clamped value.
   */
  public function step(int $value, int $direction): int {
    return $this->clamp($value + $direction * ($this->step ?? 1));
  }

}
