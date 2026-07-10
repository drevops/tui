<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Input;

use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Binding;
use DrevOps\Tui\Input\DefaultKeyMap;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Input\VimKeyMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the configurable key map: presets, scopes, resolution and validation.
 */
#[CoversClass(Action::class)]
#[CoversClass(Binding::class)]
#[CoversClass(Scope::class)]
#[CoversClass(ScopedKeyMap::class)]
#[CoversClass(KeyMap::class)]
#[CoversClass(DefaultKeyMap::class)]
#[CoversClass(VimKeyMap::class)]
#[CoversClass(KeyMapManager::class)]
#[Group('input')]
final class KeyMapTest extends TestCase {

  #[DataProvider('dataProviderDefaultBindings')]
  public function testDefaultBindings(Scope $scope, Key $key, Action $action, bool $expected): void {
    $map = KeyMapManager::create()->scope($scope);

    $this->assertSame($expected, $map->matches($key, $action));
  }

  public static function dataProviderDefaultBindings(): \Iterator {
    // Navigation.
    yield 'nav up moves up' => [Scope::navigation(), Key::named(KeyName::Up), Action::MoveUp, TRUE];
    yield 'nav enter activates' => [Scope::navigation(), Key::named(KeyName::Enter), Action::Activate, TRUE];
    yield 'nav enter is not accept' => [Scope::navigation(), Key::named(KeyName::Enter), Action::Accept, FALSE];
    yield 'nav q quits' => [Scope::navigation(), Key::char('q'), Action::Quit, TRUE];
    yield 'nav escape backs out' => [Scope::navigation(), Key::named(KeyName::Escape), Action::Back, TRUE];
    yield 'nav wheel scrolls' => [Scope::navigation(), Key::named(KeyName::MouseWheelDown), Action::ScrollDown, TRUE];
    // Text.
    yield 'text enter accepts' => [Scope::field(FieldType::Text), Key::named(KeyName::Enter), Action::Accept, TRUE];
    yield 'text space inserts' => [Scope::field(FieldType::Text), Key::named(KeyName::Space), Action::InsertSpace, TRUE];
    yield 'text backspace deletes' => [Scope::field(FieldType::Text), Key::named(KeyName::Backspace), Action::DeleteBack, TRUE];
    // Textarea overrides the base.
    yield 'textarea enter is newline' => [Scope::field(FieldType::Textarea), Key::named(KeyName::Enter), Action::NewLine, TRUE];
    yield 'textarea enter is not accept' => [Scope::field(FieldType::Textarea), Key::named(KeyName::Enter), Action::Accept, FALSE];
    yield 'textarea tab accepts' => [Scope::field(FieldType::Textarea), Key::named(KeyName::Tab), Action::Accept, TRUE];
    // A control char is bindable in a text-entry scope; a printable one is not.
    yield 'textarea ctrl-e is external edit' => [Scope::field(FieldType::Textarea), Key::char("\x05"), Action::ExternalEdit, TRUE];
    // Password reveal toggle.
    yield 'password tab reveals' => [Scope::field(FieldType::Password), Key::named(KeyName::Tab), Action::Reveal, TRUE];
    // Confirm.
    yield 'confirm up toggles' => [Scope::field(FieldType::Confirm), Key::named(KeyName::Up), Action::Toggle, TRUE];
    yield 'confirm y is yes' => [Scope::field(FieldType::Confirm), Key::char('y'), Action::Yes, TRUE];
    yield 'confirm uppercase y is yes' => [Scope::field(FieldType::Confirm), Key::char('Y'), Action::Yes, TRUE];
    yield 'confirm enter accepts' => [Scope::field(FieldType::Confirm), Key::named(KeyName::Enter), Action::Accept, TRUE];
    // Toggle switch.
    yield 'toggle space flips' => [Scope::field(FieldType::Toggle), Key::named(KeyName::Space), Action::Toggle, TRUE];
    yield 'toggle enter accepts' => [Scope::field(FieldType::Toggle), Key::named(KeyName::Enter), Action::Accept, TRUE];
    // Multi-select.
    yield 'multiselect space toggles' => [Scope::field(FieldType::MultiSelect), Key::named(KeyName::Space), Action::Toggle, TRUE];
    yield 'multiselect right selects all' => [Scope::field(FieldType::MultiSelect), Key::named(KeyName::Right), Action::SelectAll, TRUE];
    yield 'multiselect up moves up (inherited)' => [Scope::field(FieldType::MultiSelect), Key::named(KeyName::Up), Action::MoveUp, TRUE];
    yield 'multisearch space toggles' => [Scope::field(FieldType::MultiSearch), Key::named(KeyName::Space), Action::Toggle, TRUE];
    // Pause binds two keys to accept.
    yield 'pause enter accepts' => [Scope::field(FieldType::Pause), Key::named(KeyName::Enter), Action::Accept, TRUE];
    yield 'pause space accepts' => [Scope::field(FieldType::Pause), Key::named(KeyName::Space), Action::Accept, TRUE];
    // A scope with no overrides falls back to the base bindings.
    yield 'select falls back to base' => [Scope::field(FieldType::Select), Key::named(KeyName::Up), Action::MoveUp, TRUE];
  }

