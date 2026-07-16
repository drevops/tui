<?php

/**
 * @file
 * Field-value styles: the theme's "field" option on the editor input line.
 *
 * The "field" theme option controls how a field's input line looks while you
 * type a value into it - "flat" is a plain caret (the default), "boxed" fills
 * a fixed-width background block behind the value (MS-DOS style, visible even
 * when empty), and "underline" underlines the entry field. It applies to the
 * single-line inputs (text, number, password). Press Enter on a field to open
 * its editor and see the style; pass the colour mode too.
 *
 * Usage:
 *   php 10-field-styles/run.php                          # boxed, dark
 *   php 10-field-styles/run.php --field=underline
 *   php 10-field-styles/run.php --field=flat
 *   php 10-field-styles/run.php --field=boxed --mode=light
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Theme\FieldStyle;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$options = getopt('', ['field::', 'mode::', 'prompts::']);
$field = is_string($options['field'] ?? NULL) ? $options['field'] : FieldStyle::Boxed->value;
$mode = is_string($options['mode'] ?? NULL) ? $options['mode'] : Mode::Dark->value;
$prompts = is_string($options['prompts'] ?? NULL) ? $options['prompts'] : '';

$form = Form::create('Field styles')
  ->panel('order', 'Order details', function (PanelBuilder $p): void {
    $p->description('Press Enter on a field to edit it and see the input style.');
    $p->text('name', 'Name')->default('Weekly Box');
    $p->number('quantity', 'Quantity')->default(6);
    $p->text('grower', 'Grower')->default('Sunny Farm');
    $p->password('code', 'Order code')->default('melon7');
    $p->select('fruit', 'Fruit')->default('apple')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    // Empty by default: editing it shows the boxed field as an empty bar.
    $p->text('notes', 'Notes');
  });

try {
  $answers = (new Tui($form))
    ->theme('default', ['field' => $field, 'mode' => $mode])
    ->run($prompts, '1.0.0');
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

echo $answers->toSummary() . PHP_EOL;
