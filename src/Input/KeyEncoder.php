<?php

declare(strict_types=1);

namespace DrevOps\Tui\Input;

/**
 * Encodes a key into the terminal bytes a KeyParser decodes back into it.
 *
 * The inverse of {@see KeyParser}: it turns a Key into the canonical byte
 * sequence a real terminal would emit for that keypress, so scripted input can
 * be pushed onto a terminal's input pipe and read back through the production
 * parser.
 *
 * @package DrevOps\Tui\Input
 */
final class KeyEncoder {

  /**
   * Encode a key into its terminal byte sequence.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key.
   *
   * @return string
   *   The bytes a KeyParser parses back into an equivalent key.
   */
  public static function encode(Key $key): string {
    if ($key->char !== NULL) {
      return $key->char;
    }

    if (!$key->name instanceof KeyName) {
      // A key always carries a character or a name; this is unreachable.
      // @codeCoverageIgnoreStart
      throw new \InvalidArgumentException('A key must carry a character or a name.');
      // @codeCoverageIgnoreEnd
    }

    return match ($key->name) {
      KeyName::Enter => "\r",
      KeyName::Backspace => "\x7f",
      KeyName::Tab => "\t",
      KeyName::Space => ' ',
      KeyName::Escape => "\033",
      KeyName::Up => "\033[A",
      KeyName::Down => "\033[B",
      KeyName::Right => "\033[C",
      KeyName::Left => "\033[D",
      KeyName::Home => "\033[H",
      KeyName::End => "\033[F",
      KeyName::Delete => "\033[3~",
      KeyName::PageUp => "\033[5~",
      KeyName::PageDown => "\033[6~",
      KeyName::MouseWheelUp => "\033[<64;1;1M",
      KeyName::MouseWheelDown => "\033[<65;1;1M",
    };
  }

}
