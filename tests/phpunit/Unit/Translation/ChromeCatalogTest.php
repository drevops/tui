<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Translation;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guards translations/tui.php against drift from the source.
 *
 * Every literal chrome string the library emits - a `Translator::t('...')`
 * call or a `new Hint('...')` label - must have a matching template key, and
 * the template must carry no orphan key, so the canonical list of translatable
 * chrome stays complete and honest.
 */
#[CoversNothing]
#[Group('tui')]
final class ChromeCatalogTest extends TestCase {

  public function testTemplateMatchesSourceLiterals(): void {
    $root = dirname(__DIR__, 4);

    $catalog = require $root . '/translations/tui.php';
    $this->assertIsArray($catalog);
    $template = array_keys($catalog);
    sort($template);

    $literals = $this->literals($root . '/src');
    sort($literals);

    $this->assertSame($template, $literals, 'translations/tui.php is out of sync with the chrome literals in src/. Regenerate it.');

    // The template is a self-describing English catalog: value equals key.
    foreach ($catalog as $key => $value) {
      $this->assertSame($key, $value);
    }
  }

  /**
   * The distinct literal chrome keys emitted across a directory.
   *
   * @param string $directory
   *   The directory to scan.
   *
   * @return list<string>
   *   The unique literal keys from Translator::t() calls and Hint labels.
   */
  protected function literals(string $directory): array {
    $keys = [];

    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
    foreach ($iterator as $entry) {
      if (!$entry instanceof \SplFileInfo) {
        continue;
      }
      if ($entry->getExtension() !== 'php') {
        continue;
      }
      $tokens = token_get_all((string) file_get_contents($entry->getPathname()));
      $count = count($tokens);
      for ($i = 0; $i < $count - 4; $i++) {
        $key = $this->literalArgument($tokens, $i);
        if ($key !== NULL) {
          $keys[$key] = TRUE;
        }
      }
    }

    return array_keys($keys);
  }

  /**
   * The literal chrome key a token sequence introduces at an index, or NULL.
   *
   * @param array<int,array{int,string,int}|string> $tokens
   *   The token stream.
   * @param int $index
   *   The index to test.
   *
   * @return string|null
   *   The literal first argument of a Translator::t() call or a Hint
   *   construction at this index, or NULL when it is neither.
   */
  protected function literalArgument(array $tokens, int $index): ?string {
    $token = $tokens[$index];
    if (!is_array($token) || $token[0] !== T_STRING) {
      return NULL;
    }

    if ($token[1] === 'Translator'
      && is_array($tokens[$index + 1]) && $tokens[$index + 1][0] === T_DOUBLE_COLON
      && is_array($tokens[$index + 2]) && $tokens[$index + 2][1] === 't'
      && ($tokens[$index + 3] ?? NULL) === '('
      && is_array($tokens[$index + 4]) && $tokens[$index + 4][0] === T_CONSTANT_ENCAPSED_STRING) {
      return $this->literalValue($tokens[$index + 4][1]);
    }

    if ($token[1] === 'Hint'
      && ($tokens[$index + 1] ?? NULL) === '('
      && is_array($tokens[$index + 2]) && $tokens[$index + 2][0] === T_CONSTANT_ENCAPSED_STRING
      && $this->precededByNew($tokens, $index)) {
      return $this->literalValue($tokens[$index + 2][1]);
    }

    return NULL;
  }

  /**
   * Whether the token before an index is the `new` keyword.
   *
   * @param array<int,array{int,string,int}|string> $tokens
   *   The token stream.
   * @param int $index
   *   The index whose predecessor is tested.
   *
   * @return bool
   *   TRUE when the nearest prior significant token is T_NEW.
   */
  protected function precededByNew(array $tokens, int $index): bool {
    for ($i = $index - 1; $i >= 0; $i--) {
      if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], TRUE)) {
        continue;
      }

      return is_array($tokens[$i]) && $tokens[$i][0] === T_NEW;
    }

    return FALSE;
  }

  /**
   * The runtime value of a single-quoted PHP string literal token.
   *
   * @param string $token
   *   The token text, including its surrounding quotes.
   *
   * @return string
   *   The unquoted, unescaped string.
   */
  protected function literalValue(string $token): string {
    return stripcslashes(substr($token, 1, -1));
  }

}
