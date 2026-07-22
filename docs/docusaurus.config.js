// @ts-check
// `@type` JSDoc annotations allow editor autocompletion and type checking
// (when paired with `@ts-check`).
// There are various equivalent ways to declare your Docusaurus config.
// @see https://docusaurus.io/docs/api/docusaurus-config

import {themes as prismThemes} from 'prism-react-renderer';

/** @type {import('@docusaurus/types').Config} */
const config = {
  title: 'TUI',
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
      image: 'img/logo.png',
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
        theme: prismThemes.github,
        darkTheme: prismThemes.dracula,
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
