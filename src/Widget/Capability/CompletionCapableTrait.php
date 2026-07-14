<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget\Capability;

/**
 * Inline ghost-text completion over a character buffer.
 *
 * Composes with {@see TextEditCapableTrait}: the buffer is completed to the
 * first declared candidate it is a case-insensitive prefix of.
 *
 * @package DrevOps\Tui\Widget\Capability
 */
trait CompletionCapableTrait {

  /**
   * The best completion candidate for the current buffer, if any.
   *
   * A candidate qualifies only when the caret sits at the end of a non-empty
   * buffer and the buffer is a case-insensitive prefix of a strictly longer
   * candidate; the first such candidate in declared order wins. Returns NULL
   * when nothing completes, so the field behaves as a plain text input.
   *
   * @return string|null
   *   The full candidate string, or NULL.
   */
  public function bestMatch(): ?string {
    if ($this->buffer === '' || $this->cursor !== mb_strlen($this->buffer, 'UTF-8')) {
      return NULL;
    }

    // Fold and measure by character, not byte, so non-ASCII candidates match
    // case-insensitively and the suffix never splits mid-character.
    $needle = mb_strtolower($this->buffer, 'UTF-8');
    $length = mb_strlen($this->buffer, 'UTF-8');

    foreach ($this->completions as $completion) {
      if (mb_strlen($completion, 'UTF-8') > $length && str_starts_with(mb_strtolower($completion, 'UTF-8'), $needle)) {
        return $completion;
      }
    }

    return NULL;
  }

  /**
   * The ghost-text suffix shown after the caret, or an empty string when none.
   *
   * @return string
   *   The suffix of the best candidate beyond the typed buffer.
   */
  public function ghostSuffix(): string {
    $match = $this->bestMatch();

    return $match === NULL ? '' : mb_substr($match, mb_strlen($this->buffer, 'UTF-8'), NULL, 'UTF-8');
  }

  /**
   * Fill the buffer with the current completion candidate, when one applies.
   */
  public function applyCompletion(): void {
    $match = $this->bestMatch();

    if ($match !== NULL) {
      $this->buffer = $match;
      $this->cursor = mb_strlen($match, 'UTF-8');
    }
  }

}
