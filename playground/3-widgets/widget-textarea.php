<?php

/**
 * @file
 * Interactive textarea widget: Enter inserts a newline, Tab accepts.
 *
 * With $EDITOR (or $VISUAL) set, Ctrl-E hands off to that editor for the value.
 *
 * Usage:
 *   php 3-widgets/widget-textarea.php
 *   php 3-widgets/widget-textarea.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-textarea.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Input\KeyParser;
use DrevOps\Tui\Render\ExternalEditor;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\TextareaWidget;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);
$theme = new DefaultTheme(76, ['color' => !isset($opts['no-ansi']), 'unicode' => !isset($opts['no-unicode'])]);

// The handoff is offered only when an editor is actually available.
$external_editor = new ExternalEditor();
$widget = new TextareaWidget("Redis for cache\nSolr for search", $external_editor->isAvailable());

$terminal = new Terminal();
$parser = new KeyParser();
$terminal->setup();

try {
  while (!$widget->isComplete() && !$widget->isCancelled()) {
    $terminal->render(implode("\n", [
      $theme->renderEditorHeader('Textarea widget'),
      $theme->renderHintLine('edit', 'Tab accept', 'Esc cancel'),
      '',
      $widget->view($theme),
    ]));

    foreach ($parser->parse($terminal->read()) as $key) {
      $widget->handle($key);
    }

    // Ctrl-E requested the editor: suspend the TUI, run it, capture the result.
    if ($widget->wantsExternalEdit()) {
      $value = $widget->value();
      $widget->applyExternalEdit($external_editor->edit(is_string($value) ? $value : '', $terminal));
    }
  }
}
finally {
  $terminal->restore();
}

echo 'Textarea: ' . ($widget->isCancelled() ? '(cancelled)' : (string) json_encode($widget->value())) . PHP_EOL;
