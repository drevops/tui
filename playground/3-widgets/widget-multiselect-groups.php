<?php

/**
 * @file
 * Multiselect with a group heading, a separator and a disabled option.
 *
 * Non-selectable rows are visual only: Space and the cursor skip the heading,
 * the separator and the disabled option, which shows its reason beside the
 * label and can never be checked.
 *
 * Usage:
 *   php 3-widgets/widget-multiselect-groups.php
 *   php 3-widgets/widget-multiselect-groups.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-multiselect-groups.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Config\OptionKind;
use DrevOps\Tui\Input\KeyParser;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\MultiSelectWidget;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);
$theme = new DefaultTheme(42, ['color' => !isset($opts['no-ansi']), 'unicode' => !isset($opts['no-unicode'])]);

$widget = new MultiSelectWidget([
  new Option('', 'Services', '', OptionKind::Heading),
  new Option('redis', 'Redis'),
  new Option('solr', 'Solr'),
  new Option('', '', '', OptionKind::Separator),
  new Option('clamav', 'ClamAV', '', OptionKind::Option, TRUE, 'licence required'),
], ['redis']);

$terminal = new Terminal();
$parser = new KeyParser();
$terminal->setup();

try {
  while (!$widget->isComplete() && !$widget->isCancelled()) {
    $terminal->render(implode("\n", [
      $theme->renderEditorHeader('MultiSelect with groups'),
      $theme->renderHintLine('edit', 'Enter accept', 'Esc cancel'),
      '',
      $widget->view($theme),
    ]));

    foreach ($parser->parse($terminal->read()) as $key) {
      $widget->handle($key);
    }
  }
}
finally {
  $terminal->restore();
}

echo 'MultiSelect: ' . ($widget->isCancelled() ? '(cancelled)' : (string) json_encode($widget->value())) . PHP_EOL;
