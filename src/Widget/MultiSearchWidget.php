<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A checkbox list whose query filters by fuzzy match, shown as a search line.
 *
 * @package DrevOps\Tui\Widget
 */
class MultiSearchWidget extends AbstractWidget {

  use ChoiceListTrait;
  use MultiChoiceTrait;
  use ChoiceFilterTrait;
  use FuzzySearchTrait;

  /**
   * Construct a multi-search widget.
   *
   * @param array<int|string,\DrevOps\Tui\Config\Option|string> $options
   *   Option rows in display order - a list of options or the value => label
   *   shorthand map.
   * @param list<string> $default
   *   The initially selected values (non-selectable values are ignored).
   * @param \Closure|null $validate
   *   Optional validator (see AbstractWidget).
   * @param \Closure|null $transform
   *   Optional transformer (see AbstractWidget).
   * @param int|null $pageSize
   *   The number of option rows shown at once before the list pages; NULL uses
   *   the default.
   */
  public function __construct(array $options, array $default = [], ?\Closure $validate = NULL, ?\Closure $transform = NULL, ?int $pageSize = NULL) {
    parent::__construct($validate, $transform);
    $this->initMultiChoice($options, $default);
    $this->pageSize = $this->resolvePageSize($pageSize);
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    return $this->queryLine($theme) . "\n" . $this->renderChoiceList($theme);
  }

}
