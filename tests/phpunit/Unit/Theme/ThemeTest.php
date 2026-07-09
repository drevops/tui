<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Theme\AbstractTheme;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\ThemeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the theme base and the default theme's colour modes.
 */
#[CoversClass(AbstractTheme::class)]
#[CoversClass(DefaultTheme::class)]
#[Group('tui')]
final class ThemeTest extends TestCase {

  #[DataProvider('dataProviderPalette')]
  public function testPalette(AbstractTheme $theme, string $role, string $expected): void {
    $this->assertSame($expected, $theme->styleCodes($role));
  }

  public static function dataProviderPalette(): \Iterator {
    yield 'dark value' => [new DefaultTheme(), 'value', '32'];
    yield 'dark title' => [new DefaultTheme(), 'title', '1;36'];
    // Light mode keeps green for values, like dark mode; its accents differ
    // (blue title, magenta indicator).
    yield 'light value' => [self::light(), 'value', '32'];
    yield 'light title' => [self::light(), 'title', '1;34'];
    yield 'light indicator' => [self::light(), 'indicator', '35'];
    // The border has its own role, coloured per mode (cyan dark, blue light).
    yield 'dark border' => [new DefaultTheme(), 'border', '36'];
    yield 'light border' => [self::light(), 'border', '34'];
    // Roles the default theme does not override come from the base palette.
    yield 'dark footer inherited' => [new DefaultTheme(), 'footer', '90'];
    yield 'light breadcrumb inherited' => [self::light(), 'breadcrumb', '90'];
    yield 'dark error inherited' => [new DefaultTheme(), 'error', '31'];
    yield 'light rule inherited' => [self::light(), 'rule', '90'];
  }

  public function testCustomSubclassMergesOverBase(): void {
    $theme = new class() extends AbstractTheme {

      protected function defineStyles(): array {
        return ['title' => '95'] + parent::defineStyles();
      }

      protected function defineGlyphs(): array {
        return ['marker' => ['»', '>']] + parent::defineGlyphs();
      }

    };

    // The overridden role and glyph apply; everything else inherits.
    $this->assertSame('95', $theme->styleCodes('title'));
    $this->assertSame('»', $theme->glyph('marker'));
    $this->assertSame('90', $theme->styleCodes('footer'));
    $this->assertSame('●', $theme->glyph('radio_on'));
  }

  public function testGlyphs(): void {
    $theme = new DefaultTheme();

    $this->assertSame('❯', $theme->glyph('marker'));
    $this->assertSame('▲', $theme->glyph('indicator_up'));
    $this->assertSame('─', $theme->glyph('rule'));
    $this->assertSame('', $theme->glyph('nope'));
  }

  public function testStyleAndColor(): void {
    $theme = new DefaultTheme();

    $this->assertSame("\033[1;36mT\033[0m", $theme->style('title', 'T'));
    $this->assertTrue($theme->hasColor());
    $this->assertSame('', $theme->styleCodes('nope'));
  }

  public function testNoColor(): void {
    $theme = new DefaultTheme(76, ['color' => FALSE]);

    $this->assertSame('', $theme->styleCodes('title'));
    $this->assertSame('T', $theme->style('title', 'T'));
    $this->assertFalse($theme->hasColor());
  }

  public function testUnicodeGlyphs(): void {
    $theme = new DefaultTheme();

    $this->assertTrue($theme->hasUnicode());
    $this->assertSame('●', $theme->glyph('radio_on'));
    $this->assertSame('◼', $theme->glyph('check_on'));
    $this->assertSame('█', $theme->glyph('caret'));
  }

  public function testAsciiGlyphs(): void {
    $theme = new DefaultTheme(76, ['unicode' => FALSE]);

    $this->assertFalse($theme->hasUnicode());
    $this->assertSame('>', $theme->glyph('marker'));
    $this->assertSame('(*)', $theme->glyph('radio_on'));
    $this->assertSame('[ ]', $theme->glyph('check_off'));
    $this->assertSame('|', $theme->glyph('caret'));
    $this->assertSame('-', $theme->glyph('rule'));
  }

  /**
   * A default theme in light mode.
   *
   * @return \DrevOps\Tui\Theme\DefaultTheme
   *   The theme.
   */
  protected static function light(): DefaultTheme {
    return new DefaultTheme(76, ['mode' => ThemeInterface::MODE_LIGHT]);
  }

}
