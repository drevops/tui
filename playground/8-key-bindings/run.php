<?php

/**
 * @file
 * Configurable key bindings: the default map, vim preset, or a custom override.
 *
 * Selected with --keys; run it interactively to feel the difference.
 *
 * Usage:
 *   php 8-key-bindings/run.php                 # default bindings (arrow keys)
 *   php 8-key-bindings/run.php --keys=vim      # h/j/k/l navigation
 *   php 8-key-bindings/run.php --keys=custom   # a per-widget-type override
 *   php 8-key-bindings/run.php --prompts='{"name":"Ada"}'
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Binding;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Tui;

require __DIR__ . '/../../vendor/autoload.php';

$options = getopt('', ['keys::', 'prompts::']);
$prompts = array_key_exists('prompts', $options) && is_string($options['prompts']) ? $options['prompts'] : '';
$which = array_key_exists('keys', $options) && is_string($options['keys']) ? $options['keys'] : 'default';

if (!in_array($which, ['default', 'vim', 'custom'], TRUE)) {
  fwrite(STDERR, sprintf('Unknown --keys value "%s"; use default, vim or custom.%s', $which, PHP_EOL));
  exit(1);
}

$form = Form::create('Key bindings demo')
  ->panel('profile', 'Profile', function (PanelBuilder $p): void {
    $p->text('name', 'Name')->default('Ada');
    $p->select('role', 'Role')->default('dev')->options([
      'dev' => 'Developer',
      'ops' => 'Operations',
      'design' => 'Designer',
    ]);
    $p->confirm('newsletter', 'Subscribe?')->default(TRUE);
    $p->multiSelect('langs', 'Languages')->options([
      'php' => 'PHP',
      'js' => 'JavaScript',
      'go' => 'Go',
    ]);
  });

// Three ways to set the bindings, selected with --keys. The hints at the foot
// of the panel and editor follow whatever is bound, so they always tell the
// truth about the active keys.
if ($which === 'vim') {
  // The built-in vim preset: h/j/k/l navigate alongside the arrows. Letters are
  // added only where they are not typed input, so text and filter fields keep
  // the arrow keys.
  $form->keys('vim');
}
elseif ($which === 'custom') {
  // Start from the default preset and retune two bindings. Each override names
  // a scope, an action and its keys; a conflicting or un-typeable binding
  // throws when the form is built, not mid-session.
  $form->keys('default', [
    // Quit with x as well as q.
    new Binding(Scope::navigation(), Action::Quit, 'x'),
    // In the single-choice list, Tab accepts too (Enter still does).
    new Binding(Scope::field(FieldType::Select), Action::Accept, KeyName::Tab, KeyName::Enter),
  ]);
}
// The default preset applies when --keys is default (no ->keys() call needed).
try {
  $answers = (new Tui($form))->run($prompts, '1.0.0');
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

echo $answers->toSummary() . PHP_EOL;
