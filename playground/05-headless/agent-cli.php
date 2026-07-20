<?php

/**
 * @file
 * Folding the agent guide into a consumer tool's own help output.
 *
 * The library generates the instructions for driving a form unattended;
 * agentHelp() hands them back as plain text. A consumer tool prints its own
 * help and includes that text, so an AI agent (or any automation) reading the
 * help learns how to answer the form without reading the source. Where the
 * text goes is the consumer's call - here it sits under an "AI agents"
 * heading; a real tool might gate it behind a flag of its own.
 *
 * Usage:
 *   php playground/05-headless/agent-cli.php
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

// The consumer tool's own help. agentHelp() supplies the machine-usable
// section verbatim, so the help a person reads is also the help an agent
// needs - there is no separate contract to keep in sync.
echo 'produce-order - collect a produce order' . PHP_EOL;
echo PHP_EOL;
echo 'Run it to fill the order interactively.' . PHP_EOL;
echo PHP_EOL;
echo 'For AI agents:' . PHP_EOL;
echo PHP_EOL;
echo (new Tui($form))->agentHelp() . PHP_EOL;
