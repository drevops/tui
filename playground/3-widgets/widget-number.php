<?php

/**
 * @file
 * Number field on a form, collected through the Tui facade.
 *
 * Digits (and a leading minus) edit the value, Enter accepts - the panel TUI
 * drives the number widget, instead of invoking the widget directly.
 *
 * Usage:
 *   php 3-widgets/widget-number.php
 *   php 3-widgets/widget-number.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-number.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);

$form = Form::create('Number widget')
  ->panel('main', 'Number', function (PanelBuilder $p): void {
    $p->number('number', 'Number')->default(8080);
  });

echo (new Tui($form))->color(isset($opts['no-ansi']) ? FALSE : NULL)->unicode(isset($opts['no-unicode']) ? FALSE : NULL)->run()->toJson() . "\n";
