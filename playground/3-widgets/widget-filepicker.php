<?php

/**
 * @file
 * Interactive file picker: arrows move, Right/Left browse, Enter selects.
 *
 * Usage:
 *   php 3-widgets/widget-filepicker.php
 *   php 3-widgets/widget-filepicker.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-filepicker.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Config\FilePickerMode;
use DrevOps\Tui\Input\KeyParser;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\FilePickerWidget;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);
$theme = new DefaultTheme(76, ['color' => !isset($opts['no-ansi']), 'unicode' => !isset($opts['no-unicode'])]);

// Browse a small fixture tree, limited to YAML files.
$widget = new FilePickerWidget(__DIR__ . '/filepicker-tree', mode: FilePickerMode::File, extensions: ['yml', 'yaml']);

$terminal = new Terminal();
$parser = new KeyParser();
$terminal->setup();

try {
  // The picker renders its own key-hint line, so no extra hint is added here.
  while (!$widget->isComplete() && !$widget->isCancelled()) {
    $terminal->render(implode("\n", [
      $theme->renderEditorHeader('File picker widget'),
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

echo 'File picker: ' . ($widget->isCancelled() ? '(cancelled)' : (string) json_encode($widget->value())) . PHP_EOL;
