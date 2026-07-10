<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\ScopedKeyMap;

/**
 * A theme's look: one method per themeable element.
 *
 * Every method here is a single knob. The stylers take text and return it in
 * that element's colour (already resolved for the theme's dark/light mode); the
 * symbol methods return a glyph for the theme's Unicode mode. To restyle an
 * element, a theme overrides just that one method - see {@see DefaultTheme} for
 * the dark/light palette that ships with the library.
 *
 * @code
 * class OceanTheme extends DefaultTheme {
 *   public function title(string $text): string { return $this->paint('1;96', $text); }
 *   public function marker(bool $selected): string { return $selected ? '~ ' : '  '; }
 * }
 * @endcode
 *
 * The option constants (MODE_*, SPACING_*, BORDER_*) are the values a consumer
 * passes in the theme options array. How the styled pieces are arranged into
 * rows and frames is the render*() layer on {@see DefaultTheme}.
 *
 * @package DrevOps\Tui\Theme
 */
interface ThemeInterface {

  /**
   * Colour mode: bright foregrounds for a dark terminal background.
   */
  public const string MODE_DARK = 'dark';

  /**
   * Colour mode: darker foregrounds for a light terminal background.
   */
  public const string MODE_LIGHT = 'light';

  /**
   * Spacing option: labels and values only, no descriptions, no gaps.
   */
  public const string SPACING_COMPACT = 'compact';

  /**
   * Spacing option: descriptions under each item, no gaps (the default).
   */
  public const string SPACING_NORMAL = 'normal';

  /**
   * Spacing option: descriptions plus a blank line between items.
   */
  public const string SPACING_PADDED = 'padded';

  /**
   * Border option: no box (the default).
   */
  public const string BORDER_NONE = 'none';

  /**
   * Border option: a single-line box.
   */
  public const string BORDER_LINE = 'line';

  /**
   * Border option: a single-line box with rounded corners.
   */
  public const string BORDER_ROUNDED = 'rounded';

  /**
   * Border option: a double-line box.
   */
  public const string BORDER_DOUBLE = 'double';

  /**
   * A heading or an editor label.
   */
  public function title(string $text): string;

  /**
   * A field label; bold when its row is selected.
   */
  public function label(string $text, bool $selected = FALSE): string;

  /**
   * A field value; bold when its row is selected.
   */
  public function value(string $text, bool $selected = FALSE): string;

  /**
   * A help/description line; bold when its row is selected.
   */
  public function description(string $text, bool $selected = FALSE): string;

  /**
   * A provenance badge (e.g. "edited"); bold when its row is selected.
   */
  public function badge(string $text, bool $selected = FALSE): string;

  /**
   * The active (focused) button.
   */
  public function cursor(string $text): string;

  /**
   * A footer: the status and hint lines.
   */
  public function footer(string $text): string;

  /**
   * The navigator breadcrumb.
   */
  public function breadcrumb(string $text): string;

  /**
   * A scroll indicator (the up/down arrows).
   */
  public function indicator(string $text): string;

  /**
   * The highlighted (cursor) row in a list widget.
   */
  public function highlight(string $text): string;

  /**
   * A non-selectable group heading in an option list.
   */
  public function heading(string $text): string;

  /**
   * A non-selectable separator line between options in an option list.
   */
  public function divider(): string;

  /**
   * A disabled (non-selectable) option's label and reason, dimmed.
   */
  public function disabled(string $text): string;

  /**
   * A validation error message.
   */
  public function error(string $text): string;

  /**
   * The editor-header underline.
   */
  public function rule(string $text): string;

  /**
   * The frame box, when a border is on.
   */
  public function border(string $text): string;

  /**
   * The selection cursor for a row: the marker glyph when selected, else a gap.
   */
  public function marker(bool $selected): string;

  /**
   * The drill-in / breadcrumb arrow symbol.
   */
  public function arrow(): string;

  /**
   * The breadcrumb separator symbol.
   */
  public function separator(): string;

  /**
   * The "move up" key hint symbol.
   */
  public function arrowUp(): string;

  /**
   * The "move down" key hint symbol.
   */
  public function arrowDown(): string;

  /**
   * The "move left" key hint symbol.
   */
  public function arrowLeft(): string;

  /**
   * The "move right" key hint symbol.
   */
  public function arrowRight(): string;

  /**
   * The enter/accept key hint symbol.
   */
  public function enter(): string;

  /**
   * The dot that joins hint and summary fragments.
   */
  public function dot(): string;

  /**
   * The "more above" scroll-indicator symbol.
   */
  public function indicatorUp(): string;

  /**
   * The "more below" scroll-indicator symbol.
   */
  public function indicatorDown(): string;

  /**
   * A radio symbol: filled in the cursor colour when on, empty when off.
   */
  public function radio(bool $on): string;

  /**
   * A checkbox symbol: filled in the value colour when checked, empty when off.
   */
  public function check(bool $on): string;

  /**
   * The text-input caret, in the cursor colour.
   */
  public function caret(): string;

  /**
   * The masked-character symbol for secret values.
   */
  public function mask(): string;

  /**
   * Render a single key as its hint glyph (an arrow, a word or the character).
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to render.
   *
   * @return string
   *   The glyph, respecting the theme's Unicode mode.
   */
  public function keyHint(Key $key): string;

  /**
   * Render a hint fragment: the primary keys of one or more actions, labelled.
   *
   * The glyphs are drawn from the live bindings, so a hint never contradicts a
   * remapped key. An action with no bound key contributes nothing, and when no
   * action is bound the fragment is empty.
   *
   * @param \DrevOps\Tui\Input\ScopedKeyMap $keys
   *   The scope's bindings.
   * @param string $label
   *   The label describing what the keys do (e.g. "move", "accept").
   * @param \DrevOps\Tui\Input\Action ...$actions
   *   The actions whose primary keys lead the fragment.
   *
   * @return string
   *   The fragment (e.g. "↑/↓ move"), or an empty string when nothing is bound.
   */
  public function keysHint(ScopedKeyMap $keys, string $label, Action ...$actions): string;

}
