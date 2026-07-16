<?php

/**
 * @file
 * Password widget: text rendered as a mask everywhere it appears.
 *
 * The editor, the field row and the summary all show a mask; the accepted
 * value stays plain for the consumer. Add ->revealable() for a Tab-toggled
 * plaintext peek (see password-reveal.php) and ->confirmation() to ask for
 * the value twice and reject a mismatch.
 *
 * Usage:
 *   php playground/02-widgets/password.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the widget.
$form = Form::create('Password widget')
  ->panel('main', 'Password', function (PanelBuilder $p): void {
    $p->password('code', 'Order code')->default('melon7');
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
