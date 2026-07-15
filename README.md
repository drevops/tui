<p align="center">
  <img height="200" src="logo.svg" alt="TUI logo">
</p>

<h1 align="center">Terminal user interfaces for PHP</h1>

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
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/assets/bordered-panels.svg">
    <img src="docs/assets/bordered-panels-light.svg" width="100%" alt="Terminal form with a rounded border">
  </picture>
</p>

A dependency-light PHP engine for building **terminal user interfaces**: interactive, keyboard-driven forms that collect answers and hand them to your code. Describe the questions in PHP with a fluent builder, add a handler class wherever a question needs real behaviour, and the engine renders a scrollable, themeable TUI - or collects the answers non-interactively from a JSON payload.

The engine knows nothing about the application it serves: it stays generic, the application-specific questions and handlers live in the consumer, and **applying the collected answers is the consumer's job, not the TUI's**.

The border is optional - here is the same form without one, at normal spacing:

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/assets/borderless-panels.svg">
    <img src="docs/assets/borderless-panels-light.svg" width="100%" alt="Terminal form without a border">
  </picture>
</p>

## 📖 Documentation

Full documentation - every widget, configuration, theming, key bindings and the engine architecture - lives at **[phptui.dev](https://phptui.dev)**.

- 🧭 [**Full-screen TUI**](https://phptui.dev/panels) - a scrollable, keyboard-driven form with a contextual key-hint footer and a `?` help overlay
- ⚡ [**Inline editing**](https://phptui.dev/panels#inline-editing) - a field's editor opens in place on the panel row (the widget's own view and keys), no separate screen; opt a field out to full-screen with `->standalone()`
- 🧩 [**Widgets**](https://phptui.dev/widgets) - field types for text, numbers, dates, choices, file browsing, fuzzy search and gates
- 🏗️ [**Builder-driven**](https://phptui.dev/configuration) - the form is declared in PHP with a fluent builder
- 🎛️ [**Interactive or unattended**](https://phptui.dev/headless-collection) - answer the form by keyboard, or supply the answers up front as a JSON payload and environment variables so it runs without prompting
- 🔗 [**Derived values**](https://phptui.dev/configuration#derived-values) and 🔀 [**conditional fields**](https://phptui.dev/configuration#conditional-fields) that settle to a fixpoint
- 🔍 [**Discovery**](https://phptui.dev/discovery), ⚙️ [**declared behaviour**](https://phptui.dev/field-behaviour) and 📦 [**self-describing answers**](https://phptui.dev/self-describing-answers)
- 🎨 [**Themes**](https://phptui.dev/themes), ⌨️ [**key bindings**](https://phptui.dev/key-bindings) and ✨ [**Unicode and ASCII**](https://phptui.dev/display-modes) display modes
- 🧪 [**Test harness**](https://phptui.dev/testing) - drive a form from scripted keystrokes and assert on the answers and rendered output
- 🌍 [**Translations**](https://phptui.dev/translations) - present chrome and questions in another language, falling back to English

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

// Interactive on a terminal, non-interactive otherwise.
$answers = $tui->run();

// Or call a mode directly:
echo $tui->collect('{"name":"Ada"}')->toJson();  // non-interactive: JSON + environment
$answers = $tui->interact();                     // interactive TUI
```

Read the [full guide at phptui.dev](https://phptui.dev) and browse [`playground/`](playground) for complete, runnable examples.

## Widgets

The widgets cover text entry, choices, filesystem browsing and gates. Each links to its full reference on [phptui.dev](https://phptui.dev/widgets). Every image below follows your colour scheme - light or dark.

<table>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-calendar.svg"><img src="docs/assets/widget-calendar-light.svg" width="100%" alt="Calendar widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/calendar">Calendar</a></strong><br>A month calendar returning a normalized ISO <code>YYYY-MM-DD</code>; arrows move by day and week.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-confirm.svg"><img src="docs/assets/widget-confirm-light.svg" width="100%" alt="Confirm widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/confirm">Confirm</a></strong><br>Yes/No toggle; arrows or Space switch, <code>y</code>/<code>n</code> set the choice directly, Enter accepts.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-filepicker.svg"><img src="docs/assets/widget-filepicker-light.svg" width="100%" alt="File picker widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/filepicker">File picker</a></strong><br>Browse the filesystem for a single path; arrows move, <code>→</code> enters a directory and <code>←</code> returns to its parent.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-multifilepicker.svg"><img src="docs/assets/widget-multifilepicker-light.svg" width="100%" alt="Multi file picker widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/multifilepicker">Multi file picker</a></strong><br>Like the file picker, but several paths accumulate as you browse; <code>Space</code> toggles the highlighted entry.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-multisearch.svg"><img src="docs/assets/widget-multisearch-light.svg" width="100%" alt="MultiSearch widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/multisearch">MultiSearch</a></strong><br>A multi-select whose filter query shows as a search line; typing fuzzy-matches and ranks, Space toggles matches.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-multiselect.svg"><img src="docs/assets/widget-multiselect-light.svg" width="100%" alt="MultiSelect widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/multiselect">MultiSelect</a></strong><br>Multiple choice from a checkbox list; Space toggles, typing narrows the list, select-all and deselect-all in one key.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-number.svg"><img src="docs/assets/widget-number-light.svg" width="100%" alt="Number widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/number">Number</a></strong><br>Integer entry (digits with an optional leading minus) accepted as an <code>int</code>, with optional min, max and step.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-password.svg"><img src="docs/assets/widget-password-light.svg" width="100%" alt="Password widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/password">Password</a></strong><br>Text rendered as a mask in the editor, the field row and the summary; the accepted value stays plain for the consumer, and can be made revealable.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-pause.svg"><img src="docs/assets/widget-pause-light.svg" width="100%" alt="Pause widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/pause">Pause</a></strong><br>An acknowledgement gate; Enter or Space accepts. Unattended runs auto-acknowledge it, so it never blocks automation.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-reorder.svg"><img src="docs/assets/widget-reorder-light.svg" width="100%" alt="Reorder widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/reorder">Reorder</a></strong><br>Rank a list by moving items into the order you want; Space picks an item up, arrows carry it through the list, Enter accepts.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-search.svg"><img src="docs/assets/widget-search-light.svg" width="100%" alt="Search widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/search">Search</a></strong><br>Single choice with a visible filter line; typing fuzzy-matches and ranks the labels, exact and prefix matches leading.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-select.svg"><img src="docs/assets/widget-select-light.svg" width="100%" alt="Select widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/select">Select</a></strong><br>Single choice from a list; arrows move, Enter accepts the highlighted option, long lists page around the cursor.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-suggest.svg"><img src="docs/assets/widget-suggest-light.svg" width="100%" alt="Suggest widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/suggest">Suggest</a></strong><br>Free text with autocomplete over a fixed option set: as you type, suggestions are fuzzy-matched and ranked by relevance.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-text.svg"><img src="docs/assets/widget-text-light.svg" width="100%" alt="Text widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/text">Text</a></strong><br>Single-line input with a movable caret; type to insert, arrows move, Backspace deletes, Enter accepts.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-textarea.svg"><img src="docs/assets/widget-textarea-light.svg" width="100%" alt="Textarea widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/textarea">Textarea</a></strong><br>Multi-line input; Enter inserts a newline, arrows move between lines, Tab accepts, with an external-editor handoff.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-toggle.svg"><img src="docs/assets/widget-toggle-light.svg" width="100%" alt="Toggle widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/toggle">Toggle</a></strong><br>An inline switch between two labelled values; arrows or Space flip, the first letter of each label sets it directly.</td>
</tr>
</table>

## Maintenance

```bash
composer install
composer lint
composer test
```

See the [Contributing guide](https://phptui.dev/contributing) for the full development workflow.

---
_This repository was created using the [Scaffold](https://getscaffold.dev/) project template_
