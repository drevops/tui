#!/usr/bin/env php
<?php

/**
 * @file
 * Generate animated SVG assets from asciinema recordings.
 *
 * Records terminal sessions for the playground demos (the panel TUI runners
 * and the per-widget scripts), then converts the recordings to SVGs for use
 * in README.md: animated SVGs for the full demos, static frames for the
 * per-widget screenshots.
 *
 * Supports parallel execution: when run without arguments, launches all
 * recordings as parallel worker processes for faster generation.
 *
 * Dependencies: asciinema, expect, node, npm
 *
 * Environment variables:
 * - SCRIPT_QUIET: Set to '1' to suppress verbose messages.
 *
 * Usage:
 * @code
 * php docs/util/update-assets.php
 * php docs/util/update-assets.php --record widget-select
 * @endcode
 */

declare(strict_types=1);

// Default terminal dimensions for recordings.
define('TERMINAL_COLS', 80);
define('TERMINAL_ROWS', 24);

// Maximum idle time in recordings (seconds).
define('MAX_IDLE_TIME', 3);

// Pause at the end of each recording before the animation loops (seconds).
define('END_PAUSE', 10);

/**
 * The expect snippets driving each widget demo, keyed by widget name.
 *
 * Each snippet gates on the widget title rendered by the playground script,
 * then drives the widget to completion. The snippets serve the per-widget
 * static screenshots and, concatenated, the all-widgets sequence.
 *
 * @return array<string, string>
 *   The expect script bodies keyed by widget name.
 */
function widgetInteractions(): array {
  return [
    'text' => <<<'EXPECT'
# Text: trim "Site", type "Corp", accept.
expect "Text widget" {
    pause 1000
    press_backspace
    press_backspace
    press_backspace
    press_backspace
    type_text "Corp"
    wait_and_enter
}
EXPECT,
    'number' => <<<'EXPECT'
# Number: clear the port, type a new one, accept.
expect "Number widget" {
    pause 1000
    press_backspace
    press_backspace
    press_backspace
    press_backspace
    type_text "3000"
    wait_and_enter
}
EXPECT,
    'textarea' => <<<'EXPECT'
# Textarea: Enter adds a line, type, Tab accepts.
expect "Textarea widget" {
    pause 1000
    safe_send "\r"
    type_text "ClamAV for mail"
    pause 1000
    press_tab
}
EXPECT,
    'password' => <<<'EXPECT'
# Password: type extra characters (masked), accept.
expect "Password widget" {
    pause 1000
    type_text "s3cret"
    wait_and_enter
}
EXPECT,
    'select' => <<<'EXPECT'
# Select: down to the next option, accept.
expect "Select widget" {
    pause 1000
    arrow_down
    wait_and_enter
}
EXPECT,
    'multiselect' => <<<'EXPECT'
# MultiSelect: toggle the second option, accept.
expect "MultiSelect widget" {
    pause 1000
    arrow_down
    toggle_space
    wait_and_enter
}
EXPECT,
    'suggest' => <<<'EXPECT'
# Suggest: type to filter, highlight a suggestion, accept.
expect "Suggest widget" {
    pause 1000
    type_text "Eur"
    arrow_down
    wait_and_enter
}
EXPECT,
    'search' => <<<'EXPECT'
# Search: type to filter down to one option, accept.
expect "Search widget" {
    pause 1000
    type_text "par"
    wait_and_enter
}
EXPECT,
    'multisearch' => <<<'EXPECT'
# MultiSearch: filter, toggle the match, accept.
expect "MultiSearch widget" {
    pause 1000
    type_text "cl"
    toggle_space
    wait_and_enter
}
EXPECT,
    'confirm' => <<<'EXPECT'
# Confirm: switch to No, accept.
expect "Confirm widget" {
    pause 1000
    type_text "n"
    wait_and_enter
}
EXPECT,
    'pause' => <<<'EXPECT'
# Pause: acknowledge.
expect "Pause widget" {
    wait_and_enter
}
EXPECT,
  ];
}

