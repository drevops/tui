<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Widget\Capability\FilterCapableInterface;
use DrevOps\Tui\Widget\Capability\FilterCapableTrait;
use DrevOps\Tui\Widget\Capability\MultiSelectionCapableInterface;
use DrevOps\Tui\Widget\Capability\MultiSelectionCapableTrait;
use DrevOps\Tui\Widget\Capability\OptionsCapableInterface;
use DrevOps\Tui\Widget\Capability\OptionsCapableTrait;
use DrevOps\Tui\Widget\Capability\PagingCapableInterface;
use DrevOps\Tui\Widget\Capability\PagingCapableTrait;
use DrevOps\Tui\Widget\Capability\SearchCapableInterface;
use DrevOps\Tui\Widget\Capability\SearchCapableTrait;

/**
 * A checkbox list whose query filters by fuzzy match, shown as a search line.
 *
 * @package DrevOps\Tui\Widget
 */
class MultiSearchWidget extends AbstractWidget implements
  OptionsCapableInterface,
  MultiSelectionCapableInterface,
  FilterCapableInterface,
  SearchCapableInterface,
  PagingCapableInterface {

  use OptionsCapableTrait;
  use MultiSelectionCapableTrait;
  use FilterCapableTrait;
  use SearchCapableTrait;
  use PagingCapableTrait;

  /**
   * Construct a multi-search widget.
   *
   * @param array<int|string,\DrevOps\Tui\Model\Option|string> $options
   *   Option rows in display order - a list of options or the value => label
   *   shorthand map.
   * @param list<string> $default
   *   The initially selected values (non-selectable values are ignored).
   * @param \Closure|null $validate
   *   Optional validator (see AbstractWidget).
   * @param \Closure|null $transform
   *   Optional transformer (see AbstractWidget).
   * @param int|null $page_size
   *   The number of option rows shown at once before the list pages; NULL uses
   *   the default.
   */
  public function __construct(array $options, array $default = [], ?\Closure $validate = NULL, ?\Closure $transform = NULL, ?int $page_size = NULL) {
    parent::__construct($validate, $transform);
    $this->initMultiChoice($options, $default);
    $this->pageSize = $this->resolvePageSize($page_size);
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    return $this->queryLine($theme) . "\n" . $this->renderChoiceList($theme);
  }

}
