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
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Plus Jakarta Sans"', 'Inter', ...defaultTheme.fontFamily.sans],
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
                    primary: '#CBB48A',   // Muted Gold
                    secondary: '#DCC8A5', // Warm Sand
                    glow: 'rgba(203, 180, 138, 0.5)',
                },
                sovereign: {
                    primary: '#F3E7C9',   // Soft Ivory
                    secondary: '#CBB48A', // Muted Gold
                    glow: 'rgba(243, 231, 201, 0.5)',
                },
                theme: {
                    bg: '#121315',        // Flat dark charcoal background
                    'bg-alt': '#15171A',   // Secondary dark charcoal background
                    card: '#1B1D21',      // Warm slate-black card background
                    primary: '#F3E7C9',   // Soft Ivory
                    secondary: '#DCC8A5', // Warm Sand
                    accent: '#CBB48A',    // Muted Gold
                    neutral: '#9CA3AF',   // Muted Gray
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