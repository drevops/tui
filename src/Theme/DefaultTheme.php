<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Answers\SummaryFormatter;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\Modal;
use DrevOps\Tui\Model\Panel;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Box;
use DrevOps\Tui\Render\HelpSection;
use DrevOps\Tui\Render\Navigator;
use DrevOps\Tui\Render\Overlay;
use DrevOps\Tui\Render\Scroller;
use DrevOps\Tui\Render\Viewport;
use DrevOps\Tui\Translation\Translator;

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
 *   public function title(string $text): string { return $this->paint(Sgr::of(Sgr::Bold, Sgr::BrightCyan), $text); }
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
   * The default frame width, used when a caller does not specify one.
   */
  public const int DEFAULT_WIDTH = 76;

  /**
   * The nominal width of a boxed or underlined input field, in columns.
   */
  protected const int FIELD_WIDTH = 40;

  /**
   * The minimum width of a boxed or underlined input field, in columns.
   */
  protected const int FIELD_MIN_WIDTH = 12;

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
  public function __construct(protected int $width = self::DEFAULT_WIDTH, protected array $options = []) {
    $this->validateOptions();

    $this->color = is_bool($this->options['color'] ?? NULL) ? $this->options['color'] : TRUE;
    $this->unicode = is_bool($this->options['unicode'] ?? NULL) ? $this->options['unicode'] : TRUE;
    $this->isDark = $this->mode() === Mode::Dark;
    $this->outerWidth = $this->width;

    // A border consumes two frame columns plus a one-column gutter each side.
    // Lay rows out that much narrower to keep right-aligned badges inside it.
    if ($this->borderStyle() !== Border::None) {
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
        throw new \InvalidArgumentException(Translator::t('Unknown theme option "@key". Known: @known.', [
          '@key' => $key,
          '@known' => implode(', ', array_keys($schema)),
        ]));
      }

      // An enum case and its backing value are interchangeable as an option.
      $candidate = $value instanceof \BackedEnum ? $value->value : $value;

      if (!in_array($candidate, $schema[$key], TRUE)) {
        throw new \InvalidArgumentException(Translator::t('@value is not a valid "@key". Allowed: @allowed.', [
          '@value' => $this->showValue($candidate),
          '@key' => $key,
          '@allowed' => implode(', ', array_map($this->showValue(...), $schema[$key])),
        ]));
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
      'mode' => array_column(Mode::cases(), 'value'),
      'color' => [TRUE, FALSE],
      'unicode' => [TRUE, FALSE],
      'spacing' => array_column(Spacing::cases(), 'value'),
      'border' => array_column(Border::cases(), 'value'),
      'field' => array_column(FieldStyle::cases(), 'value'),
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
   * @return \DrevOps\Tui\Theme\Mode
   *   The mode; dark when unset.
   */
  protected function mode(): Mode {
    $value = $this->options['mode'] ?? NULL;

    if ($value instanceof Mode) {
      return $value;
    }

    return is_string($value) ? (Mode::tryFrom($value) ?? Mode::Dark) : Mode::Dark;
  }

  /**
   * The frame width the renderer lays out to.
   *
   * @return int
   *   The width.
   */
  protected function width(): int {
    return $this->width;
  }

  /**
   * The vertical spacing option.
   *
   * @return \DrevOps\Tui\Theme\Spacing
   *   The spacing; normal when unset.
   */
  protected function spacing(): Spacing {
    $value = $this->options['spacing'] ?? NULL;

    if ($value instanceof Spacing) {
      return $value;
    }

    return is_string($value) ? (Spacing::tryFrom($value) ?? Spacing::Normal) : Spacing::Normal;
  }

  /**
   * The border-style option.
   *
   * @return \DrevOps\Tui\Theme\Border
   *   The border style; none when unset.
   */
  protected function borderStyle(): Border {
    $value = $this->options['border'] ?? NULL;

    if ($value instanceof Border) {
      return $value;
    }

    return is_string($value) ? (Border::tryFrom($value) ?? Border::None) : Border::None;
  }

  /**
   * The field-input style option.
   *
   * @return \DrevOps\Tui\Theme\FieldStyle
   *   The field style; flat when unset.
   */
  protected function field(): FieldStyle {
    $value = $this->options['field'] ?? NULL;

    if ($value instanceof FieldStyle) {
      return $value;
    }

    return is_string($value) ? (FieldStyle::tryFrom($value) ?? FieldStyle::Flat) : FieldStyle::Flat;
  }

  /**
   * The background the theme washes the screen with, or NULL for none.
   *
   * A styled span closes with a full reset, so a background opened once would
   * not survive it. The render layer instead re-opens this background on every
   * line and after every reset and erases each line to its end, so the whole
   * screen - the gaps between spans and the padding past the content included -
   * fills with it. A theme declares its background here the same way it
   * declares a title colour.
   *
   * @return string|null
   *   The background SGR parameters (e.g. "44" for blue), or NULL to keep the
   *   terminal's own background.
   */
  public function background(): ?string {
    return NULL;
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

    $drop = ['', Sgr::Bold->value, Sgr::Dim->value];
    $parts = array_values(array_filter(explode(';', $sgr), static fn(string $part): bool => !in_array($part, $drop, TRUE)));
    array_unshift($parts, Sgr::Bold->value);

    return implode(';', $parts);
  }

  /**
   * {@inheritdoc}
   */
  public function title(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Cyan) : Sgr::of(Sgr::Bold, Sgr::Blue), $text);
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
    return $this->paint($this->emphasize(Sgr::of(Sgr::Green), $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function description(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize(Sgr::of(Sgr::Grey), $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function badge(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize(Sgr::of(Sgr::Reverse), $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function cursor(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::Reverse), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function footer(string $text): string {
    return $this->paint(Sgr::of(Sgr::Grey), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function breadcrumb(string $text): string {
    return $this->paint(Sgr::of(Sgr::Grey), $text);
  }

  /**
   * Recede text into the background, so a modal reads as floating above it.
   *
   * @param string $text
   *   The text.
   *
   * @return string
   *   The dimmed text (unchanged when colour is off).
   */
  public function dim(string $text): string {
    return $this->paint(Sgr::of(Sgr::Dim), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function indicator(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Yellow) : Sgr::of(Sgr::Magenta), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function highlight(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Cyan) : Sgr::of(Sgr::Bold, Sgr::Blue), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function highlightMatch(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Yellow) : Sgr::of(Sgr::Bold, Sgr::Magenta), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function heading(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::Grey), $text);
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
    return $this->paint(Sgr::of(Sgr::Grey), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function error(string $text): string {
    return $this->paint(Sgr::of(Sgr::Red), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function rule(string $text): string {
    return $this->paint(Sgr::of(Sgr::Grey), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function border(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Cyan) : Sgr::of(Sgr::Blue), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function marker(bool $selected): string {
    return $selected ? $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Cyan) : Sgr::of(Sgr::Bold, Sgr::Blue), $this->unicode ? '❯' : '>') : ' ';
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
    return $on ? $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Cyan) : Sgr::of(Sgr::Bold, Sgr::Blue), $this->unicode ? '●' : '(*)') : ($this->unicode ? '○' : '( )');
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
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Cyan) : Sgr::of(Sgr::Bold, Sgr::Blue), $this->unicode ? '█' : '|');
  }

  /**
   * {@inheritdoc}
   */
  public function ghost(string $text): string {
    return $this->color ? $this->paint(Sgr::of(Sgr::Grey), $text) : '';
  }

  /**
   * {@inheritdoc}
   */
  public function renderInput(string $before, string $after, string $ghost = ''): string {
    if (!$this->color || $this->field() === FieldStyle::Flat) {
      return $before . $this->caret() . $after . ($ghost === '' ? '' : $this->ghost($ghost));
    }

    // The caret reverses the character it sits on (a space at the line end), so
    // the cursor highlights the letter rather than hiding it behind a block.
    $cursor_char = $after === '' ? ' ' : mb_substr($after, 0, 1, 'UTF-8');
    $tail = $after === '' ? '' : mb_substr($after, 1, NULL, 'UTF-8');

    $target = max(self::FIELD_MIN_WIDTH, min($this->width, self::FIELD_WIDTH));
    $visible = mb_strlen($before, 'UTF-8') + 1 + mb_strlen($tail, 'UTF-8') + mb_strlen($ghost, 'UTF-8');
    $pad = str_repeat(' ', max(0, $target - $visible));

    // The caret (reverse) and ghost (dim) toggle off again (27, 22) instead of
    // resetting, so the field fill runs unbroken behind the whole value - the
    // only reset is Ansi::style()'s closing one.
    $cursor = Ansi::ESC . '[7m' . $cursor_char . Ansi::ESC . '[27m';
    $suffix = $ghost === '' ? '' : Ansi::ESC . '[2m' . $ghost . Ansi::ESC . '[22m';

    // Underline styles draw the value colour; a box fills it - light on dark
    // (black on grey), dark on light (white on blue) - so the field reads
    // against either terminal background.
    $fill = $this->field() === FieldStyle::Underline ? Sgr::of(Sgr::Underline, Sgr::Green) : ($this->isDark ? Sgr::of(Sgr::Black, Sgr::OnGrey) : Sgr::of(Sgr::BrightWhite, Sgr::OnBlue));

    return Ansi::style($before . $cursor . $tail . $suffix . $pad, $fill);
  }

  /**
   * {@inheritdoc}
   */
  public function mask(): string {
    return $this->unicode ? '•' : '*';
  }

  /**
   * Build the body lines and the line index of the selected item.
   *
   * @param \DrevOps\Tui\Model\Panel $panel
   *   The panel.
   * @param \DrevOps\Tui\Answers\Answers $answers
   *   The current answers.
   * @param int $cursor
   *   The selected item index.
   * @param \DrevOps\Tui\Model\Field|null $editing
   *   The field whose editor is expanded inline in the panel, or NULL when no
   *   field is being edited inline.
   * @param string $editorView
   *   The inline editor's rendered view, spliced in at the editing field's row
   *   in place of its summary.
   *
   * @return array{list<string>,int}
   *   The body lines and the selected item's first line index.
   */
  public function renderBody(Panel $panel, Answers $answers, int $cursor, ?Field $editing = NULL, string $editorView = ''): array {
    $lines = [];
    $cursor_line = 0;
    $index = 0;

    $spacing = $this->spacing();
    $gap = $spacing === Spacing::Padded ? 1 : 0;
    $verbose = $spacing !== Spacing::Compact;

    foreach ($panel->fields as $field) {
      if ($index > 0 && $gap > 0) {
        $lines[] = '';
      }

      if ($index === $cursor) {
        $cursor_line = count($lines);
      }

      if ($editing instanceof Field && $field->id === $editing->id) {
        foreach ($this->renderInlineEditor($field, $editorView, $index === $cursor) as $line) {
          $lines[] = $line;
        }

        if ($verbose && $field->description !== '') {
          $lines[] = $this->renderDescriptionLine(Translator::t($field->description), $index === $cursor);
        }

        $index++;

        continue;
      }

      foreach ($this->renderFieldLine($field, $answers, $index === $cursor) as $line) {
        $lines[] = $line;
      }

      if ($verbose && $field->description !== '') {
        $lines[] = $this->renderDescriptionLine(Translator::t($field->description), $index === $cursor);
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
        $lines[] = $this->renderDescriptionLine(Translator::t($subpanel->description), $index === $cursor);
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
   * Render a field row, one entry per physical line.
   *
   * A single-line value is one row: the label, then the value. A multi-line
   * value (a textarea) spans one row per line - the first rides the label row,
   * the rest align under the value column - so no row ever carries an embedded
   * newline that would desync the box border and scroll maths. Each line is
   * styled on its own, so no colour span crosses a row boundary.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param \DrevOps\Tui\Answers\Answers $answers
   *   The current answers.
   * @param bool $selected
   *   Whether the row is selected.
   *
   * @return list<string>
   *   The field's rows: the label row carrying the value's first line, then any
   *   further value lines indented to the value column.
   */
  public function renderFieldLine(Field $field, Answers $answers, bool $selected): array {
    $prefix = $this->marker($selected) . ' ' . $this->label(Translator::t($field->label), $selected) . '  ';
    $indent = str_repeat(' ', Ansi::width($prefix));

    $lines = [];

    foreach (explode("\n", $this->renderFieldValue($field, $answers->value($field->id))) as $index => $value_line) {
      $lines[] = ($index === 0 ? $prefix : $indent) . $this->value($value_line, $selected);
    }

    $provenance = $answers->provenanceOf($field->id);

    if ($provenance !== Provenance::Default) {
      $lines[0] = Ansi::alignRight($lines[0], $this->badge(' ' . $provenance->label() . ' ', $selected), $this->width);
    }

    return $lines;
  }

  /**
   * Render a field's editor in place of its value: the label, then the view.
   *
   * The field keeps its label and marker; the widget's own rendered view takes
   * the place of the summary value, on the label row and, when it spans
   * several lines, aligning the rest under that value column - so the field
   * reads as its editor opened in place, the rest of the panel still around it.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field being edited.
   * @param string $view
   *   The widget's rendered view.
   * @param bool $selected
   *   Whether the field's row holds the cursor (it does while editing).
   *
   * @return list<string>
   *   The label row carrying the view's first line, then any further view lines
   *   indented to the value column.
   */
  public function renderInlineEditor(Field $field, string $view, bool $selected): array {
    $prefix = $this->marker($selected) . ' ' . $this->label(Translator::t($field->label), $selected) . '  ';
    $indent = str_repeat(' ', Ansi::width($prefix));

    $lines = [];

    foreach (explode("\n", $view) as $index => $line) {
      $lines[] = ($index === 0 ? $prefix : $indent) . $line;
    }

    return $lines;
  }

  /**
   * Render a sub-panel row.
   *
   * @param \DrevOps\Tui\Model\Panel $panel
   *   The sub-panel.
   * @param bool $selected
   *   Whether the row is selected.
   *
   * @return string
   *   The row.
   */
  public function renderPanelLine(Panel $panel, bool $selected): string {
    return $this->marker($selected) . ' ' . $this->label(Translator::t($panel->title), $selected) . ' ' . $this->description($this->arrow(), $selected);
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
   * @param \DrevOps\Tui\Model\Panel $panel
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
      $rendered = is_array($value) && count($value) > 3 ? Translator::t('@count selected', [
        '@count' => count($value),
      ]) : $this->renderFieldValue($field, $value);

      // A summary is one line, so a multi-line value (a textarea) folds to a
      // single row rather than breaking the row it sits on.
      $parts[] = str_replace("\n", ' ', $rendered);

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
    $clipped = mb_strlen($summary, 'UTF-8') > $max ? mb_substr($summary, 0, $max - 1, 'UTF-8') . '…' : $summary;

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
    return $this->breadcrumb(implode(' ' . $this->separator() . ' ', array_map(Translator::t(...), $navigator->breadcrumb())));
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
    return $this->renderBoxed($header, $body, $footer, $viewport, $height, $this->outerWidth, $this->borderStyle());
  }

  /**
   * Compose a frame at an explicit width and border, else the same as a frame.
   *
   * The width/border are parameters so a modal can reuse the theme's boxing in
   * a narrower box; the standard frame passes its own outer width and border.
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
   * @param int $outer_width
   *   The outer width, including the border columns.
   * @param \DrevOps\Tui\Theme\Border $border
   *   The border style to draw.
   *
   * @return string
   *   The composed frame.
   */
  protected function renderBoxed(array $header, array $body, array $footer, Viewport $viewport, int $height, int $outer_width, Border $border): string {
    if ($border === Border::None) {
      return $this->renderBorderless($header, $body, $footer, $viewport, $height);
    }

    $chars = Box::chars($border, $this->unicode);
    $middle = $this->scrolledBody($body, $viewport, $height);
    $pad = $this->spacing() === Spacing::Padded;

    $out = [$this->borderRule($chars['tl'], $chars['tr'], $chars['h'], $outer_width)];

    foreach ($header as $line) {
      $out[] = $this->boxLine($line, $chars['v'], $outer_width);
    }

    $out[] = $this->borderRule($chars['ml'], $chars['mr'], $chars['h'], $outer_width);

    if ($pad) {
      $out[] = $this->boxLine('', $chars['v'], $outer_width);
    }

    foreach ($middle as $line) {
      $out[] = $this->boxLine($line, $chars['v'], $outer_width);
    }

    if ($pad) {
      $out[] = $this->boxLine('', $chars['v'], $outer_width);
    }

    if ($footer !== []) {
      $out[] = $this->borderRule($chars['ml'], $chars['mr'], $chars['h'], $outer_width);

      foreach ($footer as $line) {
        $out[] = $this->boxLine($line, $chars['v'], $outer_width);
      }
    }

    $out[] = $this->borderRule($chars['bl'], $chars['br'], $chars['h'], $outer_width);

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
    $lines = array_merge($header, $this->scrolledBody($body, $viewport, $height));

    if ($this->spacing() !== Spacing::Compact) {
      $lines[] = '';
    }

    return implode("\n", array_merge($lines, $footer));
  }

  /**
   * The visible body window, wrapped with the scroll indicators.
   *
   * @param list<string> $body
   *   The full body lines.
   * @param \DrevOps\Tui\Render\Viewport $viewport
   *   The computed viewport.
   * @param int $height
   *   The body viewport height.
   *
   * @return list<string>
   *   The visible lines, with an indicator line for each hidden side.
   */
  protected function scrolledBody(array $body, Viewport $viewport, int $height): array {
    $lines = [];

    if ($viewport->hasAbove) {
      $lines[] = $this->indicator('  ' . $this->indicatorUp());
    }

    $lines = array_merge($lines, (new Scroller())->slice($body, $viewport->offset, $height));

    if ($viewport->hasBelow) {
      $lines[] = $this->indicator('  ' . $this->indicatorDown());
    }

    return $lines;
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
   * @param int $outer_width
   *   The total width the rule spans.
   *
   * @return string
   *   The styled rule.
   */
  protected function borderRule(string $left, string $right, string $fill, int $outer_width): string {
    return $this->border(Box::rule($left, $right, $fill, $outer_width));
  }

  /**
   * Wrap a content line in vertical borders with a one-column gutter each side.
   *
   * @param string $content
   *   The content (may carry ANSI codes and be shorter than the inner width).
   * @param string $vertical
   *   The vertical border glyph.
   * @param int $outer_width
   *   The outer width the line is padded to, including the border columns.
   *
   * @return string
   *   The boxed line, padded to the outer width.
   */
  protected function boxLine(string $content, string $vertical, int $outer_width): string {
    $bar = $this->border($vertical);

    return $bar . ' ' . Box::fit($content, max(1, $outer_width - 4)) . ' ' . $bar;
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
      $lines[] = $this->footer(Translator::t('Version: @version', ['@version' => $version]));
    }

    return implode("\n", $lines);
  }

  /**
   * {@inheritdoc}
   */
  public function keyHint(Key $key): string {
    $name = $key->name;

    if (!$name instanceof KeyName) {
      return $key->label();
    }

    return match ($name) {
      KeyName::Up, KeyName::MouseWheelUp => $this->arrowUp(),
      KeyName::Down, KeyName::MouseWheelDown => $this->arrowDown(),
      KeyName::Left => $this->arrowLeft(),
      KeyName::Right => $this->arrowRight(),
      KeyName::Enter => $this->enter(),
      KeyName::Escape => Translator::t('esc'),
      KeyName::Interrupt => Translator::t('ctrl-c'),
      KeyName::Tab => Translator::t('tab'),
      KeyName::Space => Translator::t('space'),
      KeyName::Backspace => $this->unicode ? '⌫' : Translator::t('bksp'),
      KeyName::Delete => Translator::t('del'),
      KeyName::Home => Translator::t('home'),
      KeyName::End => Translator::t('end'),
      KeyName::PageUp => Translator::t('pgup'),
      KeyName::PageDown => Translator::t('pgdn'),
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
    $underline = str_repeat($this->unicode ? '─' : '-', max(1, mb_strlen($label, 'UTF-8')));

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

    if ($this->borderStyle() !== Border::None) {
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
    $lines = [$this->title(Translator::t('Keyboard help')), ''];

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
   * Compose a modal dialog: a centered box floating over the dimmed backdrop.
   *
   * The dialog's description text, its fields and its own submit/cancel buttons
   * are boxed in a narrower frame, then spliced centered over the backdrop so
   * the dimmed parent shows through the padding on every side.
   *
   * @param \DrevOps\Tui\Model\Panel $modal
   *   The modal panel (carrying its {@see \DrevOps\Tui\Model\Modal} config).
   * @param \DrevOps\Tui\Answers\Answers $answers
   *   The current answers.
   * @param int $cursor
   *   The selected item index within the dialog.
   * @param \DrevOps\Tui\Model\Field|null $editing
   *   The field whose editor is expanded inline in the dialog, or NULL.
   * @param string $editorView
   *   The inline editor's rendered view.
   * @param int $selectedButton
   *   The index of the selected dialog button, or -1 when none is selected.
   * @param string $backdrop
   *   The rendered parent frame to dim and overlay the dialog on.
   * @param int $height
   *   The screen's body height, bounding the dialog so its footer never clips.
   *
   * @return string
   *   The composited screen.
   */
  public function renderModal(Panel $modal, Answers $answers, int $cursor, ?Field $editing, string $editorView, int $selectedButton, string $backdrop, int $height): string {
    $config = $modal->modal;

    if (!$config instanceof Modal) {
      // @codeCoverageIgnoreStart
      return $backdrop;
      // @codeCoverageIgnoreEnd
    }

    [$fields, $field_cursor] = $this->renderBody($modal, $answers, $cursor, $editing, $editorView);

    $lead = [];
    if ($modal->description !== '') {
      foreach (explode("\n", Translator::t($modal->description)) as $line) {
        $lead[] = $this->label($line);
      }

      if ($fields !== []) {
        $lead[] = '';
      }
    }

    $body = array_merge($lead, $fields);

    // The buttons pin to a footer so a dialog taller than the terminal never
    // clips its only way out; the body scrolls under them to keep the cursor
    // in view.
    $footer = [
      $this->renderButtonBar([
        Translator::t($config->buttons->submitLabel),
        Translator::t($config->buttons->cancelLabel),
      ], $selectedButton),
    ];

    $inset = max(2, intdiv($this->outerWidth, 8));
    $modal_width = max(1, $this->outerWidth - 2 * $inset);
    $border = $this->borderStyle() === Border::None ? Border::Line : $this->borderStyle();

    // Fit the dialog within the screen height so the pinned button footer is
    // never clipped, reserving the box chrome (four rules, the title, the
    // footer and any spacing pad). Only the body scrolls; the footer stays put.
    $pad = $this->spacing() === Spacing::Padded ? 1 : 0;
    $room = max(0, $height - 6 - 2 * $pad);

    if (count($body) > $room && $room >= 3) {
      // The body overflows and there is room to scroll it under the footer.
      $cursor_line = $selectedButton >= 0 ? max(0, count($body) - 1) : count($lead) + $field_cursor;
      $body_height = $room - 2;
      $viewport = (new Scroller())->follow(count($body), $body_height, $cursor_line, 0);
    }
    else {
      // The body fits, or there is too little room to scroll: show what fits.
      $body = array_slice($body, 0, $room);
      $viewport = new Viewport(0, FALSE, FALSE);
      $body_height = count($body);
    }

    $box = explode("\n", $this->renderBoxed([$this->title(Translator::t($modal->title))], $body, $footer, $viewport, $body_height, $modal_width, $border));

    // Pad the backdrop so a short parent frame still gives the dialog room to
    // sit over, rather than shrinking it.
    $backdrop_lines = array_map(fn(string $line): string => Box::fit(Ansi::strip($line), $this->outerWidth), explode("\n", $backdrop));
    $area_height = max(count($backdrop_lines), count($box));

    while (count($backdrop_lines) < $area_height) {
      $backdrop_lines[] = str_repeat(' ', $this->outerWidth);
    }

    [$top, $left] = Overlay::center($this->outerWidth, $area_height, $modal_width, count($box));

    return implode("\n", Overlay::composite($backdrop_lines, $box, $modal_width, $top, $left, fn(string $segment): string => $this->dim($segment)));
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
   * @param \DrevOps\Tui\Model\Field $field
   *   The field the value belongs to.
   * @param mixed $value
   *   The value.
   *
   * @return string
   *   The rendered value.
   */
  protected function renderFieldValue(Field $field, mixed $value): string {
    if ($field->type === FieldType::Password) {
      return is_string($value) && $value !== '' ? str_repeat($this->mask(), SummaryFormatter::MASK_LENGTH) : '';
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
      return $value ? Translator::t('yes') : Translator::t('no');
    }

    if (is_array($value)) {
      return implode(', ', array_map(static fn(mixed $item): string => is_scalar($item) ? (string) $item : '', $value));
    }

    return is_scalar($value) ? (string) $value : '';
  }

}
