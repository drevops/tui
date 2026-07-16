<?php

/**
 * @file
 * Nested panels: a hub with drill-in sub-panels, custom buttons and a fix-up.
 *
 * Usage:
 *   php 4-nested-panels/run.php                             # interactive TUI
 *   php 4-nested-panels/run.php --prompts='{"delivery":"pickup","gift":true}'
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Model\Fixup;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$options = getopt('', ['prompts::']);
$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';

$form = Form::create('Produce order')
  // Custom button labels; the buttons live on the root panel only.
  ->buttons(TRUE, 'Save', 'Discard')
  // A fix-up reconciles dependent answers on every settle pass: no gift wrap
  // outside doorstep delivery, whatever was answered.
  ->fixup(new Fixup(set: 'gift', to: FALSE, when: new Condition('delivery', ne: 'doorstep')))
  ->panel('identity', 'Order', function (PanelBuilder $p): void {
    $p->description('Who this order is for.');
    $p->text('name', 'Order name')->default('Weekly')->required();
    $p->text('slug', 'Slug')->description('Derived from the order name.')->derive(new Derive('{{name}}', 'machine'));
  })
  ->panel('shipping', 'Delivery', function (PanelBuilder $p): void {
    $p->description('How it arrives.');
    // Options declared one by one carry their own descriptions.
    $p->select('delivery', 'Delivery')->default('pickup')->option('pickup', 'Pickup', 'At the stall')->option('locker', 'Locker', 'Nearby locker')->option('doorstep', 'Doorstep', 'To your door');
    $p->confirm('gift', 'Gift wrap?')->default(TRUE);

    // A nested sub-panel: rendered as a drillable row with a value summary.
    $p->panel('extras', 'Extras', function (PanelBuilder $sp): void {
      $sp->description('Optional add-ons.');
      $sp->multiSelect('addons', 'Add-ons')->options([
        'herbs' => 'Herbs',
        'nuts' => 'Nuts',
        'seeds' => 'Seeds',
      ]);
      $sp->text('herb_note', 'Herb note')->default('mixed')->when(new Condition('addons', contains: 'herbs'));

      // Sub-panels nest to any depth.
      $sp->panel('packaging', 'Packaging', function (PanelBuilder $tp): void {
        $tp->suggest('weight', 'Bag weight')->default('250g')->options([
          '250g' => '250g',
          '500g' => '500g',
          '1kg' => '1kg',
        ]);
      });
    });
  });

try {
  // Interactive TUI on a terminal; headless when prompts are given or piped.
  // Keep the final frame on screen after the TUI exits.
  $answers = (new Tui($form))->clearOnExit(FALSE)->run($prompts, '1.0.0');
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// The self-describing summary: answers grouped by panel, with provenance
// badges - clearer than raw JSON for a human reading the result.
echo $answers->toSummary() . PHP_EOL;
