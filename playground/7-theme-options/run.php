<?php

/**
 * @file
 * Theme options: spacing, border and a custom accent, all as plain strings.
 *
 * Every option is a plain string in one array - no imports, and each value is
 * validated (a typo throws). The custom "accent" option, declared by the
 * registered AccentTheme, is set the same way as the built-ins.
 *
 * Usage:
 *   php 7-theme-options/run.php
 *   php 7-theme-options/run.php --prompts='{"name":"Acme"}'
 */

declare(strict_types=1);

use Playground\ThemeOptions\AccentTheme;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Theme\ThemeManager;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/AccentTheme.php';

// Register the custom theme under a short name so the form selects it by name -
// the class is named once, here, not at every use.
ThemeManager::register('accent', AccentTheme::class);

$options = getopt('', ['prompts::']);
$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';

$form = Form::create('Theme options demo')
  ->panel('project', 'Project', function (PanelBuilder $p): void {
    $p->text('name', 'Name')->default('Acme Site');
    $p->select('type', 'Package type')->default('library')->options([
      'library' => 'Library',
      'project' => 'Project',
    ]);
    $p->multiSelect('features', 'Features')->options([
      'tests' => 'Tests',
      'ci' => 'CI',
      'docker' => 'Docker',
    ]);
  });

try {
  $answers = (new Tui($form))
    ->theme('accent', [
      'spacing' => 'padded',
      'border' => 'rounded',
      'accent' => 'warm',
    ])
    ->run($prompts, '1.0.0');
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

echo $answers->toSummary() . PHP_EOL;
