<?php

/**
 * @file
 * Produce box runner: interactive TUI or non-interactive collection.
 *
 * Usage:
 *   php 1-scaffolder/run.php                                  # interactive TUI
 *   php 1-scaffolder/run.php --prompts='{"name":"Box"}' # non-interactive
 *   php 1-scaffolder/run.php --schema                    # print JSON schema.
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$options = getopt('', ['prompts::', 'schema']);

$form = Form::create('Produce box')
  ->panel('general', 'Basics', function (PanelBuilder $p): void {
    $p->description('Naming and identity.');
    // Declared behaviour: a dynamic default from the run context, validation
    // and a value transform - all closures on the field, no handler class.
    $p->text('name', 'Box name')->description('A human-readable name, e.g. "Summer Box".')->required()
      ->default(fn (Context $c): string => ucwords(str_replace(['-', '_'], ' ', basename($c->directory))))
      ->validate(fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? NULL : 'The box name is required.')
      ->transform(fn (mixed $v): mixed => is_string($v) ? trim($v) : $v);
    // Derived: slug of the box name (str2name "machine").
    $p->text('slug', 'Slug')->description('Derived from the box name.')->derive(new Derive('{{name}}', 'machine'));
    // A plain default that other fields derive from.
    $p->text('grower', 'Grower')->default('sunny');
    // Derived through a chain: "{{grower}}/{{slug}}", lowercased.
    $p->text('code', 'Box code')->description('Derived from grower and slug.')->derive(new Derive('{{grower}}/{{slug}}', 'lower'));
    // Derived label (str2name "pascal").
    $p->text('label', 'Label')->derive(new Derive('{{name}}', 'pascal'));
  })
  ->panel('packing', 'Contents & options', function (PanelBuilder $p): void {
    $p->description('What the box ships with.');
    // A single-choice list.
    $p->select('size', 'Box size')->default('medium')->options([
      'small' => 'Small',
      'medium' => 'Medium',
      'large' => 'Large',
    ]);
    // A multi-select list.
    $p->multiSelect('contents', 'Contents')->description('Space to toggle, type to filter.')->options([
      'fruit' => 'Fruit',
      'veg' => 'Vegetables',
      'herbs' => 'Herbs',
      'salad' => 'Salad',
    ]);
    // Conditional: only shown when "herbs" is among the selected contents.
    $p->text('herb_bundle', 'Herb bundle')->default('mixed')->when(new Condition('contents', contains: 'herbs'));
    // A multi-field conditional: conditions compose with all/any/not, so a
    // field can depend on any number of others - here herbs selected AND
    // size large.
    $p->confirm('weekly', 'Weekly delivery?')->default(TRUE)->when(Condition::all(new Condition('contents', contains: 'herbs'), new Condition('size', eq: 'large')));
    // An autocomplete with free-text fallback.
    $p->suggest('delivery', 'Delivery day')->default('Friday')->options([
      'Monday' => 'Monday',
      'Wednesday' => 'Wednesday',
      'Friday' => 'Friday',
      'Saturday' => 'Saturday',
    ]);
    // A yes/no confirmation.
    $p->confirm('gift', 'Gift wrap?')->default(FALSE);
  });

$tui = new Tui($form);

if (array_key_exists('schema', $options)) {
  echo (string) json_encode($tui->schema(), JSON_PRETTY_PRINT), PHP_EOL;
  exit(0);
}

$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';

try {
  // Interactive TUI on a terminal; headless when prompts are given or piped.
  $answers = $tui->run($prompts, '1.0.0');
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
