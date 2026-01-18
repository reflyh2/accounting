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
        // Gradient colors for help menu icons
        'from-blue-500',
        'to-indigo-600',
        'from-blue-600',
        'to-indigo-700',
        'from-green-500',
        'to-emerald-600',
        'from-orange-500',
        'to-amber-600',
        'from-purple-500',
        'to-violet-600',
        'from-indigo-500',
        'to-blue-600',
        'from-teal-500',
        'to-cyan-600',
        'from-pink-500',
        'to-rose-600',
        'from-yellow-500',
        'to-orange-500',
        'from-slate-500',
        'to-gray-600',
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
