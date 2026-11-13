import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createVuetify } from 'vuetify'
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import App from './App.vue'

const app = createApp(App)
app.use(createPinia())
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
