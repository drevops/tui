<?php

/**
 * @file
 * Password widget with the reveal toggle enabled.
 *
 * Tab cycles the display between hidden, masked and plaintext; the accepted
 * value stays plain. With the toggle on, the widget renders its own hint line
 * (accept / tab reveal / cancel), so the editor chrome adds no hint of its own.
 *
 * Usage:
 *   php 3-widgets/widget-password-reveal.php
 *   php 3-widgets/widget-password-reveal.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-password-reveal.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Input\KeyParser;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\PasswordWidget;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);
$theme = new DefaultTheme(76, ['color' => !isset($opts['no-ansi']), 'unicode' => !isset($opts['no-unicode'])]);

$widget = new PasswordWidget('hunter2', revealable: TRUE);

$terminal = new Terminal();
$parser = new KeyParser();
$terminal->setup();

try {
  while (!$widget->isComplete() && !$widget->isCancelled()) {
    $terminal->render(implode("\n", [
      $theme->renderEditorHeader('Password widget'),
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

echo 'Password: ' . ($widget->isCancelled() ? '(cancelled)' : (string) json_encode($widget->value())) . PHP_EOL;
