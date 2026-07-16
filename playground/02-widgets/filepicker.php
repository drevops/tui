<?php

/**
 * @file
 * File picker widget: browse the filesystem for one path.
 *
 * Arrows move, Right enters a directory, Left returns to its parent, Enter
 * selects. The form points the picker at a small fixture tree with
 * ->startIn(), limits it to files with ->filesOnly() and to CSV with
 * ->extensions(); ->directoriesOnly() and ->showHidden() are the other
 * filters. The field collects the selected path as a string.
 *
 * Usage:
 *   php playground/02-widgets/filepicker.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the widget.
$form = Form::create('File picker widget')
  ->panel('main', 'File picker', function (PanelBuilder $p): void {
    $p->filePicker('price_list', 'Price list')->startIn(__DIR__ . '/filepicker-tree')->filesOnly()->extensions(['csv']);
  });

try {
  // Interactive on a terminal; resolved (empty) when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
