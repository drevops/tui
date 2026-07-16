<?php

/**
 * @file
 * The smallest runner: build a form, collect answers, print the JSON result.
 *
 * The Tui facade wires the engine internally - there are no handlers, so the
 * engine's defaults do everything.
 *
 * Usage:
 *   php 0-minimal/run.php --prompts='{"name":"Pear","fruit":"apple"}'
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$options = getopt('', ['prompts::']);
$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';

$form = Form::create('Minimal')
  ->panel('main', 'Main', function (PanelBuilder $p): void {
    $p->text('name', 'Your name')->required();
    $p->select('fruit', 'Favourite fruit')->default('banana')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
  });

$answers = (new Tui($form))->collect($prompts);

echo $answers->toJson() . PHP_EOL;
