<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Theme\ThemeManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the theme registry and factory.
 */
#[CoversClass(ThemeManager::class)]
#[Group('tui')]
final class ThemeManagerTest extends TestCase {

  #[DataProvider('dataProviderCreate')]
  public function testCreate(string $name, array $options, string $role, string $expected): void {
    $theme = ThemeManager::create($name, 76, $options);

    $this->assertInstanceOf(DefaultTheme::class, $theme);
    $this->assertSame($expected, $theme->styleCodes($role));
  }

  public static function dataProviderCreate(): \Iterator {
    yield 'default is dark' => ['default', [], 'title', '1;36'];
    yield 'empty is dark' => ['', [], 'title', '1;36'];
    // The dark/light palette is now a mode option, not a separate theme.
    yield 'light mode' => ['default', ['mode' => ThemeInterface::MODE_LIGHT], 'title', '1;34'];
    yield 'light mode indicator' => ['default', ['mode' => ThemeInterface::MODE_LIGHT], 'indicator', '35'];
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
    ThemeManager::register('registered', DefaultTheme::class);

    $this->assertInstanceOf(DefaultTheme::class, ThemeManager::create('registered'));
  }

  public function testRegisterNonThemeClassThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('must extend');

    ThemeManager::register('bogus', \stdClass::class);
  }

  public function testCreateFromClassName(): void {
    // A theme class name resolves directly, without registration.
    $this->assertInstanceOf(DefaultTheme::class, ThemeManager::create(DefaultTheme::class));
  }

  public function testCreatePassesOptions(): void {
    $theme = ThemeManager::create('default', 40, ['color' => FALSE, 'unicode' => FALSE]);

    $this->assertFalse($theme->hasColor());
    $this->assertFalse($theme->hasUnicode());

    $default = ThemeManager::create('default');
    $this->assertTrue($default->hasColor());
    $this->assertTrue($default->hasUnicode());
  }

}
