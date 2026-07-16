<?php

/**
 * @file
 * Calendar field on a form, collected through the Tui facade.
 *
 * Arrows move by day/week, PgUp/PgDn change month - the panel TUI drives the
 * calendar widget, instead of invoking the widget directly.
 *
 * Usage:
 *   php 3-widgets/widget-calendar.php
 *   php 3-widgets/widget-calendar.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-calendar.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);

$form = Form::create('Calendar widget')
  ->panel('main', 'Calendar', function (PanelBuilder $p): void {
    $p->calendar('date', 'Calendar')->default('2026-07-15');
  });

try {
  echo (new Tui($form))->color(isset($opts['no-ansi']) ? FALSE : NULL)->unicode(isset($opts['no-unicode']) ? FALSE : NULL)->run()->toJson() . "\n";
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
