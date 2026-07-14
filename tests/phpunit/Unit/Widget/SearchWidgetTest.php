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
use DrevOps\Tui\Widget\ChoiceListTrait;
use DrevOps\Tui\Widget\SearchWidget;
use DrevOps\Tui\Widget\SelectWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the search widget.
 */
#[CoversClass(SearchWidget::class)]
#[CoversClass(SelectWidget::class)]
#[CoversClass(ChoiceListTrait::class)]
#[Group('widget')]
final class SearchWidgetTest extends TestCase {

  use AssertsPagingTrait;
  use MixedOptionsTrait;

  /**
   * The options used across the tests.
   *
   * @var array<string,string>
   */
  protected array $labels = ['gha' => 'GitHub Actions', 'circleci' => 'CircleCI', 'none' => 'None'];

  public function testFilterNarrowsAndEnterAcceptsValue(): void {
    $widget = new SearchWidget($this->labels);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('circle', Key::named(KeyName::Enter)));

    $this->assertSame('circleci', $value);
  }

  public function testDefaultSeedsHighlight(): void {
    $widget = new SearchWidget($this->labels, 'none');

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame('none', $value);
  }

  public function testArrowsMoveHighlight(): void {
    $widget = new SearchWidget($this->labels);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Down), Key::named(KeyName::Enter)));

    $this->assertSame('circleci', $value);
  }

  public function testEnterIgnoredWhenNothingMatches(): void {
    $widget = new SearchWidget($this->labels);

    $widget->handle(Key::char('z'));
    $widget->handle(Key::char('z'));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertFalse($widget->isComplete());

    $widget->handle(Key::named(KeyName::Backspace));
    $widget->handle(Key::named(KeyName::Backspace));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertTrue($widget->isComplete());
    $this->assertSame('gha', $widget->value());
  }

  public function testBackspaceRemovesWholeMultibyteCharacter(): void {
    $widget = new SearchWidget($this->labels);

    // One backspace removes the whole multibyte character, not one byte, so
    // the cleared filter shows every option again instead of matching nothing.
    $widget->handle(Key::char('é'));
    $widget->handle(Key::named(KeyName::Backspace));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertTrue($widget->isComplete());
    $this->assertSame('gha', $widget->value());
  }

  public function testSpaceIsPartOfTheQuery(): void {
    $widget = new SearchWidget($this->labels);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('hub', Key::named(KeyName::Space), Key::named(KeyName::Backspace), Key::named(KeyName::Enter)));

    $this->assertSame('gha', $value);
  }

  public function testViewShowsQueryAndVisibleOptions(): void {
    $widget = new SearchWidget($this->labels);

    $widget->handle(Key::char('c'));
    $view = Ansi::strip($widget->view(new DefaultTheme()));

    $this->assertStringContainsString('c█', $view);
    $this->assertStringContainsString('CircleCI', $view);
    $this->assertStringNotContainsString('None', $view);
  }

  public function testCancel(): void {
    $widget = new SearchWidget($this->labels);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($widget->isCancelled());
    $this->assertNull($value);
  }

  public function testNavigationSkipsNonSelectable(): void {
    $widget = new SearchWidget($this->mixedOptions());

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('d', $value);
  }

  public function testUpSkipsBackOverNonSelectable(): void {
    $widget = new SearchWidget($this->mixedOptions());

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Up),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('b', $value);
  }

  public function testDefaultOnDisabledFallsBackToFirstSelectable(): void {
    $widget = new SearchWidget($this->mixedOptions(), 'c');

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame('a', $value);
  }

  public function testFilterDropsHeadingsAndSeparators(): void {
    $widget = new SearchWidget($this->mixedOptions());

    $widget->handle(Key::char('b'));
    $widget->handle(Key::char('a'));
    $widget->handle(Key::char('n'));
    $view = Ansi::strip($widget->view(new DefaultTheme()));

    $this->assertStringContainsString('Banana', $view);
    $this->assertStringNotContainsString('Fruits', $view);
    $this->assertStringNotContainsString('Apple', $view);
    $this->assertStringNotContainsString('──', $view);
  }

  public function testDisabledMatchingFilterNotAccepted(): void {
    $widget = new SearchWidget($this->mixedOptions());

    $widget->handle(Key::char('e'));
    $widget->handle(Key::char('r'));
    $widget->handle(Key::char('r'));
    $this->assertStringContainsString('Cherry (out of stock)', Ansi::strip($widget->view(new DefaultTheme())));

    $widget->handle(Key::named(KeyName::Enter));

    $this->assertFalse($widget->isComplete());
  }

  public function testRendersHeadingSeparatorAndDisabled(): void {
    $view = Ansi::strip((new SearchWidget($this->mixedOptions()))->view(new DefaultTheme()));

    $this->assertStringContainsString('Fruits', $view);
    $this->assertStringContainsString('Cherry (out of stock)', $view);
    $this->assertStringContainsString('──', $view);
  }

  public function testFuzzyMatchesNonContiguousSubsequence(): void {
    $widget = new SearchWidget(['gha' => 'GitHub Actions', 'gitlab' => 'GitLab CI', 'circle' => 'CircleCI']);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('gha', Key::named(KeyName::Enter)));

    $this->assertSame('gha', $value);
  }

  public function testRanksPrefixAheadOfLooserSubsequence(): void {
    $widget = new SearchWidget(['alpha' => 'Alpha', 'beta' => 'Beta', 'palace' => 'Palace']);

    // "pa" prefixes Palace but only scatters through Alpha, so Palace ranks
    // first and the cursor lands on it even though Alpha is declared earlier.
    $value = WidgetRunner::run($widget, ArrayKeyStream::of('pa', Key::named(KeyName::Enter)));

    $this->assertSame('palace', $value);
  }

  public function testHighlightsMatchedCharacters(): void {
    $theme = new DefaultTheme();
    $widget = new SearchWidget(['palace' => 'Palace', 'alpha' => 'Alpha']);

    $widget->handle(Key::char('p'));
    $widget->handle(Key::char('a'));
    $view = $widget->view($theme);

    $this->assertStringContainsString($theme->highlightMatch('Pa'), $view);
    $this->assertStringContainsString('Palace', Ansi::strip($view));
  }

  public function testRejectsNonPositivePageSize(): void {
    $this->assertRejectsNonPositivePageSize(static fn(int $size): SearchWidget => new SearchWidget(['a' => 'A'], pageSize: $size), -2);
  }

  public function testPagesLongOptionList(): void {
    $this->assertPagesAndFollowsCursor(static fn(int $size): SearchWidget => new SearchWidget(self::pagingOptions(), pageSize: $size));
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new SearchWidget($this->labels))->hints());

    $this->assertSame(['move', 'accept', 'cancel'], $labels);
  }

}
