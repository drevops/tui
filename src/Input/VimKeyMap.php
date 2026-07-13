<?php

declare(strict_types=1);

namespace DrevOps\Tui\Input;

use DrevOps\Tui\Config\FieldType;

/**
 * A vim-style preset: h/j/k/l navigate alongside the arrow keys.
 *
 * The letter keys are added only where they cannot be swallowed as typed input:
 * panel navigation, the single-choice list and the date calendar. Text fields
 * and the filtering
 * lists (search, suggest, checkbox) keep the arrow keys, because there a letter
 * is a character the user is typing - binding it to movement would make it
 * un-typeable, which the key map rejects outright. Everything else is inherited
 * from {@see DefaultKeyMap}.
 *
 * @package DrevOps\Tui\Input
 */
class VimKeyMap extends DefaultKeyMap {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function bindings(): array {
    return array_merge(parent::bindings(), [
      new Binding(Scope::navigation(), Action::MoveUp, 'k', KeyName::Up),
      new Binding(Scope::navigation(), Action::MoveDown, 'j', KeyName::Down),
      new Binding(Scope::navigation(), Action::MoveLeft, 'h', KeyName::Left),
      new Binding(Scope::navigation(), Action::MoveRight, 'l', KeyName::Right),

      new Binding(Scope::field(FieldType::Select), Action::MoveUp, 'k', KeyName::Up),
      new Binding(Scope::field(FieldType::Select), Action::MoveDown, 'j', KeyName::Down),

      new Binding(Scope::field(FieldType::Calendar), Action::MoveUp, 'k', KeyName::Up),
      new Binding(Scope::field(FieldType::Calendar), Action::MoveDown, 'j', KeyName::Down),
      new Binding(Scope::field(FieldType::Calendar), Action::MoveLeft, 'h', KeyName::Left),
      new Binding(Scope::field(FieldType::Calendar), Action::MoveRight, 'l', KeyName::Right),
    ]);
  }

}
