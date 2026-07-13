<p align="center">
  <img height="200" src="logo.png" alt="TUI logo">
</p>

<h1 align="center">Panel-based terminal forms for PHP</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/drevops/tui.svg)](https://github.com/drevops/tui/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/drevops/tui.svg)](https://github.com/drevops/tui/pulls)
[![Test PHP](https://github.com/drevops/tui/actions/workflows/test-php.yml/badge.svg)](https://github.com/drevops/tui/actions/workflows/test-php.yml)
[![codecov](https://codecov.io/gh/drevops/tui/graph/badge.svg?token=7WEB1IXBYT)](https://codecov.io/gh/drevops/tui)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/drevops/tui)
![LICENSE](https://img.shields.io/github/license/drevops/tui)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

</div>

---

<p align="center">
  <img src="docs/assets/scaffolder.svg" width="100%" alt="Package scaffolder panel TUI">
</p>

A dependency-light PHP engine for building **panel-based terminal forms**: interactive, keyboard-driven questionnaires that collect answers and hand them to your code. Describe the questions in PHP with a fluent builder, add a handler class wherever a question needs real behaviour, and the engine renders a scrollable, themeable TUI - or runs headless from a JSON payload.

It powers the [Vortex](https://www.vortextemplate.com) project installer, but knows nothing about Vortex: the engine is generic, the project-specific questions and handlers live in the consumer, and **applying the collected answers is the consumer's job, not the TUI's**.

## 📖 Documentation

Full documentation - every widget, configuration, theming, key bindings and the engine architecture - lives at **[tui.drevops.com](https://tui.drevops.com)**.

- 🧭 [**Panel TUI**](https://tui.drevops.com/panels) - a full-screen, scrollable, keyboard-driven form with a contextual key-hint footer and a `?` help overlay
- 🧩 [**Widgets**](https://tui.drevops.com/widgets) - `text`, `number`, `calendar`, `textarea`, `password`, `select`, `multiselect`, `suggest`, `search`, `multisearch`, `filepicker`, `multifilepicker`, `confirm`, `toggle`, `pause`
- 🏗️ [**Builder-driven**](https://tui.drevops.com/configuration) - panels and fields declared in PHP with a fluent builder
- 🤖 [**Interactive or headless**](https://tui.drevops.com/headless-collection) - drive the panel TUI by keyboard, or collect answers from a JSON payload and environment variables
- 🔗 [**Derived values**](https://tui.drevops.com/configuration#derived-values) and 🔀 [**conditional fields**](https://tui.drevops.com/configuration#conditional-fields) that settle to a fixpoint
- 🔍 [**Discovery**](https://tui.drevops.com/discovery), ⚙️ [**declared behaviour**](https://tui.drevops.com/field-behaviour) and 📦 [**self-describing answers**](https://tui.drevops.com/self-describing-answers)
- 🎨 [**Themes**](https://tui.drevops.com/themes), ⌨️ [**key bindings**](https://tui.drevops.com/key-bindings) and ✨ [**Unicode and ASCII**](https://tui.drevops.com/display-modes) display modes
- 🧪 [**Test harness**](https://tui.drevops.com/testing) - drive a form's panel TUI from scripted keystrokes and assert on the answers and rendered output

## Installation

```bash
composer require drevops/tui
```

## Quick start

Declare a form with the fluent `Form` builder, then drive it with the `Tui` facade - one class that wires the engine, resolver, schema tools and TUI for you:

```php
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

$form = Form::create('My form')
  ->panel('general', 'General', fn(PanelBuilder $p) => $p->text('name', 'Your name')->required());

$tui = new Tui($form, ['App\\Handler']);

// Interactive panel TUI on a terminal, headless otherwise.
$answers = $tui->run();

// Or call a mode directly:
echo $tui->collect('{"name":"Ada"}')->toJson();  // headless: JSON + environment
$answers = $tui->interact();                     // interactive panel TUI
```

Read the [full guide at tui.drevops.com](https://tui.drevops.com) and browse [`playground/`](playground) for complete, runnable examples.

<p align="center">
  <img src="docs/assets/widgets.svg" width="100%" alt="All widgets, one after another">
</p>

## Maintenance

```bash
composer install
composer lint
composer test
```

See the [Contributing guide](https://tui.drevops.com/contributing) for the full development workflow.

## Updating

To pull the latest infrastructure from the template into this project, ask Claude Code to "update scaffold" - see [`AGENTS.md`](AGENTS.md) for details.

---
