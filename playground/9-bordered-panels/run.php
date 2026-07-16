<?php

/**
 * @file
 * Bordered panels: the panel browser wrapped in a border frame.
 *
 * The border is a theme option ('none', 'line', 'rounded' or 'double') set as a
 * plain string alongside the spacing - no imports. The default theme draws the
 * whole frame (hub, breadcrumb header, fields and the key-hint footer) inside
 * the chosen border, and every drill-in sub-panel keeps it. The --border and
 * --spacing flags pick the styles, defaulting to a rounded, padded frame.
 *
 * Usage:
 *   php 9-bordered-panels/run.php                          # interactive TUI
 *   php 9-bordered-panels/run.php --border=double --spacing=normal
 *   php 9-bordered-panels/run.php --prompts='{"name":"weekly"}'
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$options = getopt('', ['prompts::', 'border::', 'spacing::']);
$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';
$border = array_key_exists('border', $options) && is_string($options['border']) ? $options['border'] : 'rounded';
$spacing = array_key_exists('spacing', $options) && is_string($options['spacing']) ? $options['spacing'] : 'padded';

$form = Form::create('Fruit basket')
  ->buttons(TRUE, 'Create', 'Cancel')
  ->panel('basics', 'Basics', function (PanelBuilder $p): void {
    $p->description('What the basket holds.');
    $p->text('name', 'Basket name')->default('weekly')->required();
    $p->select('fruit', 'Fruit')->default('apple')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    $p->number('quantity', 'Quantity')->default(6)->min(1)->max(99);
  })
  ->panel('deploy', 'Delivery', function (PanelBuilder $p): void {
    $p->description('How it arrives.');
    $p->select('method', 'Method')->default('pickup')->option('pickup', 'Pickup', 'At the stall')->option('doorstep', 'Doorstep', 'To your door');
    $p->confirm('gift', 'Gift wrap?')->default(FALSE);

    // A nested sub-panel keeps the border on the drilled-in screen too.
    $p->panel('resources', 'Extras', function (PanelBuilder $sp): void {
      $sp->suggest('bag', 'Bag size')->default('Medium')->options([
        'Small' => 'Small',
        'Medium' => 'Medium',
        'Large' => 'Large',
      ]);
    });
  });

try {
  // Interactive TUI on a terminal; headless when prompts are given or piped.
  // The border and spacing frame the whole panel browser; clearOnExit keeps the
  // final bordered frame on screen after the TUI exits.
  $answers = (new Tui($form))
    ->theme('default', ['border' => $border, 'spacing' => $spacing])
    ->clearOnExit(FALSE)
    ->run($prompts, '1.0.0');
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . "\n");
  exit(1);
}

// The self-describing summary: answers grouped by panel, with provenance
// badges.
echo $answers->toSummary() . "\n";