/**
 * The expect body driving the package-scaffolder panel TUI.
 *
 * @return string
 *   The expect script body.
 */
function scaffolderInteraction(): string {
  return <<<'EXPECT'
# Wait for the hub, then drill into the General panel.
expect "Build & features" {
    pause 2000
    safe_send "\r"
}

# Walk the General fields, showing the derived values.
pause 1500
arrow_down
arrow_down
arrow_down
arrow_down

# Back to the hub, drill into Build & features.
press_escape
pause 1000
arrow_down
pause 500
safe_send "\r"

# Edit the package type: pick the second option.
pause 1500
safe_send "\r"
pause 1000
arrow_down
pause 600
safe_send "\r"

# Edit the features multiselect: pick tests, ci and docker.
pause 1000
arrow_down
pause 400
safe_send "\r"
pause 1000
toggle_space
arrow_down
toggle_space
arrow_down
toggle_space
pause 600
safe_send "\r"

# Suggest a PHP version: trim the default, type another.
pause 1000
arrow_down
arrow_down
arrow_down
pause 400
safe_send "\r"
pause 1000
press_backspace
type_text "3"
pause 800
safe_send "\r"

# Back out and submit.
press_escape
pause 1000
arrow_down
arrow_down
pause 600
safe_send "\r"
EXPECT;
}

/**
 * The expect body driving the nested-panels panel TUI.
 *
 * @return string
 *   The expect script body.
 */
function nestedPanelsInteraction(): string {
  return <<<'EXPECT'
# Wait for the hub, then drill into Identity.
expect "Identity" {
    pause 2000
    safe_send "\r"
}

# Look at Identity, then back out to the hub.
pause 1500
arrow_down
pause 1000
press_escape

# Drill into Stack.
pause 1000
arrow_down
pause 500
safe_send "\r"

# Environment: pick Production (third option).
pause 1500
safe_send "\r"
pause 1000
arrow_down
arrow_down
pause 600
safe_send "\r"

# Drill into the nested Services panel.
pause 1000
arrow_down
arrow_down
pause 500
safe_send "\r"

# Enable Solr and Redis.
pause 1500
safe_send "\r"
pause 1000
toggle_space
arrow_down
toggle_space
pause 600
safe_send "\r"

# Drill into the deeper Tuning panel.
pause 1000
arrow_down
arrow_down
pause 500
safe_send "\r"

# PHP memory limit: clear the default, pick from the list.
pause 1500
safe_send "\r"
pause 1000
press_backspace
press_backspace
press_backspace
press_backspace
arrow_down
arrow_down
arrow_down
pause 600
safe_send "\r"

# Back out to the hub and save.
press_escape
press_escape
press_escape
pause 1000
arrow_down
arrow_down
pause 600
safe_send "\r"
EXPECT;
}

/**
 * The expect body driving the custom-theme (ocean) panel TUI.
 *
 * @return string
 *   The expect script body.
 */
function themeOceanInteraction(): string {
  return <<<'EXPECT'
# Acknowledge the banner.
expect "continue" {
    pause 2000
    safe_send " "
}

# Drill into the profile panel.
expect "Diver profile" {
    pause 1500
    safe_send "\r"
}

# Rename the diver.
pause 1500
safe_send "\r"
pause 1000
press_backspace
press_backspace
press_backspace
press_backspace
press_backspace
press_backspace
press_backspace
press_backspace
type_text "Nemo"
pause 600
safe_send "\r"

# Preferred depth: pick Abyss.
pause 1000
arrow_down
pause 400
safe_send "\r"
pause 1000
arrow_down
pause 600
safe_send "\r"

# Gear: take the mask and fins.
pause 1000
arrow_down
pause 400
safe_send "\r"
pause 1000
toggle_space
arrow_down
toggle_space
pause 600
safe_send "\r"

# Back to the hub and submit.
press_escape
pause 1000
arrow_down
pause 600
safe_send "\r"
EXPECT;
}

/**
 * Get all job definitions.
 *
 * @param string $project_dir
 *   Path to the project root.
 *
 * @return array<string, array<string, mixed>>
 *   Keyed by job name, each containing command, interact, rows, cols and
 *   optionally at (for static screenshots).
 */
