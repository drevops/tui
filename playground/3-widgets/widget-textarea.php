<?php

/**
 * @file
 * Textarea field on a form, collected through the Tui facade.
 *
 * Enter inserts a newline, Tab accepts. With `->externalEditor()` declared and
 * $EDITOR (or $VISUAL) available, Ctrl-E hands off to that editor for the value
 * - the panel TUI drives the textarea widget and the handoff, instead of
 * invoking the widget directly.
 *
 * Usage:
 *   php 3-widgets/widget-textarea.php
 *   php 3-widgets/widget-textarea.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-textarea.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);

$form = Form::create('Textarea widget')
  ->panel('main', 'Textarea', function (PanelBuilder $p): void {
    $p->textarea('textarea', 'Textarea')->default("Crisp and sweet\nHint of citrus")->externalEditor();
  });

echo (new Tui($form))->color(isset($opts['no-ansi']) ? FALSE : NULL)->unicode(isset($opts['no-unicode']) ? FALSE : NULL)->run()->toJson() . "\n";
