<?php

/**
 * @file
 * Most widgets as fields on one form, collected through the Tui facade.
 *
 * The single-widget examples each declare a one-field form; this gathers them
 * into one multi-field form and drives it once through the panel TUI, instead
 * of invoking each widget directly. The file pickers, which browse a fixture
 * tree, keep their standalone examples.
 *
 * Usage:
 *   php 3-widgets/widgets.php
 *   php 3-widgets/widgets.php --no-unicode   # textual glyphs
 *   php 3-widgets/widgets.php --no-ansi      # no colour.
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// Forcing the mode with a flag shows the textual (ASCII) or no-colour
// rendering without changing the terminal locale.
$opts = getopt('', ['no-unicode', 'no-ansi']);

$form = Form::create('Widgets')
  ->panel('widgets', 'Widgets', function (PanelBuilder $p): void {
    $p->text('text', 'Text')->default('Pear');
    $p->number('number', 'Number')->default(1200);
    $p->calendar('date', 'Calendar')->default('2026-07-15');
    $p->textarea('textarea', 'Textarea')->default('Crisp and sweet' . chr(10) . 'Hint of citrus');
    $p->password('password', 'Password')->default('melon7');
    $p->select('select', 'Select')->default('apple')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    $p->multiSelect('multiselect', 'MultiSelect')->default(['apple'])->options([
      'apple' => 'Apple',
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
    ]);
    $p->reorder('reorder', 'Reorder')->options([
      'apple' => 'Apple',
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
    ]);
    $p->suggest('suggest', 'Suggest')->options([
      'Apple' => 'Apple',
      'Apricot' => 'Apricot',
      'Banana' => 'Banana',
      'Cherry' => 'Cherry',
      'Mango' => 'Mango',
    ]);
    $p->search('search', 'Search')->default('carrot')->options([
      'carrot' => 'Carrot',
      'potato' => 'Potato',
      'onion' => 'Onion',
      'pepper' => 'Pepper',
    ]);
    $p->multiSearch('multisearch', 'MultiSearch')->default(['apple'])->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
    ]);
    $p->confirm('confirm', 'Confirm')->default(TRUE);
    $p->toggle('toggle', 'Toggle')->default('ripe')->options([
      'ripe' => 'Ripe',
      'unripe' => 'Unripe',
    ]);
    $p->pause('pause', 'Pause');
  });

echo (new Tui($form))->color(isset($opts['no-ansi']) ? FALSE : NULL)->unicode(isset($opts['no-unicode']) ? FALSE : NULL)->run()->toSummary() . "\n";
