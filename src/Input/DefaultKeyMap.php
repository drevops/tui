<?php

declare(strict_types=1);

namespace DrevOps\Tui\Input;

use DrevOps\Tui\Config\FieldType;

/**
 * The built-in key bindings: the defaults every form uses unless it opts out.
 *
 * A preset is a class listing its {@see Binding}s, the way a theme is a class
 * of styling methods. The base bindings are shared by every widget; the
 * navigation and per-widget-type bindings override the base only where a key
 * means something different (Enter inserts a newline in a textarea, Space
 * toggles a checkbox option, and so on). Subclass this and override
 * {@see bindings()} to ship an alternate preset - {@see VimKeyMap} does exactly
 * that.
 *
 * @package DrevOps\Tui\Input
 */
class DefaultKeyMap {

  /**
   * The bindings this preset declares.
   *
   * @return list<\DrevOps\Tui\Input\Binding>
   *   The bindings, base first, then per-scope overrides.
   */
  public function bindings(): array {
    $base = Scope::base();

    $bindings = [
      new Binding($base, Action::MoveUp, KeyName::Up),
      new Binding($base, Action::MoveDown, KeyName::Down),
      new Binding($base, Action::MoveLeft, KeyName::Left),
      new Binding($base, Action::MoveRight, KeyName::Right),
      new Binding($base, Action::Accept, KeyName::Enter),
      new Binding($base, Action::Cancel, KeyName::Escape),
      new Binding($base, Action::DeleteBack, KeyName::Backspace),
      new Binding($base, Action::InsertSpace, KeyName::Space),

      new Binding(Scope::navigation(), Action::Activate, KeyName::Enter),
      new Binding(Scope::navigation(), Action::Back, KeyName::Escape),
      new Binding(Scope::navigation(), Action::Quit, 'q'),
      new Binding(Scope::navigation(), Action::ScrollUp, KeyName::MouseWheelUp),
      new Binding(Scope::navigation(), Action::ScrollDown, KeyName::MouseWheelDown),

      new Binding(Scope::field(FieldType::Textarea), Action::NewLine, KeyName::Enter),
      new Binding(Scope::field(FieldType::Textarea), Action::Accept, KeyName::Tab),
      // The textarea hands off to the user's $EDITOR on Ctrl-E.
      new Binding(Scope::field(FieldType::Textarea), Action::ExternalEdit, Key::char("\x05")),

      new Binding(Scope::field(FieldType::Confirm), Action::Toggle, KeyName::Left, KeyName::Right, KeyName::Space, KeyName::Up, KeyName::Down),
      new Binding(Scope::field(FieldType::Confirm), Action::Yes, 'y', 'Y'),
      new Binding(Scope::field(FieldType::Confirm), Action::No, 'n', 'N'),

      new Binding(Scope::field(FieldType::Toggle), Action::Toggle, KeyName::Left, KeyName::Right, KeyName::Space, KeyName::Up, KeyName::Down),

      // The password reveal toggle cycles the display mode on Tab.
      new Binding(Scope::field(FieldType::Password), Action::Reveal, KeyName::Tab),

      new Binding(Scope::field(FieldType::Pause), Action::Accept, KeyName::Enter, KeyName::Space),
    ];

    // The checkbox and the searchable checkbox share one set of list bindings.
    foreach ([FieldType::MultiSelect, FieldType::MultiSearch] as $type) {
      $bindings[] = new Binding(Scope::field($type), Action::Toggle, KeyName::Space);
      $bindings[] = new Binding(Scope::field($type), Action::SelectAll, KeyName::Right);
      $bindings[] = new Binding(Scope::field($type), Action::SelectNone, KeyName::Left);
    }

    return $bindings;
  }

}
