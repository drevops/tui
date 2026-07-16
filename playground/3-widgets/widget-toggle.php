<?php

/**
 * @file
 * Toggle field on a form, collected through the Tui facade.
 *
 * Left/Right or Space flip, first letter selects - the panel TUI drives the
 * toggle widget, instead of invoking the widget directly.
 *
 * Usage:
 *   php 3-widgets/widget-toggle.php
 *   php 3-widgets/widget-toggle.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-toggle.php --no-ansi      # no colour.
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

$form = Form::create('Toggle widget')
  ->panel('main', 'Toggle', function (PanelBuilder $p): void {
    $p->toggle('toggle', 'Toggle')->default('ripe')->options([
      'ripe' => 'Ripe',
      'unripe' => 'Unripe',
    ]);
  });

try {
  echo (new Tui($form))->color(isset($opts['no-ansi']) ? FALSE : NULL)->unicode(isset($opts['no-unicode']) ? FALSE : NULL)->run()->toJson() . "\n";
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
