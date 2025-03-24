const defaultTheme = require('tailwindcss/defaultTheme');

/** @type {import('tailwindcss').Config} */
export default {
    prefix: 'tw-',
    content: ['./src/**/*.{js,ts,jsx,tsx}'],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter Var', 'Inter', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                b: '0 1px 0 0 rgb(0 0 0 / 0.05);',
            },
            aspectRatio: {
                '4/3': '4 / 3',
            },
        },
    },
    plugins: [],
    plugins: [require('tailwind-scrollbar')({ nocompatible: true })],
    safelist: [
        'md:tw-w-full',
        'md:tw-w-1/2',
        'md:tw-w-1/3',
        'md:tw-w-20',
        'tw-w-full',
        'tw-w-1/2',
        'tw-w-1/3',
        'tw-w-20',
        'tw-aspect-auto',
        'tw-aspect-video',
        'tw-aspect-square',
        'tw-p-0',
        'tw-m-0',
        {
            pattern: /^tw-grid-cols-([1-9]|1[0-2])$/,
            variants: ['sm', 'md', 'lg', 'xl', '2xl'],
        },
        {
            pattern: /^tw-gap-([0-9]+)$/,
            variants: ['sm', 'md', 'lg', 'xl', '2xl'],
        },
    ],
};
