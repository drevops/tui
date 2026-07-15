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
 * Each widget mirrors its whole dark set - both motions and all four display
 * modes - into light, so the per-widget naming matrix is symmetric. The panel
 * heroes only ship the animated unicode-colour variant, so only that is
 * mirrored.
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

$widgets = [
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

$panels = ['bordered-panels', 'borderless-panels'];

// Every dark variant of each widget, plus only the animated hero of each panel.
$sources = [];
foreach ($widgets as $widget) {
  foreach (glob($dir . '/' . $widget . '-dark-*.svg') ?: [] as $file) {
    $sources[] = $file;
  }
}
foreach ($panels as $panel) {
  $sources[] = $dir . '/' . $panel . '-dark-animated.svg';
}

$count = 0;
foreach ($sources as $source) {
  if (!is_file($source)) {
    fwrite(STDERR, sprintf("MISSING: %s\n", $source));
    continue;
  }

  $light = strtr((string) file_get_contents($source), $map);
  $out = str_replace('-dark-', '-light-', basename($source));
  file_put_contents($dir . '/' . $out, $light);
  $count++;
}

echo sprintf("Wrote %d light twin(s).\n", $count);
