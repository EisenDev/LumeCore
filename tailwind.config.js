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
                    primary: '#4f46e5',   // Indigo (Legacy Logic)
                    secondary: '#10b981', // Emerald (Legacy Success)
                    dark: '#030712',      // Deep Space Dark
                },
                lume: {
                    primary: '#34d399',   // Emerald 400
                    secondary: '#10b981', // Emerald 500
                    glow: 'rgba(52, 211, 153, 0.5)',
                },
                sovereign: {
                    primary: '#22d3ee',   // Cyan 400
                    secondary: '#06b6d4', // Cyan 500
                    glow: 'rgba(34, 211, 238, 0.5)',
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