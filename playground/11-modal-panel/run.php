<?php

/**
 * @file
 * Modal panels: a panel that opens as a centered dialog over the dimmed parent.
 *
 * A panel marked '->modal()' is not drilled into like an ordinary sub-panel; it
 * opens as a bordered box centered over the dimmed basket, collects its fields
 * (or just shows a message), and is dismissed through its own submit/cancel
 * buttons. Submit keeps the edits; cancel or Escape restores the answers
 * exactly as they were when the dialog opened.
 *
 * Usage:
 *   php 11-modal-panel/run.php                                # interactive TUI
 *   php 11-modal-panel/run.php --prompts='{"gift_wrap":true}'
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$options = getopt('', ['prompts::']);
$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';

$form = Form::create('Produce order')
  ->buttons(TRUE, 'Place order', 'Cancel')
  ->panel('basket', 'Basket', function (PanelBuilder $p): void {
    $p->description('Your produce selection.');
    $p->text('item', 'Item')->default('Pear');
    $p->number('quantity', 'Quantity')->default(6)->min(1)->max(99);
    $p->select('ripeness', 'Ripeness')->default('ripe')->options([
      'ripe' => 'Ripe',
      'unripe' => 'Unripe',
    ]);

    // A modal that collects fields: activating it opens a centered dialog over
    // the dimmed basket. Save keeps the edits; Discard (or Escape) restores the
    // fields exactly as they were when the dialog opened.
    $p->panel('gift', 'Gift options', function (PanelBuilder $m): void {
      $m->modal('Save', 'Discard');
      $m->description('Wrap this order as a gift.');
      $m->confirm('gift_wrap', 'Gift wrap?')->default(TRUE);
      $m->text('gift_note', 'Gift message')->default('Enjoy the harvest');
    });

    // A text-only modal: a warning the reader acknowledges. With no fields, the
    // dialog is the whole message, and either button simply dismisses it.
    $p->panel('empty', 'Empty the basket', function (PanelBuilder $m): void {
      $m->modal('Empty it', 'Keep it');
      $m->description("This clears every item from your basket.\nThere is no undo.");
    });
  });

try {
  // Interactive TUI on a terminal; headless when prompts are given or piped.
  // Keep the final frame on screen after the TUI exits.
  $answers = (new Tui($form))->clearOnExit(FALSE)->run($prompts, '1.0.0');
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// The self-describing summary: answers grouped by panel, with provenance
// badges - clearer than raw JSON for a human reading the result.
echo $answers->toSummary() . PHP_EOL;
