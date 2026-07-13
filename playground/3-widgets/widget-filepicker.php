<?php

/**
 * @file
 * File picker field on a form, collected through the Tui facade.
 *
 * Arrows move, Right/Left browse, Enter selects. The form points the picker at
 * a small fixture tree with `->start()`, limits it to files with
 * `->filesOnly()` and to YAML with `->extensions()`, instead of invoking the
 * widget directly.
 *
 * Usage:
 *   php 3-widgets/widget-filepicker.php
 *   php 3-widgets/widget-filepicker.php --no-unicode   # textual glyphs
 *   php 3-widgets/widget-filepicker.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);

$form = Form::create('File picker widget')
  ->color(isset($opts['no-ansi']) ? FALSE : NULL)
  ->unicode(isset($opts['no-unicode']) ? FALSE : NULL)
  ->panel('main', 'File picker', function (PanelBuilder $p): void {
    $p->filePicker('file', 'File picker')->start(__DIR__ . '/filepicker-tree')->filesOnly()->extensions(['yml', 'yaml']);
  });

echo (new Tui($form))->run()->toJson() . PHP_EOL;
