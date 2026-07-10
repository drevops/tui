<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Render;

use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Box;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the pure box-drawing geometry helper.
 */
#[CoversClass(Box::class)]
#[Group('tui')]
final class BoxTest extends TestCase {

  #[DataProvider('dataProviderChars')]
  public function testChars(string $style, bool $unicode, string $key, string $expected): void {
    $this->assertSame($expected, Box::chars($style, $unicode)[$key]);
  }

  public static function dataProviderChars(): \Iterator {
    yield 'line top-left' => ['line', TRUE, 'tl', '┌'];
    yield 'line horizontal' => ['line', TRUE, 'h', '─'];
    yield 'line vertical' => ['line', TRUE, 'v', '│'];
    yield 'line junction' => ['line', TRUE, 'ml', '├'];
    yield 'rounded top-left' => ['rounded', TRUE, 'tl', '╭'];
    yield 'rounded bottom-right' => ['rounded', TRUE, 'br', '╯'];
    yield 'double top-left' => ['double', TRUE, 'tl', '╔'];
    yield 'double horizontal' => ['double', TRUE, 'h', '═'];
    yield 'ascii line corner' => ['line', FALSE, 'tl', '+'];
    yield 'ascii line fill' => ['line', FALSE, 'h', '-'];
    yield 'ascii line vertical' => ['line', FALSE, 'v', '|'];
    yield 'ascii double fill' => ['double', FALSE, 'h', '='];
  }

  #[DataProvider('dataProviderRule')]
  public function testRule(string $left, string $right, string $fill, int $width, string $expected): void {
    $this->assertSame($expected, Box::rule($left, $right, $fill, $width));
  }

  public static function dataProviderRule(): \Iterator {
    yield 'even span' => ['+', '+', '-', 6, '+----+'];
    yield 'unicode span' => ['┌', '┐', '─', 4, '┌──┐'];
    // Narrower than the two corners still yields both corners, with no fill.
    yield 'degenerate' => ['+', '+', '-', 1, '++'];
  }

  #[DataProvider('dataProviderFit')]
  public function testFit(string $content, int $width, string $expected): void {
    $this->assertSame($expected, Box::fit($content, $width));
  }

  public static function dataProviderFit(): \Iterator {
    yield 'pads when short' => ['ab', 5, 'ab   '];
    yield 'exact width' => ['abc', 3, 'abc'];
    yield 'clips when long' => ['abcdef', 3, 'abc'];
    yield 'empty pads full' => ['', 3, '   '];
  }

  public function testFitClipDropsAnsiStyling(): void {
    // A too-wide styled string is clipped to plain text at the visible width.
    $this->assertSame('abc', Box::fit(Ansi::style('abcdef', '36'), 3));
  }

  public function testFitKeepsAnsiWhenItAlreadyFits(): void {
    // A short styled string keeps its styling and is padded to the width.
    $styled = Ansi::style('ab', '36');

    $this->assertSame($styled . '   ', Box::fit($styled, 5));
  }

}
