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
   * @param \DrevOps\Tui\Model\Modal|null $modal
   *   The modal presentation config when this panel opens as a centered dialog
   *   over the parent, or NULL for an ordinary drill-in panel.
   * @param list<int> $layout
   *   The sub-panel grid: one entry per visual row naming how many sub-panels
   *   sit side by side in it, consumed in declaration order. Empty renders the
   *   sub-panels as today's row list.
   */
  public function __construct(
    public string $id,
    public string $title,
    public string $description,
    public array $fields = [],
    public array $panels = [],
    public ?Modal $modal = NULL,
    public array $layout = [],
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

  /**
   * Whether this panel opens as a modal dialog rather than a drill-in panel.
   *
   * @return bool
   *   TRUE when the panel carries a modal config.
   */
  public function isModal(): bool {
    return $this->modal instanceof Modal;
  }

}
