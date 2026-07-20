# TUI playground

Runnable examples of the `drevops/tui` engine, one directory per feature, numbered in learning order. Every script is self-contained: it requires the Composer autoloader directly, declares its whole form inline and handles its own output, so any single file can be copied out as a starting point. Most take no CLI options - each demonstrates exactly one thing, and variants are separate scripts - with two exceptions: [`03-panels/fullscreen.php`](03-panels/fullscreen.php) picks its alignment with `--halign`/`--valign` (plus `--max-width`) rather than spreading nine near-identical files, and [`05-headless/agent-cli.php`](05-headless/agent-cli.php) parses `--agent`, `--no-interaction` and `--prompts` to demonstrate the `--agent` CLI recipe.

```bash
composer install
php playground/01-quickstart/run.php
```

Every interactive script also runs unattended: pipe stdin (or run it from CI) and the answers resolve from defaults and `TUI_<ID>` environment variables instead of prompting.

## Examples

| Directory | Feature | Scripts |
|---|---|---|
| [`01-quickstart/`](01-quickstart) | The documentation's quick-start form: the fluent builder, one panel, five fields, `run()` picking interactive or unattended. | `run.php` |
| [`02-widgets/`](02-widgets) | Every widget as a one-field form, plus the whole gallery on one panel. | One script per widget, `all-widgets.php` |
| [`03-panels/`](03-panels) | The full-screen panel browser: drill-in hubs, modal dialogs, the border frame, side-by-side panel grids, the fullscreen stretch with its alignment flags. | `nested.php`, `modal.php`, `bordered.php`, `borderless.php`, `layout.php`, `fullscreen.php` |
| [`04-inline-editing/`](04-inline-editing) | Editors opening in place on the panel row; `->standalone()` opting a field out to full-screen. | `run.php` |
| [`05-headless/`](05-headless) | Unattended collection from a JSON payload and environment variables; the JSON schema, answer validation, generated agent help and the `--agent` CLI recipe. | `collect.php`, `schema.php`, `agent-help.php`, `agent-cli.php` |
| [`06-form-logic/`](06-form-logic) | Answers that react to other answers, settling to a fixpoint. | `derived-values.php`, `conditional-fields.php`, `fixup-rules.php` |
| [`07-field-behaviour/`](07-field-behaviour) | Dynamic defaults, validation and transforms - as field closures and as reusable handler classes. | `closures.php`, `handlers.php` |
| [`08-discovery/`](08-discovery) | Update-mode discovery against the bundled `sample/` project: dotenv, JSON dot-path, path-exists and directory-scan rules, plus a custom env prefix. | `run.php` |
| [`09-themes/`](09-themes) | The six built-in themes, a custom theme class, theme options and the field input styles. | One script per built-in theme, `custom.php`, `options.php`, `field-boxed.php`, `field-underline.php` |
| [`10-key-bindings/`](10-key-bindings) | The `vim` preset and per-binding overrides on top of a preset. | `vim.php`, `custom.php` |
| [`11-display-modes/`](11-display-modes) | Dark/light detection and forcing, ASCII glyphs, colour off, and a static Unicode-vs-ASCII gallery. | `mode-auto.php`, `mode-forced.php`, `ascii.php`, `no-color.php`, `glyph-gallery.php` |
| [`12-testing/`](12-testing) | The scripted-keystroke harness: drive the real TUI without a terminal, read back answers and rendered frames. | `run.php` |
| [`13-translations/`](13-translations) | Chrome and questions localized through a consumer catalog, English fallback. | `run.php`, `catalog/es.php` |
| [`14-produce-box/`](14-produce-box) | The capstone: panels, widgets, derivation, conditions and behaviour composed into one real form. | `run.php` |

## Running the examples

Each script prints how to invoke it in its `@file` docblock. The common patterns:

```bash
# Interactive TUI (any interactive example).
php playground/14-produce-box/run.php

# Unattended: defaults and environment answer instead of a keyboard.
TUI_NAME='Summer Box' php playground/14-produce-box/run.php < /dev/null

# Discovery uses its own env prefix, declared by the form.
BOX_SEASON=winter php playground/08-discovery/run.php
```

Display modes follow the terminal and the standard environment conventions, so no script needs flags for them:

```bash
NO_COLOR=1 php playground/02-widgets/select.php       # colour off
LC_ALL=C php playground/02-widgets/select.php         # ASCII glyphs
COLORFGBG='0;15' php playground/02-widgets/select.php # hint a light background
```

## How the TUI picks a theme

Set it on the `Tui` facade with `->theme(...)`, lowest friction first:

1. **Name the class** - `->theme('\Your\ThemeClass')`. The class is instantiated directly; no registration needed. This is what [`09-themes/custom.php`](09-themes/custom.php) does.
2. **Register a short name** - `ThemeManager::register('accent', AccentTheme::class)`, then `->theme('accent')`. Useful to give a class a stable alias. This is what [`09-themes/options.php`](09-themes/options.php) does.
3. **Built-in name** - `->theme('midnight')` (or `frost`, `ember`, `mono`, `default` or `dos`). Dark or light is a separate `mode` display option, not a theme, so a built-in adapts to both. One script per theme in [`09-themes/`](09-themes).
4. **Auto-detect** - leave it unset (or `->theme('auto')`) and the `default` theme is used, with the interactive TUI picking the dark or light `mode` from the terminal background (an OSC 11 query, then `COLORFGBG`, then a dark default). Setting `mode` explicitly opts out. This is what [`11-display-modes/mode-auto.php`](11-display-modes/mode-auto.php) demonstrates.

## How the TUI sets key bindings

Set them on the `Tui` facade with `->keys(...)`, mirroring `->theme(...)`:

1. **A preset name** - `->keys('vim')` for the built-in vim navigation, or a name registered with `KeyMapManager::register('name', MyKeyMap::class)`.
2. **A preset class** - `->keys('\Your\KeyMapClass')`, instantiated directly with no registration.
3. **Overrides** - `->keys('default', [new Binding(Scope::field(FieldType::Select), Action::Accept, KeyName::Tab)])` retunes individual bindings on top of a preset. A binding names a scope (the base, navigation, or a widget type), an action and its keys.
4. **Defaults** - leave it unset for the built-in bindings. This is what most examples do.

Conflicting or un-typeable bindings throw when the facade is configured, so a bad key map is caught at declaration time, not mid-session. Both override styles live in [`10-key-bindings/`](10-key-bindings).
