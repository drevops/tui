<?php

/**
 * @file
 * Determinate progress bar: step-by-step feedback for work of known length.
 *
 * When the number of steps is known up front, progress() shows a filling bar
 * with a count and a label instead of an open-ended spinner. Each advance()
 * moves the bar one step and can replace the trailing label; the callback's
 * result is passed straight back. Off a TTY (piped or redirected) the bar
 * degrades to a single plain caption line and emits no control sequences.
 * Colour and Unicode follow the facade's own switches.
 *
 * Usage:
 *   php playground/15-feedback-progress.php
 *
 *   # Off a TTY it degrades to one plain line:
 *   php playground/15-feedback-progress.php 2>&1 | cat
 *
 *   # ASCII cells when the locale is not UTF-8:
 *   LC_ALL=C php playground/15-feedback-progress.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Feedback\ProgressBar;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Produce order')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    $p->text('name', 'Order name')->default('Weekly Box');
  });

$tui = new Tui($form);

$items = ['Apple', 'Carrot', 'Tomato', 'Spinach', 'Pear', 'Beet'];

// The bar knows its total up front; each advance() fills one step and updates
// the label shown after it.
$packed = $tui->progress(count($items), 'Packing the order', function (ProgressBar $bar) use ($items): array {
  $done = [];

  foreach ($items as $item) {
    usleep(220000);
    $done[] = $item;
    $bar->advance('packed ' . $item);
  }

  return $done;
});

echo 'Packed ' . count($packed) . ' items into the box.' . PHP_EOL;
