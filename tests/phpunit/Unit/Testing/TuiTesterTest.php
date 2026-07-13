<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Testing;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Testing\TuiTester;
use PHPUnit\Framework\Attributes\CoversClass;
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

    $this->assertInstanceOf(TuiTester::class, $tester);

    $answers = $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Down), Key::named(KeyName::Enter));

    $this->assertInstanceOf(Answers::class, $answers);
    $this->assertNotSame('', $tester->output());
  }

  public function testAnswersBeforeRunThrows(): void {
    $this->expectException(\LogicException::class);

    (new TuiTester($this->form()))->answers();
  }

  public function testOutputBeforeRunThrows(): void {
    $this->expectException(\LogicException::class);

    (new TuiTester($this->form()))->output();
  }

  public function testIsCancelledBeforeRunThrows(): void {
    $this->expectException(\LogicException::class);

    (new TuiTester($this->form()))->isCancelled();
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
