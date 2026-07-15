#!/usr/bin/env php
<?php

/**
 * @file
 * Render the README's per-widget animated SVGs deterministically.
 *
 * The full-demo montages and panel walkthroughs are recorded from a live pty
 * (see update-assets.php), but the per-widget assets are single-field forms
 * that the library's own scripted-keystroke harness drives with no terminal at
 * all. For each widget, in all four glyph and colour display modes, this renders
 * both an animation (every rendered frame captured, laid out into an asciicast
 * and handed to the shared svg-term renderer; the unicode-colour one is the hero
 * README.md and the docs pages embed) and a static screenshot of the opened
 * editor (the four the docs grid shows). The result is reproducible on any
 * machine (and in CI), unlike a pty recording.
 *
 * Dependencies: node, npm (for the svg-term renderer shared with update-assets).
 *
 * Usage:
 * @code
 * php docs/util/render-widget-svgs.php            # all widgets
 * php docs/util/render-widget-svgs.php confirm    # one or more by name
 * @endcode
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\TuiTester;
use DrevOps\Tui\Theme\Mode;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/svg-slowdown.php';

// Seconds each captured frame is held: a longer beat on the opening frame, an
// even cadence through the interaction, and a rest on the last frame before the
// animation loops.
const HOLD_FIRST = 1.1;
const HOLD_STEP = 0.65;
const HOLD_LAST = 2.2;

// The four glyph and colour display modes, keyed by the filename suffix that
// distinguishes them. Both the animated cards and the static screenshots are
// rendered in every mode. Unicode and colour are the unmarked default.
const DISPLAY_MODES = [
  '' => ['color' => TRUE, 'unicode' => TRUE],
  '-ascii' => ['color' => TRUE, 'unicode' => FALSE],
  '-no-ansi' => ['color' => FALSE, 'unicode' => TRUE],
  '-ascii-no-ansi' => ['color' => FALSE, 'unicode' => FALSE],
];

/**
 * The per-widget forms and the keystrokes that drive them.
 *
 * Each single-field form mirrors its playground example. The keystrokes follow
 * the panel model: Enter drills the hub into the panel, a second Enter opens the
 * field editor, then the widget-specific keys exercise it and Enter (or Tab)
 * accepts - the same path a person walks.
 *
 * @param string $tree
 *   The fixture directory the file-picker widgets browse.
 *
 * @return array<string, array{form: \DrevOps\Tui\Builder\Form, keys: list<string|\DrevOps\Tui\Input\Key>, rows: int}>
 *   The widget specs keyed by asset name.
 */
