<?php

/**
 * @file
 * Select widget with ->multiple(): any number of checked options.
 *
 * Space toggles the highlighted option, typing narrows the list by substring,
 * Right/Left check or clear everything visible, Enter accepts. The field
 * collects a list of the checked option values.
 *
 * Usage:
 *   php playground/02-widgets/select-multiple.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the widget.
$form = Form::create('MultiSelect widget')
  ->panel('main', 'MultiSelect', function (PanelBuilder $p): void {
    // The default pre-checks values, so it is a list here.
    $p->select('basket', 'Basket')->multiple()->default(['apple'])->options([
      'apple' => 'Apple',
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
    ]);
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
