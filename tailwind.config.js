import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';
import forms from '@tailwindcss/forms';
import flowbite from 'flowbite/plugin';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './node_modules/flowbite/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Roboto', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50: '#eef6f1',
                    100: '#d9ebe1',
                    200: '#b5d6c5',
                    300: '#86bda3',
                    400: '#4f9c7c',
                    500: '#2a7f62',
                    600: '#146a50',
                    700: '#0f5642',
                    800: '#0d4536',
                    900: '#0b3529',
                    950: '#06201a',
                },
            },
        },
    },

    plugins: [forms, flowbite],
};
