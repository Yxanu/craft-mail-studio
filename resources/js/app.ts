import { createApp } from 'vue'
import App from './App.vue'
import '../css/app.css'

const root = document.getElementById('mail-studio-app')

if (root) {
  createApp(App, {
    bootstrapUrl: root.dataset.bootstrapUrl,
    csrfToken: root.dataset.csrfToken,
    initialView: root.dataset.initialView,
  }).mount(root)
}
