<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Config;

use DrevOps\Tui\Config\NumberBounds;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the number bounds value object.
 */
#[CoversClass(NumberBounds::class)]
#[Group('config')]
final class NumberBoundsTest extends TestCase {

  #[DataProvider('dataProviderContains')]
  public function testContains(?int $min, ?int $max, int $value, bool $expected): void {
    $this->assertSame($expected, (new NumberBounds($min, $max))->contains($value));
  }

  public static function dataProviderContains(): array {
    return [
      'within both' => [1, 10, 5, TRUE],
      'on lower bound' => [1, 10, 1, TRUE],
      'on upper bound' => [1, 10, 10, TRUE],
      'below both' => [1, 10, 0, FALSE],
      'above both' => [1, 10, 11, FALSE],
      'below open max' => [1, NULL, 0, FALSE],
      'above open max' => [1, NULL, 99, TRUE],
      'above open min' => [NULL, 10, 11, FALSE],
      'below open min' => [NULL, 10, -5, TRUE],
      'unbounded' => [NULL, NULL, 999, TRUE],
    ];
  }

  #[DataProvider('dataProviderDescribe')]
  public function testDescribe(?int $min, ?int $max, string $expected): void {
    $this->assertSame($expected, (new NumberBounds($min, $max))->describe());
  }

  public static function dataProviderDescribe(): array {
    return [
      'both' => [1, 10, 'between 1 and 10'],
      'min only' => [1, NULL, 'at least 1'],
      'max only' => [NULL, 10, 'at most 10'],
      'neither' => [NULL, NULL, ''],
    ];
  }

  #[DataProvider('dataProviderClamp')]
  public function testClamp(?int $min, ?int $max, int $value, int $expected): void {
    $this->assertSame($expected, (new NumberBounds($min, $max))->clamp($value));
  }

  public static function dataProviderClamp(): array {
    return [
      'within' => [1, 10, 5, 5],
      'below min' => [1, 10, -3, 1],
      'above max' => [1, 10, 42, 10],
      'open min below max' => [NULL, 10, 42, 10],
      'open max above min' => [1, NULL, -3, 1],
      'unbounded' => [NULL, NULL, 42, 42],
    ];
  }

  #[DataProvider('dataProviderStep')]
  public function testStep(?int $min, ?int $max, ?int $step, int $value, int $direction, int $expected): void {
    $this->assertSame($expected, (new NumberBounds($min, $max, $step))->step($value, $direction));
  }

  public static function dataProviderStep(): array {
    return [
      'default step up' => [0, 10, NULL, 5, 1, 6],
      'default step down' => [0, 10, NULL, 5, -1, 4],
      'custom step up' => [0, 10, 3, 5, 1, 8],
      'custom step up clamps to max' => [0, 10, 3, 9, 1, 10],
      'custom step down clamps to min' => [0, 10, 3, 1, -1, 0],
      'unbounded step up' => [NULL, NULL, 5, 100, 1, 105],
      'snaps into range from below' => [5, 10, NULL, 0, 1, 5],
    ];
  }

}