function getJobs(string $project_dir): array {
  $jobs = [];

  // Flag variants for the widget demo scripts (forced by script flags).
  $flag_variants = ['' => '', '-ascii' => ' --no-unicode', '-no-ansi' => ' --no-ansi', '-ascii-no-ansi' => ' --no-unicode --no-ansi'];

  // Env variants for the panel TUI runners: glyphs follow the locale and
  // colour follows NO_COLOR, so the modes are forced via the environment.
  $env_variants = ['' => '', '-ascii' => 'LC_ALL=C ', '-no-ansi' => 'NO_COLOR=1 ', '-ascii-no-ansi' => 'LC_ALL=C NO_COLOR=1 '];

  // The all-widgets sequence, in the order widgets.php runs them.
  $sequence = implode("\n\n", widgetInteractions());
  foreach ($flag_variants as $suffix => $flags) {
    $jobs['widgets' . $suffix] = [
      'command' => 'php ' . $project_dir . '/playground/3-widgets/widgets.php' . $flags,
      'interact' => $sequence,
      'rows' => 14,
      'cols' => 60,
    ];
  }

  // The package scaffolder: the hero panel TUI demo, in all display modes.
  foreach ($env_variants as $suffix => $env) {
    $jobs['scaffolder' . $suffix] = [
      'command' => 'env LINES=' . TERMINAL_ROWS . ' COLUMNS=' . TERMINAL_COLS . ' ' . $env . 'php ' . $project_dir . '/playground/1-scaffolder/run.php',
      'interact' => scaffolderInteraction(),
      'rows' => TERMINAL_ROWS,
      'cols' => TERMINAL_COLS,
    ];
  }

  // Nested panels with drill-in sub-panels, custom buttons and a fix-up.
  $jobs['nested-panels'] = [
    'command' => 'env LINES=' . TERMINAL_ROWS . ' COLUMNS=' . TERMINAL_COLS . ' php ' . $project_dir . '/playground/4-nested-panels/run.php',
    'interact' => nestedPanelsInteraction(),
    'rows' => TERMINAL_ROWS,
    'cols' => TERMINAL_COLS,
  ];

  // The custom ocean theme with a banner.
  $jobs['theme-ocean'] = [
    'command' => 'env LINES=20 COLUMNS=' . TERMINAL_COLS . ' php ' . $project_dir . '/playground/2-custom-theme/run.php',
    'interact' => themeOceanInteraction(),
    'rows' => 20,
    'cols' => TERMINAL_COLS,
  ];

  // Update-mode discovery: headless, shows the provenance-badged summary.
  $jobs['discovery'] = [
    'command' => 'php ' . $project_dir . '/playground/5-discovery/run.php',
    'interact' => '# Headless run: wait for the summary output.',
    'rows' => 12,
    'cols' => TERMINAL_COLS,
    'at' => 2500,
  ];

  // Static per-widget screenshots: a single frame of the initial state.
  $widget_rows = [
    'text' => 6,
    'number' => 6,
    'textarea' => 8,
    'password' => 6,
    'select' => 8,
    'multiselect' => 8,
    'suggest' => 10,
    'search' => 10,
    'multisearch' => 10,
    'confirm' => 6,
    'pause' => 6,
  ];

  foreach (widgetInteractions() as $widget => $interact) {
    foreach ($flag_variants as $suffix => $flags) {
      $jobs['widget-' . $widget . $suffix] = [
        'command' => 'php ' . $project_dir . '/playground/3-widgets/widget-' . $widget . '.php' . $flags,
        'interact' => $interact,
        'rows' => $widget_rows[$widget],
        'cols' => 44,
        'at' => 800,
      ];
    }
  }

  return $jobs;
}

/**
 * Main functionality - orchestrator mode.
 *
 * Launches all recordings as parallel worker processes.
 */
