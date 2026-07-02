import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  publicDir: false,
  plugins: [react()],
  build: {
    outDir: 'public/assets',
    emptyOutDir: false,
    sourcemap: false,
    rollupOptions: {
      input: 'resources/js/main.jsx',
      output: {
        entryFileNames: 'app.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'app.css';
          }

          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
});
