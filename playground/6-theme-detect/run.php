<?php

/**
 * @file
 * Mode auto-detection vs forcing: the same form, resolved either way.
 *
 * With no --mode (or --mode=auto) the interactive TUI reads the terminal
 * background and picks the light or dark palette to match; passing --mode=dark
 * or --mode=light forces that palette and overrides detection. Switch your
 * terminal between a light and a dark colour scheme and re-run the auto mode
 * to watch the palette follow it.
 *
 * Usage:
 *   php 6-theme-detect/run.php               # detect from the background
 *   php 6-theme-detect/run.php --mode=auto   # explicit auto
 *   php 6-theme-detect/run.php --mode=dark   # force the dark palette
 *   php 6-theme-detect/run.php --mode=light  # force the light palette
 *   php 6-theme-detect/run.php --prompts='{"name":"Sam"}'
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$options = getopt('', ['prompts::', 'mode::']);
$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';
// Empty or "auto" auto-detects the mode from the terminal background; "dark" or
// "light" force that palette. The closed set is normalised to Mode cases here.
$mode = array_key_exists('mode', $options) && is_string($options['mode']) ? $options['mode'] : '';
$theme_options = match ($mode) {
  '', 'auto' => [],
  'dark' => ['mode' => Mode::Dark],
  'light' => ['mode' => Mode::Light],
  default => throw new \InvalidArgumentException(sprintf('Unsupported mode "%s". Use auto, dark, or light.', $mode)),
};

$form = Form::create('Theme detection demo')
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
  $answers = (new Tui($form))->theme('', $theme_options)->run($prompts, '1.0.0');
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