function main(): void {
  $script_dir = __DIR__;
  $project_dir = dirname($script_dir, 2);
  $assets_dir = dirname($script_dir) . '/assets';

  info('TUI - Asset Generator');
  info('=====================');
  info('');

  checkDependencies();
  installNodeDependencies($script_dir);

  if (!is_dir($assets_dir)) {
    mkdir($assets_dir, 0755, TRUE);
  }

  $jobs = getJobs($project_dir);
  $tmp_dir = $project_dir . '/.artifacts/tmp/asciinema';
  if (!is_dir($tmp_dir)) {
    mkdir($tmp_dir, 0755, TRUE);
  }

  // Launch all workers in parallel.
  $script_path = __FILE__;
  $processes = [];
  $pipes_list = [];

  info('Launching ' . count($jobs) . ' workers in parallel...');
  info('');

  foreach (array_keys($jobs) as $name) {
    $cmd = sprintf(
      'php %s --record %s',
      escapeshellarg($script_path),
      escapeshellarg($name)
    );

    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];

    $pipes = [];
    $process = proc_open($cmd, $descriptors, $pipes, $project_dir);

    if (!is_resource($process)) {
      throw new \RuntimeException('Failed to launch worker for: ' . $name);
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], FALSE);
    stream_set_blocking($pipes[2], FALSE);

    $processes[$name] = $process;
    $pipes_list[$name] = $pipes;

    info('  Started: ' . $name);
  }

  info('');

  // Wait for all workers to complete and reset terminal state.
  // Workers run asciinema/expect which can leave the terminal in raw mode.
  $failed = [];
  foreach ($processes as $name => $process) {
    $stdout = stream_get_contents($pipes_list[$name][1]);
    $stderr = stream_get_contents($pipes_list[$name][2]);
    fclose($pipes_list[$name][1]);
    fclose($pipes_list[$name][2]);

    $exit_code = proc_close($process);

    if ($exit_code !== 0) {
      $failed[$name] = trim(($stdout ?: '') . ($stderr ?: ''));
      info('  FAILED: ' . $name);
    }
    else {
      info('  Done: ' . $name);
    }
  }

  // Reset terminal - workers may leave it in raw mode.
  shell_exec('stty sane 2>/dev/null');

  // Cleanup.
  info('');
  info('Cleaning up: ' . $tmp_dir);
  removeDir($tmp_dir);

  if (!empty($failed)) {
    info('');
    info('Errors:');
    foreach ($failed as $name => $output) {
      info('  ' . $name . ': ' . $output);
    }
    throw new \RuntimeException('Failed to generate ' . count($failed) . ' asset(s).');
  }

  info('');
  info('Done. ' . count($jobs) . ' SVG assets updated in ' . $assets_dir);
}

/**
 * Worker mode - process a single recording.
 *
 * @param string $name
 *   The job name to process.
 */
function processOne(string $name): void {
  $script_dir = __DIR__;
  $project_dir = dirname($script_dir, 2);
  $assets_dir = dirname($script_dir) . '/assets';
  $tmp_dir = $project_dir . '/.artifacts/tmp/asciinema';

  $jobs = getJobs($project_dir);
  if (!isset($jobs[$name])) {
    throw new \RuntimeException('Unknown job: ' . $name);
  }

  if (!is_dir($tmp_dir)) {
    mkdir($tmp_dir, 0755, TRUE);
  }

  if (!is_dir($assets_dir)) {
    mkdir($assets_dir, 0755, TRUE);
  }

  $job = $jobs[$name];
  $cast_file = $tmp_dir . '/' . $name . '.cast';
  $expect_script = $tmp_dir . '/' . $name . '.exp';
  $svg_file = $assets_dir . '/' . $name . '.svg';
  $rows = $job['rows'] ?? TERMINAL_ROWS;
  $cols = $job['cols'] ?? TERMINAL_COLS;
  $at = $job['at'] ?? NULL;

  createExpectScript($expect_script, $job['command'], $job['interact']);
  recordSession($cast_file, $expect_script, $rows, $cols);
  postProcessCast($cast_file);
  convertToSvg($cast_file, $svg_file, $script_dir, $at);
}