  public function testForFieldFallsBackToBaseInstance(): void {
    $map = KeyMapManager::create();

    // Select has no overrides, so it is the very same base instance.
    $this->assertSame($map->scope(Scope::base()), $map->forField(FieldType::Select));
  }

  #[DataProvider('dataProviderVimPreset')]
  public function testVimPreset(Scope $scope, Key $key, Action $action, bool $expected): void {
    $map = KeyMapManager::create('vim')->scope($scope);

    $this->assertSame($expected, $map->matches($key, $action));
  }

  public static function dataProviderVimPreset(): \Iterator {
    yield 'nav j moves down' => [Scope::navigation(), Key::char('j'), Action::MoveDown, TRUE];
    yield 'nav k moves up' => [Scope::navigation(), Key::char('k'), Action::MoveUp, TRUE];
    yield 'nav h moves left' => [Scope::navigation(), Key::char('h'), Action::MoveLeft, TRUE];
    yield 'nav l moves right' => [Scope::navigation(), Key::char('l'), Action::MoveRight, TRUE];
    yield 'nav arrows still work' => [Scope::navigation(), Key::named(KeyName::Down), Action::MoveDown, TRUE];
    yield 'select j moves down' => [Scope::field(FieldType::Select), Key::char('j'), Action::MoveDown, TRUE];
    // A text field must keep j typeable, so it is not movement there.
    yield 'text j is not movement' => [Scope::field(FieldType::Text), Key::char('j'), Action::MoveDown, FALSE];
  }

  public function testOverrideRetunesOneScope(): void {
    $map = KeyMapManager::create('default', [
      new Binding(Scope::field(FieldType::Select), Action::Accept, 'l', KeyName::Enter),
    ])->forField(FieldType::Select);

    $this->assertTrue($map->matches(Key::char('l'), Action::Accept));
    $this->assertTrue($map->matches(Key::named(KeyName::Enter), Action::Accept));
  }

  public function testScopeAccessorResolvesEachKind(): void {
    $map = KeyMapManager::create();

    $this->assertInstanceOf(ScopedKeyMap::class, $map->scope(Scope::base()));
    $this->assertSame($map->navigation(), $map->scope(Scope::navigation()));
    $this->assertSame($map->forField(FieldType::Textarea), $map->scope(Scope::field(FieldType::Textarea)));
  }

