<?php

declare(strict_types=1);

namespace DrevOps\Tui\Render;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\ScopedKeyMap;

/**
 * One section of the help overlay: a titled context with its key hints.
 *
 * The controller assembles one per context it wants to teach - the panel hub
 * and each widget type the form uses - pairing the section's bindings with the
 * hint fragments to render against them, so the overlay reads from the same
 * source of truth as the live footer.
 *
 * @package DrevOps\Tui\Render
 */
final readonly class HelpSection {

  /**
   * The hint fragments to render for this section.
   *
   * @var list<\DrevOps\Tui\Input\Hint>
   */
  public array $hints;

  /**
   * Construct a help section.
   *
   * @param string $title
   *   The section heading (e.g. "Navigation", "Select").
   * @param \DrevOps\Tui\Input\ScopedKeyMap $keys
   *   The section's bindings, so the glyphs reflect the live keys.
   * @param \DrevOps\Tui\Input\Hint ...$hints
   *   The hint fragments in display order.
   */
  public function __construct(
    public string $title,
    public ScopedKeyMap $keys,
    Hint ...$hints,
  ) {
    $this->hints = array_values($hints);
  }

}
