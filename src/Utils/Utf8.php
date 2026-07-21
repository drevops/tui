<?php

declare(strict_types=1);

namespace DrevOps\Tui\Utils;

/**
 * UTF-8 string helpers backed by mbstring when available.
 *
 * Every helper uses the mbstring extension when it is loaded and an
 * extension-free fallback otherwise. The fallbacks treat text as UTF-8 via
 * PCRE and match the mbstring results for valid input, except case folding,
 * which covers ASCII letters only.
 *
 * @package DrevOps\Tui\Utils
 */
final class Utf8 {

  /**
   * Whether mbstring backs the helpers; NULL re-detects on next use.
   */
  protected static ?bool $mbstring = NULL;

  /**
   * Force or reset the mbstring branch selection.
   *
   * @param bool|null $enabled
   *   TRUE to use mbstring, FALSE to use the fallbacks, NULL to re-detect
   *   from the loaded extensions on next use.
   */
  public static function useMbstring(?bool $enabled): void {
    self::$mbstring = $enabled;
  }

  /**
   * Whether the mbstring-backed branch is active.
   *
   * @return bool
   *   TRUE when the mbstring functions are used.
   */
  protected static function mbstring(): bool {
    return self::$mbstring ??= function_exists('mb_strlen');
  }

  /**
   * Split text into a list of single characters.
   *
   * @param string $text
   *   The text.
   *
   * @return list<string>
   *   The characters.
   */
  public static function split(string $text): array {
    if (self::mbstring()) {
      return mb_str_split($text, 1, 'UTF-8');
    }

    // PCRE rejects malformed UTF-8, where mbstring substitutes; splitting
    // such input into bytes keeps the helper total.
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

    return $chars === FALSE ? str_split($text) : $chars;
  }

  /**
   * The length of text in characters.
   *
   * @param string $text
   *   The text.
   *
   * @return int
   *   The number of characters.
   */
  public static function length(string $text): int {
    return self::mbstring() ? mb_strlen($text, 'UTF-8') : count(self::split($text));
  }

  /**
   * A portion of text bounded by character offsets.
   *
   * @param string $text
   *   The text.
   * @param int $start
   *   The start offset in characters; negative counts from the end.
   * @param int|null $length
   *   The maximum characters to take; negative leaves that many characters
   *   off the end, NULL takes everything to the end.
   *
   * @return string
   *   The portion.
   */
  public static function substr(string $text, int $start, ?int $length = NULL): string {
    return self::mbstring() ? mb_substr($text, $start, $length, 'UTF-8') : implode('', array_slice(self::split($text), $start, $length));
  }

  /**
   * Lowercase text.
   *
   * @param string $text
   *   The text.
   *
   * @return string
   *   The lowercased text; the fallback lowercases ASCII letters only.
   */
  public static function lower(string $text): string {
    return self::mbstring() ? mb_strtolower($text, 'UTF-8') : strtolower($text);
  }

}
