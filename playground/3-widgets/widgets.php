<?php

/**
 * @file
 * Runs every widget interactively, one after another.
 *
 * Usage:
 *   php 3-widgets/widgets.php
 *   php 3-widgets/widgets.php --no-unicode   # textual glyphs
 *   php 3-widgets/widgets.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Input\KeyParser;
use DrevOps\Tui\Render\Terminal;
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

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);
$theme = new DefaultTheme(76, ['color' => !isset($opts['no-ansi']), 'unicode' => !isset($opts['no-unicode'])]);

// Drive a widget to completion against the real terminal, then print the
// value; the single-widget examples inline this same loop.
$interact = static function (WidgetInterface $widget, string $label, array $hints = []) use ($theme): void {
  $hints = $hints === [] ? ['edit', 'Enter accept', 'Esc cancel'] : $hints;
  $terminal = new Terminal();
  $parser = new KeyParser();
  $terminal->setup();

  try {
    while (!$widget->isComplete() && !$widget->isCancelled()) {
      $lines = [$theme->renderEditorHeader($label . ' widget')];
      $lines[] = $theme->renderHintLine(...$hints);
      $lines[] = '';
      $lines[] = $widget->view($theme);
      $terminal->render(implode("\n", $lines));

      foreach ($parser->parse($terminal->read()) as $key) {
        $widget->handle($key);
      }
    }
  }
  finally {
    $terminal->restore();
  }

  echo $label . ': ' . ($widget->isCancelled() ? '(cancelled)' : (string) json_encode($widget->value())) . PHP_EOL;
};

$interact(new TextWidget('Acme Site'), 'Text');
$interact(new NumberWidget('8080'), 'Number');
$interact(new DateWidget('2026-07-15'), 'Date');
$interact(new TextareaWidget("Redis for cache\nSolr for search"), 'Textarea', ['edit', 'Tab accept', 'Esc cancel']);
$interact(new PasswordWidget('hunter2'), 'Password');
$interact(new SelectWidget(['standard' => 'Standard', 'minimal' => 'Minimal', 'demo_umami' => 'Demo Umami'], 'minimal'), 'Select');
$interact(new MultiSelectWidget(['redis' => 'Redis', 'solr' => 'Solr', 'clamav' => 'ClamAV'], ['redis']), 'MultiSelect');
$interact(new SuggestWidget(['UTC', 'Europe/London', 'Europe/Paris', 'Australia/Sydney'], ''), 'Suggest');
$interact(new SearchWidget([
  'utc' => 'UTC',
  'london' => 'Europe/London',
  'paris' => 'Europe/Paris',
  'sydney' => 'Australia/Sydney',
], 'london'), 'Search');
$interact(new MultiSearchWidget(['redis' => 'Redis', 'solr' => 'Solr', 'clamav' => 'ClamAV', 'memcached' => 'Memcached'], ['redis']), 'MultiSearch');
$interact(new ConfirmWidget(TRUE), 'Confirm');
$interact(new ToggleWidget(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'enabled'), 'Toggle');
$interact(new PauseWidget(), 'Pause', ['Enter continue', 'Esc cancel']);
