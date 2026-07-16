<?php

/**
 * @file
 * Custom theme example: the form names a theme class; run it via the facade.
 *
 * Usage:
 *   php 2-custom-theme/run.php                       # interactive, ocean theme
 *   php 2-custom-theme/run.php --prompts='{"name":"Nemo"}'
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;
use Playground\CustomTheme\OceanTheme;

require __DIR__ . '/../../vendor/autoload.php';
// The require makes the class loadable; the form names it directly, so no
// ThemeManager::register() call is needed.
require __DIR__ . '/OceanTheme.php';

$options = getopt('', ['prompts::']);
$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';

$banner = <<<'EOT'
  ~ ~ ~  O C E A N  ~ ~ ~
EOT;

$form = Form::create('Ocean theme demo')
  ->banner($banner)
  ->panel('profile', 'Diver profile', function (PanelBuilder $p): void {
    $p->text('name', 'Name')->default('Explorer');
    $p->select('depth', 'Preferred depth')->default('reef')->options([
      'surface' => 'Surface',
      'reef' => 'Reef',
      'abyss' => 'Abyss',
    ]);
    $p->multiSelect('gear', 'Gear')->options(['mask' => 'Mask', 'fins' => 'Fins', 'tank' => 'Tank']);
  });

try {
  // The banner comes from the form; the theme is set on the facade.
  $answers = (new Tui($form))->theme(OceanTheme::class)->run($prompts, '1.0.0');
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// The self-describing summary: answers grouped by panel, with provenance
// badges - clearer than raw JSON for a human reading the result.
echo $answers->toSummary() . PHP_EOL;
