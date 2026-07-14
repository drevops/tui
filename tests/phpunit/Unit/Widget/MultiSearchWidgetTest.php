<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\WidgetRunner;
use DrevOps\Tui\Tests\Traits\AssertsPagingTrait;
use DrevOps\Tui\Tests\Traits\MixedOptionsTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\ChoiceFilterTrait;
use DrevOps\Tui\Widget\ChoiceListTrait;
use DrevOps\Tui\Widget\FuzzySearchTrait;
use DrevOps\Tui\Widget\MultiChoiceTrait;
use DrevOps\Tui\Widget\MultiSearchWidget;
use DrevOps\Tui\Widget\PageableTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the multi-search widget.
 */
#[CoversClass(MultiSearchWidget::class)]
#[CoversClass(MultiChoiceTrait::class)]
#[CoversClass(ChoiceFilterTrait::class)]
#[CoversClass(FuzzySearchTrait::class)]
#[CoversClass(ChoiceListTrait::class)]
#[CoversClass(PageableTrait::class)]
#[Group('widget')]
final class MultiSearchWidgetTest extends TestCase {

  use AssertsPagingTrait;
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

  public function testInheritsMultiselectHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new MultiSearchWidget($this->labels))->hints());

    $this->assertSame(['select', 'move', 'none/all', 'accept', 'cancel'], $labels);
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
    $this->assertPagesAndFollowsCursor(static fn(int $size): MultiSearchWidget => new MultiSearchWidget(self::pagingOptions(), page_size: $size));
  }

}
