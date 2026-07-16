<?php

/**
 * @file
 * Discovery: detect defaults from an existing project directory (update mode).
 *
 * The fields carry `discover` rules evaluated against `sample/`: a `.env` key,
 * a JSON dot-path, a path-exists check and a directory scan. Per-question env
 * overrides use the form-declared `BOX_` prefix instead of the default
 * `TUI_`.
 *
 * Usage:
 *   php 5-discovery/run.php                            # discover from sample/
 *   BOX_SEASON=winter php 5-discovery/run.php          # env override wins
 *   php 5-discovery/run.php --prompts='{"name":"Renamed"}'  # prompts win
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Discovery\Dotenv;
use DrevOps\Tui\Discovery\JsonValue;
use DrevOps\Tui\Discovery\PathExists;
use DrevOps\Tui\Discovery\Scan;
use DrevOps\Tui\Discovery\ScanType;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$options = getopt('', ['prompts::']);
$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';

$form = Form::create('Discovery demo', 'an existing box')
  // Per-question env overrides read BOX_<ID> instead of the default TUI_<ID>.
  ->envPrefix('BOX_')
  ->panel('box', 'Box', function (PanelBuilder $p): void {
    // Read a dot-path from a JSON file.
    $p->text('name', 'Box name')->discover(new JsonValue('box.json', 'name'));
    // Read a key from the .env file.
    $p->text('season', 'Season')->default('summer')->discover(new Dotenv('SEASON'));
    // Whether a path exists.
    $p->confirm('inseason', 'In season?')->discover(new PathExists('harvest.csv'));
    // List directory entries (the type keeps dirs, files or any entry).
    $p->multiSelect('baskets', 'Baskets')->options(['apples' => 'Apples', 'pears' => 'Pears', 'plums' => 'Plums'])->discover(new Scan('baskets', type: ScanType::Dir));
  });

$tui = new Tui($form);

try {
  // Update mode (the third argument) is what enables discovery.
  $answers = $tui->collect($prompts, __DIR__ . '/sample', TRUE, '1.0.0');
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// The answers are self-describing: the summary groups them by panel and
// badges non-default provenance - "detected" for discovered values, "edited"
// for env and prompt inputs.
echo $answers->toSummary() . PHP_EOL;
