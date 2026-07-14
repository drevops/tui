<?php

declare(strict_types=1);

namespace DrevOps\Tui\Render;

/**
 * The computed state of a scrolling viewport.
 *
 * @package DrevOps\Tui\Render
 */
final readonly class Viewport {

  /**
   * Construct a viewport state.
   *
   * @param int $offset
   *   The index of the first visible line.
   * @param bool $hasAbove
   *   Whether there is content scrolled off above (▲).
   * @param bool $hasBelow
   *   Whether there is content scrolled off below (▼).
   */
  public function __construct(
    public int $offset,
    public bool $hasAbove,
    public bool $hasBelow,
  ) {
  }

}
