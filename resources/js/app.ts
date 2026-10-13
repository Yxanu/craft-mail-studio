import { createApp } from 'vue'
import App from './App.vue'
import '../css/app.css'

// Craft's Inertia screen inserts HTML after asset loading and replaces it on navigation.
class MailStudioEditor extends HTMLElement {
  private app: ReturnType<typeof createApp> | null = null

  connectedCallback() {
    if (this.app) return
    this.app = createApp(App, {
      bootstrapUrl: this.dataset.bootstrapUrl,
      csrfToken: this.dataset.csrfToken,
      initialView: this.dataset.initialView,
    })
    this.app.mount(this)
  }

  disconnectedCallback() {
    queueMicrotask(() => {
      if (this.isConnected) return
      this.app?.unmount()
      this.app = null
    })
  }
}

if (!customElements.get('mail-studio-editor')) {
  customElements.define('mail-studio-editor', MailStudioEditor)
}
