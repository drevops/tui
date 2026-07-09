<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\Panel;
use DrevOps\Tui\Render\Navigator;
use DrevOps\Tui\Render\Viewport;

/**
 * A theme's rendering contract: the elements a theme composes, and how.
 *
 * Every method here renders one part of the TUI - a field row, a description, a
 * sub-panel summary, the frame, the editor, the buttons. That is the whole
 * extension surface: to change how any element looks, a theme overrides just
 * that one method (see {@see AbstractTheme}, which implements the lot). The
 * option constants below (MODE_*, SPACING_*, BORDER_*) are the values a consumer
 * passes in the theme options array.
 *
 * The low-level helpers a render method uses - style() to colour text by role,
 * glyph() to fetch a named symbol - are not part of this contract; they live on
 * {@see AbstractTheme}.
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
   * The number of navigable items in a panel (fields plus sub-panels).
   *
   * @param \DrevOps\Tui\Config\Panel $panel
   *   The panel.
   *
   * @return int
   *   The item count.
   */
  public function itemCount(Panel $panel): int;

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
  public function renderBody(Panel $panel, Answers $answers, int $cursor): array;

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
  public function renderFieldLine(Field $field, Answers $answers, bool $selected): string;

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
  public function renderPanelLine(Panel $panel, bool $selected): string;

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
  public function renderDescriptionLine(string $description, bool $selected): string;

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
  public function summarizePanel(Panel $panel, Answers $answers): string;

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
  public function renderSummaryLine(string $summary, bool $selected): string;

  /**
   * Render a breadcrumb line for the navigator.
   *
   * @param \DrevOps\Tui\Render\Navigator $navigator
   *   The navigator.
   *
   * @return string
   *   The breadcrumb line.
   */
  public function renderBreadcrumbLine(Navigator $navigator): string;

  /**
   * Compose a frame: pinned header, scrolled body with indicators, footer.
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
  public function renderFrame(array $header, array $body, array $footer, Viewport $viewport, int $height): string;

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
  public function renderBanner(string $logo, string $version): string;

  /**
   * Render the status line shown at the foot of a panel.
   *
   * @return string
   *   The themed status line.
   */
  public function renderStatusLine(): string;

  /**
   * Render a dimmed line of key hints, joined with the dot glyph.
   *
   * @param string ...$hints
   *   The hint fragments (e.g. "enter accept", "esc cancel").
   *
   * @return string
   *   The themed hint line.
   */
  public function renderHintLine(string ...$hints): string;

  /**
   * Render the header shown above a field's editor: its label, underlined.
   *
   * @param string $label
   *   The field label.
   *
   * @return string
   *   The two-line themed header.
   */
  public function renderEditorHeader(string $label): string;

  /**
   * Compose a field's editor screen: the label, the widget view and hints.
   *
   * @param string $label
   *   The field label.
   * @param string $view
   *   The widget's rendered view.
   *
   * @return string
   *   The editor screen - boxed when the theme has a border, else plain.
   */
  public function renderEditor(string $label, string $view): string;

  /**
   * Render a row of inline submit/cancel buttons.
   *
   * @param list<string> $labels
   *   The button labels.
   * @param int $selected
   *   The selected button index, or -1 for none.
   *
   * @return string
   *   The button row.
   */
  public function renderButtonBar(array $labels, int $selected): string;

}
