<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Render;

use DrevOps\Tui\Render\Overlay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the line-compositor that overlays a box on a backdrop.
 */
#[CoversClass(Overlay::class)]
#[Group('tui')]
final class OverlayTest extends TestCase {

  public function testCenterPositionsWithEqualPadding(): void {
    $this->assertSame([1, 3], Overlay::center(10, 4, 4, 2));
  }

  public function testCenterClampsWhenBoxExceedsArea(): void {
    // A box larger than the area cannot be inset: both offsets clamp to zero.
    $this->assertSame([0, 0], Overlay::center(4, 2, 10, 8));
  }

  public function testCompositeSplicesBoxAndStylesTheExposedBackdrop(): void {
    $backdrop = ['0123456789', 'abcdefghij', 'ABCDEFGHIJ', 'klmnopqrst'];
    $box = ['[XX]', '[YY]'];

    $out = Overlay::composite($backdrop, $box, 4, 1, 3, static fn(string $segment): string => '<' . $segment . '>');

    // Rows outside the box's vertical span are wholly styled; rows within it keep
    // the box verbatim between a styled prefix and suffix sliced by column.
    $this->assertSame([
      '<0123456789>',
      '<abc>[XX]<hij>',
      '<ABC>[YY]<HIJ>',
      '<klmnopqrst>',
    ], $out);
  }

  public function testCompositeLeavesAnEmptyEdgeUnstyled(): void {
    // At the left edge the prefix is empty, so no styling wraps nothing.
    $out = Overlay::composite(['abcde'], ['[X]'], 3, 0, 0, static fn(string $segment): string => '<' . $segment . '>');

    $this->assertSame(['[X]<de>'], $out);
  }

  public function testCompositeClipsBoxRowsPastTheBackdrop(): void {
    // A box taller than the backdrop loses the overflow: the backdrop bounds it.
    $out = Overlay::composite(['aaaa'], ['[XX]', '[YY]'], 4, 0, 0, static fn(string $segment): string => $segment);

    $this->assertSame(['[XX]'], $out);
  }

}
