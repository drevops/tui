<?php

declare(strict_types=1);

namespace DrevOps\Tui\Render;

/**
 * Pure line-compositor: splice a box of lines over a backdrop, centered.
 *
 * Theme-agnostic like {@see Box} and {@see Ansi} - it knows nothing about
 * colour. It places a rendered box over a rectangular backdrop and splices each
 * box line into the backdrop by visible column, so the backdrop shows through
 * the padding on every side. Only the backdrop is sliced - it is plain text the
 * caller has already flattened and padded to the composite width - while the
 * styled box lines are placed verbatim. A caller styles the exposed backdrop
 * (typically dimming it) through the supplied callback.
 *
 * @package DrevOps\Tui\Render
 */
final class Overlay {

  /**
   * The top-left offset that centers a box within an area.
   *
   * @param int $area_width
   *   The area's width in columns.
   * @param int $area_height
   *   The area's height in rows.
   * @param int $box_width
   *   The box's width in columns.
   * @param int $box_height
   *   The box's height in rows.
   *
   * @return array{int,int}
   *   The [top, left] offsets, never negative.
   */
  public static function center(int $area_width, int $area_height, int $box_width, int $box_height): array {
    $top = intdiv(max(0, $area_height - $box_height), 2);
    $left = intdiv(max(0, $area_width - $box_width), 2);

    return [$top, $left];
  }

  /**
   * Splice box lines over plain backdrop lines at a position.
   *
   * Box lines that fall past the backdrop's last row are clipped - the backdrop
   * bounds the composite, so a box taller than the backdrop loses its overflow.
   *
   * @param list<string> $backdrop
   *   The backdrop lines: plain text (no ANSI), each already padded to at least
   *   the box's right edge.
   * @param list<string> $box
   *   The box lines to overlay (may carry ANSI codes).
   * @param int $box_width
   *   The box's visible width - the column span it occupies.
   * @param int $top
   *   The row offset of the box's first line.
   * @param int $left
   *   The column offset of the box's left edge.
   * @param callable(string):string $style_backdrop
   *   Styles an exposed plain-text backdrop segment (e.g. dims it).
   *
   * @return list<string>
   *   The composited lines.
   */
  public static function composite(array $backdrop, array $box, int $box_width, int $top, int $left, callable $style_backdrop): array {
    $out = [];

    foreach ($backdrop as $row => $line) {
      $box_index = $row - $top;

      if ($box_index < 0 || $box_index >= count($box)) {
        $out[] = self::style($line, $style_backdrop);

        continue;
      }

      $prefix = mb_substr($line, 0, $left, 'UTF-8');
      $suffix = mb_substr($line, $left + $box_width, NULL, 'UTF-8');

      $out[] = self::style($prefix, $style_backdrop) . $box[$box_index] . self::style($suffix, $style_backdrop);
    }

    return $out;
  }

  /**
   * Style a backdrop segment, leaving an empty segment untouched.
   *
   * @param string $segment
   *   The plain-text backdrop segment.
   * @param callable(string):string $style_backdrop
   *   The styling callback.
   *
   * @return string
   *   The styled segment, or the empty string when there is nothing to style.
   */
  protected static function style(string $segment, callable $style_backdrop): string {
    return $segment === '' ? '' : $style_backdrop($segment);
  }

}
