<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\Option;
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
 * A single-choice list with fuzzy type-to-filter over the option labels.
 *
 * A radio list under a query line: printable characters narrow the list,
 * ranked by fuzzy relevance, and the matched characters are highlighted.
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
   * @param array<int|string,\DrevOps\Tui\Config\Option|string> $options
   *   Option rows in display order - a list of options or the value => label
   *   shorthand map.
   * @param string $default
   *   The initially highlighted value.
   * @param \Closure|null $validate
   *   Optional validator (see AbstractWidget).
   * @param \Closure|null $transform
   *   Optional transformer (see AbstractWidget).
   * @param int|null $page_size
   *   The number of option rows shown at once before the list pages; NULL uses
   *   the default.
   */
  public function __construct(array $options, string $default = '', ?\Closure $validate = NULL, ?\Closure $transform = NULL, ?int $page_size = NULL) {
    parent::__construct($validate, $transform);
    $this->initSingleChoice($options, $default);
    $this->pageSize = $this->resolvePageSize($page_size);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
    // Space is part of the query, so it cannot double as a select key here.
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
   * Render one option row: the radio glyph and the match-highlighted label.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Config\Option $option
   *   The option row.
   * @param bool $current
   *   Whether the row holds the cursor.
   *
   * @return string
   *   The rendered row.
   */
  public function renderOptionRow(ThemeInterface $theme, Option $option, bool $current): string {
    if ($option->disabled) {
      return $theme->radio(FALSE) . ' ' . $this->renderDisabledLabel($theme, $option);
    }

    return $theme->radio($current) . ' ' . $this->renderMatchedLabel($theme, $option->label, $this->matchPositions($option->label), $current);
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    return $this->queryLine($theme) . "\n" . $this->renderChoiceList($theme);
  }

}
