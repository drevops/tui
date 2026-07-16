<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Widget\Capability\FilterCapableInterface;
use DrevOps\Tui\Widget\Capability\FilterCapableTrait;
use DrevOps\Tui\Widget\Capability\OptionsCapableInterface;
use DrevOps\Tui\Widget\Capability\OptionsCapableTrait;
use DrevOps\Tui\Widget\Capability\PagingCapableInterface;
use DrevOps\Tui\Widget\Capability\PagingCapableTrait;
use DrevOps\Tui\Widget\Capability\SearchCapableInterface;
use DrevOps\Tui\Widget\Capability\SearchCapableTrait;
use DrevOps\Tui\Widget\Capability\SelectionCapableInterface;
use DrevOps\Tui\Widget\Capability\SelectionCapableTrait;

/**
 * A fuzzy type-to-filter choice list under a query line.
 *
 * A single-choice radio list or a multiple-choice checkbox list: printable
 * characters narrow the list, ranked by fuzzy relevance, and the matched
 * characters are highlighted.
 *
 * @package DrevOps\Tui\Widget
 */
class SearchWidget extends AbstractWidget implements
  OptionsCapableInterface,
  SelectionCapableInterface,
  FilterCapableInterface,
  SearchCapableInterface,
  PagingCapableInterface {

  use OptionsCapableTrait;
  use SelectionCapableTrait;
  use FilterCapableTrait;
  use SearchCapableTrait;
  use PagingCapableTrait;

  /**
   * Construct a search widget.
   *
   * @param array<int|string,\DrevOps\Tui\Model\Option|string> $options
   *   Option rows in display order - a list of options or the value => label
   *   shorthand map.
   * @param string|list<string> $default
   *   The initially highlighted value (single) or selected values (multiple).
   * @param bool $multiple
   *   Whether several options are collected as a list.
   * @param \Closure|null $validate
   *   Optional validator (see AbstractWidget).
   * @param \Closure|null $transform
   *   Optional transformer (see AbstractWidget).
   * @param int|null $page_size
   *   The number of option rows shown at once before the list pages; NULL uses
   *   the default.
   */
  public function __construct(array $options, string|array $default = '', bool $multiple = FALSE, ?\Closure $validate = NULL, ?\Closure $transform = NULL, ?int $page_size = NULL) {
    parent::__construct($validate, $transform);
    $this->initChoice($options, $default, $multiple);
    $this->pageSize = $this->resolvePageSize($page_size);
  }

  /**
   * The field type this widget binds its keys under.
   *
   * @return \DrevOps\Tui\Model\FieldType
   *   The search field type.
   */
  protected function choiceType(): FieldType {
    return FieldType::Search;
  }

  /**
   * {@inheritdoc}
   *
   * Space is part of the query in single mode, so it cannot double as a select
   * key there; multiple mode binds Space to toggle the highlighted option.
   */
  protected function handleSingleMode(Key $key): void {
    if ($this->keys()->matches($key, Action::InsertSpace)) {
      $this->filter .= ' ';
      $this->resetFilterCursor();

      return;
    }

    if ($this->handleFilterKey($key)) {
      return;
    }

    $this->handleSingleChoiceKey($key);
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    return $this->queryLine($theme) . "\n" . $this->renderChoiceList($theme);
  }

}
