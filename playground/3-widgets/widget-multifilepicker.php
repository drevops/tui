<?php

/**
 * @file
 * Multi file picker field on a form, collected through the Tui facade.
 *
 * Space toggles, arrows move, Enter accepts. The form points the picker at a
 * small fixture tree with `->startIn()` and lets several entries be chosen,
 * instead of invoking the widget directly.
 *
 * Usage:
 *   php 3-widgets/widget-multifilepicker.php
 *   php 3-widgets/widget-multifilepicker.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-multifilepicker.php --no-ansi      # no colour.
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

$form = Form::create('Multi file picker widget')
  ->panel('main', 'Multi file picker', function (PanelBuilder $p): void {
    $p->filePicker('files', 'Multi file picker')->multiple()->startIn(__DIR__ . '/filepicker-tree');
  });

try {
  echo (new Tui($form))->color(isset($opts['no-ansi']) ? FALSE : NULL)->unicode(isset($opts['no-unicode']) ? FALSE : NULL)->run()->toJson() . "\n";
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
