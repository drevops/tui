<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Theme\DarkTheme;
use DrevOps\Tui\Theme\LightTheme;
use DrevOps\Tui\Theme\ThemeManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the theme registry, factory and capability detection.
 */
#[CoversClass(ThemeManager::class)]
#[Group('tui')]
final class ThemeManagerTest extends TestCase {

  #[DataProvider('dataProviderCreate')]
  public function testCreate(string $name, string $class, string $role, string $expected): void {
    $theme = ThemeManager::create($name);

    $this->assertSame($theme::class, $class);
    $this->assertSame($expected, $theme->styleCodes($role));
  }

  public static function dataProviderCreate(): \Iterator {
    yield 'dark' => ['dark', DarkTheme::class, 'value', '32'];
    yield 'light' => ['light', LightTheme::class, 'value', '34'];
    yield 'light indicator' => ['light', LightTheme::class, 'indicator', '35'];
    yield 'default is dark' => ['default', DarkTheme::class, 'title', '1;36'];
    yield 'empty is dark' => ['', DarkTheme::class, 'title', '1;36'];
  }

  public function testCreateUnknownThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown theme "bogus"');

    ThemeManager::create('bogus');
  }

  public function testCreateNonThemeClassThrows(): void {
    $this->expectException(\InvalidArgumentException::class);

    ThemeManager::create(\stdClass::class);
  }

  public function testRegister(): void {
    ThemeManager::register('mylight', LightTheme::class);

    $this->assertInstanceOf(LightTheme::class, ThemeManager::create('mylight'));
  }

  public function testCreateFromClassName(): void {
    // A theme class name resolves directly, without registration.
    $this->assertInstanceOf(LightTheme::class, ThemeManager::create(LightTheme::class));
  }

  public function testCreatePassesModes(): void {
    $theme = ThemeManager::create('dark', FALSE, 40, FALSE);

    $this->assertFalse($theme->hasColor());
    $this->assertFalse($theme->hasUnicode());

    $default = ThemeManager::create('dark');
    $this->assertTrue($default->hasColor());
    $this->assertTrue($default->hasUnicode());
  }

  #[DataProvider('dataProviderDetectUnicode')]
  public function testDetectUnicode(?string $lc_all, ?string $lc_ctype, ?string $lang, bool $expected): void {
    $restore = [];
    foreach (['LC_ALL' => $lc_all, 'LC_CTYPE' => $lc_ctype, 'LANG' => $lang] as $var => $value) {
      $restore[$var] = getenv($var);
      is_string($value) ? putenv($var . '=' . $value) : putenv($var);
    }

    try {
      $this->assertSame($expected, ThemeManager::detectUnicode());
    }
    finally {
      foreach ($restore as $var => $value) {
        is_string($value) ? putenv($var . '=' . $value) : putenv($var);
      }
    }
  }

  public static function dataProviderDetectUnicode(): \Iterator {
    yield 'utf lang' => [NULL, NULL, 'en_US.UTF-8', TRUE];
    yield 'non-utf lang' => [NULL, NULL, 'C', FALSE];
    yield 'lc_all wins over lang' => ['en_AU.UTF-8', NULL, 'C', TRUE];
    yield 'lc_ctype checked before lang' => [NULL, 'POSIX', 'en_US.UTF-8', FALSE];
    yield 'none set falls back to ascii' => [NULL, NULL, NULL, FALSE];
  }

  #[DataProvider('dataProviderDetectColor')]
  public function testDetectColor(?string $no_color, ?string $term, bool $expected): void {
    $restore = ['NO_COLOR' => getenv('NO_COLOR'), 'TERM' => getenv('TERM')];
    is_string($no_color) ? putenv('NO_COLOR=' . $no_color) : putenv('NO_COLOR');
    is_string($term) ? putenv('TERM=' . $term) : putenv('TERM');

    try {
      $this->assertSame($expected, ThemeManager::detectColor());
    }
    finally {
      foreach ($restore as $var => $value) {
        is_string($value) ? putenv($var . '=' . $value) : putenv($var);
      }
    }
  }

  public static function dataProviderDetectColor(): \Iterator {
    yield 'normal terminal' => [NULL, 'xterm-256color', TRUE];
    yield 'no_color set' => ['1', 'xterm', FALSE];
    yield 'dumb terminal' => [NULL, 'dumb', FALSE];
    yield 'no_color empty still disables' => ['', 'xterm', FALSE];
  }

}
