<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Render;

use DrevOps\Tui\Render\ExternalEditor;
use DrevOps\Tui\Render\Terminal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the external editor service.
 */
#[CoversClass(ExternalEditor::class)]
#[Group('tui')]
final class ExternalEditorTest extends TestCase {

  /**
   * The VISUAL value before the test, restored afterwards.
   */
  protected string|false $visual;

  /**
   * The EDITOR value before the test, restored afterwards.
   */
  protected string|false $editor;

  #[\Override]
  protected function setUp(): void {
    parent::setUp();
    $this->visual = getenv('VISUAL');
    $this->editor = getenv('EDITOR');
  }

  #[\Override]
  protected function tearDown(): void {
    $this->putEnv('VISUAL', $this->visual === FALSE ? NULL : $this->visual);
    $this->putEnv('EDITOR', $this->editor === FALSE ? NULL : $this->editor);
    parent::tearDown();
  }

  #[DataProvider('dataProviderCommand')]
  public function testCommand(?string $visual, ?string $editor, ?string $expected): void {
    $this->putEnv('VISUAL', $visual);
    $this->putEnv('EDITOR', $editor);

    $this->assertSame($expected, (new ExternalEditor())->command());
  }

  public static function dataProviderCommand(): \Iterator {
    yield 'visual wins over editor' => ['vim', 'nano', 'vim'];
    yield 'editor is the fallback' => [NULL, 'nano', 'nano'];
    yield 'neither set' => [NULL, NULL, NULL];
    yield 'blank visual falls through to editor' => ['   ', 'nano', 'nano'];
    yield 'surrounding whitespace trimmed' => [NULL, '  nano  ', 'nano'];
    yield 'all blank is none' => ['', '  ', NULL];
  }

  public function testIsAvailable(): void {
    $this->putEnv('VISUAL', NULL);
    $this->putEnv('EDITOR', 'vi');
    $this->assertTrue((new ExternalEditor())->isAvailable());

    $this->putEnv('EDITOR', NULL);
    $this->assertFalse((new ExternalEditor())->isAvailable());
  }

  public function testEditReturnsNullWhenNoEditor(): void {
    $this->putEnv('VISUAL', NULL);
    $this->putEnv('EDITOR', NULL);

    $this->assertNull((new ExternalEditor())->edit('seed'));
  }

  public function testEditReturnsNullWhenTempFileFails(): void {
    $this->putEnv('EDITOR', 'vi');

    $editor = new class extends ExternalEditor {

      #[\Override]
      protected function tempFile(): ?string {
        return NULL;
      }

      #[\Override]
      protected function spawn(string $command, string $file): int {
        throw new \RuntimeException('the editor must not launch when no temp file exists');
      }

    };

    $this->assertNull($editor->edit('seed'));
  }

  public function testEditCapturesSavedBufferAndRestoresTerminal(): void {
    $this->putEnv('EDITOR', 'vi');

    $editor = $this->stubEditor('edited value', 0);
    $terminal = $this->terminal();

    $this->assertSame('edited value', $editor->edit('seed', $terminal));
    $this->assertSame('seed', $editor->seenInitial);
    $this->assertTrue($terminal->restored, 'the terminal was suspended');
    $this->assertTrue($terminal->resumed, 'the terminal was restored');
    $this->assertFileDoesNotExist($editor->seenFile);
  }

  public function testEditWorksWithoutTerminal(): void {
    $this->putEnv('EDITOR', 'vi');

    $editor = $this->stubEditor('no terminal', 0);

    $this->assertSame('no terminal', $editor->edit('seed'));
    $this->assertFileDoesNotExist($editor->seenFile);
  }

  public function testEditReturnsNullOnNonZeroExit(): void {
    $this->putEnv('EDITOR', 'vi');

    $editor = $this->stubEditor('ignored', 1);
    $terminal = $this->terminal();

    $this->assertNull($editor->edit('seed', $terminal));
    $this->assertTrue($terminal->resumed, 'the terminal is restored even on abort');
    $this->assertFileDoesNotExist($editor->seenFile);
  }

  public function testEditReturnsNullWhenEditorDeletesTheFile(): void {
    $this->putEnv('EDITOR', 'vi');

    $this->assertNull($this->stubEditor(NULL, 0, TRUE)->edit('seed'));
  }

  #[DataProvider('dataProviderNormalize')]
  public function testEditNormalizesTrailingNewline(string $saved, string $expected): void {
    $this->putEnv('EDITOR', 'vi');

    $this->assertSame($expected, $this->stubEditor($saved, 0)->edit('seed'));
  }

  public static function dataProviderNormalize(): \Iterator {
    yield 'single trailing lf dropped' => ["one\ntwo\n", "one\ntwo"];
    yield 'trailing crlf dropped' => ["text\r\n", 'text'];
    yield 'only one trailing newline dropped' => ["text\n\n", "text\n"];
    yield 'interior newlines preserved' => ["a\n\nb\n", "a\n\nb"];
    yield 'no trailing newline unchanged' => ['plain', 'plain'];
    yield 'empty stays empty' => ['', ''];
  }

  /**
   * A stub editor whose spawn records inputs and simulates a save.
   *
   * @param string|null $write_back
   *   The content the "editor" writes to the file, or NULL to leave it as seeded.
   * @param int $code
   *   The exit code the "editor" returns.
   * @param bool $delete_file
   *   Whether the "editor" removes the file instead of saving.
   *
   * @return \DrevOps\Tui\Render\ExternalEditor
   *   The stub.
   */
  protected function stubEditor(?string $write_back, int $code, bool $delete_file = FALSE): ExternalEditor {
    return new class($write_back, $code, $delete_file) extends ExternalEditor {

      /**
       * The buffer the spawn saw seeded in the file.
       */
      public string $seenInitial = '';

      /**
       * The temp file path the spawn saw.
       */
      public string $seenFile = '';

      public function __construct(protected ?string $writeBack, protected int $code, protected bool $deleteFile) {
      }

      #[\Override]
      protected function spawn(string $command, string $file): int {
        $this->seenInitial = (string) file_get_contents($file);
        $this->seenFile = $file;

        if ($this->deleteFile) {
          unlink($file);
        }
        elseif ($this->writeBack !== NULL) {
          file_put_contents($file, $this->writeBack);
        }

        return $this->code;
      }

    };
  }

  /**
   * A terminal double recording suspend/restore without touching a TTY.
   *
   * @return \DrevOps\Tui\Render\Terminal
   *   The double, exposing $restored and $resumed flags.
   */
  protected function terminal(): Terminal {
    return new class extends Terminal {

      /**
       * Whether restore() (suspend) was called.
       */
      public bool $restored = FALSE;

      /**
       * Whether setup() (resume) was called.
       */
      public bool $resumed = FALSE;

      #[\Override]
      public function restore(): void {
        $this->restored = TRUE;
      }

      #[\Override]
      public function setup(): void {
        $this->resumed = TRUE;
      }

    };
  }

  /**
   * Set or unset an environment variable.
   *
   * @param string $name
   *   The variable name.
   * @param string|null $value
   *   The value, or NULL to unset.
   */
  protected function putEnv(string $name, ?string $value): void {
    $value === NULL ? putenv($name) : putenv($name . '=' . $value);
  }

}
