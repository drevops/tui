<?php

/**
 * @file
 * Agent help: generated instructions for driving the form unattended.
 *
 * agentHelp() renders a plain-text cheat sheet for the form: every question
 * with its type, options and default, plus how to answer via the prompts JSON
 * and the per-field environment variables and where each ranks in the
 * precedence order. Print it from your tool's --help so automation (or an
 * agent) can answer the form without reading its source.
 *
 * Usage:
 *   php playground/05-headless/agent-help.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$form = Form::create('Produce order')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    $p->text('name', 'Order name')->required();
    $p->select('fruit', 'Fruit')->default('banana')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    $p->number('quantity', 'Quantity')->min(1)->max(99)->default(6);
    $p->confirm('organic', 'Organic only?')->default(FALSE);
  });

echo (new Tui($form))->agentHelp() . PHP_EOL;
