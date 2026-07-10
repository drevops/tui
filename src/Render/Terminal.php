<?php

declare(strict_types=1);

namespace DrevOps\Tui\Render;

use DrevOps\Tui\Theme\ThemeInterface;
use Symfony\Component\Console\Terminal as ConsoleTerminal;

/**
 * Thin terminal I/O: raw mode, alternate screen, mouse, render and restore.
 *
 * The raw-mode toggling and input reading touch the real TTY and are excluded
 * from coverage; the output writing is stream-injectable and testable.
 *
 * @package DrevOps\Tui\Render
 */
class Terminal {

  /**
   * The output stream.
   *
   * @var resource
   */
  protected $output;

  /**
   * The input stream.
   *
   * @var resource
   */
  protected $input;

  /**
   * Construct a terminal.
   *
   * @param mixed $output
   *   The output stream (defaults to STDOUT).
   * @param mixed $input
   *   The input stream (defaults to STDIN).
   */
  public function __construct(mixed $output = NULL, mixed $input = NULL) {
    $this->output = is_resource($output) ? $output : STDOUT;
    $this->input = is_resource($input) ? $input : STDIN;
  }

  /**
   * Write text to the output.
   *
   * @param string $text
   *   The text.
   */
  public function write(string $text): void {
    fwrite($this->output, $text);
  }

  /**
   * Clear the screen and write a frame.
   *
   * @param string $frame
   *   The frame.
   */
  public function render(string $frame): void {
    $this->write(TerminalControl::clear() . $frame);
  }

  /**
   * Clear the screen.
   */
  public function clear(): void {
    $this->write(TerminalControl::clear());
  }

  /**
   * Enter the full-screen raw-input mode.
   */
  public function setup(): void {
    // @codeCoverageIgnoreStart
    $this->stty('-echo -icanon');
    $this->write(TerminalControl::altScreenOn() . TerminalControl::hideCursor() . TerminalControl::mouseOn());
    // @codeCoverageIgnoreEnd
  }

  /**
   * Restore the terminal to its normal mode.
   */
  public function restore(): void {
    // @codeCoverageIgnoreStart
    $this->write(TerminalControl::restore());
    $this->stty('sane');
    // @codeCoverageIgnoreEnd
  }

  /**
   * Read raw bytes from the input.
   *
   * @param int $bytes
   *   The maximum number of bytes to read.
   *
   * @return string
   *   The bytes read.
   */
  public function read(int $bytes = 32): string {
    // @codeCoverageIgnoreStart
    $data = fread($this->input, max(1, $bytes));

    return $data === FALSE ? '' : $data;
    // @codeCoverageIgnoreEnd
  }

  /**
   * The terminal height in rows.
   *
   * @return int
   *   The number of rows available for rendering.
   */
  public function height(): int {
    return (new ConsoleTerminal())->getHeight();
  }

  /**
   * Query the terminal background colour via OSC 11.
   *
   * Writes the query and polls for the reply with stream_select() so a terminal
   * that never answers cannot block, and so a no-reply probe never issues the
   * zero-byte read that would latch EOF on the shared input stream. Leaves the
   * terminal on the main screen.
   *
   * @return string|null
   *   The raw reply bytes, or NULL when the input is not a TTY or no reply
   *   arrived.
   */
  public function queryBackground(): ?string {
    // @codeCoverageIgnoreStart
    if (!stream_isatty($this->input)) {
      return NULL;
    }

    $this->stty('-echo -icanon');
    $response = '';

    try {
      $this->write(TerminalControl::queryBackground());
      fflush($this->output);

      for ($poll = 0; $poll < 3; $poll++) {
        $read = [$this->input];
        $write = [];
        $except = [];
        $ready = stream_select($read, $write, $except, 0, 100000);

        if ($ready === FALSE || $ready < 1) {
          if ($response !== '') {
            break;
          }

          continue;
        }

        $chunk = fread($this->input, 64);
        if (!is_string($chunk)) {
          continue;
        }
        if ($chunk === '') {
          continue;
        }

        $response .= $chunk;

        if (str_contains($response, "\007") || str_contains($response, Ansi::ESC . '\\')) {
          break;
        }
      }
    }
    finally {
      $this->stty('sane');
    }

    return $response === '' ? NULL : $response;
    // @codeCoverageIgnoreEnd
  }

  /**
   * Detect whether the environment advertises a Unicode-capable locale.
   *
   * The first set of LC_ALL, LC_CTYPE or LANG decides, and a "UTF" locale
   * enables Unicode. An unset locale falls back to ASCII.
   *
   * @return bool
   *   TRUE when a UTF locale is advertised.
   */
  public static function detectUnicode(): bool {
    foreach (['LC_ALL', 'LC_CTYPE', 'LANG'] as $var) {
      $value = getenv($var);
      if (is_string($value) && $value !== '') {
        return stripos($value, 'utf') !== FALSE;
      }
    }

    return FALSE;
  }

