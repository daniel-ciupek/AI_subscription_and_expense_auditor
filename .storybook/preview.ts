import type { Preview } from '@storybook/react-vite';
import '../resources/css/app.css';

const preview: Preview = {
    parameters: {
        backgrounds: {
            default: 'app',
            values: [
                // Match the running app — components are designed to sit on
                // the dark mesh background, not a plain white canvas.
                { name: 'app', value: '#0A0A0F' },
                { name: 'light', value: '#ffffff' },
            ],
        },
        layout: 'centered',
        controls: {
            expanded: true,
            matchers: { color: /(background|color)$/i, date: /Date$/i },
        },
        a11y: {
            test: 'todo',
        },
    },
    decorators: [
        (Story) => {
            // Storybook's body has its own background. Force the app's
            // `dark` color-scheme + Inter font so glass/blur effects render
            // the same as in production.
            document.documentElement.style.colorScheme = 'dark';

            return Story();
        },
    ],
};

export default preview;
