<?php

/**
 * @file
 * Multiselect with a group heading, a separator and a disabled option.
 *
 * Non-selectable rows are visual only: Space and the cursor skip the heading,
 * the separator and the disabled option, which shows its reason beside the
 * label and can never be checked. The form declares them with `->heading()`,
 * `->separator()` and `->option(disabled: TRUE)`, instead of invoking the
 * widget directly.
 *
 * Usage:
 *   php 3-widgets/widget-select-multiple-groups.php
 *   php 3-widgets/widget-select-multiple-groups.php --no-unicode # ASCII glyphs
 *   php 3-widgets/widget-select-multiple-groups.php --no-ansi    # no colour.
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

$form = Form::create('MultiSelect with groups')
  ->panel('main', 'MultiSelect', function (PanelBuilder $p): void {
    $p->select('multiselect', 'MultiSelect')->multiple()->default(['apple'])
      ->heading('Basket')
      ->option('apple', 'Apple')
      ->option('banana', 'Banana')
      ->separator()
      ->option('rhubarb', 'Rhubarb', disabled: TRUE, disabled_reason: 'out of season');
  });

try {
  echo (new Tui($form))->color(isset($opts['no-ansi']) ? FALSE : NULL)->unicode(isset($opts['no-unicode']) ? FALSE : NULL)->run()->toJson() . "\n";
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
