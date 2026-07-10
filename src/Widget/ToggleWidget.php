<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * An inline switch between two labeled values.
 *
 * @package DrevOps\Tui\Widget
 */
class ToggleWidget extends AbstractWidget {

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
  public function handle(Key $key): void {
    if ($this->handleCancel($key)) {
      return;
    }

    if ($key->is(KeyName::Enter)) {
      $this->accept($this->liveValue());

      return;
    }

    if ($this->isToggle($key)) {
      $this->flip();

      return;
    }

    if ($key->isChar()) {
      $this->applyChar($key->char ?? '');
    }
  }

  /**
   * Whether the key flips the switch.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to test.
   *
   * @return bool
   *   TRUE when the key flips.
   */
  protected function isToggle(Key $key): bool {
    return in_array($key->name, [KeyName::Left, KeyName::Right, KeyName::Space, KeyName::Up, KeyName::Down], TRUE);
  }

  /**
   * Move the selection to the next value, wrapping at the end.
   */
  protected function flip(): void {
    $count = count($this->values);
    if ($count < 2) {
      return;
    }

    $this->cursor = ($this->cursor + 1) % $count;
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
    $char = mb_strtolower($char);

    foreach ($this->values as $index => $value) {
      $label = $this->labels[$value] ?? $value;
      if ($label !== '' && mb_strtolower(mb_substr($label, 0, 1)) === $char) {
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

    return implode('  ', $parts);
  }

}