/**
 * Check that all required dependencies are installed.
 */
function checkDependencies(): void {
  $deps = ['asciinema', 'expect', 'node', 'npm'];
  $missing = [];

  foreach ($deps as $dep) {
    if (empty(shell_exec('which ' . escapeshellarg($dep) . ' 2>/dev/null'))) {
      $missing[] = $dep;
    }
  }

  if (!empty($missing)) {
    throw new \RuntimeException('Missing required dependencies: ' . implode(', ', $missing));
  }

  info('All dependencies found.');
}

/**
 * Install Node.js dependencies for svg-term rendering.
 *
 * @param string $util_dir
 *   Path to the tooling directory containing svg-term-render.js.
 */
function installNodeDependencies(string $util_dir): void {
  info('Installing svg-term Node.js dependency...');

  $node_modules = $util_dir . '/node_modules';
  if (is_dir($node_modules . '/svg-term')) {
    info('svg-term already installed.');

    return;
  }

  $cmd = sprintf('npm install --prefix %s svg-term@1.3.1 2>&1', escapeshellarg($util_dir));
  $output = shell_exec($cmd);
  if (!is_dir($node_modules . '/svg-term')) {
    throw new \RuntimeException('Failed to install svg-term: ' . ($output ?? 'unknown error'));
  }

  info('svg-term installed.');
}

/**
 * Create an expect script that spawns a command and drives it.
 *
 * The shared prologue defines the interaction procs; the per-job body uses
 * them to drive the demo. Every script ends waiting for the process to exit.
 *
 * The pause proc waits while continuously draining the child's output: the
 * full-screen TUI redraws a whole frame per key press, so a plain sleep would
 * let the pty buffer fill and block the child on write, desynchronizing the
 * interaction and batching frames at the wrong timestamps.
 *
 * @param string $script_path
 *   Path to write the expect script.
 * @param string $command
 *   The command to spawn.
 * @param string $body
 *   The expect body driving the interaction.
 */
function createExpectScript(string $script_path, string $command, string $body): void {
  $template = <<<'EXPECT'
#!/usr/bin/env expect

set timeout 60
match_max 100000
log_user 1

proc safe_send {s} {
    if {[exp_pid] > 0} {
        send -- $s
    }
}

# Wait for the given time while draining the child's output, so frames reach
# the recorder as they are drawn.
proc pause {ms} {
    set end [expr {[clock milliseconds] + $ms}]
    while {[clock milliseconds] < $end} {
        expect -timeout 0 -re "___never_matches___"
        after 25
    }
}

proc wait_and_enter {} {
    pause 1000
    safe_send "\r"
}

proc type_text {text} {
    foreach char [split $text ""] {
        pause 120
        safe_send $char
    }
}

proc arrow_up {} {
    pause 300
    safe_send "\033\[A"
}

proc arrow_down {} {
    pause 300
    safe_send "\033\[B"
}

proc toggle_space {} {
    pause 300
    safe_send " "
}

proc press_backspace {} {
    pause 150
    safe_send "\x7f"
}

proc press_tab {} {
    pause 300
    safe_send "\t"
}

proc press_escape {} {
    pause 500
    safe_send "\033"
}

spawn @command@

@body@

# Let the final frames drain before waiting for the process to exit.
pause 1500

expect eof
EXPECT;

  $content = str_replace(['@command@', '@body@'], [$command, $body], $template);

  file_put_contents($script_path, $content);
  chmod($script_path, 0755);
}

/**
 * Record a session using asciinema with an expect script.
 *
 * @param string $cast_file
 *   Path to write the cast file.
 * @param string $expect_script
 *   Path to the expect script for automation.
 * @param int $rows
 *   Number of terminal rows.
 * @param int $cols
 *   Number of terminal columns.
 */
