<?php

/**
 * @file
 * Quick-start runner: a compact multi-widget form, interactive or headless.
 *
 * One panel with a handful of widgets, showing the fluent builder pattern used
 * throughout the documentation. The Tui facade wires the engine internally.
 *
 * Usage:
 *   php 0-quickstart/run.php                     # interactive TUI
 *   php 0-quickstart/run.php --prompts='{"name":"Box"}'  # headless
 *   php 0-quickstart/run.php --schema            # print JSON schema
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$options = getopt('', ['prompts::', 'schema']);

$form = Form::create('Quick start')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    // A required single-line text field.
    $p->text('name', 'Order name')->required();
    // A single choice, starting on "Banana".
    $p->select('fruit', 'Fruit')->default('banana')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    // A multi-select, with one option pre-checked.
    $p->select('veg', 'Vegetables')->multiple()->default(['carrot'])->options([
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
      'spinach' => 'Spinach',
    ]);
    // An integer field bounded to a sensible quantity.
    $p->number('quantity', 'Quantity')->min(1)->max(99)->default(6);
    // A yes/no gate.
    $p->confirm('organic', 'Organic only?')->default(FALSE);
  });

$tui = new Tui($form);

if (array_key_exists('schema', $options)) {
  echo (string) json_encode($tui->schema(), JSON_PRETTY_PRINT), PHP_EOL;
  exit(0);
}

$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';

try {
  // Interactive TUI on a terminal; headless when prompts are given or piped.
  $answers = $tui->run($prompts);
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// The self-describing summary: answers by panel, with provenance badges.
echo $answers->toSummary() . PHP_EOL;
