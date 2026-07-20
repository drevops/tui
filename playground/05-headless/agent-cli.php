<?php

/**
 * @file
 * The --agent CLI pattern: a consumer tool that describes itself to agents.
 *
 * A consumer's installer or command wires the facade to a few flags so an AI
 * agent (or any automation) can drive it without reading the source:
 *
 *   --agent            Print the generated driving guide and exit.
 *   --schema           Print the JSON schema - each field's type, bounds and
 *                      allowed select values - and exit.
 *   --no-interaction   Resolve every answer from --prompts, environment and
 *                      defaults without opening the TUI, even on a terminal.
 *   --prompts <json>   A JSON object of answers (or a path to a JSON file),
 *                      keyed by question id; the highest-precedence input.
 *
 * The lines that make a tool agent-drivable are the --agent and --schema
 * branches below: the guide explains how to answer, the schema lists the
 * allowed values, and both exit before the form runs.
 *
 * Usage:
 *   # An agent runs --agent and --schema first to learn the questions and their
 *   # allowed values, then supplies the answers and runs unattended:
 *   php playground/05-headless/agent-cli.php --agent
 *   php playground/05-headless/agent-cli.php --schema
 *   php playground/05-headless/agent-cli.php --no-interaction \
 *     --prompts '{"name":"Weekly Box","fruit":"cherry"}'
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\InterruptException;
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

$tui = new Tui($form);

$args = array_slice($argv, 1);

// --agent prints the guide and exits before the form runs, so an agent
// learns how to answer it first.
if (in_array('--agent', $args, TRUE)) {
  echo $tui->agentHelp() . PHP_EOL;
  exit(0);
}

// --schema prints the machine-readable schema - each field's type, bounds and
// allowed select values - so an agent can build a constrained answer that the
// text guide alone does not spell out.
if (in_array('--schema', $args, TRUE)) {
  echo json_encode($tui->schema(), JSON_PRETTY_PRINT) . PHP_EOL;
  exit(0);
}

// --no-interaction forces a headless resolve even on a terminal; without it
// run() opens the TUI when stdin is a terminal and resolves headlessly
// otherwise.
$interactive = in_array('--no-interaction', $args, TRUE) ? FALSE : NULL;

// --prompts takes the next argument: the answers as a JSON object or a path
// to a JSON file. The flag with no value is a usage error; absent entirely,
// answers still come from the environment and defaults.
$prompts = '';
$flag = array_search('--prompts', $args, TRUE);
if ($flag !== FALSE) {
  if (!isset($args[$flag + 1])) {
    fwrite(STDERR, 'Missing value for --prompts.' . PHP_EOL);
    exit(2);
  }

  $prompts = $args[$flag + 1];
}

try {
  $answers = $tui->run($prompts, interactive: $interactive);
}
catch (InterruptException) {
  // Ctrl-C aborts an interactive session; the partial answers are discarded.
  exit(130);
}
catch (EngineException $exception) {
  // A missing required answer or a value failing validation lands here.
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// The machine-readable result: every field id to its typed value, ready for
// the agent to consume or pipe onward.
echo $answers->toJson() . PHP_EOL;
