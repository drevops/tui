<?php

declare(strict_types=1);

namespace DrevOps\Tui\Answers;

use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Translation\Translator;

/**
 * Formats a self-describing answer set as a human summary grouped by panel.
 *
 * Panel headings come from each answer's panel trail, so only panels with
 * active answers appear; each value is rendered readably and non-default
 * answers carry a provenance badge.
 *
 * @package DrevOps\Tui\Answers
 */
class SummaryFormatter {

  /**
   * The fixed mask length for secret values, concealing their real length.
   */
  public const int MASK_LENGTH = 8;

  /**
   * Format the answers grouped by their panel trails.
   *
   * @param \DrevOps\Tui\Answers\Answers $answers
   *   The answer set (as produced by the engine or the panel TUI).
   *
   * @return string
   *   The formatted summary.
   */
  public function format(Answers $answers): string {
    $lines = [];
    $trail = [];

    foreach ($answers->items as $item) {
      $common = 0;
      while ($common < count($trail) && isset($item->panels[$common]) && $trail[$common] === $item->panels[$common]) {
        $common++;
      }

      foreach (array_slice($item->panels, $common) as $offset => $title) {
        $lines[] = str_repeat('  ', $common + $offset) . Translator::t($title);
      }

      $trail = $item->panels;
      $lines[] = str_repeat('  ', count($item->panels)) . Translator::t($item->label) . ': ' . $this->renderValue($item) . $this->badge($item->provenance);
    }

    return implode("\n", $lines);
  }

  /**
   * Render an answer's value readably, masking secret values.
   *
   * @param \DrevOps\Tui\Answers\Answer $answer
   *   The answer.
   *
   * @return string
   *   The rendered value.
   */
  protected function renderValue(Answer $answer): string {
    $value = $answer->value;

    // Secrets never print: a fixed-length mask hides both value and length.
    if ($answer->type === FieldType::Password) {
      return is_string($value) && $value !== '' ? str_repeat('*', self::MASK_LENGTH) : '';
    }

    if (is_bool($value)) {
      return $value ? Translator::t('yes') : Translator::t('no');
    }

    if (is_array($value)) {
      return implode(', ', array_map(static fn(mixed $item): string => is_scalar($item) ? (string) $item : '', $value));
    }

    return is_scalar($value) ? (string) $value : '';
  }

  /**
   * The provenance badge for a value (empty for defaults).
   *
   * @param \DrevOps\Tui\Answers\Provenance $provenance
   *   The provenance.
   *
   * @return string
   *   The badge suffix.
   */
  protected function badge(Provenance $provenance): string {
    return $provenance === Provenance::Default ? '' : ' (' . $provenance->label() . ')';
  }

}
