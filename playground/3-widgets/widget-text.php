<?php

/**
 * @file
 * Text field on a form, collected through the Tui facade.
 *
 * Type to edit, arrows move the caret, Enter accepts - the panel TUI drives the
 * text widget, instead of invoking the widget directly.
 *
 * Usage:
 *   php 3-widgets/widget-text.php
 *   php 3-widgets/widget-text.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-text.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);

$form = Form::create('Text widget')
  ->panel('main', 'Text', function (PanelBuilder $p): void {
    $p->text('text', 'Text')->default('Acme Site');
  });

echo (new Tui($form))->color(isset($opts['no-ansi']) ? FALSE : NULL)->unicode(isset($opts['no-unicode']) ? FALSE : NULL)->run()->toJson() . "\n";
