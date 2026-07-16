<?php

/**
 * @file
 * Fullscreen: the panel browser stretched to the whole terminal screen.
 *
 * Fullscreen is a facade switch (->fullscreen()); where the fields sit inside
 * the stretched frame is a pair of theme options set as plain strings -
 * 'halign' ('left', 'center' or 'right') and 'valign' ('top', 'middle' or
 * 'bottom') - alongside the border. A 'max_width' cap keeps the frame
 * readable on very wide terminals, and below 'min_width' / 'min_height' (the
 * width is measured from the form's own content unless set) the TUI shows a
 * resize notice instead of a broken layout.
 *
 * Usage:
 *   php playground/03-panels/fullscreen.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$form = Form::create('Market stall')
  ->buttons(TRUE, 'Place order', 'Cancel')
  ->panel('order', 'Order', function (PanelBuilder $p): void {
    $p->description('What goes in the box.');
    $p->text('name', 'Order name')->default('Weekly Box')->required();
    $p->select('fruit', 'Fruit')->default('apple')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    $p->number('quantity', 'Quantity')->default(6)->min(1)->max(99);
    $p->confirm('organic', 'Organic only?')->default(TRUE);
  });

try {
  // A centered frame in a rounded border is the documentation demo; swap the
  // alignments for 'left'/'right' and 'top'/'bottom', or add 'max_width' to
  // float a capped frame like a dialog.
  $answers = (new Tui($form))
    ->theme('default', ['border' => 'rounded', 'halign' => 'center', 'valign' => 'middle'])
    ->fullscreen()
    ->run();
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// The summary groups the answers by panel with provenance badges.
echo $answers->toSummary() . PHP_EOL;
