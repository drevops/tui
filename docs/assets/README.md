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

- **`render-widget-svgs.php`** - every per-widget asset, driven deterministically
  through the library's own keystroke harness with no terminal: the animated
  cards in all four display modes (`widget-*-dark-animated*.svg`, the unmarked one
  being the hero) and the matching static screenshots (`widget-*-dark-static*.svg`).
- **`make-light-svgs.php`** - the light twins, recoloured from the dark
  originals: each widget's whole dark set (both motions, all four display modes)
  mirrored into `widget-*-light-*.svg`, plus each panel hero's `-light-animated`.
- **`update-assets.php`** - the full panel demos, the widget montage and the
  option-group / password-reveal / discovery frames, recorded from a live
  terminal.

Every animated SVG is slowed to a shared playback factor so the motion stays
readable.
