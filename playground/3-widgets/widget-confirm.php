<?php

/**
 * @file
 * Interactive confirm widget: Left/Right or y/n toggle, Enter accepts.
 *
 * Usage:
 *   php 3-widgets/widget-confirm.php
 *   php 3-widgets/widget-confirm.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-confirm.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Input\KeyParser;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\ConfirmWidget;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);
$theme = new DefaultTheme(76, ['color' => !isset($opts['no-ansi']), 'unicode' => !isset($opts['no-unicode'])]);

$widget = new ConfirmWidget(TRUE);

$terminal = new Terminal();
$parser = new KeyParser();
$terminal->setup();

try {
  while (!$widget->isComplete() && !$widget->isCancelled()) {
    $terminal->render(implode("\n", [
      $theme->renderEditorHeader('Confirm widget'),
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

echo 'Confirm: ' . ($widget->isCancelled() ? '(cancelled)' : (string) json_encode($widget->value())) . PHP_EOL;
