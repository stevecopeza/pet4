import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'dist',
    manifest: true,
    rollupOptions: {
      input: {
        admin:  path.resolve(__dirname, 'src/UI/Admin/main.tsx'),
        portal: path.resolve(__dirname, 'src/UI/Portal/main.tsx'),
        staff:  path.resolve(__dirname, 'src/UI/Staff/main.tsx'),
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
});
