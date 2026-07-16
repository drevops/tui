<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Testing;

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Testing\TuiTester;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the form-level scripted-keystroke harness.
 */
#[CoversClass(TuiTester::class)]
#[Group('testing')]
final class TuiTesterTest extends TestCase {

  public function testRunWithKeysAndStrings(): void {
    $tester = new TuiTester($this->form());

    $answers = $tester->run(
      Key::named(KeyName::Enter),
      Key::named(KeyName::Enter),
      'Ada',
      Key::named(KeyName::Enter),
      Key::named(KeyName::Escape),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    );

    $this->assertSame('Ada', $answers->value('name'));
    $this->assertSame('Ada', $tester->answers()->value('name'));
    $this->assertFalse($tester->isCancelled());
    $this->assertStringContainsString('Ada', $tester->display());
    $this->assertStringContainsString('Ada', $tester->output());
  }

  public function testRunWithRawByteSequences(): void {
    $tester = new TuiTester($this->form());

    // "\r" is Enter, "\033" is Escape, "\033[B" is Down.
    $answers = $tester->run("\r", "\r", 'Bo', "\r", "\033", "\033[B", "\r");

    $this->assertSame('Bo', $answers->value('name'));
    $this->assertFalse($tester->isCancelled());
  }

  public function testCancelButtonIsReported(): void {
    $tester = new TuiTester($this->form());

    // Root items: the panel, then Submit, then Cancel.
    $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Down), Key::named(KeyName::Enter));

    $this->assertTrue($tester->isCancelled());
  }

  public function testInterruptIsReported(): void {
    $tester = new TuiTester($this->form());

    // Ctrl-C mid-form aborts the loop: interrupted, not a cancel-button finish.
    $tester->run("\x03");

    $this->assertTrue($tester->isInterrupted());
    $this->assertFalse($tester->isCancelled());
  }

  public function testEmptyRunCollectsDefaults(): void {
    $answers = (new TuiTester($this->form()))->run();

    $this->assertSame('', $answers->value('name'));
  }

  public function testFluentSettersConfigureTheRun(): void {
    $tester = (new TuiTester($this->form()))
      ->theme('default')
      ->options(['color' => TRUE])
      ->rows(30)
      ->version('1.2.3')
      ->directory('somewhere');

    $answers = $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Down), Key::named(KeyName::Enter));

    $this->assertSame('', $answers->value('name'));
    // The colour option flowed through: the captured output carries ANSI.
    $this->assertStringContainsString("\033[", $tester->output());
  }

  /**
   * Every result accessor guards against being read before run().
   *
   * @param \Closure $access
   *   Reads one result accessor off a tester.
   */
  #[DataProvider('dataProviderAccessorBeforeRunThrows')]
  public function testAccessorBeforeRunThrows(\Closure $access): void {
    $this->expectException(\LogicException::class);

    $access(new TuiTester($this->form()));
  }

  /**
   * Data provider for testAccessorBeforeRunThrows().
   *
   * @return \Iterator<string,array{\Closure}>
   *   One accessor closure per case.
   */
  public static function dataProviderAccessorBeforeRunThrows(): \Iterator {
    yield 'answers' => [static fn(TuiTester $tester): mixed => $tester->answers()];
    yield 'output' => [static fn(TuiTester $tester): mixed => $tester->output()];
    yield 'is cancelled' => [static fn(TuiTester $tester): mixed => $tester->isCancelled()];
    yield 'is interrupted' => [static fn(TuiTester $tester): mixed => $tester->isInterrupted()];
  }

  /**
   * A single-field form used across the harness tests.
   */
  protected function form(): Form {
    return Form::create('Demo')
      ->panel('main', 'Main', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      });
  }

}
