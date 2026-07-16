<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

/**
 * The submit/cancel action pair that closes a form or a modal.
 *
 * Shared chrome: a {@see FormDefinition} carries one for its root submit/cancel
 * row, and a {@see Modal} carries one for the dialog's own row. The labels are
 * configurable; a modal always shows its pair, while a form may hide it.
 *
 * @package DrevOps\Tui\Model
 */
final readonly class Buttons {

  /**
   * Construct a button pair.
   *
   * @param bool $show
   *   Whether the pair is shown.
   * @param string $submitLabel
   *   The submit (accept) button label.
   * @param string $cancelLabel
   *   The cancel (dismiss) button label.
   */
  public function __construct(
    public bool $show = TRUE,
    public string $submitLabel = 'Submit',
    public string $cancelLabel = 'Cancel',
  ) {
  }

}
