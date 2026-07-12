<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Input\ArrayKeyStream;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Tests\Traits\MixedOptionsTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\ChoiceListTrait;
use DrevOps\Tui\Widget\MultiSearchWidget;
use DrevOps\Tui\Widget\WidgetRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the multi-search widget.
 */
#[CoversClass(MultiSearchWidget::class)]
#[CoversClass(ChoiceListTrait::class)]
#[Group('widget')]
final class MultiSearchWidgetTest extends TestCase {

  use MixedOptionsTrait;

  /**
   * The options used across the tests.
   *
   * @var array<string,string>
   */
  protected array $labels = ['clamav' => 'ClamAV', 'redis' => 'Redis', 'solr' => 'Solr'];

  public function testFilterToggleAndAccept(): void {
    $widget = new MultiSearchWidget($this->labels);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('sol', Key::named(KeyName::Space), Key::named(KeyName::Enter)));

    $this->assertSame(['solr'], $value);
  }

  public function testSeededSelectionKept(): void {
    $widget = new MultiSearchWidget($this->labels, ['redis']);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['redis'], $value);
  }

  public function testViewShowsQueryLineAboveOptions(): void {
    $widget = new MultiSearchWidget($this->labels);

    $widget->handle(Key::char('r'));
    $view = Ansi::strip($widget->view(new DefaultTheme()));

    $this->assertStringContainsString("r█\n", $view);
    $this->assertStringContainsString('Redis', $view);
    $this->assertStringNotContainsString('ClamAV', $view);
  }

  public function testViewShowsInheritedKeyHint(): void {
    $widget = new MultiSearchWidget($this->labels);

    $view = Ansi::strip($widget->view(new DefaultTheme()));

    $this->assertStringContainsString('space select · ↑/↓ move · ←/→ none/all · ↵ accept · esc cancel', $view);
  }

  public function testSkipsNonSelectableWhenToggling(): void {
    $widget = new MultiSearchWidget($this->mixedOptions());

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'b', 'd'], $value);
  }

  public function testRendersKindsBelowQueryLine(): void {
    $view = Ansi::strip((new MultiSearchWidget($this->mixedOptions()))->view(new DefaultTheme()));

    $this->assertStringContainsString("█\n", $view);
    $this->assertStringContainsString('Fruits', $view);
    $this->assertStringContainsString('Cherry (out of stock)', $view);
    $this->assertStringContainsString('──', $view);
  }

  public function testFuzzyMatchesNonContiguousSubsequence(): void {
    $widget = new MultiSearchWidget(['banana' => 'Banana', 'apple' => 'Apple', 'cherry' => 'Cherry']);

    // "bn" is not a substring of any label but is a subsequence of "Banana".
    $value = WidgetRunner::run($widget, ArrayKeyStream::of('bn', Key::named(KeyName::Space), Key::named(KeyName::Enter)));

    $this->assertSame(['banana'], $value);
  }

  public function testHighlightsMatchedCharacters(): void {
    $theme = new DefaultTheme();
    $widget = new MultiSearchWidget(['banana' => 'Banana']);

    $widget->handle(Key::char('b'));
    $widget->handle(Key::char('n'));
    $view = $widget->view($theme);

    // The non-contiguous match highlights each hit character on its own,
    // leaving the intervening characters unstyled.
    $this->assertStringContainsString($theme->highlightMatch('B'), $view);
    $this->assertStringContainsString($theme->highlightMatch('n'), $view);
    $this->assertStringContainsString('Banana', Ansi::strip($view));
  }

  public function testPagesLongOptionList(): void {
    $widget = new MultiSearchWidget(['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry', 'd' => 'Date'], pageSize: 2);

    $view = Ansi::strip($widget->view(new DefaultTheme()));

    $this->assertStringContainsString('Apple', $view);
    $this->assertStringContainsString('Banana', $view);
    $this->assertStringNotContainsString('Cherry', $view);
    $this->assertStringContainsString('▼', $view);
  }

  public function testPagingFollowsCursorDownTheList(): void {
    $widget = new MultiSearchWidget(['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry', 'd' => 'Date'], pageSize: 2);

    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Down));
    $view = Ansi::strip($widget->view(new DefaultTheme()));

    $this->assertStringContainsString('Cherry', $view);
    $this->assertStringContainsString('▲', $view);
    $this->assertStringNotContainsString('Apple', $view);
  }

}
