<?php

declare(strict_types=1);

namespace DrevOps\Tui\Discovery;

/**
 * Discovers a list of directory entries, optionally filtered by type.
 *
 * @package DrevOps\Tui\Discovery
 */
class Scan extends AbstractDiscover {

  /**
   * The entry type to keep.
   */
  public readonly ScanType $type;

  /**
   * Construct a scan discovery rule.
   *
   * @param string $dir
   *   The directory to scan, relative to the project directory.
   * @param \DrevOps\Tui\Discovery\ScanType|string $type
   *   The entry type to keep; a string value resolves through the enum, so a
   *   typo fails at declaration time.
   */
  public function __construct(public readonly string $dir, ScanType|string $type = ScanType::Any) {
    $this->type = $type instanceof ScanType ? $type : ScanType::from($type);
  }

  /**
   * {@inheritdoc}
   */
  public function discover(string $directory): mixed {
    $full = $this->join($directory, $this->dir);

    if (!is_dir($full)) {
      return [];
    }

    $entries = scandir($full);
    // @codeCoverageIgnoreStart
    if ($entries === FALSE) {
      return [];
    }
    // @codeCoverageIgnoreEnd
    $out = [];

    foreach ($entries as $entry) {
      if ($entry === '.') {
        continue;
      }
      if ($entry === '..') {
        continue;
      }
      $path = $full . '/' . $entry;
      if ($this->type === ScanType::Dir && !is_dir($path)) {
        continue;
      }
      if ($this->type === ScanType::File && !is_file($path)) {
        continue;
      }

      $out[] = $entry;
    }

    sort($out);

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return ['scan' => ['dir' => $this->dir, 'type' => $this->type->value]];
  }

}