  /**
   * Detect whether the environment supports ANSI colour.
   *
   * Honours the NO_COLOR convention and the "dumb" terminal.
   *
   * @return bool
   *   TRUE unless NO_COLOR is set to a non-empty value or TERM is "dumb".
   */
  public static function detectColor(): bool {
    $no_color = getenv('NO_COLOR');

    // The NO_COLOR convention treats an empty value as unset.
    if (is_string($no_color) && $no_color !== '') {
      return FALSE;
    }

    return getenv('TERM') !== 'dumb';
  }

  /**
   * Detect the colour mode that best matches the terminal background.
   *
   * Resolves in order: the OSC 11 background reply (when one was captured),
   * then the COLORFGBG environment variable, then a dark default. Always
   * returns a MODE_* value.
   *
   * @param string|null $osc_response
   *   The raw OSC 11 reply bytes, or NULL when the terminal was not queried or
   *   did not answer.
   *
   * @return string
   *   ThemeInterface::MODE_DARK or ThemeInterface::MODE_LIGHT.
   */
  public static function detectMode(?string $osc_response = NULL): string {
    if (is_string($osc_response)) {
      $mode = self::modeFromOsc($osc_response);

      if ($mode !== NULL) {
        return $mode;
      }
    }

    return self::modeFromColorFgBg() ?? ThemeInterface::MODE_DARK;
  }

  /**
   * Derive the colour mode from an OSC 11 background-colour reply.
   *
   * A terminal answers with a payload such as "rgb:rrrr/gggg/bbbb" whose three
   * channels each carry 1 to 4 hex digits (an "rgba:" prefix is also seen).
   * The background's relative luminance selects the mode.
   *
   * @param string $response
   *   The raw reply bytes.
   *
   * @return string|null
   *   A MODE_* value, or NULL when the reply holds no parseable colour.
   */
  protected static function modeFromOsc(string $response): ?string {
    if (preg_match('#rgba?:([0-9a-f]{1,4})/([0-9a-f]{1,4})/([0-9a-f]{1,4})#i', $response, $matches) !== 1) {
      return NULL;
    }

    $is_dark = self::luminanceIsDark(self::channel($matches[1]), self::channel($matches[2]), self::channel($matches[3]));

    return $is_dark ? ThemeInterface::MODE_DARK : ThemeInterface::MODE_LIGHT;
  }

  /**
   * Scale a 1-4 hex-digit colour channel to the 0-255 range.
   *
   * @param string $hex
   *   The channel as 1 to 4 hexadecimal digits.
   *
   * @return int
   *   The channel value, 0-255.
   */
  protected static function channel(string $hex): int {
    $max = (2 ** (4 * strlen($hex))) - 1;

    return (int) round((int) hexdec($hex) / $max * 255);
  }

  /**
   * Whether an RGB colour reads as a dark background.
   *
   * Uses the Rec. 709 relative-luminance coefficients; a colour below the
   * midpoint of the range is dark.
   *
   * @param int $r
   *   Red, 0-255.
   * @param int $g
   *   Green, 0-255.
   * @param int $b
   *   Blue, 0-255.
   *
   * @return bool
   *   TRUE when the colour is dark.
   */
  protected static function luminanceIsDark(int $r, int $g, int $b): bool {
    return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) < 128;
  }

  /**
   * Derive the colour mode from the COLORFGBG environment variable.
   *
   * Some terminals export "fg;bg" (or "fg;decoration;bg") as palette indices.
   * The background is the last field; indices 0-6 and 8 are the dark half of
   * the standard sixteen-colour palette, the rest light.
   *
   * @return string|null
   *   A MODE_* value, or NULL when COLORFGBG is unset or its background is not
   *   a palette index.
   */
  protected static function modeFromColorFgBg(): ?string {
    $value = getenv('COLORFGBG');

    if (!is_string($value) || $value === '') {
      return NULL;
    }

    $parts = explode(';', $value);
    $background = end($parts);

    if (!ctype_digit($background)) {
      return NULL;
    }

    return in_array((int) $background, [0, 1, 2, 3, 4, 5, 6, 8], TRUE) ? ThemeInterface::MODE_DARK : ThemeInterface::MODE_LIGHT;
  }

  /**
   * Run stty with the given arguments.
   *
   * @param string $args
   *   The stty arguments.
   */
  protected function stty(string $args): void {
    // @codeCoverageIgnoreStart
    exec('stty ' . $args . ' 2>/dev/null');
    // @codeCoverageIgnoreEnd
  }

}
