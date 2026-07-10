<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Config\OptionKind;
use DrevOps\Tui\Input\ArrayKeyStream;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\ChoiceList;
use DrevOps\Tui\Widget\MultiSearchWidget;
use DrevOps\Tui\Widget\WidgetRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the multi-search widget.
 */
#[CoversClass(MultiSearchWidget::class)]
#[CoversClass(ChoiceList::class)]
#[Group('widget')]
final class MultiSearchWidgetTest extends TestCase {

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
    $view = $widget->view(new DefaultTheme());

    $this->assertStringContainsString("r█\n", Ansi::strip($view));
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

  /**
   * A list mixing selectable options with a heading, separator and disabled.
   *
   * @return list<\DrevOps\Tui\Config\Option>
   *   The option rows.
   */
  protected function mixedOptions(): array {
    return [
      new Option('a', 'Apple'),
      new Option('', 'Fruits', '', OptionKind::Heading),
      new Option('b', 'Banana'),
      new Option('', '', '', OptionKind::Separator),
      new Option('c', 'Cherry', '', OptionKind::Option, TRUE, 'out of stock'),
      new Option('d', 'Date'),
    ];
  }

}
