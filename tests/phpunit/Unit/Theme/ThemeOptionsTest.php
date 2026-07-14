<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Config\Panel;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Viewport;
use DrevOps\Tui\Tests\Fixtures\Theme\AccentOptionTheme;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\Spacing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the theme spacing, border and custom display options.
 */
#[CoversClass(DefaultTheme::class)]
#[Group('tui')]
final class ThemeOptionsTest extends TestCase {

  public function testCompactSpacingDropsDescriptionsAndSummary(): void {
    $panel = new Panel('p', 'P', '', [
      new Field('a', 'A', 'field help', FieldType::Text, ''),
    ], [
      new Panel('sub', 'Sub', 'panel help', [new Field('b', 'B', '', FieldType::Text, '')]),
    ]);

    $theme = new DefaultTheme(40, ['color' => FALSE, 'spacing' => Spacing::Compact]);
    [$lines] = $theme->renderBody($panel, new Answers(['b' => 'Beta'], []), 0);
    $body = Ansi::strip(implode("\n", $lines));

    // Compact keeps labels but drops descriptions, the summary and any gaps.
    $this->assertStringContainsString('A', $body);
    $this->assertStringContainsString('Sub', $body);
    $this->assertStringNotContainsString('field help', $body);
    $this->assertStringNotContainsString('panel help', $body);
    $this->assertStringNotContainsString('Beta', $body);
    $this->assertStringNotContainsString("\n\n", implode("\n", $lines));
  }

  public function testPaddedSpacingInsertsGapsBetweenItems(): void {
    $panel = new Panel('p', 'P', '', [
      new Field('a', 'A', '', FieldType::Text, ''),
      new Field('b', 'B', '', FieldType::Text, ''),
    ]);

    $theme = new DefaultTheme(40, ['color' => FALSE, 'spacing' => Spacing::Padded]);
    [$lines] = $theme->renderBody($panel, new Answers(), 0);

    // A blank line separates the two fields.
    $this->assertStringContainsString('A', Ansi::strip($lines[0]));
    $this->assertSame('', $lines[1]);
    $this->assertStringContainsString('B', Ansi::strip($lines[2]));
  }

  #[DataProvider('dataProviderBorderDrawsBox')]
  public function testBorderDrawsBox(bool $unicode, Border $border, string $expected): void {
    $theme = new DefaultTheme(24, ['color' => FALSE, 'unicode' => $unicode, 'border' => $border]);
    $frame = $theme->renderFrame(['HEAD'], ['body'], ['FOOT'], new Viewport(0, FALSE, FALSE), 1);

    $this->assertStringContainsString($expected, $frame);
    $this->assertStringContainsString('HEAD', Ansi::strip($frame));
    $this->assertStringContainsString('body', Ansi::strip($frame));
    $this->assertStringContainsString('FOOT', Ansi::strip($frame));
  }

  public static function dataProviderBorderDrawsBox(): \Iterator {
    yield 'line' => [TRUE, Border::Line, '┌'];
    yield 'rounded' => [TRUE, Border::Rounded, '╭'];
    yield 'double' => [TRUE, Border::Double, '╔'];
    yield 'ascii line corner' => [FALSE, Border::Line, '+'];
    yield 'ascii double fill' => [FALSE, Border::Double, '='];
  }

  public function testBorderedLinesAreExactlyOuterWidthAndClip(): void {
    $theme = new DefaultTheme(12, ['color' => FALSE, 'border' => Border::Line]);

    // Inner width is 12 - 4 = 8, so a 20-char body line must clip; every line
    // is exactly the outer width.
    $frame = $theme->renderFrame(['H'], [str_repeat('x', 20)], ['F'], new Viewport(0, FALSE, FALSE), 1);

    foreach (explode("\n", $frame) as $line) {
      $this->assertSame(12, Ansi::width($line));
    }
  }

  public function testPaddedBorderAddsInnerPadding(): void {
    $padded = new DefaultTheme(20, ['color' => FALSE, 'spacing' => Spacing::Padded, 'border' => Border::Line]);
    $plain = new DefaultTheme(20, ['color' => FALSE, 'border' => Border::Line]);

    $args = [['H'], ['b'], ['F'], new Viewport(0, FALSE, FALSE), 1];

    // Padded adds a blank boxed line above and below the body.
    $this->assertSame(substr_count($plain->renderFrame(...$args), "\n") + 2, substr_count($padded->renderFrame(...$args), "\n"));
  }

