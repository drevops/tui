<?php

/**
 * @file
 * Translations: chrome and questions presented in another language.
 *
 * A Translator carries the active language and catalog directories; set on
 * the facade, it localizes everything user-facing - key hints, buttons,
 * badges, validation messages, and the form's own labels - with English as
 * the fallback for anything untranslated. The language can also be the
 * 'auto' sentinel to follow the environment locale (LC_ALL, LC_MESSAGES,
 * LANG), and 'es_ES' falls back to the 'es' catalog when no region catalog
 * exists.
 *
 * Usage:
 *   php playground/13-translations/run.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Translation\Translator;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// The form is declared in English; the catalog translates it at render time,
// so the answer ids and values stay language-neutral.
$form = Form::create('Produce order')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    $p->text('name', 'Order name')->default('Semanal');
    $p->select('fruit', 'Fruit')->default('banana')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    $p->number('quantity', 'Quantity')->min(1)->max(99)->default(6);
    $p->confirm('organic', 'Organic only?')->default(TRUE);
  });

// Spanish, from the catalog/ directory beside this script. Directories are
// searched in order and a later one overrides an earlier one, so a consumer
// catalog can override a bundled one. Translator('auto', [...]) would follow
// the terminal locale instead.
$translator = new Translator('es', [__DIR__ . '/catalog']);

try {
  $answers = (new Tui($form))->translator($translator)->run();
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// The summary renders through the same catalog; the collected values are
// untouched - only the presentation is localized.
echo $answers->toSummary() . PHP_EOL;
