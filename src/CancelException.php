<?php

declare(strict_types=1);

namespace DrevOps\Tui;

/**
 * Thrown when the user dismisses an interactive session via the cancel button.
 *
 * A cancel is the button-driven twin of the Ctrl-C abort: the session ends
 * without a submit, so the answers edited before it must never be mistaken for
 * a completed form. It extends {@see InterruptException} so a caller that only
 * tells aborted from submitted catches one exception; a caller that reacts
 * differently to an explicit cancel catches this class first.
 *
 * @package DrevOps\Tui
 */
class CancelException extends InterruptException {

}
