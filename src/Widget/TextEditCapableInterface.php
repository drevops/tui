<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

/**
 * A widget that edits a character buffer.
 *
 * {@see TextEditTrait} carries the default cursor-based implementation; an
 * append-only widget may implement the vocabulary directly.
 *
 * @package DrevOps\Tui\Widget
 */
interface TextEditCapableInterface {

  /**
   * The live input buffer.
   *
   * @return string
   *   The buffer.
   */
  public function buffer(): string;

  /**
   * Insert text at the editing position.
   *
   * @param string $text
   *   The text to insert.
   */
  public function insert(string $text): void;

  /**
   * Delete the character before the editing position.
   */
  public function backspace(): void;

}
