# TUI documentation site

The [phptui.dev](https://phptui.dev) documentation site, built with
[Docusaurus](https://docusaurus.io/).

The documentation content lives in [`content/`](content) as `.mdx` pages; the
sidebar is generated from the folder structure.

## Local development

```bash
npm install
npm start
```

This starts a local development server and opens a browser window. Most changes
are reflected live without having to restart the server.

## Build

```bash
npm run build
```

This generates static content into the `build` directory that can be served by
any static hosting service.

## Tests

```bash
npm run test
```

Runs the [Jest](https://jestjs.io/) component tests and the
[CSpell](https://cspell.org/) spell check over the content. Add project-specific
terms to [`cspell.json`](cspell.json).

## Publishing

The site is published to GitHub Pages on release (a pushed tag) by the
[`release-docs.yml`](../.github/workflows/release-docs.yml) workflow. The
released version is imprinted into the site footer via the `RELEASE_VERSION`
environment variable.

## Terminal recordings

The [`AsciinemaPlayer`](src/components/AsciinemaPlayer/AsciinemaPlayer.js)
component embeds interactive [asciinema](https://asciinema.org/) recordings.
Record a session to a cast file under `static/img/`:

```bash
asciinema rec --output-format asciicast-v2 static/img/demo.cast
```

Then embed the player in any `.mdx` page:

```jsx
import AsciinemaPlayer from '@site/src/components/AsciinemaPlayer';

<AsciinemaPlayer src="/img/demo.cast" autoPlay loop controls />
```