function widgetSpecs(string $tree): array {
  $enter = Key::named(KeyName::Enter);
  $down = Key::named(KeyName::Down);
  $space = Key::named(KeyName::Space);
  $bs = Key::named(KeyName::Backspace);
  // Two Enters walk the hub into the panel and open the field editor; the
  // animation then ends inside the editor on the changed value, so the frame
  // stays as narrow as the widget itself rather than the full-width panel row.
  $open = [$enter, $enter];

  return [
    'text' => [
      'form' => Form::create('Text widget')->panel('main', 'Text', function (PanelBuilder $p): void { $p->text('text', 'Text')->default('Acme Site'); }),
      'keys' => [...$open, $bs, $bs, $bs, $bs, 'C', 'o', 'r', 'p'],
      'rows' => 6,
    ],
    'number' => [
      'form' => Form::create('Number widget')->panel('main', 'Number', function (PanelBuilder $p): void { $p->number('number', 'Number')->default(8080); }),
      'keys' => [...$open, $bs, $bs, $bs, $bs, '3', '0', '0', '0'],
      'rows' => 6,
    ],
    'calendar' => [
      'form' => Form::create('Calendar widget')->panel('main', 'Calendar', function (PanelBuilder $p): void { $p->calendar('date', 'Calendar')->default('2026-07-15'); }),
      'keys' => [...$open, $down],
      'rows' => 14,
    ],
    'textarea' => [
      'form' => Form::create('Textarea widget')->panel('main', 'Textarea', function (PanelBuilder $p): void { $p->textarea('textarea', 'Textarea')->default("Redis for cache\nSolr for search"); }),
      'keys' => [...$open, $enter, 'C', 'l', 'a', 'm', 'A', 'V'],
      'rows' => 8,
    ],
    'password' => [
      'form' => Form::create('Password widget')->panel('main', 'Password', function (PanelBuilder $p): void { $p->password('password', 'Password')->default('hunter2'); }),
      'keys' => [...$open, $bs, $bs, $bs, $bs, $bs, $bs, $bs, 's', '3', 'c', 'r', 'e', 't'],
      'rows' => 6,
    ],
    'select' => [
      'form' => Form::create('Select widget')->panel('main', 'Select', function (PanelBuilder $p): void { $p->select('select', 'Select')->default('minimal')->options(['standard' => 'Standard', 'minimal' => 'Minimal', 'demo_umami' => 'Demo Umami']); }),
      'keys' => [...$open, $down],
      'rows' => 8,
    ],
    'multiselect' => [
      'form' => Form::create('MultiSelect widget')->panel('main', 'MultiSelect', function (PanelBuilder $p): void { $p->multiSelect('multiselect', 'MultiSelect')->default(['redis'])->options(['redis' => 'Redis', 'solr' => 'Solr', 'clamav' => 'ClamAV']); }),
      'keys' => [...$open, $down, $space],
      'rows' => 8,
    ],
    'reorder' => [
      'form' => Form::create('Reorder widget')->panel('main', 'Reorder', function (PanelBuilder $p): void { $p->reorder('reorder', 'Reorder')->options(['redis' => 'Redis', 'solr' => 'Solr', 'clamav' => 'ClamAV']); }),
      'keys' => [...$open, $space, $down, $space],
      'rows' => 12,
    ],
    'suggest' => [
      'form' => Form::create('Suggest widget')->panel('main', 'Suggest', function (PanelBuilder $p): void { $p->suggest('suggest', 'Suggest')->options(['UTC' => 'UTC', 'Europe/London' => 'Europe/London', 'Europe/Paris' => 'Europe/Paris', 'Australia/Sydney' => 'Australia/Sydney']); }),
      'keys' => [...$open, 'E', 'u', 'r', $down],
      'rows' => 10,
    ],
    'search' => [
      'form' => Form::create('Search widget')->panel('main', 'Search', function (PanelBuilder $p): void { $p->search('search', 'Search')->default('london')->options(['utc' => 'UTC', 'london' => 'Europe/London', 'paris' => 'Europe/Paris', 'sydney' => 'Australia/Sydney']); }),
      'keys' => [...$open, 'p', 'a', 'r'],
      'rows' => 10,
    ],
    'multisearch' => [
      'form' => Form::create('MultiSearch widget')->panel('main', 'MultiSearch', function (PanelBuilder $p): void { $p->multiSearch('multisearch', 'MultiSearch')->default(['redis'])->options(['redis' => 'Redis', 'solr' => 'Solr', 'clamav' => 'ClamAV', 'memcached' => 'Memcached']); }),
      'keys' => [...$open, 'c', 'l', $space],
      'rows' => 10,
    ],
    'confirm' => [
      'form' => Form::create('Confirm widget')->panel('main', 'Confirm', function (PanelBuilder $p): void { $p->confirm('confirm', 'Confirm')->default(TRUE); }),
      'keys' => [...$open, 'n'],
      'rows' => 6,
    ],
    'toggle' => [
      'form' => Form::create('Toggle widget')->panel('main', 'Toggle', function (PanelBuilder $p): void { $p->toggle('toggle', 'Toggle')->default('enabled')->options(['enabled' => 'Enabled', 'disabled' => 'Disabled']); }),
      'keys' => [...$open, 'd'],
      'rows' => 6,
    ],
    'pause' => [
      'form' => Form::create('Pause widget')->panel('main', 'Pause', function (PanelBuilder $p): void { $p->pause('pause', 'Pause'); }),
      'keys' => [$enter],
      'rows' => 6,
    ],
    'filepicker' => [
      'form' => Form::create('File picker widget')->panel('main', 'File picker', function (PanelBuilder $p) use ($tree): void { $p->filePicker('file', 'File picker')->startIn($tree)->filesOnly()->extensions(['yml', 'yaml']); }),
      'keys' => [...$open, $down],
      'rows' => 8,
    ],
    'multifilepicker' => [
      'form' => Form::create('Multi file picker widget')->panel('main', 'Multi file picker', function (PanelBuilder $p) use ($tree): void { $p->multiFilePicker('files', 'Multi file picker')->startIn($tree); }),
      'keys' => [...$open, $space, $down, $space],
      'rows' => 10,
    ],
  ];
}

