<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Config\NumberBounds;
use DrevOps\Tui\Config\Option;
use DrevOps\Tui\Config\OptionKind;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\ConfirmWidget;
use DrevOps\Tui\Widget\FilePickerWidget;
use DrevOps\Tui\Widget\MultiSearchWidget;
use DrevOps\Tui\Widget\MultiSelectWidget;
use DrevOps\Tui\Widget\NumberWidget;
use DrevOps\Tui\Widget\PasswordWidget;
use DrevOps\Tui\Widget\PauseWidget;
use DrevOps\Tui\Widget\SearchWidget;
use DrevOps\Tui\Widget\SelectWidget;
use DrevOps\Tui\Widget\SuggestWidget;
use DrevOps\Tui\Widget\TextareaWidget;
use DrevOps\Tui\Widget\TextWidget;
use DrevOps\Tui\Widget\ToggleWidget;
use DrevOps\Tui\Widget\WidgetFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the widget factory.
 */
#[CoversClass(WidgetFactory::class)]
#[Group('widget')]
final class WidgetFactoryTest extends TestCase {

  public function testCreatesByType(): void {
    $factory = new WidgetFactory();

    $this->assertInstanceOf(TextWidget::class, $factory->create($this->field(FieldType::Text), 'x'));
    $this->assertInstanceOf(ConfirmWidget::class, $factory->create($this->field(FieldType::Confirm), TRUE));
    $this->assertInstanceOf(ToggleWidget::class, $factory->create($this->fieldWithOptions(FieldType::Toggle), 'a'));
    $this->assertInstanceOf(SelectWidget::class, $factory->create($this->fieldWithOptions(FieldType::Select), 'a'));
    $this->assertInstanceOf(MultiSelectWidget::class, $factory->create($this->fieldWithOptions(FieldType::MultiSelect), ['a']));
    $this->assertInstanceOf(SuggestWidget::class, $factory->create($this->fieldWithOptions(FieldType::Suggest), 'a'));
    $this->assertInstanceOf(NumberWidget::class, $factory->create($this->field(FieldType::Number), 42));
    $this->assertInstanceOf(TextareaWidget::class, $factory->create($this->field(FieldType::Textarea), 'x'));
    $this->assertInstanceOf(PasswordWidget::class, $factory->create($this->field(FieldType::Password), 'x'));
    $this->assertInstanceOf(SearchWidget::class, $factory->create($this->fieldWithOptions(FieldType::Search), 'a'));
    $this->assertInstanceOf(MultiSearchWidget::class, $factory->create($this->fieldWithOptions(FieldType::MultiSearch), ['a']));
    $this->assertInstanceOf(FilePickerWidget::class, $factory->create($this->field(FieldType::FilePicker), '/tmp'));
    $this->assertInstanceOf(FilePickerWidget::class, $factory->create($this->field(FieldType::MultiFilePicker), ['/tmp']));
    $this->assertInstanceOf(PauseWidget::class, $factory->create($this->field(FieldType::Pause), TRUE));
  }

  public function testFilePickerFlagsPassedThrough(): void {
    $single = new Field('f', 'F', '', FieldType::FilePicker, '', pickerStart: '/nonexistent');
    $multi = new Field('g', 'G', '', FieldType::MultiFilePicker, [], pickerStart: '/nonexistent');

    // The single picker yields a string; a current path outside the start is
    // ignored and the missing directory lists nothing, so the value is empty.
    $this->assertSame('', (new WidgetFactory())->create($single, 'x')->value());

    // The multiple picker yields a list seeded from the current value, proving
    // the multiple flag is threaded through.
    $this->assertSame(['/a', '/b'], (new WidgetFactory())->create($multi, ['/a', '/b'])->value());
  }

  public function testPasswordFlagsPassedThrough(): void {
    $field = new Field('f', 'F', '', FieldType::Password, '', revealable: TRUE, confirm: TRUE);

    $widget = (new WidgetFactory())->create($field, 'secret');

    // Revealable shows through the widget owning its own hint line.
    $this->assertTrue($widget->rendersHint());

    // Confirm shows through the two-step flow: the first Enter does not accept.
    $widget->handle(Key::named(KeyName::Enter));
    $this->assertFalse($widget->isComplete());
  }

  public function testNumberSeededFromIntCurrent(): void {
    $widget = (new WidgetFactory())->create($this->field(FieldType::Number), 8080);

    $this->assertSame(8080, $widget->value());
  }

  public function testNumberWithNonNumericCurrentIsEmpty(): void {
    $widget = (new WidgetFactory())->create($this->field(FieldType::Number), 'oops');

    $this->assertSame(0, $widget->value());
  }

  public function testNumberBoundsPassedThrough(): void {
    $field = new Field('f', 'F', '', FieldType::Number, 0, bounds: new NumberBounds(0, 10));

    $widget = (new WidgetFactory())->create($field, 5);

    // Bounds show through the widget owning its own hint line and stepping.
    $this->assertTrue($widget->rendersHint());
    $widget->handle(Key::named(KeyName::Up));
    $this->assertSame(6, $widget->value());
  }

  public function testSeedsCurrentValue(): void {
    $widget = (new WidgetFactory())->create($this->field(FieldType::Text), 'Acme');

    $this->assertSame('Acme', $widget->value());
  }

