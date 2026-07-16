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
 *   php 3-widgets/widget-multiselect-groups.php
 *   php 3-widgets/widget-multiselect-groups.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-multiselect-groups.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);

$form = Form::create('MultiSelect with groups')
  ->panel('main', 'MultiSelect', function (PanelBuilder $p): void {
    $p->multiSelect('multiselect', 'MultiSelect')->default(['redis'])
      ->heading('Services')
      ->option('redis', 'Redis')
      ->option('solr', 'Solr')
      ->separator()
      ->option('clamav', 'ClamAV', disabled: TRUE, disabled_reason: 'licence required');
  });

echo (new Tui($form))->color(isset($opts['no-ansi']) ? FALSE : NULL)->unicode(isset($opts['no-unicode']) ? FALSE : NULL)->run()->toJson() . "\n";
