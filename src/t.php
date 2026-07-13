<?php

/**
 * @file
 * The t() translation function for user-facing strings.
 */

declare(strict_types=1);

namespace DrevOps\Tui;

use DrevOps\Tui\Translation\Translator;

/**
 * Translate a user-facing string to the active language.
 *
 * Delegates to the process-wide translator set with
 * {@see \DrevOps\Tui\Translation\Translator::setShared()}. With none set,
 * translation is off and the source string is returned with its placeholders
 * substituted, so a call is always safe and defaults to English.
 *
 * @param string $message
 *   The English source string, used as the catalog key.
 * @param array<string,string|int|float|\Stringable> $args
 *   Replacements for the @name placeholders in the message.
 *
 * @return string
 *   The translated string, or the interpolated source when untranslated.
 */
function t(string $message, array $args = []): string {
  $shared = Translator::shared();

  return $shared instanceof Translator ? $shared->translate($message, $args) : Translator::interpolate($message, $args);
}