function recordSession(string $cast_file, string $expect_script, int $rows = TERMINAL_ROWS, int $cols = TERMINAL_COLS): void {
  $cmd = sprintf(
    'asciinema rec --command=%s --window-size=%dx%d --idle-time-limit=%d --overwrite %s 2>&1',
    escapeshellarg($expect_script),
    $cols,
    $rows,
    MAX_IDLE_TIME,
    escapeshellarg($cast_file)
  );

  $output = shell_exec($cmd);

  if (!file_exists($cast_file)) {
    throw new \RuntimeException('Failed to record session: ' . $cast_file . "\n" . ($output ?? ''));
  }
}

/**
 * Post-process a cast file.
 *
 * Removes the spawn command line and sanitizes paths.
 *
 * @param string $cast_file
 *   Path to the cast file.
 */
function postProcessCast(string $cast_file): void {
  $content = file_get_contents($cast_file);
  if ($content === FALSE) {
    return;
  }

  // Remove the spawn command line from the recording.
  $lines = explode("\n", $content);
  $filtered = [$lines[0]];
  for ($i = 1; $i < count($lines); $i++) {
    if (str_contains($lines[$i], 'spawn ')) {
      continue;
    }
    $filtered[] = $lines[$i];
  }

  // Add a pause at the end of the recording before the animation loops.
  // In asciicast v3, timestamps are relative (delta from previous event),
  // so we add an empty output event with the pause duration.
  $filtered[] = json_encode([END_PAUSE, 'o', ' ']);

  $content = implode("\n", $filtered);

  // Sanitize home directory paths.
  $home = getenv('HOME');
  if ($home !== FALSE && $home !== '') {
    $content = str_replace($home, '/home/user', $content);
  }

  file_put_contents($cast_file, $content);
}

/**
 * Convert a cast file to an SVG.
 *
 * When $at is provided, renders a single static frame at that timestamp.
 * Otherwise, renders an animated SVG of the full recording.
 *
 * @param string $cast_file
 *   Path to the input cast file.
 * @param string $svg_file
 *   Path to the output SVG file.
 * @param string $util_dir
 *   Path to the tooling directory containing svg-term-render.js.
 * @param int|null $at
 *   Optional timestamp in ms to capture a static frame.
 */
function convertToSvg(string $cast_file, string $svg_file, string $util_dir, ?int $at = NULL): void {
  $renderer = $util_dir . '/svg-term-render.js';

  $at_flag = $at !== NULL ? sprintf(' --at %d', $at) : '';
  $cmd = sprintf(
    'node %s %s %s --line-height 1.1%s 2>&1',
    escapeshellarg($renderer),
    escapeshellarg($cast_file),
    escapeshellarg($svg_file),
    $at_flag
  );

  $output = shell_exec($cmd);

  if (!file_exists($svg_file) || filesize($svg_file) === 0) {
    throw new \RuntimeException('Failed to convert cast to SVG: ' . $cast_file . "\n" . ($output ?? ''));
  }
}

/**
 * Remove a directory recursively.
 *
 * @param string $directory
 *   Path to the directory to remove.
 */
function removeDir(string $directory): void {
  if (!is_dir($directory)) {
    return;
  }

  $cmd = sprintf('rm -rf %s 2>&1', escapeshellarg($directory));
  shell_exec($cmd);
}

/**
 * Print an informational message.
 *
 * @param string $message
 *   The message to print.
 */
function info(string $message): void {
  if (getenv('SCRIPT_QUIET') === '1') {
    return;
  }
  print $message . PHP_EOL;
}

// Entrypoint.
ini_set('display_errors', '1');

if (PHP_SAPI !== 'cli' || !empty($_SERVER['REMOTE_ADDR'])) {
  die('This script can be only ran from the command line.');
}

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
  if ((error_reporting() & $severity) === 0) {
    return FALSE;
  }
  throw new \ErrorException($message, 0, $severity, $file, $line);
});

try {
  // Worker mode: process a single recording.
  $record_index = array_search('--record', $argv, true);
  if ($record_index !== FALSE && isset($argv[$record_index + 1])) {
    processOne($argv[$record_index + 1]);
  }
  else {
    // Orchestrator mode: launch all in parallel.
    main();
  }
}
catch (\Exception $exception) {
  info('');
  info('ERROR: ' . $exception->getMessage());
  exit(1);
}
