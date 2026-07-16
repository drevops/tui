<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * The horizontal alignment of content within the available width.
 *
 * A consumer passes a case (or its string value) as the "halign" theme
 * option; it takes effect in fullscreen mode.
 *
 * @package DrevOps\Tui\Theme
 */
enum HAlign: string {

  // Content sits against the left edge (the default).
  case Left = 'left';

  // Content centers within the available width.
  case Center = 'center';

  // Content sits against the right edge.
  case Right = 'right';

}
