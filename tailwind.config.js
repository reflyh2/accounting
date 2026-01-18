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
            pattern: /(.*)-indigo-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /(.*)-purple-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /(.*)-slate-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /(.*)-yellow-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /(.*)-orange-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /(.*)-amber-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /(.*)-sky-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /(.*)-emerald-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /(.*)-gray-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /(.*)-zinc-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /(.*)-pink-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /(.*)-rose-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /(.*)-violet-(.*)/,
            variants: ['hover', 'focus'],
        },
        {
            pattern: /(.*)-fuchsia-(.*)/,
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
