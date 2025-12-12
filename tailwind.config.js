import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

const colors = require('tailwindcss/colors');

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                main: colors.blue,
            },
        },
    },

    plugins: [forms],

    safelist: [
        {
            pattern: /bg-purple-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /bg-slate-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /text-purple-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /bg-amber-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /bg-sky-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /ml-(.*)/,
            variants: ['xs', 'sm', 'md', 'lg', 'xl', '2xl'],
        },
        {
            pattern: /pl-(.*)/,
            variants: ['xs', 'sm', 'md', 'lg', 'xl', '2xl'],
        },
    ],
};
