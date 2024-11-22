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

    safelist: ['ml-1', 'ml-2', 'ml-3', 'ml-4', 'ml-5', 'ml-6', 'ml-7', 'ml-8', 'ml-9', 'ml-10', 'ml-11', 'ml-12', 'ml-14', 'ml-16', 'ml-20', 'ml-24', 'ml-28', 'ml-32', 'ml-36', 'ml-40', 'ml-44', 'ml-48', 'ml-52', 'ml-56', 'ml-60', 'ml-64', 'ml-72', 'ml-80', 'ml-96', 'pl-1', 'pl-2', 'pl-3', 'pl-4', 'pl-5', 'pl-6', 'pl-7', 'pl-8', 'pl-9', 'pl-10', 'pl-11', 'pl-12', 'pl-14', 'pl-16', 'pl-20', 'pl-24', 'pl-28', 'pl-32', 'pl-36', 'pl-40', 'pl-44', 'pl-48', 'pl-52', 'pl-56', 'pl-60', 'pl-64', 'pl-72', 'pl-80', 'pl-96',],
};