  public function testKeysForAndPrimaryAndUnbound(): void {
    $nav = KeyMapManager::create()->navigation();

    $this->assertTrue($nav->primary(Action::MoveUp)?->is(KeyName::Up));
    $this->assertCount(1, $nav->keysFor(Action::Quit));

    // Newline is not a navigation action.
    $this->assertNotInstanceOf(Key::class, $nav->primary(Action::NewLine));
    $this->assertSame([], $nav->keysFor(Action::NewLine));
    $this->assertFalse($nav->matches(Key::named(KeyName::Enter), Action::NewLine));
  }

  public function testNormalisesAllKeyForms(): void {
    $map = new KeyMap([
      new Binding(Scope::field(FieldType::Select), Action::Accept, Key::named(KeyName::Tab), KeyName::Enter, 'l'),
    ]);
    $select = $map->forField(FieldType::Select);

    $this->assertTrue($select->matches(Key::named(KeyName::Tab), Action::Accept));
    $this->assertTrue($select->matches(Key::named(KeyName::Enter), Action::Accept));
    $this->assertTrue($select->matches(Key::char('l'), Action::Accept));
  }

  #[DataProvider('dataProviderFailsLoudly')]
  public function testFailsLoudly(Binding $binding, string $message): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($message);

    KeyMapManager::create('default', [$binding]);
  }

  public static function dataProviderFailsLoudly(): \Iterator {
    yield 'conflict in one scope' => [
      new Binding(Scope::navigation(), Action::Quit, KeyName::Enter),
      'bound to both Activate and Quit in the navigation scope',
    ];
    yield 'printable char in the base scope' => [
      new Binding(Scope::base(), Action::Accept, 'x'),
      'base scope may not bind the printable character',
    ];
    yield 'printable char in a text-entry scope' => [
      new Binding(Scope::field(FieldType::Search), Action::MoveDown, 'j'),
      'search scope consumes typed characters',
    ];
    yield 'multi-character binding' => [
      new Binding(Scope::field(FieldType::Select), Action::Accept, 'ab'),
      'must be a single character',
    ];
  }

  public function testUnknownPresetThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown key-map preset "nope"');

    KeyMapManager::create('nope');
  }

  public function testRegisterAndSelectByName(): void {
    KeyMapManager::register('registered-vim', VimKeyMap::class);

    $map = KeyMapManager::create('registered-vim')->navigation();

    $this->assertTrue($map->matches(Key::char('j'), Action::MoveDown));
  }

  public function testRegisterRejectsNonPreset(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('must extend');

    KeyMapManager::register('bad', \stdClass::class);
  }

  public function testCreateAcceptsPresetClassName(): void {
    $map = KeyMapManager::create(VimKeyMap::class)->navigation();

    $this->assertTrue($map->matches(Key::char('k'), Action::MoveUp));
  }

  public function testEmptyPresetNameIsDefault(): void {
    $map = KeyMapManager::create('')->forField(FieldType::Textarea);

    $this->assertTrue($map->matches(Key::named(KeyName::Enter), Action::NewLine));
  }

  #[DataProvider('dataProviderScope')]
  public function testScope(Scope $scope, string $token, string $label, bool $consumes_text): void {
    $this->assertSame($token, $scope->token());
    $this->assertSame($label, $scope->label());
    $this->assertSame($consumes_text, $scope->consumesText());
  }

  public static function dataProviderScope(): \Iterator {
    yield 'base' => [Scope::base(), '@base', 'base', FALSE];
    yield 'navigation' => [Scope::navigation(), '@navigation', 'navigation', FALSE];
    yield 'text is a text-entry scope' => [Scope::field(FieldType::Text), 'field:Text', 'text', TRUE];
    yield 'select is not a text-entry scope' => [Scope::field(FieldType::Select), 'field:Select', 'select', FALSE];
  }

  public function testBindingHoldsItsDeclaration(): void {
    $binding = new Binding(Scope::navigation(), Action::Quit, KeyName::Escape, 'x');

    $this->assertTrue($binding->scope->navigation);
    $this->assertSame(Action::Quit, $binding->action);
    $this->assertSame([KeyName::Escape, 'x'], $binding->keys);
  }

}
