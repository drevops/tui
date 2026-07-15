# Terminal SVG assets

Generated terminal renders used by the project README and the documentation
site. Do not edit these by hand - they are produced by the scripts in
[`../util`](../util).

## Naming convention

Every file spells out four properties, so you can tell what it is from the name
alone:

```text
<subject>-<theme>-<motion>[-ascii][-no-ansi].svg
```

| Segment    | Values              | Meaning                                            |
|------------|---------------------|----------------------------------------------------|
| `subject`  | e.g. `widget-text`  | What is shown (a widget, a panel demo, the montage) |
| `theme`    | `dark` \| `light`   | Colour scheme                                       |
| `motion`   | `animated` \| `static` | An animation, or a single frame                  |
| `-ascii`   | present when set    | Textual glyphs instead of Unicode (default Unicode) |
| `-no-ansi` | present when set    | No colour (default colour)                          |

Unicode and colour are the unmarked defaults, so `widget-text-dark-animated.svg`
is the dark, Unicode, colour animation, and
`widget-text-dark-static-ascii-no-ansi.svg` is its ASCII, no-colour static twin.

## What generates what

- **`render-widget-svgs.php`** - the per-widget hero cards the README embeds
  (`widget-*-dark-animated.svg`), driven deterministically through the library's
  own keystroke harness, no terminal required.
- **`make-light-svgs.php`** - the `*-light-animated.svg` twins, recoloured from
  the dark originals.
- **`update-assets.php`** - the full panel demos and the alternate display-mode
  screenshots, recorded from a live terminal.

Every animated SVG is slowed to a shared playback factor so the motion stays
readable.
