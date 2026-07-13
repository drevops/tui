<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Render;

use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Theme\ThemeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the terminal output and capability detection.
 */
#[CoversClass(Terminal::class)]
#[Group('tui')]
final class TerminalTest extends TestCase {

  public function testWriteAndRender(): void {
    $stream = fopen('php://memory', 'rw');
    $this->assertIsResource($stream);

    $terminal = new Terminal($stream);
    $terminal->write('hello');
    $terminal->render('FRAME');

    rewind($stream);
    $contents = (string) stream_get_contents($stream);
    fclose($stream);

    $this->assertStringContainsString('hello', $contents);
    $this->assertStringContainsString('FRAME', $contents);
    $this->assertStringContainsString("\033[2J", $contents);
  }

  public function testHeight(): void {
    $this->assertGreaterThan(0, (new Terminal())->height());
  }

  public function testClear(): void {
    $stream = fopen('php://memory', 'rw');
    $this->assertIsResource($stream);

    $terminal = new Terminal($stream);
    $terminal->clear();

    rewind($stream);
    $contents = (string) stream_get_contents($stream);
    fclose($stream);

    $this->assertStringContainsString("\033[2J", $contents);
  }

  public function testRead(): void {
    $output = fopen('php://memory', 'r+');
    $input = fopen('php://memory', 'r+');
    $this->assertIsResource($output);
    $this->assertIsResource($input);

    fwrite($input, 'abc');
    rewind($input);

    $terminal = new Terminal($output, $input);
    $this->assertSame('abc', $terminal->read(32));
    // A read past the end of the stream reports no bytes.
    $this->assertSame('', $terminal->read(32));

    fclose($output);
    fclose($input);
  }

  public function testQueryBackgroundReturnsNullOffTty(): void {
    $output = fopen('php://memory', 'r+');
    $input = fopen('php://memory', 'r+');
    $this->assertIsResource($output);
    $this->assertIsResource($input);

    // A non-TTY input has no queryable background.
    $this->assertNull((new Terminal($output, $input))->queryBackground());

    fclose($output);
    fclose($input);
  }

  #[DataProvider('dataProviderDetectUnicode')]
  public function testDetectUnicode(?string $lc_all, ?string $lc_ctype, ?string $lang, bool $expected): void {
    $restore = [];
    foreach (['LC_ALL' => $lc_all, 'LC_CTYPE' => $lc_ctype, 'LANG' => $lang] as $var => $value) {
      $restore[$var] = getenv($var);
      is_string($value) ? putenv($var . '=' . $value) : putenv($var);
    }

    try {
      $this->assertSame($expected, Terminal::detectUnicode());
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
      $this->assertSame($expected, Terminal::detectColor());
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
    yield 'no_color empty is treated as unset' => ['', 'xterm', TRUE];
  }

  #[DataProvider('dataProviderDetectMode')]
  public function testDetectMode(?string $osc_response, ?string $colorfgbg, string $expected): void {
    $restore = getenv('COLORFGBG');
    is_string($colorfgbg) ? putenv('COLORFGBG=' . $colorfgbg) : putenv('COLORFGBG');

    try {
      $this->assertSame($expected, Terminal::detectMode($osc_response));
    }
    finally {
      is_string($restore) ? putenv('COLORFGBG=' . $restore) : putenv('COLORFGBG');
    }
  }

  public static function dataProviderDetectMode(): \Iterator {
    $dark = ThemeInterface::MODE_DARK;
    $light = ThemeInterface::MODE_LIGHT;

    yield 'osc black is dark' => ["\033]11;rgb:0000/0000/0000\007", NULL, $dark];
    yield 'osc white is light' => ["\033]11;rgb:ffff/ffff/ffff\007", NULL, $light];
    yield 'osc 8-bit black' => ['rgb:00/00/00', NULL, $dark];
    yield 'osc 8-bit white' => ['rgb:ff/ff/ff', NULL, $light];
    yield 'osc single-digit white' => ['rgb:f/f/f', NULL, $light];
    yield 'osc mid grey is light at the boundary' => ['rgb:8080/8080/8080', NULL, $light];
    yield 'osc dark grey is dark' => ['rgb:3030/3030/3030', NULL, $dark];
    yield 'osc rgba prefix ignores alpha' => ['rgba:0000/0000/0000/ffff', NULL, $dark];
    yield 'osc st terminator' => ["\033]11;rgb:ffff/ffff/ffff\033\\", NULL, $light];
    yield 'osc bright green is light' => ['rgb:0000/ffff/0000', NULL, $light];
    yield 'osc pure blue is dark' => ['rgb:0000/0000/ffff', NULL, $dark];
    yield 'unparseable osc falls through to colorfgbg' => ['garbage', '0;15', $light];
    yield 'unparseable osc falls through to dark' => ['garbage', NULL, $dark];
    yield 'null osc colorfgbg dark bg' => [NULL, '15;0', $dark];
    yield 'null osc colorfgbg light bg' => [NULL, '0;15', $light];
    yield 'null osc colorfgbg decoration field' => [NULL, '1;15;0', $dark];
    yield 'null osc colorfgbg index 7 is light' => [NULL, '0;7', $light];
    yield 'null osc colorfgbg index 8 is dark' => [NULL, '15;8', $dark];
    yield 'null osc colorfgbg default bg is dark' => [NULL, 'default;default', $dark];
    yield 'null osc colorfgbg empty is dark' => [NULL, '', $dark];
    yield 'null osc no colorfgbg is dark' => [NULL, NULL, $dark];
  }

}
