<?php

/**
 * @file
 * Reorder field on a form, collected through the Tui facade.
 *
 * Up/Down move, Space grabs the highlighted item, Enter accepts - the panel
 * TUI drives the reorder widget, instead of invoking the widget directly.
 *
 * Usage:
 *   php 3-widgets/widget-reorder.php
 *   php 3-widgets/widget-reorder.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-reorder.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);

$form = Form::create('Reorder widget')
  ->color(isset($opts['no-ansi']) ? FALSE : NULL)
  ->unicode(isset($opts['no-unicode']) ? FALSE : NULL)
  ->panel('main', 'Reorder', function (PanelBuilder $p): void {
    $p->reorder('reorder', 'Reorder')->options([
      'redis' => 'Redis',
      'solr' => 'Solr',
      'clamav' => 'ClamAV',
    ]);
  });

echo (new Tui($form))->run()->toJson() . "\n";
