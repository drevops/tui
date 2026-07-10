<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Config\Panel;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Box;
use DrevOps\Tui\Render\HelpSection;
use DrevOps\Tui\Render\Navigator;
use DrevOps\Tui\Render\Scroller;
use DrevOps\Tui\Render\Viewport;

/**
 * The default theme: the appearance atoms plus the assembly that arranges them.
 *
 * Two layers, one class. The **atoms** are one method per colour and glyph
 * (title(), value(), marker(), border(), caret()…) - each takes text or a flag
 * and returns it styled for the theme's mode; these are what a consumer theme
 * overrides. The **render*()** methods are the assembly: they arrange those
 * atoms into field rows, the scrolled frame and the editor. Pure box geometry
 * (character sets, width fitting) lives in {@see Box}; everything visual routes
 * through the atoms.
 *
 * A consumer theme extends this and overrides just what it wants - usually an
 * atom, occasionally a render method for a layout tweak:
 *
 * @code
 * class OceanTheme extends DefaultTheme {
 *   public function title(string $text): string { return $this->paint('1;96', $text); }
 *   public function renderPanelLine(Panel $panel, bool $selected): string {
 *     return $this->marker($selected) . ' ' . $this->label($panel->title);
 *   }
 * }
 * @endcode
 *
 * @package DrevOps\Tui\Theme
 */
class DefaultTheme implements ThemeInterface {

  /**
   * Whether colour (ANSI) is enabled, resolved from the "color" option.
   */
  protected bool $color;

  /**
   * Whether Unicode glyphs are used, resolved from the "unicode" option.
   */
  protected bool $unicode;

  /**
   * Whether the dark palette is used, resolved from the "mode" option.
   */
  protected bool $isDark;

  /**
   * The outer frame width, including the border when one is drawn.
   */
  protected int $outerWidth;

  /**
   * Construct a theme.
   *
   * @param int $width
   *   The frame width used for right-aligned badges and the border.
   * @param array<string,mixed> $options
   *   Display options keyed by name and validated against optionSchema():
   *   "mode" (a MODE_* value), "color" and "unicode" (booleans; unset defaults
   *   on), "spacing" (a SPACING_* value), "border" (a BORDER_* value), plus any
   *   option a concrete theme declares.
   */
  public function __construct(protected int $width = 76, protected array $options = []) {
    $this->validateOptions();

    $this->color = is_bool($this->options['color'] ?? NULL) ? $this->options['color'] : TRUE;
    $this->unicode = is_bool($this->options['unicode'] ?? NULL) ? $this->options['unicode'] : TRUE;
    $this->isDark = $this->mode() === self::MODE_DARK;
    $this->outerWidth = $this->width;

    // A border consumes two frame columns plus a one-column gutter each side.
    // Lay rows out that much narrower to keep right-aligned badges inside it.
    if ($this->borderStyle() !== self::BORDER_NONE) {
      $this->width = max(1, $this->width - 4);
    }
  }

  /**
   * Validate the options against optionSchema(), failing loudly on a mistake.
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
   * A concrete theme adds its own options by merging over the base -
   * `return ['accent' => ['cool', 'warm']] + parent::optionSchema();`.
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
   * The frame width the renderer lays out to.
   *
   * @return int
   *   The width.
   */
  public function width(): int {
    return $this->width;
  }

  /**
   * The vertical spacing option, for the renderer.
   *
   * @return string
   *   One of the SPACING_* values.
   */
  public function spacing(): string {
    return $this->option('spacing', self::SPACING_NORMAL);
  }

