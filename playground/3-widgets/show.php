<?php

/**
 * @file
 * Renders every widget in both Unicode and textual (ASCII) glyph modes.
 *
 * Widgets pull their glyphs from the theme, so the same widget renders with
 * Unicode glyphs under a Unicode theme and ASCII glyphs under an ASCII theme -
 * exactly how the TUI adapts to the terminal (prompty-style: Unicode is
 * auto-detected from the locale, ASCII is the fallback). This showcase forces
 * each mode side by side so the difference is visible without a terminal.
 *
 * Usage:
 *   php 3-widgets/run.php
 */

declare(strict_types=1);

use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\ConfirmWidget;
use DrevOps\Tui\Widget\DateWidget;
use DrevOps\Tui\Widget\MultiSearchWidget;
use DrevOps\Tui\Widget\MultiSelectWidget;
use DrevOps\Tui\Widget\NumberWidget;
use DrevOps\Tui\Widget\PasswordWidget;
use DrevOps\Tui\Widget\PauseWidget;
use DrevOps\Tui\Widget\SearchWidget;
use DrevOps\Tui\Widget\SelectWidget;
use DrevOps\Tui\Widget\SuggestWidget;
use DrevOps\Tui\Widget\TextareaWidget;
use DrevOps\Tui\Widget\TextWidget;
use DrevOps\Tui\Widget\ToggleWidget;
use DrevOps\Tui\Widget\WidgetInterface;

require __DIR__ . '/../../vendor/autoload.php';

// Colour is disabled so the output is plain; only the glyph mode differs.
$unicode = new DefaultTheme(76, ['color' => FALSE]);
$ascii = new DefaultTheme(76, ['color' => FALSE, 'unicode' => FALSE]);

/**
 * The widgets to showcase, each built freshly (widgets are stateful).
 *
 * @var array<string,callable():\DrevOps\Tui\Widget\WidgetInterface>
 */
$widgets = [
  'Text' => static fn(): WidgetInterface => new TextWidget('Acme Site'),
  'Number' => static fn(): WidgetInterface => new NumberWidget('8080'),
  'Date' => static fn(): WidgetInterface => new DateWidget('2026-07-15'),
  'Textarea' => static fn(): WidgetInterface => new TextareaWidget("Redis for cache\nSolr for search"),
  'Password' => static fn(): WidgetInterface => new PasswordWidget('hunter2'),
  'Select' => static fn(): WidgetInterface => new SelectWidget([
    'standard' => 'Standard',
    'minimal' => 'Minimal',
    'demo_umami' => 'Demo Umami',
  ], 'minimal'),
  'MultiSelect' => static fn(): WidgetInterface => new MultiSelectWidget([
    'redis' => 'Redis',
    'solr' => 'Solr',
    'clamav' => 'ClamAV',
  ], ['redis', 'solr']),
  'Suggest' => static fn(): WidgetInterface => new SuggestWidget([
    'UTC',
    'Europe/London',
    'Europe/Paris',
    'Australia/Sydney',
  ], 'Europe/'),
  'Search' => static fn(): WidgetInterface => new SearchWidget([
    'utc' => 'UTC',
    'london' => 'Europe/London',
    'paris' => 'Europe/Paris',
    'sydney' => 'Australia/Sydney',
  ], 'london'),
  'MultiSearch' => static fn(): WidgetInterface => new MultiSearchWidget([
    'redis' => 'Redis',
    'solr' => 'Solr',
    'clamav' => 'ClamAV',
    'memcached' => 'Memcached',
  ], ['redis']),
  'Confirm' => static fn(): WidgetInterface => new ConfirmWidget(TRUE),
  'Toggle' => static fn(): WidgetInterface => new ToggleWidget([
    'enabled' => 'Enabled',
    'disabled' => 'Disabled',
  ], 'enabled'),
  'Pause' => static fn(): WidgetInterface => new PauseWidget(),
];

$columns = static function (string $left, string $right, int $gap = 8): string {
  $left_lines = explode("\n", $left);
  $right_lines = explode("\n", $right);
  $width = 0;
  foreach ($left_lines as $line) {
    $width = max($width, mb_strlen($line));
  }

  $rows = [];
  for ($i = 0, $count = max(count($left_lines), count($right_lines)); $i < $count; $i++) {
    $line = $left_lines[$i] ?? '';
    $pad = str_repeat(' ', max(0, $width - mb_strlen($line) + $gap));
    $rows[] = '    ' . $line . $pad . ($right_lines[$i] ?? '');
  }

  return implode("\n", $rows);
};

echo "\n";
echo $columns('UNICODE', 'TEXTUAL (ASCII)') . "\n";
echo str_repeat('-', 60) . "\n\n";

foreach ($widgets as $name => $make) {
  echo $name . "\n";
  echo $columns($make()->view($unicode), $make()->view($ascii)) . "\n\n";
}
