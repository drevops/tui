// @ts-check
// `@type` JSDoc annotations allow editor autocompletion and type checking
// (when paired with `@ts-check`).
// There are various equivalent ways to declare your Docusaurus config.
// @see https://docusaurus.io/docs/api/docusaurus-config

// The docs code blocks highlight with the homepage code-window palette (the
// token colours in `src/pages/index.module.css`), so every code sample on the
// site reads as one system: teal keywords and class names, off-white
// functions, grey-blue variables, sage strings on the deep slate surface. The
// light variant deepens the same hues for contrast on a light background.
/** @type {import('prism-react-renderer').PrismTheme} */
const tuiDark = {
  plain: {color: '#eae4d4', backgroundColor: '#1a1d23'},
  styles: [
    {types: ['comment', 'prolog', 'doctype', 'cdata'], style: {color: '#5f6b63', fontStyle: 'italic'}},
    {types: ['string', 'attr-value', 'inserted'], style: {color: '#8fb98a'}},
    {types: ['keyword', 'boolean', 'important', 'atrule'], style: {color: '#2dd4bf'}},
    {types: ['class-name', 'maybe-class-name', 'builtin', 'tag'], style: {color: '#5ec8c0'}},
    {types: ['function', 'attr-name'], style: {color: '#f4f1e8'}},
    {types: ['variable'], style: {color: '#b7c0cd'}},
    {types: ['punctuation'], style: {color: 'rgba(234, 228, 212, 0.45)'}},
    {types: ['deleted'], style: {color: '#c98a8a'}},
  ],
};

/** @type {import('prism-react-renderer').PrismTheme} */
const tuiLight = {
  plain: {color: '#3b4148', backgroundColor: '#fafafa'},
  styles: [
    {types: ['comment', 'prolog', 'doctype', 'cdata'], style: {color: '#8a938f', fontStyle: 'italic'}},
    {types: ['string', 'attr-value', 'inserted'], style: {color: '#557b46'}},
    {types: ['keyword', 'boolean', 'important', 'atrule'], style: {color: '#0d9488'}},
    {types: ['class-name', 'maybe-class-name', 'builtin', 'tag'], style: {color: '#0e7490'}},
    {types: ['function', 'attr-name'], style: {color: '#30363d'}},
    {types: ['variable'], style: {color: '#64748b'}},
    {types: ['punctuation'], style: {color: 'rgba(59, 65, 72, 0.5)'}},
    {types: ['deleted'], style: {color: '#b05b5b'}},
  ],
};

/** @type {import('@docusaurus/types').Config} */
const config = {
  // The official slogan: it suffixes every page's <title> and stands alone as
  // the homepage title; the navbar keeps its own short 'TUI' brand.
  title: 'TUI - Terminal user interfaces for PHP',
  tagline: 'Terminal user interfaces for PHP',
  favicon: 'img/logo.svg',

  // Set the production url of your site here.
  url: 'https://phptui.dev',
  // Set the /<baseUrl>/ pathname under which your site is served.
  // For GitHub pages deployment, it is often '/<projectName>/'.
  baseUrl: '/',

  // GitHub pages deployment config.
  organizationName: 'drevops',
  projectName: 'tui',

  onBrokenLinks: 'throw',

  // Serve the generated widget SVGs (docs/assets) and the architecture diagrams
  // (docs/architecture) as static files alongside docs/static, so the pages can
  // reference them in place - without relocating the asset-generation pipeline
  // or the diagram sources maintained by the render-tui-diagrams skill.
  staticDirectories: ['static', 'assets', 'architecture'],

  // Even if you don't use internationalization, you can use this field to set
  // useful metadata like html lang. For example, if your site is Chinese, you
  // may want to replace "en" with "zh-Hans".
  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          routeBasePath: '/',
          sidebarPath: './sidebars.js',
          path: 'content',
          editUrl: 'https://github.com/drevops/tui/tree/main/docs/',
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
        gtag: {
          trackingID: 'G-9LM6X8F3XL',
          anonymizeIP: true,
        },
      }),
    ],
  ],

  markdown: {
    mermaid: true,
    hooks: {
      onBrokenMarkdownLinks: 'warn',
    },
  },

  plugins: [
    [
      '@docusaurus/plugin-client-redirects',
      {
        // Pages absorbed into larger ones keep their old URLs working.
        redirects: [
          {from: '/discovery', to: '/field-behaviour'},
          {from: '/self-describing-answers', to: '/headless-collection'},
        ],
      },
    ],
  ],

  themes: [
    [
      '@easyops-cn/docusaurus-search-local',
      /** @type {import("@easyops-cn/docusaurus-search-local").PluginOptions} */
      ({
        // @see https://github.com/easyops-cn/docusaurus-search-local#theme-options
        searchBarPosition: 'left',
        docsDir: 'content',
        docsRouteBasePath: '/',
        indexBlog: false,
        hashed: true,
        highlightSearchTermsOnTargetPage: true,
        explicitSearchResultPath: true,
      }),
    ],
    '@docusaurus/theme-mermaid',
  ],

  themeConfig:
  /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      // The social card lives in docs/assets (a static directory), generated
      // by docs/util/render-social-card.php as part of the asset pipeline.
      image: 'social-card.png',
      metadata: [
        {name: 'keywords', content: 'php, tui, terminal user interface, terminal forms, cli, console, prompts, interactive forms'},
      ],
      navbar: {
        title: 'TUI',
        logo: {
          alt: 'TUI Logo',
          src: 'img/logo.svg',
        },
        items: [
          {
            to: '/introduction',
            label: 'Docs',
            position: 'left',
          },
          {
            label: 'Download',
            href: 'https://github.com/drevops/tui/releases/latest',
            position: 'right',
            title: 'Download the latest version',
          },
          {
            href: 'https://github.com/drevops/tui',
            label: 'GitHub',
            position: 'right',
            title: 'View source on GitHub',
          },
          {
            type: 'search',
            position: 'right',
          },
        ],
      },
      footer: {
        style: 'dark',
        links: [
          {
            label: 'GitHub',
            href: 'https://github.com/drevops/tui',
          },
        ],
        copyright: `Version: ${process.env.RELEASE_VERSION || 'development'} <br/> Copyright ©${new Date().getFullYear()} DrevOps. Built with Docusaurus.`,
      },
      prism: {
        theme: tuiLight,
        darkTheme: tuiDark,
        additionalLanguages: ['php', 'bash'],
      },
      colorMode: {
        defaultMode: 'light',
        disableSwitch: false,
        respectPrefersColorScheme: true,
      },
    }),
};

export default config;
