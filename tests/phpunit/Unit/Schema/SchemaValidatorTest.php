<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Schema;

use DrevOps\Tui\Builder\FieldBuilder;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Config\Config;
use DrevOps\Tui\Schema\SchemaValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the schema validator.
 */
#[CoversClass(SchemaValidator::class)]
#[Group('schema')]
final class SchemaValidatorTest extends TestCase {

  public function testValidPasses(): void {
    $errors = (new SchemaValidator($this->config()))->validate([
      'name' => 'Acme',
      'profile' => 'standard',
      'agree' => TRUE,
      'mods' => ['a', 'b'],
    ]);

    $this->assertSame([], $errors);
  }

  public function testMissingRequired(): void {
    $errors = (new SchemaValidator($this->config()))->validate(['profile' => 'standard']);

    $this->assertContains('Missing required question "name".', $errors);
  }

  public function testRequiredEmptyString(): void {
    $errors = (new SchemaValidator($this->config()))->validate(['name' => '']);

    $this->assertContains('Question "name" is required.', $errors);
  }

  public function testWrongType(): void {
    $errors = (new SchemaValidator($this->config()))->validate(['name' => 'Acme', 'agree' => 'yes']);

    $this->assertContains('Question "agree" must be a boolean.', $errors);
  }

  public function testInvalidSelectOption(): void {
    $errors = (new SchemaValidator($this->config()))->validate(['name' => 'Acme', 'profile' => 'bogus']);

    $this->assertContains('Question "profile": value "bogus" is not one of: standard, minimal.', $errors);
  }

  public function testDisabledSelectOptionRejected(): void {
    $errors = (new SchemaValidator($this->config()))->validate(['name' => 'Acme', 'profile' => 'demo']);

    $this->assertContains('Question "profile": option "demo" is disabled: unavailable.', $errors);
  }

  public function testInvalidMultiselectOption(): void {
    $errors = (new SchemaValidator($this->config()))->validate(['name' => 'Acme', 'mods' => ['a', 'z']]);

    $this->assertContains('Question "mods": value "z" is not one of: a, b.', $errors);
  }

  public function testDisabledMultiselectOptionRejected(): void {
    $errors = (new SchemaValidator($this->config()))->validate(['name' => 'Acme', 'mods' => ['a', 'c']]);

    $this->assertContains('Question "mods": option "c" is disabled.', $errors);
  }

  public function testMultiselectWrongType(): void {
    $errors = (new SchemaValidator($this->config()))->validate(['name' => 'Acme', 'mods' => 'notalist']);

    $this->assertContains('Question "mods" must be a list.', $errors);
  }

  public function testUnknownQuestion(): void {
    $errors = (new SchemaValidator($this->config()))->validate(['name' => 'Acme', 'bogus' => 'x']);

    $this->assertContains('Unknown question "bogus".', $errors);
  }

  public function testInactiveRequiredFieldSkipped(): void {
    // 'custom' is required but only appears when profile == custom.
    $errors = (new SchemaValidator($this->config()))->validate(['name' => 'Acme', 'profile' => 'standard']);

    $this->assertSame([], $errors);
  }

  public function testNumberAcceptsIntRejectsString(): void {
    $validator = new SchemaValidator($this->config());

    $this->assertSame([], $validator->validate(['name' => 'Acme', 'port' => 8080]));
    $this->assertContains('Question "port" must be a number.', $validator->validate(['name' => 'Acme', 'port' => '8080']));
  }

  public function testNumberBoundsRejectOutOfRange(): void {
    $validator = new SchemaValidator($this->config());

    $this->assertSame([], $validator->validate(['name' => 'Acme', 'port' => 8080]));
    $this->assertContains('Question "port" must be between 1 and 65535.', $validator->validate(['name' => 'Acme', 'port' => 99999]));
  }

  public function testDateAcceptsValidRejectsMalformed(): void {
    $validator = new SchemaValidator($this->config());
    $error = 'Question "due" must be a date (YYYY-MM-DD).';

    $this->assertSame([], $validator->validate(['name' => 'Acme', 'due' => '2026-07-15']));
    // A wrongly-padded value and an impossible calendar date are both rejected.
    $this->assertContains($error, $validator->validate(['name' => 'Acme', 'due' => '2026-7-5']));
    $this->assertContains($error, $validator->validate(['name' => 'Acme', 'due' => '2026-02-30']));
  }

