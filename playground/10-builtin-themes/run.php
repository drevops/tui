<?php

/**
 * @file
 * Built-in themes preview: render one form under a chosen built-in theme.
 *
 * The dark or light palette is auto-detected from the terminal background; pass
 * --mode to force one, so the same theme name renders either way.
 *
 * Usage:
 *   php 10-builtin-themes/run.php                      # interactive, midnight
 *   php 10-builtin-themes/run.php --theme=frost
 *   php 10-builtin-themes/run.php --theme=ember --mode=light
 *   php 10-builtin-themes/run.php --theme=dos
 *   php 10-builtin-themes/run.php --theme=mono --prompts='{"name":"Acme"}'
 *
 * Built-in themes: default, midnight, frost, ember, mono, dos.
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$options = getopt('', ['theme::', 'mode::', 'prompts::']);
$theme = array_key_exists('theme', $options) && is_string($options['theme']) && $options['theme'] !== '' ? $options['theme'] : 'midnight';
$mode = array_key_exists('mode', $options) && is_string($options['mode']) && $options['mode'] !== '' ? $options['mode'] : '';
$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';

// An explicit mode forces the palette; otherwise the TUI auto-detects it. The
// closed set is normalised to Mode cases here.
$theme_options = match ($mode) {
  '' => [],
  'dark' => ['mode' => Mode::Dark],
  'light' => ['mode' => Mode::Light],
  default => throw new \InvalidArgumentException(sprintf('Unsupported mode "%s". Use dark or light.', $mode)),
};

$form = Form::create('Built-in theme preview')
  ->panel('preview', 'Preview', function (PanelBuilder $p): void {
    $p->text('name', 'Project name')->default('Acme Corp')->description('Shown in the header.');
    $p->select('env', 'Environment')->default('prod')->description('Deployment target.')->options([
      'dev' => 'Development',
      'stage' => 'Staging',
      'prod' => 'Production',
    ]);
    $p->multiselect('features', 'Features')->default(['ssl', 'cache'])->description('Enabled features.')->options([
      'ssl' => 'SSL',
      'cdn' => 'CDN',
      'cache' => 'Cache',
      'queue' => 'Queue',
    ]);
    $p->confirm('analytics', 'Analytics')->default(TRUE)->description('Send anonymous usage data.');
  });

try {
  // The theme is set on the facade; run() picks the dark/light mode.
  $answers = (new Tui($form))->theme($theme, $theme_options)->run($prompts);
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

echo $answers->toSummary() . PHP_EOL;
