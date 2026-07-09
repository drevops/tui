<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Theme\DarkTheme;
use DrevOps\Tui\Theme\LightTheme;
use DrevOps\Tui\Theme\AbstractTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the theme base and its concrete themes.
 */
#[CoversClass(AbstractTheme::class)]
#[CoversClass(DarkTheme::class)]
#[CoversClass(LightTheme::class)]
#[Group('tui')]
final class ThemeTest extends TestCase {

  #[DataProvider('dataProviderPalette')]
  public function testPalette(AbstractTheme $theme, string $role, string $expected): void {
    $this->assertSame($expected, $theme->styleCodes($role));
  }

  public static function dataProviderPalette(): \Iterator {
    yield 'dark value' => [new DarkTheme(), 'value', '32'];
    yield 'dark title' => [new DarkTheme(), 'title', '1;36'];
    // The light theme keeps green for values, like the dark theme; its accents
    // differ (blue title, magenta indicator).
    yield 'light value' => [new LightTheme(), 'value', '32'];
    yield 'light title' => [new LightTheme(), 'title', '1;34'];
    yield 'light indicator' => [new LightTheme(), 'indicator', '35'];
    // Roles a concrete theme does not override come from the base palette.
    yield 'dark footer inherited' => [new DarkTheme(), 'footer', '90'];
    yield 'light breadcrumb inherited' => [new LightTheme(), 'breadcrumb', '90'];
    yield 'dark error inherited' => [new DarkTheme(), 'error', '31'];
    yield 'light rule inherited' => [new LightTheme(), 'rule', '90'];
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
    $theme = new DarkTheme();

    $this->assertSame('❯', $theme->glyph('marker'));
    $this->assertSame('▲', $theme->glyph('indicator_up'));
    $this->assertSame('─', $theme->glyph('rule'));
    $this->assertSame('', $theme->glyph('nope'));
  }

  public function testStyleAndColor(): void {
    $theme = new DarkTheme();

    $this->assertSame("\033[1;36mT\033[0m", $theme->style('title', 'T'));
    $this->assertTrue($theme->hasColor());
    $this->assertSame('', $theme->styleCodes('nope'));
  }

  public function testNoColor(): void {
    $theme = new DarkTheme(FALSE);

    $this->assertSame('', $theme->styleCodes('title'));
    $this->assertSame('T', $theme->style('title', 'T'));
    $this->assertFalse($theme->hasColor());
  }

  public function testUnicodeGlyphs(): void {
    $theme = new DarkTheme();

    $this->assertTrue($theme->hasUnicode());
    $this->assertSame('●', $theme->glyph('radio_on'));
    $this->assertSame('◼', $theme->glyph('check_on'));
    $this->assertSame('█', $theme->glyph('caret'));
  }

  public function testAsciiGlyphs(): void {
    $theme = new DarkTheme(TRUE, 76, FALSE);

    $this->assertFalse($theme->hasUnicode());
    $this->assertSame('>', $theme->glyph('marker'));
    $this->assertSame('(*)', $theme->glyph('radio_on'));
    $this->assertSame('[ ]', $theme->glyph('check_off'));
    $this->assertSame('|', $theme->glyph('caret'));
    $this->assertSame('-', $theme->glyph('rule'));
  }

}
