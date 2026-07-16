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
 *   php 7-theme-options/run.php --prompts='{"name":"Weekly Box"}'
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Theme\ThemeManager;
use DrevOps\Tui\Tui;
use Playground\ThemeOptions\AccentTheme;

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/AccentTheme.php';

// Register the custom theme under a short name so the form selects it by name -
// the class is named once, here, not at every use.
ThemeManager::register('accent', AccentTheme::class);

$options = getopt('', ['prompts::']);
$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';

$form = Form::create('Theme options demo')
  ->panel('order', 'Order', function (PanelBuilder $p): void {
    $p->text('name', 'Name')->default('Weekly Box');
    $p->select('size', 'Box size')->default('medium')->options([
      'small' => 'Small',
      'medium' => 'Medium',
      'large' => 'Large',
    ]);
    $p->select('extras', 'Extras')->multiple()->options([
      'herbs' => 'Herbs',
      'nuts' => 'Nuts',
      'seeds' => 'Seeds',
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
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

echo $answers->toSummary() . PHP_EOL;
