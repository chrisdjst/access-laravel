import type { StorybookConfig } from '@storybook/react-vite';

const config: StorybookConfig = {
  stories: ['../src/**/*.stories.@(ts|tsx|mdx)'],
  addons: ['@storybook/addon-essentials'],
  framework: {
    name: '@storybook/react-vite',
    options: {},
  },
  // public/ holds the msw service worker that msw-storybook-addon
  // registers in the browser. `npx msw init public/` to (re)generate
  // it; the file is committed at public/mockServiceWorker.js.
  staticDirs: ['../public'],
  typescript: {
    // The package is strict-TS already; let Storybook re-use the same
    // compiler options instead of forcing react-docgen extraction.
    reactDocgen: false,
  },
};

export default config;
