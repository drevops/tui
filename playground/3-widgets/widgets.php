<?php

/**
 * @file
 * Every core widget as fields on one form, collected through the Tui facade.
 *
 * The single-widget examples each declare a one-field form; this gathers them
 * into one multi-field form and drives it once through the panel TUI, instead
 * of invoking each widget directly.
 *
 * Usage:
 *   php 3-widgets/widgets.php
 *   php 3-widgets/widgets.php --no-unicode   # textual glyphs
 *   php 3-widgets/widgets.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);

$form = Form::create('Widgets')
  ->color(isset($opts['no-ansi']) ? FALSE : NULL)
  ->unicode(isset($opts['no-unicode']) ? FALSE : NULL)
  ->panel('widgets', 'Widgets', function (PanelBuilder $p): void {
    $p->text('text', 'Text')->default('Acme Site');
    $p->number('number', 'Number')->default(8080);
    $p->date('date', 'Date')->default('2026-07-15');
    $p->textarea('textarea', 'Textarea')->default("Redis for cache\nSolr for search");
    $p->password('password', 'Password')->default('hunter2');
    $p->select('select', 'Select')->default('minimal')->options([
      'standard' => 'Standard',
      'minimal' => 'Minimal',
      'demo_umami' => 'Demo Umami',
    ]);
    $p->multiselect('multiselect', 'MultiSelect')->default(['redis'])->options([
      'redis' => 'Redis',
      'solr' => 'Solr',
      'clamav' => 'ClamAV',
    ]);
    $p->suggest('suggest', 'Suggest')->options([
      'UTC' => 'UTC',
      'Europe/London' => 'Europe/London',
      'Europe/Paris' => 'Europe/Paris',
      'Australia/Sydney' => 'Australia/Sydney',
    ]);
    $p->search('search', 'Search')->default('london')->options([
      'utc' => 'UTC',
      'london' => 'Europe/London',
      'paris' => 'Europe/Paris',
      'sydney' => 'Australia/Sydney',
    ]);
    $p->multisearch('multisearch', 'MultiSearch')->default(['redis'])->options([
      'redis' => 'Redis',
      'solr' => 'Solr',
      'clamav' => 'ClamAV',
      'memcached' => 'Memcached',
    ]);
    $p->confirm('confirm', 'Confirm')->default(TRUE);
    $p->toggle('toggle', 'Toggle')->default('enabled')->options([
      'enabled' => 'Enabled',
      'disabled' => 'Disabled',
    ]);
    $p->pause('pause', 'Pause');
  });

echo (new Tui($form))->run()->toSummary() . PHP_EOL;
