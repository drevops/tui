<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Config\OptionKind;

/**
 * Ranks candidates against a query by fuzzy (subsequence) relevance.
 *
 * A candidate matches when the query's characters appear in it in order, not
 * necessarily adjacent - so "gha" matches "GitHub Actions". Matches are scored
 * in tiers (exact, prefix, substring, scattered subsequence) so a tighter match
 * always ranks ahead of a looser one, then refined within a tier by how early
 * and how contiguous the match is. Matching is case-insensitive and
 * multibyte-aware, and every match reports the character indices it hit so a
 * widget can highlight them.
 *
 * The matcher is stateless: one instance serves every candidate and query.
 *
 * @package DrevOps\Tui\Widget
 */
final class Matcher {

  /**
   * The tier multiplier: it exceeds the maximum refinement so tiers dominate.
   */
  protected const int TIER_WEIGHT = 1000;

  /**
   * Match a query against a candidate, scoring it and locating the hit.
   *
   * @param string $haystack
   *   The candidate text.
   * @param string $needle
   *   The query.
   *
   * @return \DrevOps\Tui\Widget\MatchResult|null
   *   The result, or NULL when the query is not a subsequence of the candidate.
   *   An empty query matches everything with a zero score and no positions.
   */
  public function match(string $haystack, string $needle): ?MatchResult {
    if ($needle === '') {
      return new MatchResult(0, []);
    }

    $lower_haystack = mb_strtolower($haystack);
    $lower_needle = mb_strtolower($needle);
    $haystack_chars = mb_str_split($lower_haystack);
    $needle_chars = mb_str_split($lower_needle);
    $needle_count = count($needle_chars);

    $positions = [];
    $needle_index = 0;
    foreach ($haystack_chars as $index => $char) {
      if ($needle_index < $needle_count && $char === $needle_chars[$needle_index]) {
        $positions[] = $index;
        $needle_index++;
      }
    }

    if ($needle_index < $needle_count) {
      return NULL;
    }

    $tier = $this->tier($lower_haystack, $lower_needle);

    // A prefix, substring or exact hit is one contiguous run, so highlight that
    // run rather than the greedily-collected subsequence indices.
    if ($tier >= 2) {
      $start = mb_strpos($lower_haystack, $lower_needle);
      if ($start !== FALSE) {
        $positions = range($start, $start + $needle_count - 1);
      }
    }

    return new MatchResult($tier * self::TIER_WEIGHT + $this->refine($positions, $haystack_chars), $positions);
  }

  /**
   * The matched character indices, or an empty list when there is no match.
   *
   * @param string $haystack
   *   The candidate text.
   * @param string $needle
   *   The query.
   *
   * @return list<int>
   *   The matched indices.
   */
  public function positions(string $haystack, string $needle): array {
    return $this->match($haystack, $needle)?->positions ?? [];
  }

  /**
   * Filter and rank a list of string candidates by relevance, best first.
   *
   * @param list<string> $values
   *   The candidate values.
   * @param string $needle
   *   The query.
   *
   * @return list<string>
   *   The matching values, most relevant first; ties keep their input order.
   */
  public function rankValues(array $values, string $needle): array {
    $scores = [];
    foreach ($values as $index => $value) {
      $result = $this->match($value, $needle);
      if ($result instanceof MatchResult) {
        $scores[$index] = $result->score;
      }
    }

    // Sort by score, best first; uasort is stable, so equal scores keep their
    // insertion order - which is the input order.
    uasort($scores, static fn(int $a, int $b): int => $b <=> $a);

    $ranked = [];
    foreach (array_keys($scores) as $index) {
      $ranked[] = $values[$index];
    }

    return $ranked;
  }

  /**
   * Filter and rank option rows by label relevance, best first.
   *
   * Only selectable-or-disabled Option rows take part; separators and headings
   * carry no label and drop away, so the filtered result reads as a flat
   * relevance list.
   *
   * @param list<\DrevOps\Tui\Config\Option> $options
   *   The option rows.
   * @param string $needle
   *   The query.
   *
   * @return list<\DrevOps\Tui\Config\Option>
   *   The matching options, most relevant first; ties keep their input order.
   */
  public function rankOptions(array $options, string $needle): array {
    $scores = [];
    foreach ($options as $index => $option) {
      if ($option->kind !== OptionKind::Option) {
        continue;
      }

      $result = $this->match($option->label, $needle);
      if ($result instanceof MatchResult) {
        $scores[$index] = $result->score;
      }
    }

    // Sort by score, best first; uasort is stable, so equal scores keep their
    // insertion order - which is the declaration order.
    uasort($scores, static fn(int $a, int $b): int => $b <=> $a);

    $ranked = [];
    foreach (array_keys($scores) as $index) {
      $ranked[] = $options[$index];
    }

    return $ranked;
  }

  /**
   * The match tier: exact (4), prefix (3), substring (2) or subsequence (1).
   *
   * @param string $haystack
   *   The lowercased candidate.
   * @param string $needle
   *   The lowercased query.
   *
   * @return int
   *   The tier.
   */
  protected function tier(string $haystack, string $needle): int {
    if ($haystack === $needle) {
      return 4;
    }

    if (str_starts_with($haystack, $needle)) {
      return 3;
    }

    return str_contains($haystack, $needle) ? 2 : 1;
  }

  /**
   * The within-tier refinement, bounded below the tier weight.
   *
   * Rewards a match that starts early, sits at a word boundary and runs
   * contiguously, so the more intuitive hit ranks first among same-tier peers.
   *
   * @param list<int> $positions
   *   The matched indices, ascending.
   * @param list<string> $haystack_chars
   *   The lowercased candidate characters.
   *
   * @return int
   *   A refinement in the range 0..TIER_WEIGHT-1.
   */
  protected function refine(array $positions, array $haystack_chars): int {
    $first = $positions[0];
    $span = $positions[count($positions) - 1] - $first;
    $gaps = $span - (count($positions) - 1);
    $boundary = $this->isWordStart($first, $haystack_chars) ? 50 : 0;

    return max(0, min(self::TIER_WEIGHT - 1, 500 - $first * 10 - $gaps * 20 + $boundary));
  }

  /**
   * Whether an index begins a word (string start or after a non-alphanumeric).
   *
   * @param int $index
   *   The index into the candidate.
   * @param list<string> $haystack_chars
   *   The lowercased candidate characters.
   *
   * @return bool
   *   TRUE when the index starts a word.
   */
  protected function isWordStart(int $index, array $haystack_chars): bool {
    if ($index === 0) {
      return TRUE;
    }

    return preg_match('/[\p{L}\p{N}]/u', $haystack_chars[$index - 1] ?? '') !== 1;
  }

}