  public function testMultiselectWithNonArrayValueHasNoDefaults(): void {
    $widget = (new WidgetFactory())->create($this->fieldWithOptions(FieldType::MultiSelect), 'notalist');

    $this->assertSame([], $widget->value());
  }

  public function testTextareaExternalEditorOfferedWhenOptedInAndAvailable(): void {
    $field = new Field('f', 'F', '', FieldType::Textarea, '', externalEditor: TRUE);

    $widget = (new WidgetFactory(externalEditorAvailable: TRUE))->create($field, 'x');
    $this->assertInstanceOf(TextareaWidget::class, $widget);

    $widget->handle(Key::char("\x05"));
    $this->assertTrue($widget->wantsExternalEdit());
  }

  public function testTextareaExternalEditorNotOfferedWhenUnavailable(): void {
    $field = new Field('f', 'F', '', FieldType::Textarea, '', externalEditor: TRUE);

    $widget = (new WidgetFactory(externalEditorAvailable: FALSE))->create($field, 'x');
    $this->assertInstanceOf(TextareaWidget::class, $widget);

    $widget->handle(Key::char("\x05"));
    $this->assertFalse($widget->wantsExternalEdit());
  }

  public function testTextareaExternalEditorNotOfferedWhenNotOptedIn(): void {
    $widget = (new WidgetFactory(externalEditorAvailable: TRUE))->create($this->field(FieldType::Textarea), 'x');
    $this->assertInstanceOf(TextareaWidget::class, $widget);

    $widget->handle(Key::char("\x05"));
    $this->assertFalse($widget->wantsExternalEdit());
  }

  public function testInjectsScopedKeymapIntoWidget(): void {
    // The vim preset binds j to move-down in the select scope, so the injected
    // widget responds to j where a default-preset widget would not.
    $widget = (new WidgetFactory(KeyMapManager::create('vim')))->create($this->fieldWithOptions(FieldType::Select), 'a');

    $widget->handle(Key::char('j'));

    $this->assertSame('b', $widget->value());
  }

  public function testPageSizePassedThrough(): void {
    $options = ['a' => new Option('a', 'A'), 'b' => new Option('b', 'B'), 'c' => new Option('c', 'C')];
    $field = new Field('f', 'F', '', FieldType::Select, '', $options, pageSize: 2);

    $view = (new WidgetFactory())->create($field, 'a')->view(new DefaultTheme());

    // A page size of 2 over three options hides the last one and shows the
    // "more below" indicator, proving the field's page size reached the widget.
    $this->assertStringContainsString('▼', $view);
    $this->assertStringNotContainsString('C', Ansi::strip($view));
  }

  public function testSuggestReceivesSelectableValuesOnly(): void {
    $field = new Field('tz', 'TZ', '', FieldType::Suggest, '', [
      new Option('utc', 'UTC'),
      new Option('gmt', 'GMT', '', OptionKind::Option, TRUE),
      new Option('', '', '', OptionKind::Separator),
    ]);

    $widget = (new WidgetFactory())->create($field, '');
    $view = $widget->view(new DefaultTheme());

    $this->assertStringContainsString('utc', $view);
    $this->assertStringNotContainsString('gmt', $view);
  }

  public function testTextCompletionStaticListReachesWidget(): void {
    $field = new Field('name', 'Name', '', FieldType::Text, '', completion: ['acme-site']);

    $view = (new WidgetFactory())->create($field, 'ac')->view(new DefaultTheme());

    // The matching candidate's remaining suffix shows as dimmed ghost-text.
    $this->assertStringContainsString('me-site', $view);
  }

  public function testTextCompletionClosureReceivesAnswers(): void {
    $seen = [];
    $field = new Field('repo', 'Repo', '', FieldType::Text, '', completion: function (array $answers) use (&$seen): array {
      $seen = $answers;

      return ['acme-site'];
    });

    $view = (new WidgetFactory())->create($field, 'ac', ['owner' => 'acme'])->view(new DefaultTheme());

    // The closure is handed the answers collected so far and its result reaches
    // the widget as ghost-text.
    $this->assertSame(['owner' => 'acme'], $seen);
    $this->assertStringContainsString('me-site', $view);
  }

  public function testTextCompletionCoercesInvalidResult(): void {
    // A mistyped source degrades to no completion rather than erroring: a list
    // with non-strings is filtered, and a non-list result is ignored.
    $items = new Field('a', 'A', '', FieldType::Text, '', completion: fn (array $answers): array => [123, NULL]);
    $this->assertStringNotContainsString("\033[90m", (new WidgetFactory())->create($items, 'ac')->view(new DefaultTheme()));

    $scalar = new Field('b', 'B', '', FieldType::Text, '', completion: fn (array $answers): string => 'oops');
    $this->assertStringNotContainsString("\033[90m", (new WidgetFactory())->create($scalar, 'ac')->view(new DefaultTheme()));
  }

  /**
   * A field of the given type.
   *
   * @param \DrevOps\Tui\Config\FieldType $type
   *   The field type.
   */
  protected function field(FieldType $type): Field {
    return new Field('f', 'F', '', $type, '');
  }

  /**
   * A choice field of the given type with two options.
   *
   * @param \DrevOps\Tui\Config\FieldType $type
   *   The field type.
   */
  protected function fieldWithOptions(FieldType $type): Field {
    return new Field('f', 'F', '', $type, '', ['a' => new Option('a', 'A'), 'b' => new Option('b', 'B')]);
  }

}
