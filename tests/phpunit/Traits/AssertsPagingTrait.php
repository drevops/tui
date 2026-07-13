<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Traits;

use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\WidgetInterface;

/**
 * Shared paging assertions for the list widgets.
 *
 * Every paged widget honours the same contract: a non-positive page size is
 * rejected at construction, a long list clips to the page with a "more below"
 * indicator, and the window follows the cursor down. A test supplies a factory
 * closure building its widget at a given page size.
 */
trait AssertsPagingTrait {

  /**
   * The four-option fixture the paging assertions run against.
   *
   * @return array<string,string>
   *   The value => label map.
   */
  protected static function pagingOptions(): array {
    return ['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry', 'd' => 'Date'];
  }

  /**
   * A non-positive page size is rejected at construction.
   *
   * @param \Closure $factory
   *   Builds the widget: `fn (int $page_size): WidgetInterface`.
   * @param int $page_size
   *   The invalid page size to pass.
   */
  protected function assertRejectsNonPositivePageSize(\Closure $factory, int $page_size): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage(sprintf('Page size must be a positive integer, %d given.', $page_size));

    $factory($page_size);
  }

  /**
   * A long list clips to the page and the window follows the cursor down.
   *
   * @param \Closure $factory
   *   Builds the widget over the paging fixture at a page size of two:
   *   `fn (int $page_size): WidgetInterface`.
   * @param int $downs
   *   The Down presses that carry the cursor onto the third item.
   */
  protected function assertPagesAndFollowsCursor(\Closure $factory, int $downs = 2): void {
    $widget = $factory(2);
    $this->assertInstanceOf(WidgetInterface::class, $widget);

    $view = Ansi::strip($widget->view(new DefaultTheme()));

    $this->assertStringContainsString('Apple', $view);
    $this->assertStringContainsString('Banana', $view);
    $this->assertStringNotContainsString('Cherry', $view);
    $this->assertStringContainsString('▼', $view);

    for ($i = 0; $i < $downs; $i++) {
      $widget->handle(Key::named(KeyName::Down));
    }

    $scrolled = Ansi::strip($widget->view(new DefaultTheme()));

    // The window followed the cursor: the "more above" indicator shows and
    // the first option has scrolled off.
    $this->assertStringContainsString('Cherry', $scrolled);
    $this->assertStringContainsString('▲', $scrolled);
    $this->assertStringNotContainsString('Apple', $scrolled);
  }

}
