<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A multi-select whose query filters by fuzzy match, shown as a search line.
 *
 * @package DrevOps\Tui\Widget
 */
class MultiSearchWidget extends MultiSelectWidget {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function filterOptions(string $needle): array {
    return $this->matcher()->rankOptions($this->options, $needle);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function matchPositions(string $label): array {
    return $this->filter === '' ? [] : $this->matcher()->positions($label, $this->filter);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function view(ThemeInterface $theme): string {
    return $this->filter . $theme->caret() . "\n" . parent::view($theme);
  }

}
