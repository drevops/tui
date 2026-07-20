// @ts-check

/**
 * The top-level pages are grouped into categories for a clearer information
 * architecture. No files move, so every page keeps its URL; the widget order
 * follows this list rather than each page's frontmatter position.
 *
 * @type {import('@docusaurus/plugin-content-docs').SidebarsConfig}
 */
const sidebars = {
  tutorialSidebar: [
    'index',
    {
      type: 'category',
      label: 'Getting started',
      collapsed: false,
      items: ['installation', 'panels', 'configuration', 'headless-collection'],
    },
    {
      type: 'category',
      label: 'Guides',
      items: ['field-behaviour', 'discovery', 'self-describing-answers', 'ai-agents', 'testing'],
    },
    {
      type: 'category',
      label: 'Widgets',
      link: {type: 'doc', id: 'widgets/index'},
      items: [
        'widgets/calendar',
        'widgets/confirm',
        'widgets/filepicker',
        'widgets/number',
        'widgets/option-groups',
        'widgets/password',
        'widgets/pause',
        'widgets/reorder',
        'widgets/search',
        'widgets/select',
        'widgets/suggest',
        'widgets/text',
        'widgets/textarea',
        'widgets/toggle',
      ],
    },
    {
      type: 'category',
      label: 'Appearance & input',
      items: ['themes', 'display-modes', 'key-bindings', 'translations'],
    },
    {
      type: 'category',
      label: 'About',
      items: ['architecture', 'playground', 'contributing'],
    },
  ],
};

export default sidebars;
