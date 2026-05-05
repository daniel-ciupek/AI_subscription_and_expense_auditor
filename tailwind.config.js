import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            colors: {
                bg: {
                    base: '#0A0A0F',
                    surface: '#13131A',
                    elevated: '#1C1C26',
                },
                accent: {
                    primary: '#7C3AED',
                    neon: '#22D3EE',
                },
                state: {
                    success: '#10B981',
                    warning: '#F59E0B',
                    danger: '#EF4444',
                },
                text: {
                    primary: '#FAFAFA',
                    secondary: '#A1A1AA',
                },
            },
            fontFamily: {
                sans: ['"Inter Variable"', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono Variable"', ...defaultTheme.fontFamily.mono],
            },
            borderRadius: {
                '2xl': '1rem',
                '3xl': '1.5rem',
            },
            boxShadow: {
                glow: '0 0 40px rgba(124, 58, 237, 0.3)',
                'glow-neon': '0 0 40px rgba(34, 211, 238, 0.25)',
                'glow-danger': '0 0 30px rgba(239, 68, 68, 0.3)',
            },
            backgroundImage: {
                mesh: 'radial-gradient(at 20% 20%, rgba(124, 58, 237, 0.25) 0px, transparent 50%), radial-gradient(at 80% 0%, rgba(34, 211, 238, 0.18) 0px, transparent 50%), radial-gradient(at 100% 100%, rgba(124, 58, 237, 0.18) 0px, transparent 50%), radial-gradient(at 0% 100%, rgba(34, 211, 238, 0.12) 0px, transparent 50%)',
            },
            keyframes: {
                shimmer: {
                    '0%': { backgroundPosition: '-200% 0' },
                    '100%': { backgroundPosition: '200% 0' },
                },
                'mesh-shift': {
                    '0%, 100%': { transform: 'translate(0, 0) scale(1)' },
                    '50%': { transform: 'translate(2%, -1%) scale(1.05)' },
                },
                'gradient-shift': {
                    '0%, 100%': { backgroundPosition: '0% 50%' },
                    '50%': { backgroundPosition: '100% 50%' },
                },
                'sparkle-float': {
                    '0%, 100%': {
                        transform: 'translateY(0) rotate(0deg)',
                        opacity: '0.5',
                    },
                    '50%': {
                        transform: 'translateY(-4px) rotate(180deg)',
                        opacity: '1',
                    },
                },
            },
            animation: {
                shimmer: 'shimmer 2s linear infinite',
                'mesh-shift': 'mesh-shift 18s ease-in-out infinite',
                'gradient-shift': 'gradient-shift 6s ease infinite',
                'sparkle-float': 'sparkle-float 3s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
