<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Translation;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Answers\SummaryFormatter;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Config\Config;
use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\PanelController;
use DrevOps\Tui\Schema\AgentHelp;
use DrevOps\Tui\Schema\SchemaValidator;
use DrevOps\Tui\Tests\Traits\ResetsTranslatorTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Translation\Translator;
use DrevOps\Tui\Widget\WidgetFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that chrome and questions render in the active language end to end.
 */
#[CoversClass(PanelController::class)]
#[CoversClass(DefaultTheme::class)]
#[CoversClass(WidgetFactory::class)]
#[CoversClass(SummaryFormatter::class)]
#[CoversClass(SchemaValidator::class)]
#[CoversClass(AgentHelp::class)]
#[Group('tui')]
final class TranslationRenderTest extends TestCase {

  use ResetsTranslatorTrait;

  protected function setUp(): void {
    Translator::setShared(new Translator('es', [dirname(__DIR__, 2) . '/Fixtures/translations-render']));
  }

  protected function config(): Config {
    return Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $panel): void {
        $panel->text('name', 'Site name')->description('The name.');
        $panel->select('plan', 'Plan')->options(['basic' => 'Basic tier']);
        $panel->confirm('agree', 'Agree');
      })
      ->build();
  }

  public function testInteractiveChromeAndQuestionsTranslated(): void {
    $controller = new PanelController($this->config(), new DefaultTheme(60, ['color' => FALSE]), [], []);

    $root = Ansi::strip($controller->frame(16));
    // The breadcrumb (config title) and the drill-in panel row (panel title).
    $this->assertStringContainsString('Demostracion', $root);
    $this->assertStringContainsString('General ES', $root);
    // Chrome: the submit/cancel buttons and a footer hint label.
    $this->assertStringContainsString('[ Enviar ]', $root);
    $this->assertStringContainsString('[ Cancelar ]', $root);
    $this->assertStringContainsString('mover', $root);

    // Drilling into the panel shows the field label and description translated.
    $controller->handle(Key::named(KeyName::Enter));
    $panel = Ansi::strip($controller->frame(16));
    $this->assertStringContainsString('Nombre del sitio', $panel);
    $this->assertStringContainsString('El nombre.', $panel);
  }

  public function testOptionLabelsTranslated(): void {
    $field = $this->config()->field('plan');
    $this->assertInstanceOf(Field::class, $field);

    $widget = (new WidgetFactory())->create($field, 'basic');

    $this->assertStringContainsString('Nivel basico', Ansi::strip($widget->view(new DefaultTheme(60, ['color' => FALSE]))));
  }

  public function testSummaryTranslated(): void {
    $answers = Answers::forConfig($this->config(), ['agree' => TRUE], ['agree' => Provenance::Edited]);

    $summary = (new SummaryFormatter())->format($answers);

    $this->assertStringContainsString('General ES', $summary);
    $this->assertStringContainsString('De acuerdo', $summary);
    $this->assertStringContainsString('si', $summary);
    $this->assertStringContainsString('editado', $summary);
  }

  public function testHeadlessMessagesTranslated(): void {
    $config = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $panel): void {
        $panel->text('name', 'Site name')->required();
      })
      ->build();

    // A headless validation error and the agent help both localize.
    $this->assertContains('Falta la pregunta obligatoria "name".', (new SchemaValidator($config))->validate([]));
    $this->assertStringContainsString('Preguntas:', (new AgentHelp($config, 'TUI_'))->generate());
  }

}
