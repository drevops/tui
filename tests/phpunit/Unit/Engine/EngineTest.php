<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Engine;

use DrevOps\Tui\Builder\FieldBuilder;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\Engine;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Tests\Fixtures\Handler\Spy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the generic lifecycle engine with discovered static behaviour.
 */
#[CoversClass(Engine::class)]
#[CoversClass(EngineException::class)]
#[Group('engine')]
final class EngineTest extends TestCase {

  protected function setUp(): void {
    parent::setUp();
    Spy::$calls = [];
  }

  public function testDiscoveredStaticsRunInOrder(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('spy')->default('seed');
      $p->text('plain');
    });

    $answers = $engine->collect(['spy' => 'given'], new Context('project'));

    // The supplied input flows through the discovered static transform.
    $this->assertSame('given!', $answers['spy']);
    // A field with no input keeps its default untouched by the guards.
    $this->assertSame('', $answers['plain']);
    // Lifecycle order per field: normalize first, then validate.
    $this->assertSame(['transform', 'validate'], Spy::$calls);
  }

  public function testSuppliedInputWins(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('spy');
    });

    $answers = $engine->collect(['spy' => 'given'], new Context('project'));

    $this->assertSame('given!', $answers['spy']);
    $this->assertSame(['transform', 'validate'], Spy::$calls);
  }

  public function testInvalidValueThrows(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('machine_name');
    });

    $this->expectException(EngineException::class);
    $this->expectExceptionMessage('Invalid value for field "machine_name"');
    // The MachineName fixture rejects an empty supplied input.
    $engine->collect(['machine_name' => ''], new Context('project'));
  }

  public function testDiscoveredTransformNormalizes(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('machine_name');
    });

    $answers = $engine->collect(['machine_name' => 'ACME'], new Context('project'));

    $this->assertSame('acme', $answers['machine_name']);
  }

  #[DataProvider('dataProviderCollectRejectsNonSelectableOption')]
  public function testCollectRejectsNonSelectableOption(\Closure $build, mixed $value, string $message): void {
    $engine = $this->engine($build);

    $this->expectException(EngineException::class);
    $this->expectExceptionMessage($message);
    $engine->collect(['choice' => $value], new Context('project'));
  }

  public static function dataProviderCollectRejectsNonSelectableOption(): \Iterator {
    yield 'select disabled' => [static fn(PanelBuilder $p): FieldBuilder => self::choiceOptions($p->select('choice')), 'demo', 'Invalid value for field "choice": option "demo" is disabled: unavailable'];
    yield 'select unknown' => [static fn(PanelBuilder $p): FieldBuilder => self::choiceOptions($p->select('choice')), 'bogus', 'Invalid value for field "choice": value "bogus" is not one of: standard, minimal'];
    yield 'search disabled' => [static fn(PanelBuilder $p): FieldBuilder => self::choiceOptions($p->search('choice')), 'demo', 'Invalid value for field "choice": option "demo" is disabled: unavailable'];
    yield 'search unknown' => [static fn(PanelBuilder $p): FieldBuilder => self::choiceOptions($p->search('choice')), 'bogus', 'Invalid value for field "choice": value "bogus" is not one of: standard, minimal'];
    yield 'multiselect disabled' => [static fn(PanelBuilder $p): FieldBuilder => self::choiceOptions($p->multiselect('choice')), ['demo'], 'Invalid value for field "choice": option "demo" is disabled: unavailable'];
    yield 'multiselect unknown' => [static fn(PanelBuilder $p): FieldBuilder => self::choiceOptions($p->multiselect('choice')), ['bogus'], 'Invalid value for field "choice": value "bogus" is not one of: standard, minimal'];
    yield 'multisearch disabled' => [static fn(PanelBuilder $p): FieldBuilder => self::choiceOptions($p->multisearch('choice')), ['demo'], 'Invalid value for field "choice": option "demo" is disabled: unavailable'];
    yield 'multisearch unknown' => [static fn(PanelBuilder $p): FieldBuilder => self::choiceOptions($p->multisearch('choice')), ['bogus'], 'Invalid value for field "choice": value "bogus" is not one of: standard, minimal'];
  }

  public function testCollectAcceptsSelectableOptions(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->select('profile')->option('standard')->option('minimal');
      $p->multiselect('mods')->option('a')->option('b');
      $p->search('engine')->option('solr')->option('none');
      $p->multisearch('tags')->option('x')->option('y');
    });

    $answers = $engine->collect(['profile' => 'standard', 'mods' => ['a', 'b'], 'engine' => 'solr', 'tags' => ['x']], new Context('project'));

    $this->assertSame('standard', $answers['profile']);
    $this->assertSame(['a', 'b'], $answers['mods']);
    $this->assertSame('solr', $answers['engine']);
    $this->assertSame(['x'], $answers['tags']);
  }

  public function testCollectRejectsNonArrayMultiValue(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->multiselect('mods')->option('a')->option('b');
    });

    $this->expectException(EngineException::class);
    $this->expectExceptionMessage('Invalid value for field "mods": value must be a list');
    $engine->collect(['mods' => 'notalist'], new Context('project'));
  }

  /**
   * Add a shared standard/minimal/disabled option set to a choice builder.
   *
   * @param \DrevOps\Tui\Builder\FieldBuilder $builder
   *   The choice field builder.
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The same builder, for chaining.
   */
  protected static function choiceOptions(FieldBuilder $builder): FieldBuilder {
    return $builder->option('standard')->option('minimal')->option('demo', 'Demo', disabled: TRUE, disabled_reason: 'unavailable');
  }

  /**
   * Build an engine over a single panel wired to the fixture namespace.
   *
   * @param \Closure $build
   *   The callback receiving the panel builder to declare its fields.
   */
  protected function engine(\Closure $build): Engine {
    $config = Form::create('T')->panel('p', 'p', $build)->build();
    $registry = new HandlerRegistry(['DrevOps\\Tui\\Tests\\Fixtures\\Handler']);

    return new Engine($config, $registry);
  }

}
