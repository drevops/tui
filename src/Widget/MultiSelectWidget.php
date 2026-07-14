<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Config\OptionKind;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Widget\Capability\FilterCapableInterface;
use DrevOps\Tui\Widget\Capability\FilterCapableTrait;
use DrevOps\Tui\Widget\Capability\MultiSelectionCapableInterface;
use DrevOps\Tui\Widget\Capability\MultiSelectionCapableTrait;
use DrevOps\Tui\Widget\Capability\OptionsCapableInterface;
use DrevOps\Tui\Widget\Capability\OptionsCapableTrait;
use DrevOps\Tui\Widget\Capability\PagingCapableInterface;
use DrevOps\Tui\Widget\Capability\PagingCapableTrait;

/**
 * A checkbox list with type-to-filter and select-all/none.
 *
 * Printable characters narrow the list; Space toggles the highlighted option;
 * Right selects all visible options and Left deselects them.
 *
 * @package DrevOps\Tui\Widget
 */
class MultiSelectWidget extends AbstractWidget implements
  OptionsCapableInterface,
  MultiSelectionCapableInterface,
  FilterCapableInterface,
  PagingCapableInterface {

  use OptionsCapableTrait;
  use MultiSelectionCapableTrait;
  use FilterCapableTrait;
  use PagingCapableTrait;

  /**
   * Construct a multiselect widget.
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
   * Filter the options by case-insensitive substring over the labels.
   *
   * @param string $needle
   *   The query.
   *
   * @return list<\DrevOps\Tui\Config\Option>
   *   The matching option rows.
   */
  protected function filterOptions(string $needle): array {
    $lower = mb_strtolower($needle, 'UTF-8');

    return array_values(array_filter($this->options, static fn(Option $option): bool => $option->kind === OptionKind::Option && str_contains(mb_strtolower($option->label, 'UTF-8'), $lower)));
  }

  /**
   * The matched-character positions: a plain checkbox list highlights none.
   *
   * @param string $label
   *   The option label.
   *
   * @return list<int>
   *   The matched indices (always empty).
   */
  protected function matchPositions(string $label): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    return $this->renderChoiceList($theme);
  }

}
