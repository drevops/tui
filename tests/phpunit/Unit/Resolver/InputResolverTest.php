<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Resolver;

use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\FieldType;
use DrevOps\Tui\Resolver\InputResolver;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the non-interactive input resolver.
 */
#[CoversClass(InputResolver::class)]
#[Group('resolver')]
final class InputResolverTest extends TestCase {

  public function testEnvCoercion(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', [
      'APP_NAME' => 'Acme',
      'APP_AGREE' => 'yes',
      'APP_MODS' => 'a, b ,c',
    ]);

    $this->assertSame('Acme', $inputs['name']);
    $this->assertTrue($inputs['agree']);
    $this->assertSame(['a', 'b', 'c'], $inputs['mods']);
  }

  public function testConfirmFalsey(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', ['APP_AGREE' => 'no']);

    $this->assertFalse($inputs['agree']);
  }

  public function testToggleCoercion(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', ['APP_VIS' => 'private']);

    $this->assertSame('private', $inputs['vis']);
  }

  public function testEmptyMultiselect(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', ['APP_MODS' => '']);

    $this->assertSame([], $inputs['mods']);
  }

  public function testPromptsJsonWinsOverEnv(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '{"name": "FromPrompts", "agree": true}', [
      'APP_NAME' => 'FromEnv',
      'APP_AGREE' => 'no',
    ]);

    $this->assertSame('FromPrompts', $inputs['name']);
    $this->assertTrue($inputs['agree']);
  }

  public function testMissingEnvOmitsField(): void {
    $this->assertSame([], (new InputResolver('APP_'))->resolve($this->fields(), '', []));
  }

  public function testMalformedPromptsIgnored(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), 'not json', ['APP_NAME' => 'Acme']);

    $this->assertSame(['name' => 'Acme'], $inputs);
  }

  public function testPromptsFromFile(): void {
    vfsStream::setup('p', NULL, ['prompts.json' => '{"name": "FromFile"}']);

    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), vfsStream::url('p/prompts.json'), []);

    $this->assertSame('FromFile', $inputs['name']);
  }

  public function testDateCoercionPassesThroughString(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', ['APP_DUE' => '2026-07-15']);

    $this->assertSame('2026-07-15', $inputs['due']);
  }

  public function testEnvName(): void {
    $this->assertSame('APP_MACHINE_NAME', (new InputResolver('APP_'))->envName('machine_name'));
  }

  public function testFilePickerCoercion(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', [
      'APP_PATHS' => 'a/b, c/d',
      'APP_CFG' => '/etc/app.yml',
    ]);

    // A multiple picker splits a comma list; a single picker stays a string.
    $this->assertSame(['a/b', 'c/d'], $inputs['paths']);
    $this->assertSame('/etc/app.yml', $inputs['cfg']);
  }

  public function testNumberPauseAndMultisearchCoercion(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', [
      'APP_PORT' => ' 8080 ',
      'APP_ACK' => 'yes',
      'APP_TAGS' => 'a, b',
    ]);

    $this->assertSame(8080, $inputs['port']);
    $this->assertTrue($inputs['ack']);
    $this->assertSame(['a', 'b'], $inputs['tags']);
  }

  public function testReorderCoercion(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', ['APP_RANK' => 'c, a, b']);

    $this->assertSame(['c', 'a', 'b'], $inputs['rank']);
  }

  /**
   * Build one field of each coercible type for resolution.
   *
   * @return \DrevOps\Tui\Config\Field[]
   *   The fields.
   */
  protected function fields(): array {
    return [
      new Field('name', 'Name', '', FieldType::Text, ''),
      new Field('agree', 'Agree', '', FieldType::Confirm, FALSE),
      new Field('mods', 'Mods', '', FieldType::MultiSelect, []),
      new Field('port', 'Port', '', FieldType::Number, 0),
      new Field('ack', 'Ack', '', FieldType::Pause, TRUE),
      new Field('tags', 'Tags', '', FieldType::MultiSearch, []),
      new Field('rank', 'Rank', '', FieldType::Reorder, []),
      new Field('vis', 'Visibility', '', FieldType::Toggle, 'public'),
      new Field('paths', 'Paths', '', FieldType::MultiFilePicker, []),
      new Field('cfg', 'Config', '', FieldType::FilePicker, ''),
      new Field('due', 'Due', '', FieldType::Calendar, ''),
    ];
  }

}
