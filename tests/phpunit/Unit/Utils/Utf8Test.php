<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Utils;

use DrevOps\Tui\Utils\Utf8;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the UTF-8 helpers in the mbstring and fallback branches.
 */
#[CoversClass(Utf8::class)]
#[Group('utils')]
final class Utf8Test extends TestCase {

  protected function tearDown(): void {
    Utf8::useMbstring(NULL);
    parent::tearDown();
  }

  #[DataProvider('dataProviderLength')]
  public function testLength(string $text, int $expected): void {
    Utf8::useMbstring(TRUE);
    $this->assertSame($expected, Utf8::length($text));

    Utf8::useMbstring(FALSE);
    $this->assertSame($expected, Utf8::length($text));
  }

  public static function dataProviderLength(): \Iterator {
    yield 'empty' => ['', 0];
    yield 'ascii' => ['Pear', 4];
    yield 'accented' => ['pêche', 5];
    yield 'emoji' => ['🍎🍐', 2];
    yield 'mixed' => ['Grape 🍇', 7];
  }

  #[DataProvider('dataProviderSubstr')]
  public function testSubstr(string $text, int $start, ?int $length, string $expected): void {
    Utf8::useMbstring(TRUE);
    $this->assertSame($expected, Utf8::substr($text, $start, $length));

    Utf8::useMbstring(FALSE);
    $this->assertSame($expected, Utf8::substr($text, $start, $length));
  }

  public static function dataProviderSubstr(): \Iterator {
    yield 'empty' => ['', 0, NULL, ''];
    yield 'ascii middle' => ['Pear', 1, 2, 'ea'];
    yield 'prefix' => ['🍎🍐🍒🍋', 0, 2, '🍎🍐'];
    yield 'to end' => ['🍎🍐🍒🍋', 1, NULL, '🍐🍒🍋'];
    yield 'negative start' => ['🍎🍐🍒🍋', -2, NULL, '🍒🍋'];
    yield 'negative length' => ['🍎🍐🍒🍋', 0, -1, '🍎🍐🍒'];
    yield 'length past end' => ['🍎🍐🍒🍋', 3, 5, '🍋'];
    yield 'start past end' => ['🍎🍐🍒🍋', 9, 2, ''];
    yield 'negative start clamped' => ['🍎🍐🍒🍋', -9, 2, '🍎🍐'];
    yield 'negative length past start' => ['🍎🍐🍒🍋', 0, -9, ''];
  }

  #[DataProvider('dataProviderLower')]
  public function testLower(string $text, string $expected_mbstring, string $expected_fallback): void {
    Utf8::useMbstring(TRUE);
    $this->assertSame($expected_mbstring, Utf8::lower($text));

    Utf8::useMbstring(FALSE);
    $this->assertSame($expected_fallback, Utf8::lower($text));
  }

  public static function dataProviderLower(): \Iterator {
    yield 'ascii' => ['PEAR', 'pear', 'pear'];
    yield 'accented' => ['PÊCHE', 'pêche', 'pÊche'];
    yield 'cyrillic' => ['ЯБЛОКО', 'яблоко', 'ЯБЛОКО'];
    yield 'emoji' => ['Tomato 🍅', 'tomato 🍅', 'tomato 🍅'];
  }

  #[DataProvider('dataProviderSplit')]
  public function testSplit(string $text, array $expected): void {
    Utf8::useMbstring(TRUE);
    $this->assertSame($expected, Utf8::split($text));

    Utf8::useMbstring(FALSE);
    $this->assertSame($expected, Utf8::split($text));
  }

  public static function dataProviderSplit(): \Iterator {
    yield 'empty' => ['', []];
    yield 'ascii' => ['pea', ['p', 'e', 'a']];
    yield 'emoji between ascii' => ['🍎x🍐', ['🍎', 'x', '🍐']];
    yield 'accented' => ['pêche', ['p', 'ê', 'c', 'h', 'e']];
  }

  public function testMalformedInputFallsBackToBytes(): void {
    Utf8::useMbstring(FALSE);

    $this->assertSame(["\xC3", '('], Utf8::split("\xC3("));
    $this->assertSame(2, Utf8::length("\xC3("));
    $this->assertSame("\xC3", Utf8::substr("\xC3(", 0, 1));
  }

  public function testDetectsMbstring(): void {
    Utf8::useMbstring(NULL);

    $this->assertSame(1, Utf8::length('🍎'));
  }

}
