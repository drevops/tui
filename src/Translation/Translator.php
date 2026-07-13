<?php

declare(strict_types=1);

namespace DrevOps\Tui\Translation;

/**
 * Resolves user-facing strings to a target language, English as the fallback.
 *
 * The English source string is the catalog key: a lookup that misses returns
 * the source unchanged, so an untranslated string always renders in English.
 * Catalogs are per-language PHP files (e.g. "es.php") that return a
 * `source => translation` map; directories are searched in order and a later
 * one overrides an earlier one, so a consumer catalog overrides a bundled one.
 * The array-file format loads under a PHAR without a parser.
 *
 * The active language is either empty (translation off), the "auto" sentinel
 * (detected from the environment), or a locale such as "es" or "es_ES". A
 * region locale falls back to its primary subtag, so "es_ES" uses "es.php" when
 * no "es_ES.php" exists.
 *
 * A process-wide instance set with {@see Translator::setShared()} backs the
 * {@see \DrevOps\Tui\t()} function, so chrome can translate from anywhere
 * without threading a translator through every constructor.
 *
 * @package DrevOps\Tui\Translation
 */
final class Translator {

  /**
   * The process-wide translator backing the t() function.
   */
  protected static ?Translator $shared = NULL;

  /**
   * The merged catalog for the active language, loaded on first use.
   *
   * @var array<string,string>|null
   */
  protected ?array $catalog = NULL;

  /**
   * Construct a translator.
   *
   * @param string $language
   *   The active language: empty to disable translation (English source),
   *   "auto" to detect from the environment, or a locale such as "es" or
   *   "es_ES".
   * @param list<string> $directories
   *   Catalog directories, searched in order; a later directory overrides an
   *   earlier one.
   */
  public function __construct(
    protected string $language = '',
    protected array $directories = [],
  ) {
  }

  /**
   * Set the process-wide translator backing the t() function.
   *
   * @param \DrevOps\Tui\Translation\Translator|null $translator
   *   The translator, or NULL to disable translation (English source).
   */
  public static function setShared(?Translator $translator): void {
    self::$shared = $translator;
  }

  /**
   * The process-wide translator backing the t() function.
   *
   * @return \DrevOps\Tui\Translation\Translator|null
   *   The translator, or NULL when none is set.
   */
  public static function shared(): ?Translator {
    return self::$shared;
  }

  /**
   * Translate a string through the process-wide translator, English fallback.
   *
   * The entry point for every user-facing string: with a shared translator set
   * it localizes, otherwise it returns the source with its placeholders filled,
   * so a call is always safe and defaults to English.
   *
   * @param string $message
   *   The English source string, used as the catalog key.
   * @param array<string,string|int|float|\Stringable> $args
   *   Replacements for the @name placeholders in the message.
   *
   * @return string
   *   The translated string, or the interpolated source when untranslated.
   */
  public static function t(string $message, array $args = []): string {
    return self::$shared instanceof self ? self::$shared->translate($message, $args) : self::interpolate($message, $args);
  }

  /**
   * Translate a source string, substituting its placeholders.
   *
   * @param string $source
   *   The English source string, used as the catalog key.
   * @param array<string,string|int|float|\Stringable> $args
   *   Replacements for the @name placeholders in the resolved string.
   *
   * @return string
   *   The translation, or the interpolated source when it is untranslated.
   */
  public function translate(string $source, array $args = []): string {
    $catalog = $this->catalog();

    return self::interpolate($catalog[$source] ?? $source, $args);
  }

  /**
   * Substitute @name placeholders in a message.
   *
   * The one substitution routine, shared by the instance path and the t()
   * fallback path so a translated and an untranslated string interpolate
   * identically.
   *
   * @param string $message
   *   The message, possibly carrying @name placeholders.
   * @param array<string,string|int|float|\Stringable> $args
   *   The placeholder replacements, keyed by placeholder (e.g. "@count").
   *
   * @return string
   *   The message with its placeholders replaced.
   */
  public static function interpolate(string $message, array $args = []): string {
    if ($args === []) {
      return $message;
    }

    $map = [];
    foreach ($args as $placeholder => $value) {
      $map[$placeholder] = (string) $value;
    }

    return strtr($message, $map);
  }

  /**
   * Detect the language from the environment's POSIX locale variables.
   *
   * The first set and non-empty LC_ALL, LC_MESSAGES or LANG decides. The
   * encoding and modifier are stripped ("es_ES.UTF-8@euro" becomes "es_ES"),
   * and a "C" or "POSIX" locale means no translation.
   *
   * @return string
   *   The detected locale (e.g. "es_ES"), or an empty string when none applies.
   */
  public static function detectLanguage(): string {
    foreach (['LC_ALL', 'LC_MESSAGES', 'LANG'] as $var) {
      $value = getenv($var);
      if (!is_string($value) || $value === '') {
        continue;
      }

      $locale = (string) preg_replace('/[.@].*$/', '', $value);

      return $locale === 'C' || $locale === 'POSIX' ? '' : $locale;
    }

    return '';
  }

  /**
   * The merged catalog for the active language, loaded once.
   *
   * @return array<string,string>
   *   The source => translation map (empty when translation is off).
   */
  protected function catalog(): array {
    if ($this->catalog !== NULL) {
      return $this->catalog;
    }

    $language = $this->language === 'auto' ? self::detectLanguage() : $this->language;

    $this->catalog = $language === '' ? [] : $this->load($language);

    return $this->catalog;
  }

  /**
   * Load and merge the catalog for a language across the directories.
   *
   * @param string $language
   *   The effective language (a locale such as "es" or "es_ES").
   *
   * @return array<string,string>
   *   The merged source => translation map.
   */
  protected function load(string $language): array {
    $candidates = self::candidates($language);
    $catalog = [];

    foreach ($this->directories as $directory) {
      foreach ($candidates as $candidate) {
        $file = rtrim($directory, '/') . '/' . $candidate . '.php';
        if (!is_file($file)) {
          continue;
        }

        $data = require $file;
        if (is_array($data)) {
          foreach ($data as $key => $value) {
            if (is_string($key) && is_string($value)) {
              $catalog[$key] = $value;
            }
          }
        }

        // The first candidate present in a directory wins; a region catalog is
        // not merged on top of its primary-subtag catalog within one directory.
        break;
      }
    }

    return $catalog;
  }

  /**
   * The catalog filenames to try for a locale, most specific first.
   *
   * A candidate becomes part of a `require`d file path, so anything that is not
   * a plain locale identifier is dropped: this keeps a path separator or a
   * traversal segment out of the filename and leaves such a locale with no
   * catalog (English).
   *
   * @param string $language
   *   The locale (e.g. "es" or "es_ES"; a hyphen is normalized to underscore).
   *
   * @return list<string>
   *   The valid candidate language codes, e.g. ["es_ES", "es"] or ["es"].
   */
  protected static function candidates(string $language): array {
    $normalized = str_replace('-', '_', $language);
    $primary = explode('_', $normalized)[0];

    return array_values(array_filter(array_unique([$normalized, $primary]), static fn(string $candidate): bool => preg_match('/^[A-Za-z0-9_]+$/', $candidate) === 1));
  }

}
