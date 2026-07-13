<?php

/**
 * @file
 * Password field with the reveal toggle, collected through the Tui facade.
 *
 * Tab cycles the display between hidden, masked and plaintext; the accepted
 * value stays plain. Declaring `->revealable()` turns the toggle on - the panel
 * TUI drives the password widget, instead of invoking the widget directly.
 *
 * Usage:
 *   php 3-widgets/widget-password-reveal.php
 *   php 3-widgets/widget-password-reveal.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-password-reveal.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);

$form = Form::create('Password widget')
  ->color(isset($opts['no-ansi']) ? FALSE : NULL)
  ->unicode(isset($opts['no-unicode']) ? FALSE : NULL)
  ->panel('main', 'Password', function (PanelBuilder $p): void {
    $p->password('password', 'Password')->default('hunter2')->revealable();
  });

echo (new Tui($form))->run()->toJson() . PHP_EOL;
