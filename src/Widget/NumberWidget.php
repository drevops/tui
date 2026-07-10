<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\NumberBounds;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Theme\ThemeInterface;

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
  public function handle(Key $key): void {
    $bounds = $this->bounds;

    if ($bounds instanceof NumberBounds && $key->is(KeyName::Up)) {
      $this->adjust($bounds, 1);

      return;
    }

    if ($bounds instanceof NumberBounds && $key->is(KeyName::Down)) {
      $this->adjust($bounds, -1);

      return;
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
      $this->error = sprintf('Enter a number %s.', $violation);

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
   *   1 to increment, -1 to decrement.
   */
  protected function adjust(NumberBounds $bounds, int $direction): void {
    $this->buffer = (string) $bounds->step((int) $this->buffer, $direction);
    $this->cursor = strlen($this->buffer);
    $this->error = NULL;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function view(ThemeInterface $theme): string {
    if (!$this->bounds instanceof NumberBounds) {
      return parent::view($theme);
    }

    $rows = [$this->caretLine($theme), $this->hint($theme)];

    if ($this->error !== NULL) {
      $rows[] = $theme->error($this->error);
    }

    return implode("\n", $rows);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function rendersHint(): bool {
    return $this->bounds instanceof NumberBounds;
  }

  /**
   * Build the key-hint line shown beneath a bounded number entry.
   *
   * The arrow keys are the non-obvious binding here - nothing else signals that
   * they adjust the value - so they lead, followed by the remaining bindings.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The themed, dot-joined hint line.
   */
  protected function hint(ThemeInterface $theme): string {
    $fragments = [
      $theme->arrowUp() . '/' . $theme->arrowDown() . ' adjust',
      $theme->enter() . ' accept',
      'esc cancel',
    ];

    return $theme->footer(implode(' ' . $theme->dot() . ' ', $fragments));
  }

}
