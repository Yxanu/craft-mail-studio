import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  publicDir: false,
  build: {
    manifest: 'manifest.json',
    outDir: 'public/build',
    emptyOutDir: true,
    rollupOptions: {
      input: 'resources/js/app.ts',
      output: {
        manualChunks: {
          vue: ['vue'],
          editor: ['@tiptap/vue-3', '@tiptap/starter-kit', '@tiptap/extension-link'],
          utilities: ['@lucide/vue', 'sortablejs'],
        },
      },
    },
  },
})
