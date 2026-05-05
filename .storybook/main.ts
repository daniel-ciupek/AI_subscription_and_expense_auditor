import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import type { StorybookConfig } from '@storybook/react-vite';

const here = dirname(fileURLToPath(import.meta.url));

const config: StorybookConfig = {
    stories: ['../resources/js/Components/**/*.stories.@(ts|tsx)'],
    addons: ['@storybook/addon-a11y'],
    framework: {
        name: '@storybook/react-vite',
        options: {},
    },
    typescript: {
        check: false,
        reactDocgen: 'react-docgen-typescript',
    },
    viteFinal: async (config) => {
        // Reuse the project's path alias so stories can import @/Components/...
        // exactly like the rest of the codebase. Avoid pulling in laravel-vite-plugin
        // — Storybook's own dev server replaces it.
        config.resolve = config.resolve ?? {};
        config.resolve.alias = {
            ...(config.resolve.alias ?? {}),
            '@': resolve(here, '../resources/js'),
        };

        return config;
    },
};

export default config;
