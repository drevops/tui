<?php

/**
 * @file
 * Declared behaviour: default, validation and transform as field closures.
 *
 * Three seams on any field, no classes needed: ->default() accepts a closure
 * receiving the run Context (directory, update flag, version) for values
 * computed at run time; ->validate() returns an error string to reject a
 * value or NULL to accept it, and blocks accepting until it passes;
 * ->transform() rewrites the accepted value before it is stored.
 *
 * Usage:
 *   php playground/06-field-behaviour-closures.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Field behaviour')
  ->panel('stall', 'Stall', function (PanelBuilder $p): void {
    // A dynamic default: computed when the run starts, here from the target
    // directory's name ("my-stall" becomes "My Stall").
    $p->text('name', 'Stall name')->required()
      ->default(fn (Context $c): string => ucwords(str_replace(['-', '_'], ' ', basename($c->directory))));

    // Validation: NULL accepts, any string rejects with that message. Try
    // accepting a weight below 100.
    $p->number('weight', 'Crate weight (g)')->default(1200)
      ->validate(fn (mixed $v): ?string => is_int($v) && $v >= 100 ? NULL : 'A crate weighs at least 100 g.');

    // A transform normalizes the accepted value - trimmed and lowercased
    // here - before it lands in the answers.
    $p->text('variety', 'Variety')->default(' Golden Delicious ')
      ->transform(fn (mixed $v): mixed => is_string($v) ? strtolower(trim($v)) : $v);
  });

try {
  $answers = (new Tui($form))->run();
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

echo $answers->toSummary() . PHP_EOL;
