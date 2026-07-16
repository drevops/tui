<?php

declare(strict_types=1);

/**
 * @file
 * Produce light-scheme twins of the terminal SVGs by remapping the dark
 * palette's background and text greys to light equivalents. The accent colours
 * (teal caret, green values, blue cursor, muted grey) already read on both
 * backgrounds, so only the surface and foreground greys are inverted.
 * Deterministic and exact: each light twin shares its dark source's geometry.
 *
 * Every subject that renders in the standard palette gets a light twin for each
 * of its dark variants, so the documentation can serve either scheme with a
 * ThemedImage. Each widget mirrors its whole dark set (both motions, all four
 * display modes); the panel heroes and demos mirror whatever dark variants they
 * ship. The ocean theme is deliberately excluded - it demonstrates a custom
 * palette, not the default light/dark, so it has no meaningful light twin.
 */

// strtr() applies all pairs simultaneously against the original, so the
// background target and the emphasis target never cascade into each other.
$map = [
  'rgb(40,44,52)' => 'rgb(250,250,250)',
  'rgb(171,178,191)' => 'rgb(56,58,66)',
  'rgb(185,192,203)' => 'rgb(40,44,52)',
  'rgb(128,128,128)' => 'rgb(90,96,105)',
];

$dir = __DIR__ . '/../../docs/assets';

$subjects = [
  'widget-calendar',
  'widget-confirm',
  'widget-filepicker',
  'widget-filepicker-multiple',
  'widget-search-multiple',
  'widget-select-multiple',
  'widget-number',
  'widget-password',
  'widget-pause',
  'widget-reorder',
  'widget-search',
  'widget-select',
  'widget-suggest',
  'widget-text',
  'widget-textarea',
  'widget-toggle',
  'widget-select-groups',
  'widget-select-multiple-groups',
  'widget-password-reveal',
  'quickstart',
  'produce-box',
  'widgets',
  'bordered-panels',
  'borderless-panels',
  'nested-panels',
  'modal-panels',
  'fullscreen-panels',
  'discovery',
];

// Mirror every dark variant of each subject. A subject with no dark variant
// means the dark pass has not run or is broken, so stop rather than emit a
// partial light set.
$sources = [];
foreach ($subjects as $subject) {
  $matches = glob($dir . '/' . $subject . '-dark-*.svg');
  if ($matches === FALSE || $matches === []) {
    throw new \RuntimeException(sprintf('No dark variants found for "%s".', $subject));
  }

  $sources = array_merge($sources, $matches);
}

$count = 0;
foreach ($sources as $source) {
  if (!is_file($source)) {
    throw new \RuntimeException('Missing source: ' . $source);
  }

  $dark = file_get_contents($source);
  if ($dark === FALSE) {
    throw new \RuntimeException('Failed to read source: ' . $source);
  }

  $out = $dir . '/' . str_replace('-dark-', '-light-', basename($source));
  if (file_put_contents($out, strtr($dark, $map)) === FALSE) {
    throw new \RuntimeException('Failed to write: ' . $out);
  }

  $count++;
}

echo sprintf('Wrote %d light twin(s).', $count) . PHP_EOL;
