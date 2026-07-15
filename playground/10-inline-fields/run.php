<?php

/**
 * @file
 * Inline editing: each field's editor opens in place on the panel row.
 *
 * Press Enter on a field and its editor opens right where the value sits - the
 * confirm's Yes/No, the select's list, the number's input - edited with the
 * widget's own keys, collapsing back on accept or cancel. Inline is the default;
 * the last field opts out with ->standalone() to open its editor full-screen.
 *
 * Usage:
 *   php 10-inline-fields/run.php                              # interactive TUI
 *   php 10-inline-fields/run.php --prompts='{"env":"prod"}'  # headless
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$options = getopt('', ['prompts::']);
$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';

$form = Form::create('Release settings')
  ->panel('build', 'Build', function (PanelBuilder $p): void {
    $p->description('Press Enter on a field to edit it in place; Enter or Esc closes it.');

    // Inline by default: the Yes/No opens in the row.
    $p->confirm('sourcemaps', 'Emit source maps?')->default(FALSE);

    // The number's input opens in the row.
    $p->number('workers', 'Parallel workers')->min(1)->max(8)->default(4);

    // The select drops its option list under the label, in the panel.
    $p->select('env', 'Target environment')->default('dev')->options([
      'dev' => 'Development',
      'stage' => 'Staging',
      'prod' => 'Production',
    ]);

    // A month grid wants the whole screen: opt out of inline.
    $p->calendar('release_on', 'Release date')->standalone()->default('2026-07-15');
  });

try {
  // Interactive TUI on a terminal; headless when prompts are given or piped.
  $answers = (new Tui($form))->run($prompts, '1.0.0');
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// The self-describing summary: answers grouped by panel, with provenance badges.
echo $answers->toSummary() . PHP_EOL;
