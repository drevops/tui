<?php

declare(strict_types=1);

/**
 * @file
 * Produce light-scheme twins of the README's terminal SVGs by remapping the
 * dark palette's background and text greys to light equivalents. The accent
 * colours (teal caret, green values, blue cursor, muted grey) already read on
 * both backgrounds, so only the surface and foreground greys are inverted.
 * Deterministic and exact: the light twin shares the dark frame's geometry.
 */

// strtr() applies all pairs simultaneously against the original, so the
// background target and the emphasis target never cascade into each other.
$map = [
  'rgb(40,44,52)' => 'rgb(250,250,250)',
  'rgb(171,178,191)' => 'rgb(56,58,66)',
  'rgb(185,192,203)' => 'rgb(40,44,52)',
  'rgb(128,128,128)' => 'rgb(90,96,105)',
];

$names = [
  'bordered-panels',
  'borderless-panels',
  'widget-calendar',
  'widget-confirm',
  'widget-filepicker',
  'widget-multifilepicker',
  'widget-multisearch',
  'widget-multiselect',
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
];

$dir = __DIR__ . '/../../docs/assets';

foreach ($names as $name) {
  $src = $dir . '/' . $name . '-dark-animated.svg';

  if (!is_file($src)) {
    fwrite(STDERR, sprintf("MISSING: %s\n", $src));
    continue;
  }

  $dark = (string) file_get_contents($src);
  $light = strtr($dark, $map);
  $out = $name . '-light-animated.svg';
  file_put_contents($dir . '/' . $out, $light);
  echo sprintf("%-24s -> %s\n", $name . '-dark-animated', $out);
}
