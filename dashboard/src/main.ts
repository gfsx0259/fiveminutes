import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createVuetify } from 'vuetify'
import '@fontsource/inter/400.css'
import '@fontsource/inter/600.css'
import '@fontsource/inter/700.css'
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import '@/styles/tokens.css'
import '@/styles/shell.css'
import '@/styles/app.css'
import App from './App.vue'
import { router } from './router'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(createVuetify({
  theme: {
    defaultTheme: 'rider',
    themes: {
      rider: {
        dark: false,
        colors: {
          primary: '#5B45E8',
          secondary: '#464D78',
          background: '#F7F7FF',
          surface: '#FFFFFF',
        },
      },
    },
  },
}))
app.mount('#app')
