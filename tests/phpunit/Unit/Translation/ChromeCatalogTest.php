<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Translation;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guards translations/tui.php against drift from the source.
 *
 * Every literal chrome string passed to Translator::t() must have a matching
 * key in the shipped catalog template, and the template must carry no orphan
 * key, so the canonical list of translatable chrome stays complete and honest.
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

    $this->assertSame($template, $literals, 'translations/tui.php is out of sync with the Translator::t() literals in src/. Regenerate it.');

    // The template is a self-describing English catalog: value equals key.
    foreach ($catalog as $key => $value) {
      $this->assertSame($key, $value);
    }
  }

  /**
   * The distinct literal keys passed to Translator::t() across a directory.
   *
   * @param string $directory
   *   The directory to scan.
   *
   * @return list<string>
   *   The unique literal first arguments of Translator::t() calls.
   */
  protected function literals(string $directory): array {
    $keys = [];

    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
    foreach ($iterator as $entry) {
      if (!$entry instanceof \SplFileInfo || $entry->getExtension() !== 'php') {
        continue;
      }

      $tokens = token_get_all((string) file_get_contents($entry->getPathname()));
      $count = count($tokens);
      for ($i = 0; $i < $count - 4; $i++) {
        if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING && $tokens[$i][1] === 'Translator'
          && is_array($tokens[$i + 1]) && $tokens[$i + 1][0] === T_DOUBLE_COLON
          && is_array($tokens[$i + 2]) && $tokens[$i + 2][1] === 't'
          && $tokens[$i + 3] === '('
          && is_array($tokens[$i + 4]) && $tokens[$i + 4][0] === T_CONSTANT_ENCAPSED_STRING) {
          $keys[$this->literalValue($tokens[$i + 4][1])] = TRUE;
        }
      }
    }

    return array_keys($keys);
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
