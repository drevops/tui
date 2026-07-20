<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Widget\Capability\StepCapableInterface;

/**
 * An inline switch between two labeled values.
 *
 * @package DrevOps\Tui\Widget
 */
class ToggleWidget extends AbstractWidget implements StepCapableInterface {

  /**
   * The option values in display order.
   *
   * @var list<string>
   */
  protected array $values;

  /**
   * The selected option index.
   */
  protected int $cursor = 0;

  /**
   * Construct a toggle widget.
   *
   * @param array<string,string> $labels
   *   Options as value => label, in display order.
   * @param string $default
   *   The initially selected value.
   * @param \Closure|null $validate
   *   Optional validator (see AbstractWidget).
   * @param \Closure|null $transform
   *   Optional transformer (see AbstractWidget).
   */
  public function __construct(protected array $labels, string $default = '', ?\Closure $validate = NULL, ?\Closure $transform = NULL) {
    parent::__construct($validate, $transform);
    $this->values = array_keys($this->labels);
    $index = array_search($default, $this->values, TRUE);
    $this->cursor = $index === FALSE ? 0 : $index;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Toggle);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
    $keys = $this->keys();

    if ($this->handleCancel($key)) {
      return;
    }

    if ($keys->matches($key, Action::Accept)) {
      $this->accept($this->liveValue());

      return;
    }

    if ($keys->matches($key, Action::Toggle)) {
      $this->stepBy(1);

      return;
    }

    if ($key->isChar()) {
      $this->applyChar($key->char ?? '');
    }
  }

  /**
   * {@inheritdoc}
   *
   * Each position moves to the adjacent value, wrapping at either end.
   */
  public function stepBy(int $delta): void {
    $count = count($this->values);
    if ($count < 2) {
      return;
    }

    $this->cursor = (($this->cursor + $delta) % $count + $count) % $count;
  }

  /**
   * Select the value whose label starts with the typed character.
   *
   * The first matching label wins, so labels sharing a first letter resolve to
   * the one declared first; the other stays reachable by flipping.
   *
   * @param string $char
   *   The typed character.
   */
  protected function applyChar(string $char): void {
    $char = mb_strtolower($char, 'UTF-8');

    foreach ($this->values as $index => $value) {
      $label = $this->labels[$value] ?? $value;
      if ($label !== '' && mb_strtolower(mb_substr($label, 0, 1, 'UTF-8'), 'UTF-8') === $char) {
        $this->cursor = $index;

        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return $this->values[$this->cursor] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    $parts = [];

    foreach ($this->values as $index => $value) {
      $parts[] = $this->renderRadioRow($theme, $this->labels[$value] ?? $value, $index === $this->cursor);
    }

    return $this->withError($theme, implode('  ', $parts));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hints(): array {
    return [new Hint('toggle', Action::Toggle), ...parent::hints()];
  }

}
