<?php

/**
 * @file
 * Indeterminate spinner: visible feedback while slow work runs.
 *
 * Some work has no known length - counting what a directory holds, resolving a
 * value against external state - and while it runs the form can look frozen.
 * spinner() wraps that work: it animates a glyph beside a caption while the
 * callback runs and passes the callback's result straight back. The callback
 * ticks the animation forward as it goes; one that never ticks just shows a
 * steady first frame. Off a TTY (piped or redirected) the spinner degrades to a
 * single plain caption line and emits no control sequences. Colour and Unicode
 * follow the facade's own switches.
 *
 * Usage:
 *   php playground/15-feedback-spinner.php
 *
 *   # Off a TTY it degrades to one plain line:
 *   php playground/15-feedback-spinner.php 2>&1 | cat
 *
 *   # ASCII frames when the locale is not UTF-8:
 *   LC_ALL=C php playground/15-feedback-spinner.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Feedback\Spinner;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Produce order')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    $p->text('name', 'Order name')->default('Weekly Box');
  });

$tui = new Tui($form);

// The spinner wraps the slow work and returns whatever the callback returns.
// Each tick() advances the animation by one frame.
$baskets = $tui->spinner('Counting the baskets', function (Spinner $spinner): array {
  $counted = [];

  foreach (['Apple', 'Pear', 'Plum', 'Cherry', 'Apricot', 'Peach'] as $fruit) {
    usleep(180000);
    $counted[] = $fruit;
    $spinner->tick();
  }

  return $counted;
});

echo 'Counted ' . count($baskets) . ' baskets: ' . implode(', ', $baskets) . '.' . PHP_EOL;
