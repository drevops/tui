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
  ([`OceanTheme.php`](2-custom-theme/OceanTheme.php)) named directly on the facade,
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
  `widget-select-multiple.php`, `widget-suggest.php`, `widget-search.php`,
  `widget-search-multiple.php`, `widget-filepicker.php`,
  `widget-filepicker-multiple.php`, `widget-confirm.php`, `widget-toggle.php`,
  `widget-pause.php`.
  The `widget-select-groups.php` and `widget-select-multiple-groups.php` demos show
  group headings, separators and disabled options.

- **[`4-nested-panels/`](4-nested-panels)** - a hub with drill-in sub-panels
  (nested to any depth), per-option descriptions, custom button labels
  (`->buttons(TRUE, 'Save', 'Discard')`), a `->fixup()` rule that reconciles
  dependent answers on every settle pass, and the facade's `->clearOnExit(FALSE)`.

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
  auto-detecting the light or dark mode from the terminal background, or forcing
  one with `--mode`. The auto path follows your terminal's colour scheme; forcing
  overrides it.

  ```bash
  php playground/6-theme-detect/run.php                 # auto-detect from the background
  php playground/6-theme-detect/run.php --mode=dark     # force dark
  php playground/6-theme-detect/run.php --mode=light    # force light
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

- **[`9-bordered-panels/`](9-bordered-panels)** - the panel browser wrapped in a
  rounded border. The border is a theme option (`none`, `line`, `rounded` or
  `double`), set as a plain string alongside the spacing, and every drill-in
  sub-panel keeps it.

  ```bash
  php playground/9-bordered-panels/run.php                     # TUI
  php playground/9-bordered-panels/run.php --prompts='{"name":"api"}'
  ```

- **[`10-inline-fields/`](10-inline-fields)** - inline editing: each field's editor
  opens in place on the panel row on Enter (the confirm's Yes/No, the number's
  input, the select's list), collapsing back on accept or cancel. Inline is the
  default; the calendar opts out with `->standalone()` for its full-screen month
  grid.

  ```bash
  php playground/10-inline-fields/run.php                      # TUI
  php playground/10-inline-fields/run.php --prompts='{"env":"prod"}'
  ```

- **[`10-builtin-themes/`](10-builtin-themes)** - one form rendered under a
  chosen built-in theme, selected with `--theme`: `default`, `midnight`,
  `frost`, `ember`, `mono` or `dos` (the retro MS-DOS CGA palette on its own blue
  screen). For the adaptive themes the dark or light palette is auto-detected
  from the terminal background.

  ```bash
  php playground/10-builtin-themes/run.php                  # midnight
  php playground/10-builtin-themes/run.php --theme=frost
  php playground/10-builtin-themes/run.php --theme=dos      # its own blue screen
  ```

- **[`11-modal-panel/`](11-modal-panel)** - a panel marked `->modal()` opens as a
  centered dialog over the dimmed parent instead of drilling in, with its own
  configurable submit/cancel buttons. One dialog collects fields (Save keeps the
  edits, Discard or Escape restores them); a second is a text-only warning.

  ```bash
  php playground/11-modal-panel/run.php                        # TUI
  php playground/11-modal-panel/run.php --prompts='{"gift_wrap":true}'
  ```

## How the TUI picks a theme

Set it on the `Tui` facade with `->theme(...)`, lowest friction first:

1. **Name the class** - `->theme('\Your\ThemeClass')`. The class is instantiated
   directly; no registration needed. This is what `2-custom-theme` does.
2. **Register a short name** - `ThemeManager::register('ocean', OceanTheme::class)`,
   then `->theme('ocean')`. Useful to give a class a stable alias.
3. **Built-in name** - `->theme('midnight')` (or `frost`, `ember`, `mono`,
   `default` or `dos`). Dark or light is a separate `mode` option, not a theme, so a
   built-in adapts to both. This is what `10-builtin-themes` demonstrates.
4. **Auto-detect** - leave it unset (or `->theme('auto')`) and the `default`
   theme is used, with the interactive TUI picking the dark or light `mode` from
   the terminal background (an OSC 11 query, then `COLORFGBG`, then a dark
   default). Setting `mode` explicitly opts out. This is what `6-theme-detect`
   demonstrates.

## How the TUI sets key bindings

Set them on the `Tui` facade with `->keys(...)`, mirroring `->theme(...)`:

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