/**
 * Drive one widget and write its animated SVG.
 *
 * @param string $name
 *   The asset name.
 * @param array{form: \DrevOps\Tui\Builder\Form, keys: list<string|\DrevOps\Tui\Input\Key>, rows: int} $spec
 *   The widget spec.
 * @param string $assets_dir
 *   The output directory.
 * @param string $util_dir
 *   The tooling directory holding the svg-term renderer.
 * @param string $tmp_dir
 *   A scratch directory for the intermediate cast.
 */
function renderWidget(string $name, array $spec, string $assets_dir, string $util_dir, string $tmp_dir): void {
  foreach (DISPLAY_MODES as $suffix => $mode) {
    $tester = (new TuiTester($spec['form']))
      ->options(['color' => $mode['color'], 'unicode' => $mode['unicode'], 'mode' => Mode::Dark])
      ->rows($spec['rows']);
    $tester->run(...$spec['keys']);

    $frames = splitFrames($tester->output());

    if (count($frames) < 2) {
      throw new \RuntimeException(sprintf('Widget "%s" (%s) produced %d frame(s); an animation needs at least two.', $name, $suffix === '' ? 'default' : $suffix, count($frames)));
    }

    $cast_file = $tmp_dir . '/widget-' . $name . '-animated' . $suffix . '.cast';
    file_put_contents($cast_file, buildCast($frames, $spec['rows']));

    // The unmarked mode is the unicode, colour hero README.md embeds;
    // make-light-svgs.php derives its light twin.
    $svg_file = $assets_dir . '/widget-' . $name . '-dark-animated' . $suffix . '.svg';
    renderCast($cast_file, $svg_file, $util_dir);
    file_put_contents($svg_file, slowAnimation((string) file_get_contents($svg_file), ANIMATION_SLOWDOWN));
  }

  printf("  widget-%s-dark-animated*.svg (4 display modes)\n", $name);
}

/**
 * Render a widget's static display-mode screenshots.
 *
 * The documentation page shows each widget's editor, opened on its default, in
 * all four glyph and colour combinations. Every frame comes from the same
 * scripted open, so the grid stays consistent with itself and with the animated
 * hero above it.
 *
 * @param string $name
 *   The asset name.
 * @param array{form: \DrevOps\Tui\Builder\Form, keys: list<string|\DrevOps\Tui\Input\Key>, rows: int} $spec
 *   The widget spec.
 * @param string $assets_dir
 *   The output directory.
 * @param string $util_dir
 *   The tooling directory holding the svg-term renderer.
 * @param string $tmp_dir
 *   A scratch directory for the intermediate cast.
 */
function renderStaticVariants(string $name, array $spec, string $assets_dir, string $util_dir, string $tmp_dir): void {
  // A gate settles one Enter in; every other widget opens its editor with the
  // hub-into-panel-into-field drill.
  $enter = Key::named(KeyName::Enter);
  $open = $name === 'pause' ? [$enter] : [$enter, $enter];
  $clear = Ansi::ESC . '[2J' . Ansi::ESC . '[H';

  foreach (DISPLAY_MODES as $suffix => $mode) {
    $tester = (new TuiTester($spec['form']))
      ->options(['color' => $mode['color'], 'unicode' => $mode['unicode'], 'mode' => Mode::Dark])
      ->rows($spec['rows']);
    $tester->run(...$open);

    $frames = splitFrames($tester->output());
    if ($frames === []) {
      throw new \RuntimeException(sprintf('Widget "%s" static "%s" produced no frame.', $name, $suffix === '' ? 'default' : $suffix));
    }

    $frame = $frames[count($frames) - 1];
    $cast = json_encode(['version' => 2, 'width' => castWidth([$frame]), 'height' => $spec['rows']]) . "\n"
      . json_encode([0.0, 'o', $clear . $frame]) . "\n";
    $cast_file = $tmp_dir . '/widget-' . $name . '-static' . $suffix . '.cast';
    file_put_contents($cast_file, $cast);

    renderCast($cast_file, $assets_dir . '/widget-' . $name . '-dark-static' . $suffix . '.svg', $util_dir, 0);
  }
}

/**
 * Split captured output into whole rendered frames.
 *
 * Every frame the panel loop draws is prefixed by the clear-screen sequence, so
 * splitting on it yields one entry per repaint; the leading setup chunk and any
 * blank tail are dropped.
 *
 * @param string $output
 *   The captured terminal output.
 *
 * @return list<string>
 *   The frame byte strings, in order.
 */
