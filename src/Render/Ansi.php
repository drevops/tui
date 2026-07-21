<?php

declare(strict_types=1);

namespace DrevOps\Tui\Render;

use DrevOps\Tui\Utils\Utf8;

/**
 * ANSI helpers: styling, escape stripping and visible-width alignment.
 *
 * @package DrevOps\Tui\Render
 */
final class Ansi {

  /**
   * The escape character.
   */
  public const string ESC = "\033";

  /**
   * Wrap text in an SGR style, resetting afterwards.
   *
   * @param string $text
   *   The text.
   * @param string $sgr
   *   The SGR parameters (e.g. "1;32"); empty leaves the text unstyled.
   *
   * @return string
   *   The styled text.
   */
  public static function style(string $text, string $sgr): string {
    return $sgr === '' ? $text : self::ESC . '[' . $sgr . 'm' . $text . self::ESC . '[0m';
  }

  /**
   * Strip ANSI escape sequences from text.
   *
   * @param string $text
   *   The text.
   *
   * @return string
   *   The text without escape sequences.
   */
  public static function strip(string $text): string {
    return (string) preg_replace('/\033\[[0-9;?<>=]*[A-Za-z]/', '', $text);
  }

  /**
   * The visible width of text (ANSI-stripped, code-point counted).
   *
   * @param string $text
   *   The text.
   *
   * @return int
   *   The visible width.
   */
  public static function width(string $text): int {
    return Utf8::length(self::strip($text));
  }

  /**
   * The visible width of a block of lines: its widest line's width.
   *
   * @param list<string> $lines
   *   The lines.
   *
   * @return int
   *   The widest visible width, 0 for an empty block.
   */
  public static function blockWidth(array $lines): int {
    $width = 0;

    foreach ($lines as $line) {
      $width = max($width, self::width($line));
    }

    return $width;
  }

  /**
   * Place a left and right part on one line, right-aligning by visible width.
   *
   * @param string $left
   *   The left part.
   * @param string $right
   *   The right part.
   * @param int $width
   *   The total line width.
   *
   * @return string
   *   The composed line.
   */
  public static function alignRight(string $left, string $right, int $width): string {
    $pad = $width - self::width($left) - self::width($right);

    return $left . str_repeat(' ', max(1, $pad)) . $right;
  }

  /**
   * Wash a multi-line block with a background SGR so it fills edge to edge.
   *
   * A styled span closes with a full reset, which drops the background, so a
   * background opened once would survive only until the first span. This
   * re-opens the wash at the start of every line and again after every reset,
   * then erases each line to its end, so the gaps between spans and the padding
   * past the content all keep the background.
   *
   * @param string $text
   *   The block to wash (may span several lines and carry ANSI codes).
   * @param string $sgr
   *   The background SGR parameters (e.g. "44"); empty returns the text as-is.
   *
   * @return string
   *   The washed block.
   */
  public static function wash(string $text, string $sgr): string {
    if ($sgr === '') {
      return $text;
    }

    $open = self::ESC . '[' . $sgr . 'm';
    $reset = self::ESC . '[0m';
    $lines = explode("\n", $text);

    foreach ($lines as $index => $line) {
      $lines[$index] = $open . str_replace($reset, $reset . $open, $line) . $open . self::ESC . '[K';
    }

    return implode("\n", $lines);
  }

}