  public function testDateBoundsRejectOutOfRange(): void {
    $validator = new SchemaValidator($this->config());
    $error = 'Question "due" must be between 2026-01-01 and 2026-12-31.';

    // The inclusive endpoints are accepted; a date on either side is rejected.
    $this->assertSame([], $validator->validate(['name' => 'Acme', 'due' => '2026-01-01']));
    $this->assertSame([], $validator->validate(['name' => 'Acme', 'due' => '2026-12-31']));
    $this->assertContains($error, $validator->validate(['name' => 'Acme', 'due' => '2025-12-31']));
    $this->assertContains($error, $validator->validate(['name' => 'Acme', 'due' => '2027-01-01']));
  }

  public function testPauseAcceptsBoolRejectsString(): void {
    $validator = new SchemaValidator($this->config());

    $this->assertSame([], $validator->validate(['name' => 'Acme', 'ack' => TRUE]));
    $this->assertContains('Question "ack" must be a boolean.', $validator->validate(['name' => 'Acme', 'ack' => 'yes']));
  }

  public function testSearchOptionMembership(): void {
    $validator = new SchemaValidator($this->config());

    $this->assertSame([], $validator->validate(['name' => 'Acme', 'engine' => 'solr']));
    $this->assertContains('Question "engine": value "bogus" is not one of: solr, none.', $validator->validate(['name' => 'Acme', 'engine' => 'bogus']));
  }

  public function testMultisearchOptionMembership(): void {
    $validator = new SchemaValidator($this->config());

    $this->assertSame([], $validator->validate(['name' => 'Acme', 'tags' => ['a']]));
    $this->assertContains('Question "tags": value "z" is not one of: a, b.', $validator->validate(['name' => 'Acme', 'tags' => ['z']]));
  }

  public function testToggleOptionMembership(): void {
    $validator = new SchemaValidator($this->config());

    $this->assertSame([], $validator->validate(['name' => 'Acme', 'visibility' => 'private']));
    $this->assertContains('Question "visibility": value "bogus" is not one of: public, private.', $validator->validate(['name' => 'Acme', 'visibility' => 'bogus']));
  }

  public function testNumericStringOptionMembership(): void {
    $config = Form::create('T')
      ->panel('p', 'p', fn(PanelBuilder $p): FieldBuilder => $p->toggle('flag')->option('0', 'Off')->option('1', 'On'))
      ->build();
    $validator = new SchemaValidator($config);

    // A numeric-string value stays valid: values are compared as strings.
    $this->assertSame([], $validator->validate(['flag' => '1']));
    $this->assertContains('Question "flag": value "2" is not one of: 0, 1.', $validator->validate(['flag' => '2']));
  }

  public function testFilePickerAcceptsString(): void {
    $validator = new SchemaValidator($this->config());

    $this->assertSame([], $validator->validate(['name' => 'Acme', 'cfg' => '/etc/app.yml']));
    $this->assertContains('Question "cfg" must be a string.', $validator->validate(['name' => 'Acme', 'cfg' => ['x']]));
  }

  public function testMultiFilePickerAcceptsList(): void {
    $validator = new SchemaValidator($this->config());

    $this->assertSame([], $validator->validate(['name' => 'Acme', 'paths' => ['/a', '/b']]));
    $this->assertContains('Question "paths" must be a list.', $validator->validate(['name' => 'Acme', 'paths' => 'notalist']));
  }

  /**
   * Build a config exercising every validation branch.
   */
  protected function config(): Config {
    return Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('name')->required();
        $p->select('profile')->option('standard')->option('minimal')->option('demo', 'Demo', disabled: TRUE, disabled_reason: 'unavailable');
        $p->confirm('agree');
        $p->multiselect('mods')->option('a')->option('b')->option('c', 'C', disabled: TRUE);
        $p->text('custom')->required()->when(new Condition('profile', eq: 'custom'));
        $p->number('port')->min(1)->max(65535);
        $p->date('due')->minDate('2026-01-01')->maxDate('2026-12-31');
        $p->pause('ack');
        $p->search('engine')->option('solr')->option('none');
        $p->multisearch('tags')->option('a')->option('b');
        $p->toggle('visibility')->option('public')->option('private');
        $p->filePicker('cfg');
        $p->multiFilePicker('paths');
      })
      ->build();
  }

}