  public function testBorderlessStatusGapFollowsSpacing(): void {
    $args = [['H'], ['b'], ['F'], new Viewport(0, FALSE, FALSE), 1];

    // Normal detaches the footer with a blank line; compact keeps it attached.
    $normal = explode("\n", Ansi::strip((new DefaultTheme(40, ['color' => FALSE]))->renderFrame(...$args)));
    $this->assertSame(['H', 'b', '', 'F'], $normal);

    $compact = explode("\n", Ansi::strip((new DefaultTheme(40, ['color' => FALSE, 'spacing' => Spacing::Compact]))->renderFrame(...$args)));
    $this->assertSame(['H', 'b', 'F'], $compact);
  }

  public function testEditorAdoptsBorder(): void {
    $plain = (new DefaultTheme(30, ['color' => FALSE]))->renderEditor('Name', 'Acme');
    $boxed = (new DefaultTheme(30, ['color' => FALSE, 'border' => Border::Line]))->renderEditor('Name', 'Acme');

    // Borderless keeps today's label-over-rule editor; a border boxes it.
    $this->assertStringContainsString("Name\n────", Ansi::strip($plain));
    $this->assertStringNotContainsString('┌', $plain);

    $this->assertStringContainsString('┌', $boxed);
    $this->assertStringContainsString('Name', Ansi::strip($boxed));
    $this->assertStringContainsString('Acme', Ansi::strip($boxed));
  }

  public function testEditorDrawsHintsOnlyWhenGiven(): void {
    $plain = new DefaultTheme(30, ['color' => FALSE]);
    $keys = KeyMapManager::create()->forField(FieldType::Text);

    // An empty hint list draws no footer (the footer can be turned off); a
    // non-empty list draws it.
    $this->assertStringNotContainsString('accept', $plain->renderEditor('Name', 'body', [], $keys));
    $this->assertStringContainsString('accept', $plain->renderEditor('Name', 'body', [new Hint('accept', Action::Accept)], $keys));

    // Bordered: an empty hint list still closes the box with a single rule.
    $boxed = new DefaultTheme(30, ['color' => FALSE, 'border' => Border::Line]);
    $frame = $boxed->renderEditor('Name', 'body', [], $keys);
    $this->assertStringNotContainsString('accept', $frame);
    $this->assertStringContainsString('body', Ansi::strip($frame));
  }

  public function testBorderColourFollowsMode(): void {
    $args = [['H'], ['b'], ['F'], new Viewport(0, FALSE, FALSE), 1];

    // The border is drawn in the mode's border colour - cyan in dark, blue in
    // light - not the editor-rule grey.
    $dark = (new DefaultTheme(20, ['border' => Border::Line]))->renderFrame(...$args);
    $this->assertStringContainsString("\033[36m", $dark);

    $light = (new DefaultTheme(20, ['border' => Border::Line, 'mode' => Mode::Light]))->renderFrame(...$args);
    $this->assertStringContainsString("\033[34m", $light);
  }

  public function testUnknownOptionThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown theme option "spacng"');

    new DefaultTheme(40, ['spacng' => 'padded']);
  }

  public function testInvalidValueThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('is not a valid "spacing"');

    new DefaultTheme(40, ['spacing' => 'padd']);
  }

  public function testCustomOptionDeclaredBySchema(): void {
    $theme = $this->accentTheme(['color' => FALSE, 'accent' => 'warm']);
    $this->assertSame('warm', $theme->accent());

    // Unset falls back to the theme's default.
    $this->assertSame('cool', $this->accentTheme(['color' => FALSE])->accent());
  }

  public function testCustomOptionInvalidValueThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('is not a valid "accent"');

    $this->accentTheme(['accent' => 'hot']);
  }

  public function testNonStringOptionFallsBackToDefault(): void {
    // "color" is a bool, so reading it as a string yields the default.
    $theme = new class(40, ['color' => FALSE]) extends DefaultTheme {

      public function colorAsString(): string {
        return $this->option('color', 'fallback');
      }

    };

    $this->assertSame('fallback', $theme->colorAsString());
  }

  /**
   * A theme declaring a custom "accent" option.
   *
   * @param array<string,mixed> $options
   *   The theme options.
   *
   * @return \DrevOps\Tui\Tests\Fixtures\Theme\AccentOptionTheme
   *   The theme.
   */
  protected function accentTheme(array $options): AccentOptionTheme {
    return new AccentOptionTheme($options);
  }

}
