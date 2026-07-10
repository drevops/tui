<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Schema;

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Schema\SchemaGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the schema generator.
 */
#[CoversClass(SchemaGenerator::class)]
#[Group('schema')]
final class SchemaGeneratorTest extends TestCase {

  public function testGenerate(): void {
    $config = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $profile = $p->select('profile', 'Profile')->description('The profile')->default('standard')->required();
        $profile->option('standard', 'Standard', 'Std')->option('minimal', 'Minimal');
        $p->text('theme')->derive(new Derive('{{profile}}'))->when(new Condition('profile', eq: 'standard'));
        $p->number('port', 'Port')->min(1)->max(65535)->step(5);
      })
      ->build();

    $expected = [
      'prompts' => [
        [
          'id' => 'profile',
          'type' => 'select',
          'label' => 'Profile',
          'description' => 'The profile',
          'options' => [
            ['value' => 'standard', 'label' => 'Standard', 'description' => 'Std'],
            ['value' => 'minimal', 'label' => 'Minimal', 'description' => ''],
          ],
          'default' => 'standard',
          'required' => TRUE,
          'min' => NULL,
          'max' => NULL,
          'step' => NULL,
          'when' => NULL,
          'derive' => NULL,
          'discover' => NULL,
          'depends_on' => [],
        ],
        [
          'id' => 'theme',
          'type' => 'text',
          'label' => 'theme',
          'description' => '',
          'options' => [],
          'default' => '',
          'required' => FALSE,
          'min' => NULL,
          'max' => NULL,
          'step' => NULL,
          'when' => ['field' => 'profile', 'eq' => 'standard'],
          'derive' => ['template' => '{{profile}}'],
          'discover' => NULL,
          'depends_on' => ['profile'],
        ],
        [
          'id' => 'port',
          'type' => 'number',
          'label' => 'Port',
          'description' => '',
          'options' => [],
          'default' => 0,
          'required' => FALSE,
          'min' => 1,
          'max' => 65535,
          'step' => 5,
          'when' => NULL,
          'derive' => NULL,
          'discover' => NULL,
          'depends_on' => [],
        ],
      ],
    ];

    $this->assertSame($expected, (new SchemaGenerator($config))->generate());
  }

  public function testExcludesNonSelectableOptions(): void {
    $config = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->select('profile', 'Profile')
          ->heading('Recommended')
          ->option('standard', 'Standard')
          ->separator()
          ->option('demo', 'Demo', disabled: TRUE, disabled_reason: 'nope');
      })
      ->build();

    $schema = (new SchemaGenerator($config))->generate();

    $this->assertSame([
      ['value' => 'standard', 'label' => 'Standard', 'description' => ''],
    ], $schema['prompts'][0]['options']);
  }

  public function testDependsOnCollectsNestedFieldRefs(): void {
    $config = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('a');
        $p->text('b');
        $p->text('c')->when(Condition::all(new Condition('a', eq: 'x'), new Condition('b', eq: 'y')));
      })
      ->build();

    $json = (string) json_encode((new SchemaGenerator($config))->generate());

    $this->assertStringContainsString('"depends_on":["a","b"]', $json);
  }

  public function testToggleDescribesBothValues(): void {
    $config = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->toggle('visibility', 'Visibility')->options(['public' => 'Public', 'private' => 'Private'])->default('public');
      })
      ->build();

    $json = (string) json_encode((new SchemaGenerator($config))->generate());

    $this->assertStringContainsString('"type":"toggle"', $json);
    $this->assertStringContainsString('"value":"public"', $json);
    $this->assertStringContainsString('"value":"private"', $json);
  }

  public function testRoundTripsThroughJson(): void {
    $config = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->confirm('x')->default(TRUE);
      })
      ->build();

    $schema = (new SchemaGenerator($config))->generate();
    $decoded = json_decode((string) json_encode($schema), TRUE);

    $this->assertSame($schema, $decoded);
  }

}
