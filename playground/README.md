# TUI playground

Runnable, self-contained examples of the `drevops/tui` engine. Each
numbered directory is independent - copy one as a starting point.

```bash
composer install
```

## Examples

- **[`0-minimal/`](0-minimal)** - the smallest runner: a two-field config, no
  handlers, collected non-interactively.

  ```bash
  php playground/0-minimal/run.php --prompts='{"name":"Ada","colour":"green"}'
  ```

- **[`1-scaffolder/`](1-scaffolder)** - a "package scaffolder" that exercises
  every widget type, conditional visibility (`when`), derived values with
  str2name transforms, and declared field behaviour (a dynamic default,
  validation and a transform as closures). Runs the interactive TUI or
  collects non-interactively.

  ```bash
  php playground/1-scaffolder/run.php                                  # TUI
  php playground/1-scaffolder/run.php --prompts='{"name":"My Widget"}'
  php playground/1-scaffolder/run.php --schema
  ```

- **[`2-custom-theme/`](2-custom-theme)** - a self-contained custom theme class
  ([`OceanTheme.php`](2-custom-theme/OceanTheme.php)) named directly on the form,
  driving the TUI with a banner.

  ```bash
  php playground/2-custom-theme/run.php
  ```

- **[`3-widgets/`](3-widgets)** - each widget shown as a field on a form and
  collected through the `Tui` facade: one widget per file, or every widget on a
  single form. Widgets pull their glyphs from the theme, and the mode flags
  force textual (ASCII) or no-colour rendering so you can see either without
  changing your terminal locale (mirrors prompty's `--no-unicode` /
  `--no-ansi`):

  ```bash
  php playground/3-widgets/widget-select.php               # one widget, as a form
  php playground/3-widgets/widgets.php                     # every widget on one form
  php playground/3-widgets/widget-select.php --no-unicode  # textual glyphs
  php playground/3-widgets/widget-select.php --no-ansi     # no colour
  php playground/3-widgets/show.php                        # static, both modes side by side
  ```

  Per-widget files: `widget-text.php`, `widget-number.php`,
  `widget-textarea.php`, `widget-password.php`, `widget-select.php`,
  `widget-multiselect.php`, `widget-suggest.php`, `widget-search.php`,
  `widget-multisearch.php`, `widget-filepicker.php`,
  `widget-multifilepicker.php`, `widget-confirm.php`, `widget-toggle.php`,
  `widget-pause.php`.
  The `widget-select-groups.php` and `widget-multiselect-groups.php` demos show
  group headings, separators and disabled options.

- **[`4-nested-panels/`](4-nested-panels)** - a hub with drill-in sub-panels
  (nested to any depth), per-option descriptions, custom button labels
  (`->buttons(TRUE, 'Save', 'Discard')`), `->clearOnExit(FALSE)` and a
  `->fixup()` rule that reconciles dependent answers on every settle pass.

  ```bash
  php playground/4-nested-panels/run.php                        # TUI
  php playground/4-nested-panels/run.php --prompts='{"environment":"dev","cdn":true}'
  ```

- **[`5-discovery/`](5-discovery)** - update-mode discovery against the bundled
  [`sample/`](5-discovery/sample) project: every `->discover()` rule type
  (dotenv key, JSON dot-path, path exists, directory scan), a form-declared
  `->envPrefix('MYAPP_')` namespacing the env overrides, and the
  provenance-badged summary via `$answers->toSummary()`.

  ```bash
  php playground/5-discovery/run.php                            # discover from sample/
  MYAPP_TIMEZONE=UTC php playground/5-discovery/run.php         # env override wins
  php playground/5-discovery/run.php --prompts='{"name":"Renamed"}'
  ```

- **[`6-theme-detect/`](6-theme-detect)** - the same form resolved two ways:
  auto-detecting the light or dark theme from the terminal background, or forcing
  one with `--theme`. The auto path follows your terminal's colour scheme; forcing
  overrides it.

  ```bash
  php playground/6-theme-detect/run.php                 # auto-detect from the background
  php playground/6-theme-detect/run.php --theme=dark    # force dark
  php playground/6-theme-detect/run.php --theme=light   # force light
  ```

- **[`7-theme-options/`](7-theme-options)** - the display options (`spacing`,
  `border`) and a custom `accent` option declared by a registered theme, all set
  as plain strings in one array. Each value is validated, so a typo throws.

  ```bash
  php playground/7-theme-options/run.php
  ```

- **[`8-key-bindings/`](8-key-bindings)** - the same form driven with three key
  maps, selected with `--keys`: the default bindings, the built-in `vim` preset
  (h/j/k/l), and a custom per-widget-type override. The panel and editor hints
  follow whatever is bound.

  ```bash
  php playground/8-key-bindings/run.php              # default (arrow keys)
  php playground/8-key-bindings/run.php --keys=vim   # h/j/k/l navigation
  php playground/8-key-bindings/run.php --keys=custom
  ```

## How a form picks a theme

Set it on the builder with `->theme(...)`, lowest friction first:

1. **Name the class** - `->theme('\Your\ThemeClass')`. The class is instantiated
   directly; no registration needed. This is what `2-custom-theme` does.
2. **Register a short name** - `ThemeManager::register('ocean', OceanTheme::class)`,
   then `->theme('ocean')`. Useful to give a class a stable alias.
3. **Built-ins** - `->theme('dark')` or `->theme('light')` to force one.
4. **Auto-detect** - leave it unset (or `->theme('auto')`) and the interactive TUI
   picks `dark` or `light` from the terminal background (an OSC 11 query, then
   `COLORFGBG`, then a dark default). Forcing a built-in opts out. This is what
   `6-theme-detect` demonstrates.

## How a form sets key bindings

Set them on the builder with `->keys(...)`, mirroring `->theme(...)`:

1. **A preset name** - `->keys('vim')` for the built-in vim navigation, or a name
   registered with `KeyMapManager::register('name', MyKeyMap::class)`.
2. **A preset class** - `->keys('\Your\KeyMapClass')`, instantiated directly with
   no registration.
3. **Overrides** - `->keys('default', [new Binding(Scope::field(FieldType::Select), Action::Accept, KeyName::Tab)])`
   retunes individual bindings on top of a preset. A binding names a scope (the
   base, navigation, or a widget type), an action and its keys.
4. **Defaults** - leave it unset for the built-in bindings. This is what most
   examples do.

Conflicting or un-typeable bindings throw when the form is built, so a bad key map
is caught at declaration time, not mid-session. This is what `8-key-bindings`
demonstrates.
