<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Builder;

use DrevOps\Tui\Builder\FieldBuilder;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Config\ConfigException;
use DrevOps\Tui\Config\DateBounds;
use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Config\FilePickerMode;
use DrevOps\Tui\Config\Fixup;
use DrevOps\Tui\Config\NumberBounds;
use DrevOps\Tui\Config\OptionKind;
use DrevOps\Tui\Config\Weekday;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Discovery\Dotenv;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Binding;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the fluent form builder.
 */
#[CoversClass(Form::class)]
#[CoversClass(PanelBuilder::class)]
#[CoversClass(FieldBuilder::class)]
#[CoversClass(Translator::class)]
#[Group('config')]
final class FormTest extends TestCase {

  public function testTranslator(): void {
    $translator = new Translator('es');

    $config = Form::create('Demo')->translator($translator)->build();

    $this->assertSame($translator, $config->translator);
  }

  public function testBuildsExpectedConfig(): void {
    $fixup = new Fixup(set: 'a', to: 'b', when: new Condition('x', eq: 'y'));

    $config = Form::create('My app', 'the project')
      ->theme('dark')
      ->banner('LOGO')
      ->buttons(TRUE, 'Install', 'Quit')
      ->clearOnExit(FALSE)
      ->footer(FALSE)
      ->color(TRUE)
      ->unicode(FALSE)
      ->envPrefix('APP_')
      ->fixup($fixup)
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->description('General settings.');
        $p->text('name', 'Site name')->description('The name.')->required()->weight(10)->default('Acme');
        $p->text('machine_name', 'Machine name')->derive(new Derive('{{ name }}'));
        $p->select('profile', 'Profile')->options(['standard' => 'Standard', 'minimal' => 'Minimal'])->default('standard');
        $p->multiselect('services', 'Services')->option('solr', 'Solr', 'Search')->option('redis', 'Redis');
        $p->confirm('docs', 'Keep docs?')->default(TRUE)->when(new Condition('profile', eq: 'standard'));
        $p->toggle('visibility', 'Visibility')->options(['public' => 'Public', 'private' => 'Private'])->default('private');
        $p->password('secret', 'Secret')->revealable()->confirm();
        $p->suggest('timezone', 'Timezone')->discover(new Dotenv('TZ'));
        $p->reorder('ranking', 'Priorities')->options(['fast' => 'Fast', 'cheap' => 'Cheap', 'good' => 'Good'])->default(['good', 'fast']);
        $p->panel('advanced', 'Advanced', function (PanelBuilder $sp): void {
          $sp->text('webroot', 'Web root')->default('web');
        });
      })
      ->build();

    $this->assertSame('My app', $config->title);
    $this->assertSame('the project', $config->subject);
    $this->assertSame('dark', $config->theme);
    $this->assertSame('LOGO', $config->banner);
    $this->assertTrue($config->buttons);
    $this->assertSame('Install', $config->submitLabel);
    $this->assertSame('Quit', $config->cancelLabel);
    $this->assertFalse($config->clearOnExit);
    $this->assertFalse($config->footer);
    $this->assertTrue($config->color);
    $this->assertFalse($config->unicode);
    $this->assertSame('APP_', $config->envPrefix);
    $this->assertSame([$fixup], $config->fixups);
    // A form declares no translator by default, leaving every string English.
    $this->assertNotInstanceOf(Translator::class, $config->translator);
    $this->assertSame('General settings.', $config->panels[0]->description);

    $name = $config->field('name');
    $this->assertInstanceOf(Field::class, $name);
    $this->assertSame('Site name', $name->label);
    $this->assertSame('The name.', $name->description);
    $this->assertSame(FieldType::Text, $name->type);
    $this->assertSame('Acme', $name->default);
    $this->assertTrue($name->required);
    $this->assertSame(10, $name->weight);

    $machine = $config->field('machine_name');
    $this->assertInstanceOf(Field::class, $machine);
    $this->assertSame('{{ name }}', $machine->derive?->template);

    $profile = $config->field('profile');
    $this->assertInstanceOf(Field::class, $profile);
    $this->assertSame(FieldType::Select, $profile->type);
    $this->assertSame('standard', $profile->default);
    $this->assertSame('Standard', $profile->option('standard')?->label);

    $services = $config->field('services');
    $this->assertInstanceOf(Field::class, $services);
    $this->assertSame(FieldType::MultiSelect, $services->type);
    $this->assertSame('Search', $services->option('solr')?->description);

    $docs = $config->field('docs');
    $this->assertInstanceOf(Field::class, $docs);
    $this->assertSame(FieldType::Confirm, $docs->type);
    $this->assertTrue($docs->default);
    $this->assertSame(['field' => 'profile', 'eq' => 'standard'], $docs->when?->toArray());

    $visibility = $config->field('visibility');
    $this->assertInstanceOf(Field::class, $visibility);
    $this->assertSame(FieldType::Toggle, $visibility->type);
    $this->assertSame('private', $visibility->default);
    $this->assertSame('Public', $visibility->option('public')?->label);

    $secret = $config->field('secret');
    $this->assertInstanceOf(Field::class, $secret);
    $this->assertSame(FieldType::Password, $secret->type);
    $this->assertTrue($secret->revealable);
    $this->assertTrue($secret->confirm);

    $timezone = $config->field('timezone');
    $this->assertInstanceOf(Field::class, $timezone);
    $this->assertSame(FieldType::Suggest, $timezone->type);
    $this->assertInstanceOf(Dotenv::class, $timezone->discover);
    $this->assertSame('TZ', $timezone->discover->key);

    $ranking = $config->field('ranking');
    $this->assertInstanceOf(Field::class, $ranking);
    $this->assertSame(FieldType::Reorder, $ranking->type);
    // A partial declared default is completed to a full ranking in declared
    // order: the given values first, the remaining options appended.
    $this->assertSame(['good', 'fast', 'cheap'], $ranking->default);

    $webroot = $config->field('webroot');
    $this->assertInstanceOf(Field::class, $webroot);
    $this->assertSame('web', $webroot->default);
    $this->assertSame('Advanced', $config->panels[0]->panels[0]->title);
  }

  public function testDefaultsAndFallbacks(): void {
    $config = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->text('t');
        $panel->select('s')->option('a');
        $panel->multiselect('m');
        $panel->confirm('c');
        $panel->suggest('g');
        $panel->number('n');
        $panel->calendar('dt');
        $panel->textarea('ta');
        $panel->password('pw');
        $panel->search('se')->option('a');
        $panel->multisearch('ms')->option('a');
        $panel->toggle('tg')->option('on', 'On')->option('off', 'Off');
        $panel->filePicker('fp');
        $panel->multiFilePicker('mfp');
        $panel->pause('pa');
        $panel->reorder('rk')->option('a')->option('b')->option('c');
      })
      ->build();

    // Type defaults when none is declared.
    $this->assertSame('', $config->field('t')?->default);
    $this->assertSame('', $config->field('s')?->default);
    $this->assertSame([], $config->field('m')?->default);
    $this->assertFalse($config->field('c')?->default);
    $this->assertSame('', $config->field('g')?->default);
    $this->assertSame(0, $config->field('n')?->default);
    // A date with no explicit default is empty; the widget opens on today.
    $this->assertSame('', $config->field('dt')?->default);
    $this->assertSame('', $config->field('ta')?->default);
    $this->assertSame('', $config->field('pw')?->default);
    // The password options are opt-in, so they default off.
    $this->assertFalse($config->field('pw')->revealable);
    $this->assertFalse($config->field('pw')->confirm);
    $this->assertSame('', $config->field('se')?->default);
    $this->assertSame([], $config->field('ms')?->default);
    // A toggle defaults to its first option, since it always holds a value.
    $this->assertSame('on', $config->field('tg')?->default);
    // A single picker defaults to an empty path; a multiple picker to no paths.
    $this->assertSame('', $config->field('fp')?->default);
    $this->assertSame([], $config->field('mfp')?->default);
    // A reorder with no declared default ranks every option in declared order.
    $this->assertSame(['a', 'b', 'c'], $config->field('rk')?->default);
    // The picker options are opt-in, so they default off.
    $this->assertSame(FilePickerMode::Any, $config->field('fp')->pickerMode);
    $this->assertSame('', $config->field('fp')->pickerStart);
    $this->assertSame([], $config->field('fp')->pickerExtensions);
    $this->assertFalse($config->field('fp')->pickerShowHidden);
    // A pause defaults to acknowledged so headless runs never block on it.
    $this->assertTrue($config->field('pa')?->default);

    // Label and option-label fall back to the id/value.
    $this->assertSame('t', $config->field('t')->label);
    $this->assertSame('a', $config->field('s')->option('a')?->label);

    // Config-level defaults.
    $this->assertSame('', $config->subject);
    $this->assertTrue($config->buttons);
    $this->assertTrue($config->footer);
    $this->assertSame('Submit', $config->submitLabel);
    $this->assertSame('', $config->theme);
    $this->assertNull($config->color);
    $this->assertSame('', $config->envPrefix);
    $this->assertSame('', $config->panels[0]->description);
  }

  public function testExternalEditorFlag(): void {
    $config = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->textarea('notes', 'Notes')->externalEditor();
        $panel->textarea('plain', 'Plain');
      })
      ->build();

    $this->assertTrue($config->field('notes')?->externalEditor);
    $this->assertFalse($config->field('plain')?->externalEditor);
  }

  public function testValidateAndTransformStored(): void {
    $validator = fn (mixed $v): ?string => NULL;
    $transformer = fn (mixed $v): mixed => $v;

    $config = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel) use ($validator, $transformer): void {
        $panel->text('x')->validate($validator)->transform($transformer);
      })
      ->build();

    $field = $config->field('x');
    $this->assertInstanceOf(Field::class, $field);
    $this->assertSame($validator, $field->validate);
    $this->assertSame($transformer, $field->transform);
  }

  public function testCompletionSourceStored(): void {
    $list = ['acme-site', 'acme-app'];
    $closure = fn (array $answers): array => [];

    $config = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel) use ($list, $closure): void {
        $panel->text('name', 'Name')->complete($list);
        $panel->text('repo', 'Repo')->complete($closure);
        $panel->text('plain', 'Plain');
      })
      ->build();

    $this->assertSame($list, $config->field('name')?->completion);
    $this->assertSame($closure, $config->field('repo')?->completion);
    // A field with no completion source defaults to an empty list.
    $this->assertSame([], $config->field('plain')?->completion);
  }

  public function testNumberBoundsAssembled(): void {
    $config = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->number('port', 'Port')->min(1)->max(65535)->step(5);
        $panel->number('plain', 'Plain');
      })
      ->build();

    $port = $config->field('port');
    $this->assertInstanceOf(Field::class, $port);
    $this->assertInstanceOf(NumberBounds::class, $port->bounds);
    $this->assertSame(1, $port->bounds->min);
    $this->assertSame(65535, $port->bounds->max);
    $this->assertSame(5, $port->bounds->step);

    // A number with nothing declared carries no bounds - behaviour unchanged.
    $this->assertNotInstanceOf(NumberBounds::class, $config->field('plain')?->bounds);
  }

  public function testDateBoundsAssembled(): void {
    $config = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->calendar('birthday', 'Birthday')->minDate('2000-01-01')->maxDate('2030-12-31')->weekStart(Weekday::Sunday);
        $panel->calendar('plain', 'Plain');
      })
      ->build();

    $birthday = $config->field('birthday');
    $this->assertInstanceOf(Field::class, $birthday);
    $this->assertInstanceOf(DateBounds::class, $birthday->dateBounds);
    $this->assertSame('2000-01-01', $birthday->dateBounds->min?->format('Y-m-d'));
    $this->assertSame('2030-12-31', $birthday->dateBounds->max?->format('Y-m-d'));
    $this->assertSame(Weekday::Sunday, $birthday->dateBounds->weekStart);

    // A date with nothing declared still carries bounds, defaulting to a
    // Monday-first, open range.
    $plain = $config->field('plain');
    $this->assertInstanceOf(DateBounds::class, $plain?->dateBounds);
    $this->assertNotInstanceOf(\DateTimeImmutable::class, $plain->dateBounds->min);
    $this->assertNotInstanceOf(\DateTimeImmutable::class, $plain->dateBounds->max);
    $this->assertSame(Weekday::Monday, $plain->dateBounds->weekStart);
  }

  public function testDateInvalidBoundThrows(): void {
    $this->expectException(ConfigException::class);
    $this->expectExceptionMessage('Field "d" declares an invalid date "2026-13-01".');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->calendar('d')->minDate('2026-13-01'))
      ->build();
  }

  public function testDateMinAfterMaxThrows(): void {
    $this->expectException(ConfigException::class);
    $this->expectExceptionMessage('Field "d" declares min date 2026-12-31 after max date 2026-01-01.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->calendar('d')->minDate('2026-12-31')->maxDate('2026-01-01'))
      ->build();
  }

  public function testDateBoundsIgnoredOnNonDateField(): void {
    $config = Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->text('t')->minDate('2020-01-01')->weekStart(Weekday::Sunday))
      ->build();

    // The date setters are inert on a non-date field: no bounds are attached.
    $this->assertNotInstanceOf(DateBounds::class, $config->field('t')?->dateBounds);
  }

  public function testNumberMinGreaterThanMaxThrows(): void {
    $this->expectException(ConfigException::class);
    $this->expectExceptionMessage('Field "n" declares min 10 greater than max 1.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->number('n')->min(10)->max(1))
      ->build();
  }

  public function testNumberNonPositiveStepThrows(): void {
    $this->expectException(ConfigException::class);
    $this->expectExceptionMessage('Field "n" declares a non-positive step 0.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->number('n')->step(0))
      ->build();
  }

  public function testPageSizeAssembled(): void {
    $config = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->search('paged', 'Paged')->options(['a' => 'A'])->pageSize(5);
        $panel->search('plain', 'Plain')->options(['a' => 'A']);
      })
      ->build();

    $this->assertSame(5, $config->field('paged')?->pageSize);

    // A field with nothing declared carries no page size and uses the default.
    $this->assertNull($config->field('plain')?->pageSize);
  }

  public function testNonPositivePageSizeThrows(): void {
    $this->expectException(ConfigException::class);
    $this->expectExceptionMessage('Field "n" declares a non-positive page size 0.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->search('n')->pageSize(0))
      ->build();
  }

  public function testFilePickerOptions(): void {
    $config = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->filePicker('config', 'Config')->start('/opt')->filesOnly()->extensions(['yml', 'yaml'])->showHidden();
        $panel->multiFilePicker('assets', 'Assets')->directoriesOnly();
      })
      ->build();

    $config_field = $config->field('config');
    $this->assertInstanceOf(Field::class, $config_field);
    $this->assertSame(FieldType::FilePicker, $config_field->type);
    $this->assertSame(FilePickerMode::File, $config_field->pickerMode);
    $this->assertSame('/opt', $config_field->pickerStart);
    $this->assertSame(['yml', 'yaml'], $config_field->pickerExtensions);
    $this->assertTrue($config_field->pickerShowHidden);

    $assets = $config->field('assets');
    $this->assertInstanceOf(Field::class, $assets);
    $this->assertSame(FieldType::MultiFilePicker, $assets->type);
    $this->assertSame(FilePickerMode::Directory, $assets->pickerMode);
  }

  public function testOptionKindsAndDisabled(): void {
    $config = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->select('profile')
          ->heading('Recommended')
          ->option('standard', 'Standard')
          ->separator()
          ->option('demo', 'Demo', 'A demo', disabled: TRUE, disabled_reason: 'requires PHP 8.4');
      })
      ->build();

    $profile = $config->field('profile');
    $this->assertInstanceOf(Field::class, $profile);

    $options = $profile->options;
    $this->assertCount(4, $options);
    $this->assertSame(OptionKind::Heading, $options[0]->kind);
    $this->assertSame('Recommended', $options[0]->label);
    $this->assertSame(OptionKind::Option, $options[1]->kind);
    $this->assertSame(OptionKind::Separator, $options[2]->kind);
    $this->assertTrue($options[3]->disabled);
    $this->assertSame('requires PHP 8.4', $options[3]->disabledReason);

    $this->assertSame(['standard'], $profile->selectableValues());
  }

  public function testRepeatedOptionValueOverridesInPlace(): void {
    $config = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->select('s')->option('a', 'First')->separator()->option('a', 'Second');
      })
      ->build();

    $field = $config->field('s');
    $this->assertInstanceOf(Field::class, $field);

    // The second declaration overrides the first in place; the separator stays.
    $this->assertCount(2, $field->options);
    $this->assertSame('Second', $field->option('a')?->label);
    $this->assertSame(['a'], $field->selectableValues());
  }

  public function testDuplicateFieldIdThrows(): void {
    $this->expectException(ConfigException::class);
    $this->expectExceptionMessage('Duplicate field id "x".');

    Form::create('T')
      ->panel('a', 'A', fn(PanelBuilder $p): FieldBuilder => $p->text('x'))
      ->panel('b', 'B', fn(PanelBuilder $p): FieldBuilder => $p->text('x'))
      ->build();
  }

  public function testToggleWithoutTwoOptionsThrows(): void {
    $this->expectException(ConfigException::class);
    $this->expectExceptionMessage('Toggle field "t" must have exactly two options, 1 given.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->toggle('t')->option('only'))
      ->build();
  }

  #[DataProvider('dataProviderToggleInvalidDefaultThrows')]
  public function testToggleInvalidDefaultThrows(mixed $default): void {
    $this->expectException(ConfigException::class);
    $this->expectExceptionMessage('Toggle field "t" default must be one of: a, b.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->toggle('t')->option('a')->option('b')->default($default))
      ->build();
  }

  /**
   * Data provider for testToggleInvalidDefaultThrows().
   *
   * @return \Iterator<string, array{mixed}>
   *   A default value that is not one of the toggle's option values.
   */
  public static function dataProviderToggleInvalidDefaultThrows(): \Iterator {
    yield 'unknown string' => ['c'];
    yield 'boolean' => [TRUE];
    yield 'integer' => [123];
    yield 'null' => [NULL];
  }

  public function testToggleNumericStringOptionsDefaultToFirstValue(): void {
    $config = Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->toggle('flag')->option('0', 'Off')->option('1', 'On'))
      ->build();

    // The implicit default is the first option's value "0" as a string, not a
    // numeric-string coerced to int by the array key.
    $this->assertSame('0', $config->field('flag')?->default);
  }

  public function testReorderToleratesDirtyDefault(): void {
    $config = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->reorder('rk')->option('a')->option('b')->default('notalist');
        $p->reorder('rk2')->option('a')->option('b')->default(['b', 42, 'a']);
      })
      ->build();

    // A non-list default falls back to the full declared order.
    $this->assertSame(['a', 'b'], $config->field('rk')?->default);
    // Non-string entries are ignored; the remaining values still complete it.
    $this->assertSame(['b', 'a'], $config->field('rk2')?->default);
  }

  public function testReorderWithFewerThanTwoOptionsThrows(): void {
    $this->expectException(ConfigException::class);
    $this->expectExceptionMessage('Reorder field "r" must have at least two options, 1 given.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->reorder('r')->option('only'))
      ->build();
  }

  public function testReorderWithStructuralOptionThrows(): void {
    $this->expectException(ConfigException::class);
    $this->expectExceptionMessage('Reorder field "r" allows only plain options - no headings, separators or disabled rows.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->reorder('r')->option('a')->separator()->option('b'))
      ->build();
  }

  public function testDefaultKeymapWhenUnset(): void {
    $config = Form::create('T')
      ->panel('a', 'A', fn(PanelBuilder $p): FieldBuilder => $p->text('x'))
      ->build();

    $this->assertInstanceOf(KeyMap::class, $config->keymap);
    $this->assertTrue($config->keymap->navigation()->matches(Key::named(KeyName::Up), Action::MoveUp));
  }

  public function testKeysAppliesPresetAndOverrides(): void {
    $config = Form::create('T')
      ->keys('vim', [new Binding(Scope::navigation(), Action::Quit, 'x')])
      ->panel('a', 'A', fn(PanelBuilder $p): FieldBuilder => $p->text('x'))
      ->build();

    $this->assertInstanceOf(KeyMap::class, $config->keymap);
    $nav = $config->keymap->navigation();
    $this->assertTrue($nav->matches(Key::char('j'), Action::MoveDown));
    $this->assertTrue($nav->matches(Key::char('x'), Action::Quit));
  }

  public function testInvalidKeyBindingThrowsAtBuild(): void {
    $this->expectException(\InvalidArgumentException::class);

    Form::create('T')
      ->keys('default', [new Binding(Scope::navigation(), Action::Quit, KeyName::Enter)])
      ->panel('a', 'A', fn(PanelBuilder $p): FieldBuilder => $p->text('x'))
      ->build();
  }

}