function splitFrames(string $output): array {
  $clear = Ansi::ESC . '[2J' . Ansi::ESC . '[H';
  $parts = explode($clear, $output);
  $frames = array_values(array_filter($parts, static fn(string $s): bool => trim(Ansi::strip($s)) !== ''));

  // The in-memory capture stream applies no ONLCR translation, so the frames
  // carry bare line feeds; the emulator needs a carriage return to return to
  // column 0, or every row starts where the last one ended.
  return array_map(static fn(string $frame): string => str_replace("\n", "\r\n", str_replace("\r", '', $frame)), $frames);
}

/**
 * The width in columns the frames need, from the widest visible line.
 *
 * @param list<string> $frames
 *   The frames.
 *
 * @return int
 *   The column count.
 */
function castWidth(array $frames): int {
  $width = 0;

  foreach ($frames as $frame) {
    foreach (explode("\n", str_replace("\r", '', $frame)) as $line) {
      $width = max($width, Ansi::width($line));
    }
  }

  return $width;
}

/**
 * Assemble an asciicast v2 that plays the frames as an animation.
 *
 * @param list<string> $frames
 *   The captured frames.
 * @param int $rows
 *   The terminal height.
 *
 * @return string
 *   The cast file contents.
 */
function buildCast(array $frames, int $rows): string {
  $clear = Ansi::ESC . '[2J' . Ansi::ESC . '[H';
  $lines = [json_encode(['version' => 2, 'width' => castWidth($frames), 'height' => $rows])];

  $time = 0.0;
  foreach ($frames as $index => $frame) {
    $lines[] = json_encode([round($time, 3), 'o', $clear . $frame]);
    $time += $index === 0 ? HOLD_FIRST : HOLD_STEP;
  }
  // Hold the final frame before the animation loops back to the first.
  $lines[] = json_encode([round($time + HOLD_LAST, 3), 'o', ' ']);

  return implode("\n", $lines) . "\n";
}

/**
 * Render a cast to an SVG with the shared svg-term renderer.
 *
 * @param string $cast_file
 *   The input cast path.
 * @param string $svg_file
 *   The output SVG path.
 * @param string $util_dir
 *   The directory holding svg-term-render.js.
 * @param int|null $at
 *   A timestamp in milliseconds to capture a single static frame, or NULL to
 *   render the whole cast as an animation.
 */
function renderCast(string $cast_file, string $svg_file, string $util_dir, ?int $at = NULL): void {
  $cmd = sprintf(
    'node %s %s %s --line-height 1.1%s 2>&1',
    escapeshellarg($util_dir . '/svg-term-render.js'),
    escapeshellarg($cast_file),
    escapeshellarg($svg_file),
    $at !== NULL ? sprintf(' --at %d', $at) : ''
  );
  $output = shell_exec($cmd);

  if (!file_exists($svg_file) || filesize($svg_file) === 0) {
    throw new \RuntimeException('Failed to render SVG: ' . $svg_file . "\n" . ($output ?? ''));
  }
}

/**
 * Print an informational message unless quietened.
 *
 * @param string $message
 *   The message.
 */
function info(string $message): void {
  if (getenv('SCRIPT_QUIET') !== '1') {
    print $message . PHP_EOL;
  }
}

// Entrypoint.
ini_set('display_errors', '1');

if (PHP_SAPI !== 'cli') {
  die('This script can be only ran from the command line.');
}

$util_dir = __DIR__;
$assets_dir = dirname(__DIR__) . '/assets';
$tmp_dir = dirname(__DIR__, 2) . '/.artifacts/tmp/widget-svgs';
$tree = dirname(__DIR__, 2) . '/playground/3-widgets/filepicker-tree';

if (!is_dir($tmp_dir)) {
  mkdir($tmp_dir, 0755, TRUE);
}

$specs = widgetSpecs($tree);
$only = array_slice($argv, 1);
$names = $only === [] ? array_keys($specs) : $only;

info('Rendering ' . count($names) . ' widget animation(s)...');

foreach ($names as $name) {
  if (!isset($specs[$name])) {
    throw new \RuntimeException('Unknown widget: ' . $name);
  }

  renderWidget($name, $specs[$name], $assets_dir, $util_dir, $tmp_dir);
  renderStaticVariants($name, $specs[$name], $assets_dir, $util_dir, $tmp_dir);
}

info('Done.');
