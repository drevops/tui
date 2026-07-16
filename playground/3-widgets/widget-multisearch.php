<?php

/**
 * @file
 * Multisearch field on a form, collected through the Tui facade.
 *
 * Type to filter, Space toggles, Enter accepts - the panel TUI drives the
 * multisearch widget, instead of invoking the widget directly.
 *
 * Usage:
 *   php 3-widgets/widget-multisearch.php
 *   php 3-widgets/widget-multisearch.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-multisearch.php --no-ansi      # no colour.
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

$form = Form::create('MultiSearch widget')
  ->panel('main', 'MultiSearch', function (PanelBuilder $p): void {
    $p->search('multisearch', 'MultiSearch')->multiple()->default(['apple'])->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
    ]);
  });

try {
  echo (new Tui($form))->color(isset($opts['no-ansi']) ? FALSE : NULL)->unicode(isset($opts['no-unicode']) ? FALSE : NULL)->run()->toJson() . "\n";
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
