<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A single-choice radio list.
 *
 * @package DrevOps\Tui\Widget
 */
class SelectWidget extends AbstractWidget {

  use ChoiceListTrait;
  use SingleChoiceTrait;

  /**
   * Construct a select widget.
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
    $this->handleSingleChoiceKey($key);
  }

  /**
   * The rows currently shown: a plain select shows every declared row.
   *
   * @return list<\DrevOps\Tui\Config\Option>
   *   The visible rows.
   */
  protected function visible(): array {
    return $this->options;
  }

  /**
   * {@inheritdoc}
   */
  public function view(ThemeInterface $theme): string {
    return $this->renderChoiceList($theme);
  }

}
