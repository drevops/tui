<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Translation;

use DrevOps\Tui\Tests\Traits\ResetsTranslator;
use DrevOps\Tui\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the translator and its static t() bridge.
 */
#[CoversClass(Translator::class)]
#[Group('tui')]
final class TranslatorTest extends TestCase {

  use ResetsTranslator;

  /**
   * The absolute path to a translation fixture directory.
   */
  protected function fixtures(string $name): string {
    return dirname(__DIR__, 2) . '/Fixtures/' . $name;
  }

  /**
   * @param list<string> $directories
   *   The fixture directory names to search.
   * @param array<string,string|int|float|\Stringable> $args
   *   The placeholder replacements.
   */
  #[DataProvider('dataProviderTranslate')]
  public function testTranslate(string $language, array $directories, string $source, array $args, string $expected): void {
    $translator = new Translator($language, array_map($this->fixtures(...), $directories));

    $this->assertSame($expected, $translator->translate($source, $args));
  }

  public static function dataProviderTranslate(): \Iterator {
    yield 'basic lookup' => ['es', ['translations'], 'Submit', [], 'Enviar'];
    yield 'unknown key falls back to source' => ['es', ['translations'], 'Unknown', [], 'Unknown'];
    yield 'placeholder interpolated' => ['es', ['translations'], 'Enter a number @constraint.', ['@constraint' => 'x'], 'Introduzca un numero x.'];
    yield 'question label translated' => ['es', ['translations'], 'Colour theme', [], 'Tema de color'];
    yield 'translation off returns source' => ['', ['translations'], 'Submit', [], 'Submit'];
    yield 'translation off still interpolates' => ['', ['translations'], 'Value @n.', ['@n' => 5], 'Value 5.'];
    yield 'region catalog wins over primary' => ['es_ES', ['translations'], 'Submit', [], 'Enviar (ES)'];
    yield 'region falls back to primary' => ['es_ES', ['translations-override'], 'Submit', [], 'Enviar (override)'];
    yield 'hyphen locale normalized' => ['es-ES', ['translations'], 'Submit', [], 'Enviar (ES)'];
    yield 'later directory overrides earlier' => ['es', ['translations', 'translations-override'], 'Submit', [], 'Enviar (override)'];
    yield 'malformed catalog ignored' => ['es', ['translations-malformed'], 'Submit', [], 'Submit'];
    yield 'mixed catalog keeps string entry' => ['es', ['translations-mixed'], 'Valid', [], 'Valido'];
    yield 'mixed catalog drops non-string value' => ['es', ['translations-mixed'], 'Integer value', [], 'Integer value'];
    yield 'missing directory ignored' => ['es', ['translations-absent'], 'Submit', [], 'Submit'];
  }

  public function testCatalogLoadsOnce(): void {
    $translator = new Translator('es', [$this->fixtures('translations')]);

    $this->assertSame('Enviar', $translator->translate('Submit'));
    $this->assertSame('Enviar', $translator->translate('Submit'));
  }

  #[DataProvider('dataProviderAuto')]
  public function testAuto(?string $lc_all, string $expected): void {
    $restore = getenv('LC_ALL');
    is_string($lc_all) ? putenv('LC_ALL=' . $lc_all) : putenv('LC_ALL');

    try {
      $translator = new Translator('auto', [$this->fixtures('translations')]);
      $this->assertSame($expected, $translator->translate('Submit'));
    }
    finally {
      is_string($restore) ? putenv('LC_ALL=' . $restore) : putenv('LC_ALL');
    }
  }

  public static function dataProviderAuto(): \Iterator {
    yield 'detected region loads catalog' => ['es_ES.UTF-8', 'Enviar (ES)'];
    yield 'posix locale is english' => ['C', 'Submit'];
  }

  #[DataProvider('dataProviderDetectLanguage')]
  public function testDetectLanguage(?string $lc_all, ?string $lc_messages, ?string $lang, string $expected): void {
    $restore = [];
    foreach (['LC_ALL' => $lc_all, 'LC_MESSAGES' => $lc_messages, 'LANG' => $lang] as $var => $value) {
      $restore[$var] = getenv($var);
      is_string($value) ? putenv($var . '=' . $value) : putenv($var);
    }

    try {
      $this->assertSame($expected, Translator::detectLanguage());
    }
    finally {
      foreach ($restore as $var => $value) {
        is_string($value) ? putenv($var . '=' . $value) : putenv($var);
      }
    }
  }

  public static function dataProviderDetectLanguage(): \Iterator {
    yield 'lc_all decides first' => ['es_ES.UTF-8', 'fr_FR', 'de_DE', 'es_ES'];
    yield 'lc_messages before lang' => [NULL, 'fr_FR.UTF-8', 'de_DE', 'fr_FR'];
    yield 'lang is the last resort' => [NULL, NULL, 'de_DE.UTF-8', 'de_DE'];
    yield 'modifier stripped' => [NULL, NULL, 'ca_ES.UTF-8@valencia', 'ca_ES'];
    yield 'empty value skipped' => ['', NULL, 'it_IT', 'it_IT'];
    yield 'c locale is english' => ['C', NULL, NULL, ''];
    yield 'posix locale is english' => ['POSIX', NULL, NULL, ''];
    yield 'none set is english' => [NULL, NULL, NULL, ''];
  }

  public function testShared(): void {
    $this->assertNull(Translator::shared());

    $translator = new Translator('es', [$this->fixtures('translations')]);
    Translator::setShared($translator);
    $this->assertSame($translator, Translator::shared());

    Translator::setShared(NULL);
    $this->assertNull(Translator::shared());
  }

  public function testStaticBridgeUsesSharedTranslator(): void {
    $this->assertSame('Submit', Translator::t('Submit'));
    $this->assertSame('Value 3.', Translator::t('Value @n.', ['@n' => 3]));

    Translator::setShared(new Translator('es', [$this->fixtures('translations')]));
    $this->assertSame('Enviar', Translator::t('Submit'));
  }

}
