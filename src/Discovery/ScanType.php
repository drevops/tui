<?php

declare(strict_types=1);

namespace DrevOps\Tui\Discovery;

/**
 * Which directory entries a {@see Scan} rule keeps.
 *
 * @package DrevOps\Tui\Discovery
 */
enum ScanType: string {

  // Keep only directories.
  case Dir = 'dir';

  // Keep only files.
  case File = 'file';

  // Keep every entry.
  case Any = 'any';

}
