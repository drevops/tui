<?php

/**
 * @file
 * Suggest field on a form, collected through the Tui facade.
 *
 * Type to filter, Up/Down move, Enter accepts - the panel TUI drives the
 * suggest widget, instead of invoking the widget directly.
 *
 * Usage:
 *   php 3-widgets/widget-suggest.php
 *   php 3-widgets/widget-suggest.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-suggest.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);

$form = Form::create('Suggest widget')
  ->color(isset($opts['no-ansi']) ? FALSE : NULL)
  ->unicode(isset($opts['no-unicode']) ? FALSE : NULL)
  ->panel('main', 'Suggest', function (PanelBuilder $p): void {
    $p->suggest('suggest', 'Suggest')->options([
      'UTC' => 'UTC',
      'Europe/London' => 'Europe/London',
      'Europe/Paris' => 'Europe/Paris',
      'Australia/Sydney' => 'Australia/Sydney',
    ]);
  });

echo (new Tui($form))->run()->toJson() . "\n";
