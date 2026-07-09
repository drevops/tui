<?php

declare(strict_types=1);

namespace Playground\CustomTheme;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\Panel;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Render\Navigator;

/**
 * A custom theme that overrides as much as it sensibly can.
 *
 * It demonstrates three kinds of override, all shown below:
 *  - the constructor - to change the defaults (here, a narrower 72-col frame);
 *  - defineStyles() and defineGlyphs() - override the roles and glyph pairs
 *    that differ, and merge the rest with `+ parent::defineStyles()`;
 *  - any render*() and summarizePanel() method - to change how an element is
 *    laid out.
 *
 * It extends DefaultTheme, so anything left un-overridden (e.g. renderBody(),
 * renderFrame()) falls back to the default theme, including its dark/light
 * mode. Extend AbstractTheme instead to start from the neutral base. Select it
 * from a config with `theme: '\Playground\CustomTheme\OceanTheme'`, or register
 * a short name with ThemeManager::register('ocean', OceanTheme::class).
 */
class OceanTheme extends DefaultTheme {

  /**
   * Override the constructor to default to a narrower 72-column frame.
   *
   * @param int $width
   *   The frame width.
   * @param array<string,mixed> $options
   *   The theme display options.
   */
  public function __construct(int $width = 72, array $options = []) {
    parent::__construct($width, $options);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function defineStyles(): array {
    return [
      'title' => '1;96',
      'breadcrumb' => '36',
      'value' => '96',
      'description' => '34',
      'marker' => '1;96',
      'badge' => '7;36',
      'cursor' => '1;7;96',
      'footer' => '36',
      'indicator' => '1;96',
      'highlight' => '1;96',
    ] + parent::defineStyles();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function defineGlyphs(): array {
    return [
      'marker' => ['➤', '>'],
      'indicator_up' => ['▴', '^'],
      'indicator_down' => ['▾', 'v'],
      'separator' => ['/', '/'],
      'arrow' => ['»', '>'],
      'enter' => ['⏎', '<'],
      'dot' => ['•', '*'],
      'radio_on' => ['◉', '(o)'],
      'radio_off' => ['◯', '( )'],
      'check_on' => ['▣', '[x]'],
      'check_off' => ['▢', '[ ]'],
      'caret' => ['▎', '|'],
    ] + parent::defineGlyphs();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderFieldLine(Field $field, Answers $answers, bool $selected): string {
    return $this->marker($selected) . ' ' . $this->style('label', $field->label) . ': ' . $this->style('value', $this->renderValue($answers->value($field->id)));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderPanelLine(Panel $panel, bool $selected): string {
    $count = count($panel->fields) + count($panel->panels);

    return $this->marker($selected) . ' ' . $this->style('title', $panel->title) . '  ' . $this->style('description', $this->glyph('arrow') . ' ' . $count . ' item' . ($count === 1 ? '' : 's'));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderDescriptionLine(string $description, bool $selected): string {
    return '    ' . $this->styleSelected('description', $this->glyph('dot') . ' ' . $description, $selected);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function summarizePanel(Panel $panel, Answers $answers): string {
    $parts = [];
    foreach ($panel->fields as $field) {
      if ($answers->has($field->id)) {
        $parts[] = $this->renderValue($answers->value($field->id));
      }
    }

    return implode(' ' . $this->glyph('separator') . ' ', array_slice($parts, 0, 3));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderSummaryLine(string $summary, bool $selected): string {
    return '    ' . $this->styleSelected('description', $this->glyph('arrow') . ' ' . $summary, $selected);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderBreadcrumbLine(Navigator $navigator): string {
    return $this->style('breadcrumb', '≈ ' . implode(' ' . $this->glyph('separator') . ' ', $navigator->breadcrumb()));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderStatusLine(): string {
    $sep = '  ' . $this->glyph('dot') . '  ';

    return $this->style('footer', $this->glyph('arrow_up') . $this->glyph('arrow_down') . ' move' . $sep . $this->glyph('enter') . ' choose' . $sep . 'esc back');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderButtonBar(array $labels, int $selected): string {
    $buttons = [];
    foreach ($labels as $index => $label) {
      $text = '« ' . $label . ' »';
      $buttons[] = $index === $selected ? $this->style('cursor', $text) : $this->style('label', $text);
    }

    return '  ' . implode('   ', $buttons);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderBanner(string $logo, string $version): string {
    $lines = [];
    foreach (explode("\n", $logo) as $line) {
      $lines[] = $this->style('title', $line);
    }

    if ($version !== '') {
      $lines[] = '';
      $lines[] = $this->style('footer', '≈ ' . $version . ' ≈');
    }

    return implode("\n", $lines);
  }

}
