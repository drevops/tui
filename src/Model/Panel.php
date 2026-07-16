<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

/**
 * A panel: an ordered group of fields and nested sub-panels.
 *
 * @package DrevOps\Tui\Model
 */
final readonly class Panel {

  /**
   * Construct a panel.
   *
   * @param string $id
   *   The unique panel id.
   * @param string $title
   *   The panel title.
   * @param string $description
   *   The panel description.
   * @param \DrevOps\Tui\Model\Field[] $fields
   *   Ordered fields in this panel.
   * @param \DrevOps\Tui\Model\Panel[] $panels
   *   Ordered nested sub-panels.
   */
  public function __construct(
    public string $id,
    public string $title,
    public string $description,
    public array $fields = [],
    public array $panels = [],
  ) {
  }

  /**
   * The number of navigable items: fields plus sub-panels.
   *
   * @return int
   *   The item count.
   */
  public function itemCount(): int {
    return count($this->fields) + count($this->panels);
  }

}
