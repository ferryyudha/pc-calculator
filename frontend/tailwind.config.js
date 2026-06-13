/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        // Primary brand palette
        primary:   { DEFAULT: '#00D4FF', dark: '#00A8CC', light: '#66E4FF' },
        accent:    { DEFAULT: '#7C3AED', dark: '#5B21B6', light: '#A78BFA' },
        // Dark mode surfaces
        surface: {
          900: '#060B14',
          800: '#0D1525',
          700: '#131E33',
          600: '#1A2845',
          500: '#243356',
        },
        // Status colors
        success: '#10B981',
        warning: '#F59E0B',
        danger:  '#EF4444',
      },
      fontFamily: {
        sans:  ['Inter', 'system-ui', 'sans-serif'],
        mono:  ['JetBrains Mono', 'monospace'],
      },
      backgroundImage: {
        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
        'hero-glow': 'radial-gradient(ellipse 80% 60% at 50% -20%, rgba(0,212,255,0.15), transparent)',
      },
      boxShadow: {
        'glow-primary': '0 0 24px rgba(0,212,255,0.25)',
        'glow-accent':  '0 0 24px rgba(124,58,237,0.25)',
        'glass':        '0 8px 32px rgba(0,0,0,0.4)',
      },
      backdropBlur: {
        xs: '2px',
      },
      animation: {
        'fade-in':    'fadeIn 0.4s ease-out',
        'slide-up':   'slideUp 0.4s ease-out',
        'pulse-slow': 'pulse 3s ease-in-out infinite',
        'spin-slow':  'spin 4s linear infinite',
      },
      keyframes: {
        fadeIn:  { from: { opacity: '0' },              to: { opacity: '1' } },
        slideUp: { from: { opacity: '0', transform: 'translateY(20px)' }, to: { opacity: '1', transform: 'translateY(0)' } },
      },
    },
  },
  plugins: [],
}
