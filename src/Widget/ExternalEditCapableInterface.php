<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

/**
 * A widget that can hand its buffer to an external editor.
 *
 * The widget only raises the request; the driver launches the editor and
 * feeds the captured result back.
 *
 * @package DrevOps\Tui\Widget
 */
interface ExternalEditCapableInterface {

  /**
   * Whether the widget has requested the external-editor handoff.
   *
   * @return bool
   *   TRUE when a handoff was requested.
   */
  public function wantsExternalEdit(): bool;

  /**
   * Apply the buffer captured from the external editor.
   *
   * @param string|null $content
   *   The captured buffer, or NULL when the edit was aborted.
   */
  public function applyExternalEdit(?string $content): void;

}
