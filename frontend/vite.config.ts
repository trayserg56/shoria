import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
// Vue DevTools: расширение браузера; не подключаем vite-plugin-vue-devtools —
// транзитивные пакеты давно не объявили peer для Vite 8 и npm может долго крутить ERESOLVE.
export default defineConfig({
  plugins: [
    tailwindcss(),
    vue(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
})
