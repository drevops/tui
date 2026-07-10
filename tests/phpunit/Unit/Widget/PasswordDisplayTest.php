<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Widget\PasswordDisplay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the password display cycle.
 */
#[CoversClass(PasswordDisplay::class)]
#[Group('widget')]
final class PasswordDisplayTest extends TestCase {

  #[DataProvider('dataProviderNext')]
  public function testNext(PasswordDisplay $from, PasswordDisplay $to): void {
    $this->assertSame($to, $from->next());
  }

  /**
   * Data provider for testNext().
   *
   * @return array<array{PasswordDisplay,PasswordDisplay}>
   *   The current display and the one that follows it.
   */
  public static function dataProviderNext(): array {
    return [
      [PasswordDisplay::Hidden, PasswordDisplay::Masked],
      [PasswordDisplay::Masked, PasswordDisplay::Plaintext],
      [PasswordDisplay::Plaintext, PasswordDisplay::Hidden],
    ];
  }

}
