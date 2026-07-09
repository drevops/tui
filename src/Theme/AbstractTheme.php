<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Config\Panel;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Navigator;
use DrevOps\Tui\Render\Scroller;
use DrevOps\Tui\Render\Viewport;

/**
 * Abstract visual authority for the TUI - the complete base implementation.
 *
 * A theme owns the entire visual representation: the palette (per-role style
 * codes), the glyphs (marker, scroll indicators, separators), and how every
 * element is composed (field rows, sub-panel rows, descriptions, breadcrumb,
 * the scrolled frame and the start banner).
 *
 * This base implements all of it, with a neutral monochrome palette and the
 * full glyph set. A concrete theme only declares what differs - override
 * defineStyles() and/or defineGlyphs() and merge the specific overrides over
 * the parent's map:
 *
 * @code
 * protected function defineStyles(): array {
 *   return ['title' => '1;96', 'value' => '96'] + parent::defineStyles();
 * }
 * @endcode
 *
 * Override any render* method for full control over layout. Themes are
 * created and registered through {@see \DrevOps\Tui\Theme\ThemeManager} - the
 * config only ever references a theme name.
 *
 * @package DrevOps\Tui\Theme
 */
abstract class AbstractTheme implements ThemeInterface {

  /**
   * The role => style-code map, resolved once from defineStyles().
   *
   * @var array<string,string>
   */
  protected array $styles;

  /**
   * The name => [unicode, ascii] glyph pair map, from defineGlyphs().
   *
   * @var array<string,array{0:string,1:string}>
   */
  protected array $glyphs;

  /**
   * The outer frame width, including the border when one is drawn.
   */
  protected int $outerWidth;

  /**
   * Whether colour (ANSI) is enabled, resolved from the "color" option.
   */
  protected bool $color;

  /**
   * Whether Unicode glyphs are used, resolved from the "unicode" option.
   */
  protected bool $unicode;

  /**
   * Construct a theme.
   *
   * @param int $width
   *   The frame width used for right-aligned badges and the border.
   * @param array<string,mixed> $options
   *   Display options keyed by name and validated against optionSchema():
   *   "mode" (a MODE_* value), "color" and "unicode" (booleans; unset
   *   auto-detects from the environment), "spacing" (a SPACING_* value),
   *   "border" (a BORDER_* value), plus any option a concrete theme declares.
   */
  public function __construct(protected int $width = 76, protected array $options = []) {
    $this->validateOptions();

    // Colour and Unicode default on; the interactive path (Tui) fills them from
    // the detected terminal capabilities, and a direct caller sets them itself.
    $this->color = is_bool($this->options['color'] ?? NULL) ? $this->options['color'] : TRUE;
    $this->unicode = is_bool($this->options['unicode'] ?? NULL) ? $this->options['unicode'] : TRUE;

    $this->styles = $this->defineStyles();
    $this->glyphs = $this->defineGlyphs();
    $this->outerWidth = $this->width;

    // A border consumes two frame columns plus a one-column gutter each side, so
    // lay rows out that much narrower to keep right-aligned badges inside it.
    if ($this->border() !== self::BORDER_NONE) {
      $this->width = max(1, $this->width - 4);
    }
  }

  /**
   * Validate the options against optionSchema(), failing loudly on a mistake.
   *
   * An unknown key or a value outside the declared set throws, so a typo in the
   * plain-string options cannot pass silently.
   *
   * @throws \InvalidArgumentException
   *   When an option key is unknown or its value is not allowed.
   */
  protected function validateOptions(): void {
    $schema = $this->optionSchema();

    foreach ($this->options as $key => $value) {
      if (!array_key_exists($key, $schema)) {
        throw new \InvalidArgumentException(sprintf('Unknown theme option "%s". Known: %s.', $key, implode(', ', array_keys($schema))));
      }

      if (!in_array($value, $schema[$key], TRUE)) {
        throw new \InvalidArgumentException(sprintf('%s is not a valid "%s". Allowed: %s.', $this->showValue($value), $key, implode(', ', array_map($this->showValue(...), $schema[$key]))));
      }
    }
  }

