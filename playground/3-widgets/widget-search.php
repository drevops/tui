<?php

/**
 * @file
 * Search field on a form, collected through the Tui facade.
 *
 * Type to filter, Up/Down move, Enter accepts - the panel TUI drives the search
 * widget, instead of invoking the widget directly.
 *
 * Usage:
 *   php 3-widgets/widget-search.php
 *   php 3-widgets/widget-search.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-search.php --no-ansi      # no colour.
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

$form = Form::create('Search widget')
  ->panel('main', 'Search', function (PanelBuilder $p): void {
    $p->search('search', 'Search')->default('carrot')->options([
      'carrot' => 'Carrot',
      'potato' => 'Potato',
      'onion' => 'Onion',
      'pepper' => 'Pepper',
    ]);
  });

try {
  echo (new Tui($form))->color(isset($opts['no-ansi']) ? FALSE : NULL)->unicode(isset($opts['no-unicode']) ? FALSE : NULL)->run()->toJson() . "\n";
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