  /**
   * The border-style option, for the renderer.
   *
   * @return string
   *   One of the BORDER_* values.
   */
  public function borderStyle(): string {
    return $this->option('border', self::BORDER_NONE);
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
   * Wrap text in an SGR code, honouring colour-off.
   *
   * The single low-level helper every styler builds on.
   *
   * @param string $sgr
   *   The SGR parameters (e.g. "1;36"); empty leaves the text unstyled.
   * @param string $text
   *   The text.
   *
   * @return string
   *   The styled text (unchanged when colour is off).
   */
  protected function paint(string $sgr, string $text): string {
    return Ansi::style($text, $this->color ? $sgr : '');
  }

  /**
   * Add bold to an SGR code when an item is selected.
   *
   * @param string $sgr
   *   The base SGR code.
   * @param bool $selected
   *   Whether the item is the selected (cursor) one.
   *
   * @return string
   *   The code, made bold when selected.
   */
  protected function emphasize(string $sgr, bool $selected): string {
    if (!$selected) {
      return $sgr;
    }

    $drop = ['', '1', '2'];
    $parts = array_values(array_filter(explode(';', $sgr), static fn(string $part): bool => !in_array($part, $drop, TRUE)));
    array_unshift($parts, '1');

    return implode(';', $parts);
  }

  /**
   * {@inheritdoc}
   */
  public function title(string $text): string {
    return $this->paint($this->isDark ? '1;36' : '1;34', $text);
  }

  /**
   * {@inheritdoc}
   */
  public function label(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize('', $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function value(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize('32', $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function description(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize('90', $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function badge(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize('7', $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function cursor(string $text): string {
    return $this->paint('1;7', $text);
  }

  /**
   * {@inheritdoc}
   */
  public function footer(string $text): string {
    return $this->paint('90', $text);
  }

  /**
   * {@inheritdoc}
   */
  public function breadcrumb(string $text): string {
    return $this->paint('90', $text);
  }

  /**
   * {@inheritdoc}
   */
  public function indicator(string $text): string {
    return $this->paint($this->isDark ? '1;33' : '35', $text);
  }

  /**
   * {@inheritdoc}
   */
  public function highlight(string $text): string {
    return $this->paint($this->isDark ? '1;36' : '1;34', $text);
  }

  /**
   * {@inheritdoc}
   */
  public function highlightMatch(string $text): string {
    return $this->paint($this->isDark ? '1;33' : '1;35', $text);
  }

  /**
   * {@inheritdoc}
   */
  public function heading(string $text): string {
    return $this->paint('1;90', $text);
  }

  /**
   * {@inheritdoc}
   */
  public function divider(): string {
    return $this->footer(str_repeat($this->unicode ? '─' : '-', max(1, $this->width)));
  }

  /**
   * {@inheritdoc}
   */
  public function disabled(string $text): string {
    return $this->paint('90', $text);
  }

  /**
   * {@inheritdoc}
   */
  public function error(string $text): string {
    return $this->paint('31', $text);
  }

  /**
   * {@inheritdoc}
   */
  public function rule(string $text): string {
    return $this->paint('90', $text);
  }

  /**
   * {@inheritdoc}
   */
  public function border(string $text): string {
    return $this->paint($this->isDark ? '36' : '34', $text);
  }

  /**
   * {@inheritdoc}
   */
  public function marker(bool $selected): string {
    return $selected ? $this->paint($this->isDark ? '1;36' : '1;34', $this->unicode ? '❯' : '>') : ' ';
  }

  /**
   * {@inheritdoc}
   */
  public function arrow(): string {
    return $this->unicode ? '›' : '>';
  }

  /**
   * {@inheritdoc}
   */
  public function separator(): string {
    return $this->unicode ? '›' : '>';
  }

  /**
   * {@inheritdoc}
   */
  public function arrowUp(): string {
    return $this->unicode ? '↑' : '^';
  }

  /**
   * {@inheritdoc}
   */
  public function arrowDown(): string {
    return $this->unicode ? '↓' : 'v';
  }

  /**
   * {@inheritdoc}
   */
  public function arrowLeft(): string {
    return $this->unicode ? '←' : '<';
  }

  /**
   * {@inheritdoc}
   */
  public function arrowRight(): string {
    return $this->unicode ? '→' : '>';
  }

  /**
   * {@inheritdoc}
   */
  public function enter(): string {
    return $this->unicode ? '↵' : '<';
  }

  /**
   * {@inheritdoc}
   */
  public function dot(): string {
    return $this->unicode ? '·' : '*';
  }

  /**
   * {@inheritdoc}
   */
  public function indicatorUp(): string {
    return $this->unicode ? '▲' : '^';
  }

  /**
   * {@inheritdoc}
   */
  public function indicatorDown(): string {
    return $this->unicode ? '▼' : 'v';
  }

  /**
   * {@inheritdoc}
   */
  public function radio(bool $on): string {
    return $on ? $this->paint($this->isDark ? '1;36' : '1;34', $this->unicode ? '●' : '(*)') : ($this->unicode ? '○' : '( )');
  }

  /**
   * {@inheritdoc}
   */
  public function check(bool $on): string {
    return $on ? $this->value($this->unicode ? '◼' : '[x]') : ($this->unicode ? '◻' : '[ ]');
  }

  /**
   * {@inheritdoc}
   */
  public function caret(): string {
    return $this->paint($this->isDark ? '1;36' : '1;34', $this->unicode ? '█' : '|');
  }

  /**
   * {@inheritdoc}
   */
  public function ghost(string $text): string {
    return $this->color ? $this->paint('90', $text) : '';
  }

  /**
   * {@inheritdoc}
   */
  public function mask(): string {
    return $this->unicode ? '•' : '*';
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
    $left = $this->marker($selected) . ' ' . $this->label($field->label, $selected) . '  ' . $this->value($this->renderFieldValue($field, $answers->value($field->id)), $selected);

    $provenance = $answers->provenanceOf($field->id);
    if ($provenance === Provenance::Default) {
      return $left;
    }

    return Ansi::alignRight($left, $this->badge(' ' . $provenance->value . ' ', $selected), $this->width);
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
    return $this->marker($selected) . ' ' . $this->label($panel->title, $selected) . ' ' . $this->description($this->arrow(), $selected);
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
    return '    ' . $this->description($description, $selected);
  }

  /**
   * Summarize a sub-panel's active field values into one line, for the hub.
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

    return implode(' ' . $this->dot() . ' ', $parts);
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

    return '    ' . $this->value($clipped, $selected);
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
    return $this->breadcrumb(implode(' ' . $this->separator() . ' ', $navigator->breadcrumb()));
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
    if ($this->borderStyle() === self::BORDER_NONE) {
      return $this->renderBorderless($header, $body, $footer, $viewport, $height);
    }

    $chars = Box::chars($this->borderStyle(), $this->unicode);
    $visible = (new Scroller())->slice($body, $viewport->offset, $height);

    $middle = [];
    if ($viewport->has_above) {
      $middle[] = $this->indicator('  ' . $this->indicatorUp());
    }

    $middle = array_merge($middle, $visible);

    if ($viewport->has_below) {
      $middle[] = $this->indicator('  ' . $this->indicatorDown());
    }

    $pad = $this->spacing() === self::SPACING_PADDED;

    $out = [$this->borderRule($chars['tl'], $chars['tr'], $chars['h'])];

    foreach ($header as $line) {
      $out[] = $this->boxLine($line, $chars['v']);
    }

    $out[] = $this->borderRule($chars['ml'], $chars['mr'], $chars['h']);

    if ($pad) {
      $out[] = $this->boxLine('', $chars['v']);
    }

    foreach ($middle as $line) {
      $out[] = $this->boxLine($line, $chars['v']);
    }

    if ($pad) {
      $out[] = $this->boxLine('', $chars['v']);
    }

    if ($footer !== []) {
      $out[] = $this->borderRule($chars['ml'], $chars['mr'], $chars['h']);

      foreach ($footer as $line) {
        $out[] = $this->boxLine($line, $chars['v']);
      }
    }

    $out[] = $this->borderRule($chars['bl'], $chars['br'], $chars['h']);

    return implode("\n", $out);
  }

  /**
   * Compose a borderless frame, detaching the status line by spacing.
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
      $lines[] = $this->indicator('  ' . $this->indicatorUp());
    }

    $lines = array_merge($lines, $visible);

    if ($viewport->has_below) {
      $lines[] = $this->indicator('  ' . $this->indicatorDown());
    }

    if ($this->spacing() !== self::SPACING_COMPACT) {
      $lines[] = '';
    }

    return implode("\n", array_merge($lines, $footer));
  }

  /**
   * Build a horizontal border rule, coloured with the border atom.
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
  protected function borderRule(string $left, string $right, string $fill): string {
    return $this->border(Box::rule($left, $right, $fill, $this->outerWidth));
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
    $bar = $this->border($vertical);

    return $bar . ' ' . Box::fit($content, max(1, $this->outerWidth - 4)) . ' ' . $bar;
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
      $lines[] = $this->title($line);
    }

    if ($version !== '') {
      $lines[] = '';
      $lines[] = $this->footer('Version: ' . $version);
    }

    return implode("\n", $lines);
  }

  /**
   * {@inheritdoc}
   */
  public function keyHint(Key $key): string {
    $name = $key->name;

    if (!$name instanceof KeyName) {
      $char = (string) $key->char;

      // Render a control character (e.g. Ctrl-E) as "ctrl-e".
      return $char !== '' && ord($char) < 0x20 ? 'ctrl-' . strtolower(chr(ord($char) + 0x40)) : $char;
    }

    return match ($name) {
      KeyName::Up, KeyName::MouseWheelUp => $this->arrowUp(),
      KeyName::Down, KeyName::MouseWheelDown => $this->arrowDown(),
      KeyName::Left => $this->arrowLeft(),
      KeyName::Right => $this->arrowRight(),
      KeyName::Enter => $this->enter(),
      KeyName::Escape => 'esc',
      KeyName::Tab => 'tab',
      KeyName::Space => 'space',
      KeyName::Backspace => $this->unicode ? '⌫' : 'bksp',
      KeyName::Delete => 'del',
      KeyName::Home => 'home',
      KeyName::End => 'end',
      KeyName::PageUp => 'pgup',
      KeyName::PageDown => 'pgdn',
    };
  }

  /**
   * {@inheritdoc}
   */
  public function keysHint(ScopedKeyMap $keys, string $label, Action ...$actions): string {
    $glyphs = [];

    foreach ($actions as $action) {
      $key = $keys->primary($action);

      if ($key instanceof Key) {
        $glyphs[] = $this->keyHint($key);
      }
    }

    return $glyphs === [] ? '' : implode('/', $glyphs) . ' ' . $label;
  }

  /**
   * Render a context's hint fragments as one dot-joined footer line.
   *
   * Each {@see Hint} becomes a labelled fragment drawn from the live bindings,
   * so the line never contradicts a remapped key. Fragments whose actions are
   * all unbound drop out, and an entirely unbound context yields an empty line.
   *
   * @param \DrevOps\Tui\Input\ScopedKeyMap $keys
   *   The active scope's bindings.
   * @param \DrevOps\Tui\Input\Hint ...$hints
   *   The hint fragments, in display order.
   *
   * @return string
   *   The themed hint line, or an empty string when nothing is bound.
   */
  public function renderHints(ScopedKeyMap $keys, Hint ...$hints): string {
    $fragments = [];

    foreach ($hints as $hint) {
      $fragment = $this->keysHint($keys, $hint->label, ...$hint->actions);

      if ($fragment !== '') {
        $fragments[] = $fragment;
      }
    }

    return $fragments === [] ? '' : $this->renderHintLine(...$fragments);
  }

  /**
   * Render a dimmed line of key hints, joined with the dot glyph.
   *
   * @param string ...$hints
   *   The hint fragments (e.g. "enter accept", "esc cancel"). Empty fragments -
   *   an unbound action - are dropped so the line has no dangling separators.
   *
   * @return string
   *   The themed hint line.
   */
  public function renderHintLine(string ...$hints): string {
    return $this->footer(implode(' ' . $this->dot() . ' ', array_filter($hints)));
  }

  /**
   * Render the header shown above a field's editor: its label, underlined.
   *
   * @param string $label
   *   The field label.
   *
   * @return string
   *   The two-line themed header.
   */
  public function renderEditorHeader(string $label): string {
    $underline = str_repeat($this->unicode ? '─' : '-', max(1, mb_strlen($label)));

    return $this->title($label) . "\n" . $this->rule($underline);
  }

  /**
   * Compose a field's editor screen: the label, the widget view and its hints.
   *
   * @param string $label
   *   The field label.
   * @param string $view
   *   The widget's rendered view.
   * @param list<\DrevOps\Tui\Input\Hint> $hints
   *   The widget's hint fragments; an empty list draws no hint line, so the
   *   footer can be turned off form-wide.
   * @param \DrevOps\Tui\Input\ScopedKeyMap|null $keys
   *   The editor's scope bindings, so the hint glyphs reflect the active keys.
   *
   * @return string
   *   The editor screen - boxed when the theme has a border, else plain.
   */
  public function renderEditor(string $label, string $view, array $hints = [], ?ScopedKeyMap $keys = NULL): string {
    $hint = $keys instanceof ScopedKeyMap ? $this->renderHints($keys, ...$hints) : '';
    $footer = $hint === '' ? [] : [$hint];

    if ($this->borderStyle() !== self::BORDER_NONE) {
      $body = explode("\n", $view);

      return $this->renderFrame([$this->title($label)], $body, $footer, new Viewport(0, FALSE, FALSE), count($body));
    }

    $screen = $this->renderEditorHeader($label) . "\n" . $view;

    return $hint === '' ? $screen : $screen . "\n\n" . $hint;
  }

  /**
   * Compose the full-screen key-binding help overlay.
   *
   * @param \DrevOps\Tui\Input\ScopedKeyMap $nav
   *   The navigation bindings, for the close hint.
   * @param \DrevOps\Tui\Render\HelpSection ...$sections
   *   The contexts to list, each a heading with its bindings and hints.
   *
   * @return string
   *   The rendered overlay.
   */
  public function renderHelp(ScopedKeyMap $nav, HelpSection ...$sections): string {
    $lines = [$this->title('Keyboard help'), ''];

    foreach ($sections as $section) {
      $lines[] = $this->label($section->title);
      $hint = $this->renderHints($section->keys, ...$section->hints);

      if ($hint !== '') {
        $lines[] = $hint;
      }

      $lines[] = '';
    }

    $lines[] = $this->renderHints($nav, new Hint('close', Action::Help));

    return implode("\n", $lines);
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
      $parts[] = $index === $selected ? $this->cursor($text) : $this->value($text);
    }

    return '  ' . implode('  ', $parts);
  }

  /**
   * Render a field's value readably, masking secret values.
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
      return is_string($value) && $value !== '' ? str_repeat($this->mask(), 8) : '';
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
