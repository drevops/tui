<?php

/**
 * @file
 * Select field with a group heading, a separator and a disabled option.
 *
 * Non-selectable rows are visual only: the cursor skips the heading, the
 * separator and the disabled option, which shows its reason beside the label.
 * The form declares them with `->heading()`, `->separator()` and
 * `->option(disabled: TRUE)`, instead of invoking the widget directly.
 *
 * Usage:
 *   php 3-widgets/widget-select-groups.php
 *   php 3-widgets/widget-select-groups.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-select-groups.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);

$form = Form::create('Select with groups')
  ->color(isset($opts['no-ansi']) ? FALSE : NULL)
  ->unicode(isset($opts['no-unicode']) ? FALSE : NULL)
  ->panel('main', 'Select', function (PanelBuilder $p): void {
    $p->select('select', 'Select')->default('standard')
      ->heading('Recommended')
      ->option('standard', 'Standard')
      ->option('minimal', 'Minimal')
      ->separator()
      ->option('demo_umami', 'Demo Umami', disabled: TRUE, disabled_reason: 'requires PHP 8.4');
  });

echo (new Tui($form))->run()->toJson() . "\n";
