<?php

/**
 * @file
 * Theme auto-detection vs forcing: the same form, resolved either way.
 *
 * With no --theme (or --theme=auto) the interactive TUI reads the terminal
 * background and picks the light or dark theme to match; passing --theme=dark
 * or --theme=light forces that theme and overrides detection. Switch your
 * terminal between a light and a dark colour scheme and re-run the auto mode
 * to watch the palette follow it.
 *
 * Usage:
 *   php 6-theme-detect/run.php               # detect from the background
 *   php 6-theme-detect/run.php --theme=auto  # explicit auto sentinel
 *   php 6-theme-detect/run.php --theme=dark  # force the dark theme
 *   php 6-theme-detect/run.php --theme=light # force the light theme
 *   php 6-theme-detect/run.php --prompts='{"name":"Sam"}'
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$options = getopt('', ['prompts::', 'theme::']);
$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';
// Empty or "auto" auto-detects from the terminal background; "dark" or "light"
// force that theme regardless of the background.
$theme = array_key_exists('theme', $options) && is_string($options['theme']) ? $options['theme'] : '';

$form = Form::create('Theme detection demo')
  ->theme($theme)
  ->panel('appearance', 'Appearance', function (PanelBuilder $p): void {
    $p->text('name', 'Your name')->default('Sam');
    $p->select('fruit', 'Favourite fruit')->default('apple')->options([
      'apple' => 'Apple',
      'cherry' => 'Cherry',
      'grape' => 'Grape',
    ]);
    $p->confirm('subscribe', 'Subscribe to updates?')->default(TRUE);
  });

try {
  // run() picks interactive vs headless; the theme resolves inside interact().
  $answers = (new Tui($form))->run($prompts, '1.0.0');
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

echo $answers->toSummary() . PHP_EOL;
