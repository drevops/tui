<?php

/**
 * @file
 * Bordered panels: the panel browser wrapped in a border frame.
 *
 * The border is a theme option ('none', 'line', 'rounded' or 'double') set as a
 * plain string alongside the spacing - no imports. The default theme draws the
 * whole frame (hub, breadcrumb header, fields and the key-hint footer) inside
 * the chosen border, and every drill-in sub-panel keeps it. The --border and
 * --spacing flags pick the styles, defaulting to a rounded, padded frame.
 *
 * Usage:
 *   php 9-bordered-panels/run.php                          # interactive TUI
 *   php 9-bordered-panels/run.php --border=double --spacing=normal
 *   php 9-bordered-panels/run.php --prompts='{"name":"api"}'
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$options = getopt('', ['prompts::', 'border::', 'spacing::']);
$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';
$border = array_key_exists('border', $options) && is_string($options['border']) ? $options['border'] : 'rounded';
$spacing = array_key_exists('spacing', $options) && is_string($options['spacing']) ? $options['spacing'] : 'padded';

$form = Form::create('New service')
  // The border and spacing frame the whole panel browser; both come from the
  // flags and default to the rounded, padded look.
  ->theme('default', ['border' => $border, 'spacing' => $spacing])
  ->buttons(TRUE, 'Create', 'Cancel')
  // Keep the final bordered frame on screen after the TUI exits.
  ->clearOnExit(FALSE)
  ->panel('basics', 'Basics', function (PanelBuilder $p): void {
    $p->description('What the service is.');
    $p->text('name', 'Service name')->default('api')->required();
    $p->select('runtime', 'Runtime')->default('php')->options([
      'php' => 'PHP',
      'node' => 'Node.js',
      'python' => 'Python',
    ]);
    $p->number('port', 'Port')->default(8080)->min(1)->max(65535);
  })
  ->panel('deploy', 'Deployment', function (PanelBuilder $p): void {
    $p->description('Where and how it runs.');
    $p->select('environment', 'Environment')->default('dev')->option('dev', 'Development', 'Local containers')->option('prod', 'Production', 'Live traffic');
    $p->confirm('autoscale', 'Enable autoscaling?')->default(FALSE);

    // A nested sub-panel keeps the border on the drilled-in screen too.
    $p->panel('resources', 'Resources', function (PanelBuilder $sp): void {
      $sp->suggest('memory', 'Memory limit')->default('256M')->options([
        '128M' => '128M',
        '256M' => '256M',
        '512M' => '512M',
      ]);
    });
  });

try {
  // Interactive TUI on a terminal; headless when prompts are given or piped.
  $answers = (new Tui($form))->run($prompts, '1.0.0');
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . "\n");
  exit(1);
}

// The self-describing summary: answers grouped by panel, with provenance
// badges.
echo $answers->toSummary() . "\n";