  /**
   * The allowed options and their permitted values, keyed by option name.
   *
   * The theme's option surface: a consumer may set only these keys, to these
   * values. A concrete theme adds its own options by merging over the base -
   * `return ['accent' => ['cool', 'warm']] + parent::optionSchema();` - exactly
   * like defineStyles() and defineGlyphs().
   *
   * @return array<string,list<mixed>>
   *   The option name => allowed-values map.
   */
  protected function optionSchema(): array {
    return [
      'mode' => [self::MODE_DARK, self::MODE_LIGHT],
      'color' => [TRUE, FALSE],
      'unicode' => [TRUE, FALSE],
      'spacing' => [self::SPACING_COMPACT, self::SPACING_NORMAL, self::SPACING_PADDED],
      'border' => [self::BORDER_NONE, self::BORDER_LINE, self::BORDER_ROUNDED, self::BORDER_DOUBLE],
    ];
  }

  /**
   * Render an option value for an error message.
   *
   * @param mixed $value
   *   The value.
   *
   * @return string
   *   A readable representation.
   */
  protected function showValue(mixed $value): string {
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }

    return is_scalar($value) ? '"' . $value . '"' : gettype($value);
  }

  /**
   * A string display option, or a default when unset or non-string.
   *
   * The accessor for the string options - the built-in "mode", "spacing" and
   * "border" and any a concrete theme invents. A theme just reads the option
   * name it cares about; an unset option falls back to the default.
   *
   * @param string $name
   *   The option name (e.g. "spacing", "border", or a theme's own).
   * @param string $default
   *   The value to use when the option is unset.
   *
   * @return string
   *   The option value.
   */
  protected function option(string $name, string $default): string {
    $value = $this->options[$name] ?? $default;

    return is_string($value) ? $value : $default;
  }

  /**
   * The colour-mode option.
   *
   * @return string
   *   MODE_DARK or MODE_LIGHT.
   */
  protected function mode(): string {
    return $this->option('mode', self::MODE_DARK) === self::MODE_LIGHT ? self::MODE_LIGHT : self::MODE_DARK;
  }

  /**
   * The vertical spacing option.
   *
   * @return string
   *   One of the SPACING_* values.
   */
  protected function spacing(): string {
    return $this->option('spacing', self::SPACING_NORMAL);
  }

  /**
   * The border option.
   *
   * @return string
   *   One of the BORDER_* values.
   */
  protected function border(): string {
    return $this->option('border', self::BORDER_NONE);
  }

  /**
   * The role => style-code palette for this theme.
   *
   * The base palette is neutral - structure and emphasis only, no hues - so
   * it reads on any terminal background. A concrete theme overrides the roles
   * it colours and merges the rest: `return [...] + parent::defineStyles();`.
   *
   * Roles: "title" (headings and editor labels), "breadcrumb", "label",
   * "value", "description", "marker" (selection cursor), "badge"
   * (provenance), "cursor" (active button), "footer" (status and hint
   * lines), "indicator" (scroll arrows), "highlight" (the cursor row in list
   * widgets), "error" (validation messages), "rule" (the editor-header
   * underline) and "border" (the frame box, when a border is on).
   *
   * @return array<string,string>
   *   The palette, keyed by role.
   */
  protected function defineStyles(): array {
    return [
      'title' => '1',
      'breadcrumb' => '90',
      'label' => '',
      'value' => '',
      'description' => '90',
      'marker' => '1',
      'badge' => '7',
      'cursor' => '1;7',
      'footer' => '90',
      'indicator' => '1',
      'highlight' => '1',
      'error' => '31',
      'rule' => '90',
      'border' => '90',
    ];
  }

  /**
   * The name => [unicode, ascii] glyph pair map for this theme.
   *
   * Every glyph is a pair - the Unicode form and its ASCII fallback - and the
   * base defines the complete set. A concrete theme overrides the pairs it
   * changes and merges the rest: `return [...] + parent::defineGlyphs();`.
   *
   * @return array<string,array{0:string,1:string}>
   *   The glyphs, keyed by name, each a [unicode, ascii] pair.
   */
  protected function defineGlyphs(): array {
    return [
      'marker' => ['❯', '>'],
      'indicator_up' => ['▲', '^'],
      'indicator_down' => ['▼', 'v'],
      'separator' => ['›', '>'],
      'arrow' => ['›', '>'],
      'arrow_up' => ['↑', '^'],
      'arrow_down' => ['↓', 'v'],
      'enter' => ['↵', '<'],
      'dot' => ['·', '*'],
      'radio_on' => ['●', '(*)'],
      'radio_off' => ['○', '( )'],
      'check_on' => ['◼', '[x]'],
      'check_off' => ['◻', '[ ]'],
      'caret' => ['█', '|'],
      'mask' => ['•', '*'],
      'rule' => ['─', '-'],
    ];
  }

  /**
   * Style text for a role.
   *
   * @param string $role
   *   The role name.
   * @param string $text
   *   The text.
   *
   * @return string
   *   The styled text (plain when colour is disabled).
   */
  public function style(string $role, string $text): string {
    return Ansi::style($text, $this->styleCodes($role));
  }

  /**
   * The raw ANSI style codes for a role.
   *
   * The numbers that go inside an escape sequence to colour or emphasise text -
   * for example "1;36" (bold cyan) for the "title" role. Prefer style(), which
   * wraps text in these; this returns the raw codes for callers that need them.
   *
   * @param string $role
   *   The role name (e.g. "title", "value", "description").
   *
   * @return string
   *   The ANSI codes (empty when colour is off or the role is unknown).
   */
  public function styleCodes(string $role): string {
    return $this->color ? ($this->styles[$role] ?? '') : '';
  }

  /**
   * Style text for a role, emphasised (bold) when its item is selected.
   *
   * Keeps the role's colour but makes the whole selected item bold, dropping
   * any existing bold or faint so the emphasis is clean.
   *
   * @param string $role
   *   The role name.
   * @param string $text
   *   The text.
   * @param bool $selected
   *   Whether the item is the selected (cursor) one.
   *
   * @return string
   *   The styled text, made bold when selected.
   */
  protected function styleSelected(string $role, string $text, bool $selected): string {
    $codes = $this->styleCodes($role);

    if ($selected && $this->color) {
      $drop = ['', '1', '2'];
      $parts = array_values(array_filter(explode(';', $codes), static fn(string $part): bool => !in_array($part, $drop, TRUE)));
      array_unshift($parts, '1');
      $codes = implode(';', $parts);
    }

    return Ansi::style($text, $codes);
  }

  /**
   * The glyph for a decorative element.
   *
   * @param string $name
   *   The glyph name (e.g. "marker", "indicator_up", "separator").
   *
   * @return string
   *   The glyph character (empty when unknown).
   */
  public function glyph(string $name): string {
    return $this->glyphs[$name][$this->unicode ? 0 : 1] ?? '';
  }

  /**
   * Whether colour is enabled.
   *
   * @return bool
   *   TRUE when colour is enabled.
   */
  public function hasColor(): bool {
    return $this->color;
  }

  /**
   * Whether Unicode glyphs are enabled.
   *
   * @return bool
   *   TRUE when Unicode glyphs are used, FALSE for the ASCII fallback.
   */
  public function hasUnicode(): bool {
    return $this->unicode;
  }

  /**
   * The number of navigable items in a panel (fields + sub-panels).
   *
   * @param \DrevOps\Tui\Config\Panel $panel
   *   The panel.
   *
   * @return int
   *   The item count.
   */
  public function itemCount(Panel $panel): int {
    return count($panel->fields) + count($panel->panels);
  }

  /**
   * Build the body lines and the line index of the selected item.
   *
   * @param \DrevOps\Tui\Config\Panel $panel
   *   The panel.
   * @param \DrevOps\Tui\Answers\Answers $answers
   *   The current answers.
   * @param int $cursor
   *   The selected item index.
   *
   * @return array{list<string>,int}
   *   The body lines and the selected item's first line index.
   */
  public function renderBody(Panel $panel, Answers $answers, int $cursor): array {
    $lines = [];
    $cursor_line = 0;
    $index = 0;

    $spacing = $this->spacing();
    $gap = $spacing === self::SPACING_PADDED ? 1 : 0;
    $verbose = $spacing !== self::SPACING_COMPACT;

    foreach ($panel->fields as $field) {
      if ($index > 0 && $gap > 0) {
        $lines[] = '';
      }

      if ($index === $cursor) {
        $cursor_line = count($lines);
      }

      $lines[] = $this->renderFieldLine($field, $answers, $index === $cursor);

      if ($verbose && $field->description !== '') {
        $lines[] = $this->renderDescriptionLine($field->description, $index === $cursor);
      }

      $index++;
    }

    foreach ($panel->panels as $subpanel) {
      if ($index > 0 && $gap > 0) {
        $lines[] = '';
      }

      if ($index === $cursor) {
        $cursor_line = count($lines);
      }

      $lines[] = $this->renderPanelLine($subpanel, $index === $cursor);

      if ($verbose && $subpanel->description !== '') {
        $lines[] = $this->renderDescriptionLine($subpanel->description, $index === $cursor);
      }

      $summary = $verbose ? $this->summarizePanel($subpanel, $answers) : '';
      if ($summary !== '') {
        $lines[] = $this->renderSummaryLine($summary, $index === $cursor);
      }

      $index++;
    }

    return [$lines, $cursor_line];
  }

  /**
   * Render a field row.
   *
   * @param \DrevOps\Tui\Config\Field $field
   *   The field.
   * @param \DrevOps\Tui\Answers\Answers $answers
   *   The current answers.
   * @param bool $selected
   *   Whether the row is selected.
   *
   * @return string
   *   The row.
   */
  public function renderFieldLine(Field $field, Answers $answers, bool $selected): string {
    $left = $this->marker($selected) . ' ' . $this->styleSelected('label', $field->label, $selected) . '  ' . $this->styleSelected('value', $this->renderFieldValue($field, $answers->value($field->id)), $selected);

    $provenance = $answers->provenanceOf($field->id);
    if ($provenance === Provenance::Default) {
      return $left;
    }

    return Ansi::alignRight($left, $this->styleSelected('badge', ' ' . $provenance->value . ' ', $selected), $this->width);
  }

  /**
   * Render a sub-panel row.
   *
   * @param \DrevOps\Tui\Config\Panel $panel
   *   The sub-panel.
   * @param bool $selected
   *   Whether the row is selected.
   *
   * @return string
   *   The row.
   */
  public function renderPanelLine(Panel $panel, bool $selected): string {
    return $this->marker($selected) . ' ' . $this->styleSelected('label', $panel->title, $selected) . ' ' . $this->styleSelected('description', $this->glyph('arrow'), $selected);
  }

  /**
   * Render a description row.
   *
   * @param string $description
   *   The description.
   * @param bool $selected
   *   Whether the row's item is selected.
   *
   * @return string
   *   The row.
   */
  public function renderDescriptionLine(string $description, bool $selected): string {
    return '    ' . $this->styleSelected('description', $description, $selected);
  }

  /**
   * Summarize a sub-panel's active field values into one line, for the hub.
   *
   * Lets the hub show what is configured in each panel without drilling in.
   *
   * @param \DrevOps\Tui\Config\Panel $panel
   *   The sub-panel.
   * @param \DrevOps\Tui\Answers\Answers $answers
   *   The current answers.
   *
   * @return string
   *   The summary, or an empty string when the panel has no active fields.
   */
  public function summarizePanel(Panel $panel, Answers $answers): string {
    $parts = [];

    foreach ($panel->fields as $field) {
      if (!$answers->has($field->id)) {
        continue;
      }

      $value = $answers->value($field->id);
      $parts[] = is_array($value) && count($value) > 3 ? count($value) . ' selected' : $this->renderFieldValue($field, $value);

      if (count($parts) >= 4) {
        break;
      }
    }

    return implode(' ' . $this->glyph('dot') . ' ', $parts);
  }

  /**
   * Render a sub-panel value-summary row.
   *
   * @param string $summary
   *   The summary text.
   * @param bool $selected
   *   Whether the row's item is selected.
   *
   * @return string
   *   The row.
   */
  public function renderSummaryLine(string $summary, bool $selected): string {
    $max = max(1, $this->width - 4);
    $clipped = mb_strlen($summary) > $max ? mb_substr($summary, 0, $max - 1) . '…' : $summary;

    return '    ' . $this->styleSelected('value', $clipped, $selected);
  }

  /**
   * Render a breadcrumb line for the navigator.
   *
   * @param \DrevOps\Tui\Render\Navigator $navigator
   *   The navigator.
   *
   * @return string
   *   The breadcrumb line.
   */
  public function renderBreadcrumbLine(Navigator $navigator): string {
    return $this->style('breadcrumb', implode(' ' . $this->glyph('separator') . ' ', $navigator->breadcrumb()));
  }

  /**
   * Compose a frame: pinned header, scrolled body with indicators, footer.
   *
   * @param list<string> $header
   *   The pinned header lines.
   * @param list<string> $body
   *   The full body lines.
   * @param list<string> $footer
   *   The pinned footer lines.
   * @param \DrevOps\Tui\Render\Viewport $viewport
   *   The computed viewport.
   * @param int $height
   *   The body viewport height.
   *
   * @return string
   *   The composed frame.
   */
  public function renderFrame(array $header, array $body, array $footer, Viewport $viewport, int $height): string {
    if ($this->border() === self::BORDER_NONE) {
      return $this->renderBorderless($header, $body, $footer, $viewport, $height);
    }

    $chars = $this->borderChars();
    $visible = (new Scroller())->slice($body, $viewport->offset, $height);

    $middle = [];
    if ($viewport->has_above) {
      $middle[] = $this->style('indicator', '  ' . $this->glyph('indicator_up'));
    }

    $middle = array_merge($middle, $visible);

    if ($viewport->has_below) {
      $middle[] = $this->style('indicator', '  ' . $this->glyph('indicator_down'));
    }

    $pad = $this->spacing() === self::SPACING_PADDED;

    $out = [$this->rule($chars['tl'], $chars['tr'], $chars['h'])];

    foreach ($header as $line) {
      $out[] = $this->boxLine($line, $chars['v']);
    }

    $out[] = $this->rule($chars['ml'], $chars['mr'], $chars['h']);

    if ($pad) {
      $out[] = $this->boxLine('', $chars['v']);
    }

    foreach ($middle as $line) {
      $out[] = $this->boxLine($line, $chars['v']);
    }

    if ($pad) {
      $out[] = $this->boxLine('', $chars['v']);
    }

    $out[] = $this->rule($chars['ml'], $chars['mr'], $chars['h']);

    foreach ($footer as $line) {
      $out[] = $this->boxLine($line, $chars['v']);
    }

    $out[] = $this->rule($chars['bl'], $chars['br'], $chars['h']);

    return implode("\n", $out);
  }

  /**
   * Compose a borderless frame, detaching the status line by spacing.
   *
   * Mirrors the plain composition but inserts a blank line above the footer so
   * the status line reads as chrome, not another body row - except in the
   * densest spacing, where the status line stays attached.
   *
   * @param list<string> $header
   *   The header lines.
   * @param list<string> $body
   *   The body lines.
   * @param list<string> $footer
   *   The footer lines.
   * @param \DrevOps\Tui\Render\Viewport $viewport
   *   The viewport.
   * @param int $height
   *   The body viewport height.
   *
   * @return string
   *   The composed frame.
   */
  protected function renderBorderless(array $header, array $body, array $footer, Viewport $viewport, int $height): string {
    $visible = (new Scroller())->slice($body, $viewport->offset, $height);

    $lines = $header;
    if ($viewport->has_above) {
      $lines[] = $this->style('indicator', '  ' . $this->glyph('indicator_up'));
    }

    $lines = array_merge($lines, $visible);

    if ($viewport->has_below) {
      $lines[] = $this->style('indicator', '  ' . $this->glyph('indicator_down'));
    }

    if ($this->spacing() !== self::SPACING_COMPACT) {
      $lines[] = '';
    }

    return implode("\n", array_merge($lines, $footer));
  }

  /**
   * Build a horizontal border rule spanning the outer width.
   *
   * @param string $left
   *   The left corner or junction glyph.
   * @param string $right
   *   The right corner or junction glyph.
   * @param string $fill
   *   The horizontal fill glyph.
   *
   * @return string
   *   The styled rule.
   */
  protected function rule(string $left, string $right, string $fill): string {
    return $this->style('border', $left . str_repeat($fill, max(0, $this->outerWidth - 2)) . $right);
  }

  /**
   * Wrap a content line in vertical borders with a one-column gutter each side.
   *
   * @param string $content
   *   The content (may carry ANSI codes and be shorter than the inner width).
   * @param string $vertical
   *   The vertical border glyph.
   *
   * @return string
   *   The boxed line, padded to the outer width.
   */
  protected function boxLine(string $content, string $vertical): string {
    $inner = max(1, $this->outerWidth - 4);
    $width = Ansi::width($content);

    if ($width > $inner) {
      $content = mb_substr(Ansi::strip($content), 0, $inner);
      $width = Ansi::width($content);
    }

    $bar = $this->style('border', $vertical);

    return $bar . ' ' . $content . str_repeat(' ', max(0, $inner - $width)) . ' ' . $bar;
  }

  /**
   * The corner, junction, horizontal and vertical glyphs for the border option.
   *
   * @return array<string,string>
   *   The glyphs keyed by position: tl, tr, bl, br (corners), ml, mr
   *   (left/right junctions), h (horizontal), v (vertical).
   */
  protected function borderChars(): array {
    if (!$this->unicode) {
      $fill = $this->border() === self::BORDER_DOUBLE ? '=' : '-';

      return ['tl' => '+', 'tr' => '+', 'bl' => '+', 'br' => '+', 'ml' => '+', 'mr' => '+', 'h' => $fill, 'v' => '|'];
    }

    return match ($this->border()) {
      self::BORDER_ROUNDED => ['tl' => '╭', 'tr' => '╮', 'bl' => '╰', 'br' => '╯', 'ml' => '├', 'mr' => '┤', 'h' => '─', 'v' => '│'],
      self::BORDER_DOUBLE => ['tl' => '╔', 'tr' => '╗', 'bl' => '╚', 'br' => '╝', 'ml' => '╠', 'mr' => '╣', 'h' => '═', 'v' => '║'],
      default => ['tl' => '┌', 'tr' => '┐', 'bl' => '└', 'br' => '┘', 'ml' => '├', 'mr' => '┤', 'h' => '─', 'v' => '│'],
    };
  }

  /**
   * Compose a start banner: the logo above an optional version line.
   *
   * @param string $logo
   *   The banner logo (may be multi-line).
   * @param string $version
   *   The version string, shown dimmed below the logo when non-empty.
   *
   * @return string
   *   The composed banner.
   */
  public function renderBanner(string $logo, string $version): string {
    $lines = [];

    foreach (explode("\n", $logo) as $line) {
      $lines[] = $this->style('title', $line);
    }

    if ($version !== '') {
      $lines[] = '';
      $lines[] = $this->style('footer', 'Version: ' . $version);
    }

    return implode("\n", $lines);
  }

  /**
   * Render the status line shown at the foot of a panel.
   *
   * @return string
   *   The themed status line (hint text and arrow glyphs).
   */
  public function renderStatusLine(): string {
    return $this->renderHintLine($this->glyph('arrow_up') . '/' . $this->glyph('arrow_down') . ' move', $this->glyph('enter') . ' select', 'esc back');
  }

  /**
   * Render a dimmed line of key hints, joined with the dot glyph.
   *
   * @param string ...$hints
   *   The hint fragments (e.g. "enter accept", "esc cancel").
   *
   * @return string
   *   The themed hint line.
   */
  public function renderHintLine(string ...$hints): string {
    return $this->style('footer', implode(' ' . $this->glyph('dot') . ' ', $hints));
  }

  /**
   * Render the header shown above a field's editor: its label, underlined.
   *
   * The rule under the label keeps a label recognisable as a label in every
   * display mode - with colour off, the underline alone carries the
   * distinction from the value being edited.
   *
   * @param string $label
   *   The field label.
   *
   * @return string
   *   The two-line themed header.
   */
  public function renderEditorHeader(string $label): string {
    $rule = str_repeat($this->glyph('rule'), max(1, mb_strlen($label)));

    return $this->style('title', $label) . "\n" . $this->style('rule', $rule);
  }

  /**
   * Compose a field's editor screen: the label, the widget view and hints.
   *
   * Borderless, this is the label over its rule, the view, then the hints. With
   * a border, the editor is boxed through the same frame the panel uses - the
   * label its header, the view its body, the hints its footer.
   *
   * @param string $label
   *   The field label.
   * @param string $view
   *   The widget's rendered view.
   *
   * @return string
   *   The editor screen.
   */
  public function renderEditor(string $label, string $view): string {
    $hints = $this->renderHintLine($this->glyph('enter') . ' accept', 'esc cancel');

    if ($this->border() === self::BORDER_NONE) {
      return $this->renderEditorHeader($label) . "\n" . $view . "\n\n" . $hints;
    }

    $body = explode("\n", $view);

    return $this->renderFrame([$this->style('title', $label)], $body, [$hints], new Viewport(0, FALSE, FALSE), count($body));
  }

  /**
   * Render a row of inline submit/cancel buttons.
   *
   * @param list<string> $labels
   *   The button labels, in order.
   * @param int $selected
   *   The index of the selected button, or -1 for none.
   *
   * @return string
   *   The themed button row with the buttons side by side.
   */
  public function renderButtonBar(array $labels, int $selected): string {
    $parts = [];

    foreach ($labels as $index => $label) {
      $text = '[ ' . $label . ' ]';
      $parts[] = $index === $selected ? $this->style('cursor', $text) : $this->style('value', $text);
    }

    return '  ' . implode('  ', $parts);
  }

  /**
   * The selection marker.
   *
   * @param bool $selected
   *   Whether selected.
   *
   * @return string
   *   The marker.
   */
  protected function marker(bool $selected): string {
    return $selected ? $this->style('marker', $this->glyph('marker')) : ' ';
  }

  /**
   * Render a field's value readably, masking secret values.
   *
   * Password values render as a fixed-length mask so neither the value nor
   * its length shows on screen.
   *
   * @param \DrevOps\Tui\Config\Field $field
   *   The field the value belongs to.
   * @param mixed $value
   *   The value.
   *
   * @return string
   *   The rendered value.
   */
  protected function renderFieldValue(Field $field, mixed $value): string {
    if ($field->type === FieldType::Password) {
      return is_string($value) && $value !== '' ? str_repeat($this->glyph('mask'), 8) : '';
    }

    return $this->renderValue($value);
  }

  /**
   * Render a value readably.
   *
   * @param mixed $value
   *   The value.
   *
   * @return string
   *   The rendered value.
   */
  protected function renderValue(mixed $value): string {
    if (is_bool($value)) {
      return $value ? 'yes' : 'no';
    }

    if (is_array($value)) {
      return implode(', ', array_map(static fn(mixed $item): string => is_scalar($item) ? (string) $item : '', $value));
    }

    return is_scalar($value) ? (string) $value : '';
  }

}
