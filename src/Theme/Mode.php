<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * The colour mode: which palette suits the terminal background.
 *
 * A consumer passes a case (or its string value) as the "mode" theme option;
 * when the option is unset, detection resolves one from the terminal.
 *
 * @package DrevOps\Tui\Theme
 */
enum Mode: string {

  // Bright foregrounds for a dark terminal background.
  case Dark = 'dark';

  // Darker foregrounds for a light terminal background.
  case Light = 'light';

}
