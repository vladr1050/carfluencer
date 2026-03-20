import { defineConfig, loadEnv } from 'vite'
import path from 'path'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  /** Where Laravel runs; browser talks to Vite, Vite forwards /api and /storage here */
  // Not VITE_* — only read here, not embedded in the browser bundle
  const apiTarget = env.LARAVEL_DEV_URL || 'http://127.0.0.1:8000'

  return {
  server: {
    // Avoid clashing with the real SPA in /frontend (also Vite default 5173)
    port: 5174,
    strictPort: true,
    proxy: {
      '/api': { target: apiTarget, changeOrigin: true },
      '/storage': { target: apiTarget, changeOrigin: true },
    },
  },
  plugins: [
    // The React and Tailwind plugins are both required for Make, even if
    // Tailwind is not being actively used – do not remove them
    react(),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      // Alias @ to the src directory
      '@': path.resolve(__dirname, './src'),
    },
  },

  // File types to support raw imports. Never add .css, .tsx, or .ts files to this.
  assetsInclude: ['**/*.svg', '**/*.csv'],
  }
})
