<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { BookOpen, Cable, Download, LoaderCircle, Palette, Plus, Search, Upload, X } from '@lucide/vue'
import { ApiClient } from './api'
import type { BootstrapData } from './types'
import BrandView from './components/BrandView.vue'
import SourcesView from './components/SourcesView.vue'
import TemplateWorkspace from './components/TemplateWorkspace.vue'

const props = withDefaults(defineProps<{ bootstrapUrl?: string; csrfToken?: string; initialView?: string }>(), {
  bootstrapUrl: '', csrfToken: '', initialView: 'templates',
})

const apiBase = props.bootstrapUrl.replace(/\/bootstrap$/, '')
const api = new ApiClient(apiBase, props.csrfToken)
const data = ref<BootstrapData | null>(null)
const loading = ref(true)
const error = ref('')
const view = ref<'templates' | 'sources' | 'brand'>(['sources', 'brand'].includes(props.initialView) ? props.initialView as any : 'templates')
const selectedTemplateId = ref<number | null>(null)
const templateQuery = ref('')
const createDialog = ref<HTMLDialogElement | null>(null)
const createName = ref('')
const createPreset = ref('simple-message')
const creating = ref(false)
const importInput = ref<HTMLInputElement | null>(null)
const notice = ref('')

const selectedTemplate = computed(() => data.value?.templates.find(template => template.id === selectedTemplateId.value) ?? null)
const filteredTemplates = computed(() => data.value?.templates.filter(template => `${template.name} ${template.handle}`.toLowerCase().includes(templateQuery.value.toLowerCase())) ?? [])

async function load(selectId?: number) {
  error.value = ''
  try {
    data.value = await api.get<BootstrapData>('/bootstrap')
    const requested = selectId ?? selectedTemplateId.value
    selectedTemplateId.value = data.value.templates.some(template => template.id === requested)
      ? requested
      : data.value.templates[0]?.id ?? null
  } catch (exception) {
    error.value = (exception as Error).message
  } finally {
    loading.value = false
  }
}

function changeView(next: 'templates' | 'sources' | 'brand') {
  view.value = next
  const url = new URL(window.location.href)
  if (next === 'templates') url.searchParams.delete('view')
  else url.searchParams.set('view', next)
  window.history.replaceState({}, '', url)
}

function openCreate() {
  createName.value = ''
  createPreset.value = data.value?.presets[0]?.key ?? 'simple-message'
  createDialog.value?.showModal()
}

async function createTemplate() {
  creating.value = true
  error.value = ''
  try {
    const response = await api.post<{ template: { id: number } }>('/templates', { name: createName.value, presetKey: createPreset.value })
    createDialog.value?.close()
    await load(response.template.id)
  } catch (exception) {
    error.value = (exception as Error).message
  } finally {
    creating.value = false
  }
}

function exportData() {
  window.location.assign(`${apiBase}/export`)
}

async function importData(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  error.value = ''
  notice.value = ''
  try {
    const payload = JSON.parse(await file.text())
    const result = await api.post<{ imported: { brands: number; templates: number; revisions: number } }>('/import', { payload })
    notice.value = `Imported ${result.imported.templates} templates and ${result.imported.revisions} revisions.`
    await load()
  } catch (exception) {
    error.value = (exception as Error).message
  } finally {
    ;(event.target as HTMLInputElement).value = ''
  }
}

onMounted(load)
</script>

