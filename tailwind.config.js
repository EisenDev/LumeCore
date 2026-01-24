import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

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
            // THE LUME DESIGN TOKENS
            borderRadius: {
                'lume': '1rem', // Master rounding for the platform
            },
            colors: {
                brand: {
                    primary: '#4f46e5',   // Indigo (Logic/Fintech)
                    secondary: '#10b981', // Emerald (Success/Assets)
                    dark: '#020617',      // Slate 950 (Background)
                }
            },
            keyframes: {
                scan: {
                    '0%, 100%': { transform: 'translateY(1200%)' },
                    '50%': { transform: 'translateY(-200%)' },
                }
            },
            animation: {
                scan: 'scan 2s ease-in-out infinite',
            },
        },
    },

    plugins: [
        forms,
        require('@tailwindcss/typography'), // Add this line
    ],
};