<template>
  <div class="mail-studio">
    <div v-if="loading" class="ms-loading"><LoaderCircle class="ms-spin" :size="24" /> Loading Mail Studio</div>
    <div v-else-if="!data" class="ms-alert ms-alert--error">{{ error || 'Mail Studio could not be loaded.' }}</div>
    <template v-else>
      <nav class="ms-view-tabs" aria-label="Mail Studio views">
        <button type="button" :class="{ active: view === 'templates' }" @click="changeView('templates')"><BookOpen :size="17" /> Templates</button>
        <button type="button" :class="{ active: view === 'sources' }" @click="changeView('sources')"><Cable :size="17" /> Sources</button>
        <button type="button" :class="{ active: view === 'brand' }" @click="changeView('brand')"><Palette :size="17" /> Brand</button>
        <span v-if="data.isPro && data.permissions.manage" class="ms-tab-actions">
          <button type="button" class="ms-icon-btn" title="Import Mail Studio JSON" @click="importInput?.click()"><Upload :size="17" /></button>
          <button type="button" class="ms-icon-btn" title="Export Mail Studio JSON" @click="exportData"><Download :size="17" /></button>
          <input ref="importInput" class="ms-visually-hidden" type="file" accept="application/json,.json" @change="importData">
        </span>
        <span class="ms-edition">{{ data.edition }} edition</span>
      </nav>

      <div v-if="error" class="ms-alert ms-alert--error ms-global-alert">{{ error }}</div>
      <div v-else-if="notice" class="ms-alert ms-alert--success ms-global-alert">{{ notice }}</div>

      <div v-if="view === 'templates'" class="ms-template-layout">
        <aside class="ms-template-rail">
          <div class="ms-template-rail__heading">
            <strong>Templates</strong>
            <button v-if="data.permissions.edit" type="button" class="ms-icon-btn ms-icon-btn--bordered" title="Create template" @click="openCreate"><Plus :size="17" /></button>
          </div>
          <label class="ms-search ms-search--rail"><Search :size="16" /><input v-model="templateQuery" placeholder="Filter templates" aria-label="Filter templates"></label>
          <div class="ms-template-list">
            <button
              v-for="template in filteredTemplates"
              :key="template.id"
              type="button"
              :class="['ms-template-item', { active: template.id === selectedTemplateId }]"
              @click="selectedTemplateId = template.id"
            >
              <span>{{ template.name }}</span>
              <small>{{ template.handle }}</small>
              <i :class="template.variant.activeRevisionId ? 'published' : ''" :title="template.variant.activeRevisionId ? 'Published' : 'Draft only'" />
            </button>
          </div>
        </aside>

        <TemplateWorkspace
          v-if="selectedTemplate"
          :key="selectedTemplate.id"
          :template="selectedTemplate"
          :brands="data.brands"
          :sources="data.sources"
          :permissions="data.permissions"
          :is-pro="data.isPro"
          :sites="data.sites"
          :default-locale="data.defaultLocale"
          :api="api"
          @refresh="load"
          @deleted="load()"
        />
        <div v-else class="ms-empty-state">
          <BookOpen :size="28" />
          <h2>No templates yet</h2>
          <p>Create a template from the starter library.</p>
          <button v-if="data.permissions.edit" type="button" class="ms-btn ms-btn--primary" @click="openCreate"><Plus :size="16" /> Create template</button>
        </div>
      </div>

      <SourcesView v-else-if="view === 'sources'" :sources="data.sources" :templates="data.templates" :sites="data.sites" :default-locale="data.defaultLocale" :is-pro="data.isPro" :can-manage="data.permissions.manage" :api="api" @refresh="load()" />
      <BrandView v-else :brands="data.brands" :can-manage="data.permissions.manage" :is-pro="data.isPro" :api="api" @refresh="load()" />

      <dialog ref="createDialog" class="ms-dialog">
        <form @submit.prevent="createTemplate">
          <header><h2>Create template</h2><button type="button" class="ms-icon-btn" title="Close" @click="createDialog?.close()"><X :size="18" /></button></header>
          <label class="ms-field"><span>Name</span><input v-model="createName" required maxlength="120" autofocus placeholder="Customer confirmation"></label>
          <label class="ms-field"><span>Starter preset</span><select v-model="createPreset"><option v-for="preset in data.presets" :key="preset.key" :value="preset.key">{{ preset.name }}</option></select></label>
          <p class="ms-dialog-description">{{ data.presets.find(preset => preset.key === createPreset)?.description }}</p>
          <footer><button type="button" class="ms-btn ms-btn--secondary" @click="createDialog?.close()">Cancel</button><button type="submit" class="ms-btn ms-btn--primary" :disabled="creating || !createName.trim()"><Plus :size="16" /> Create</button></footer>
        </form>
      </dialog>
    </template>
  </div>
</template>
