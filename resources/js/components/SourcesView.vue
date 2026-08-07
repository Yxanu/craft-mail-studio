<script setup lang="ts">
import { computed, ref } from 'vue'
import { Cable, Search } from '@lucide/vue'
import { ApiClient } from '../api'
import type { EmailTemplate, SiteDefinition, SourceDefinition } from '../types'

const props = defineProps<{ sources: SourceDefinition[]; templates: EmailTemplate[]; sites: SiteDefinition[]; defaultLocale: string; isPro: boolean; canManage: boolean; api: ApiClient }>()
const emit = defineEmits<{ refresh: [] }>()
const query = ref('')
const busyKey = ref('')
const error = ref('')
const siteId = ref<number | null>(null)
const locale = ref<string | null>(null)

const filtered = computed(() => props.sources.filter(source => `${source.label} ${source.key} ${source.adapter}`.toLowerCase().includes(query.value.toLowerCase())))

function mappedTemplate(source: SourceDefinition): number | '' {
  return props.templates.find(template => template.mappings.some(mapping =>
    mapping.sourceKey === source.key && mapping.siteId === siteId.value && mapping.locale === locale.value,
  ))?.id ?? ''
}

function updateSite() {
  locale.value = siteId.value === null
    ? null
    : props.sites.find(site => site.id === Number(siteId.value))?.language ?? props.defaultLocale
}

async function map(source: SourceDefinition, event: Event) {
  busyKey.value = source.key
  error.value = ''
  try {
    const templateId = Number((event.target as HTMLSelectElement).value) || null
    await props.api.put('/mappings', { sourceKey: source.key, templateId, siteId: siteId.value, locale: locale.value })
    emit('refresh')
  } catch (exception) {
    error.value = (exception as Error).message
  } finally {
    busyKey.value = ''
  }
}
</script>

<template>
  <section class="ms-section-view">
    <header class="ms-section-header">
      <div><h2>Sources</h2><p>Explicitly connect transactional senders to a published template.</p></div>
      <div class="ms-source-tools">
        <template v-if="isPro">
          <select v-model="siteId" class="ms-header-select" aria-label="Mapping site" @change="updateSite"><option :value="null">Global mapping</option><option v-for="site in sites" :key="site.id" :value="site.id">{{ site.name }}</option></select>
          <input v-model="locale" class="ms-header-input" aria-label="Mapping locale" placeholder="Default locale">
        </template>
        <label class="ms-search"><Search :size="17" /><input v-model="query" placeholder="Search sources" aria-label="Search sources"></label>
      </div>
    </header>
    <div v-if="error" class="ms-alert ms-alert--error">{{ error }}</div>
    <div class="ms-table-wrap">
      <table class="ms-table">
        <thead><tr><th>Source</th><th>Adapter</th><th>Variables</th><th>Published template</th></tr></thead>
        <tbody>
          <tr v-for="source in filtered" :key="source.key">
            <td><div class="ms-source-name"><Cable :size="17" /><div><strong>{{ source.label }}</strong><code>{{ source.key }}</code></div></div></td>
            <td><span class="ms-adapter" :class="`ms-adapter--${source.adapter}`">{{ source.adapter }}</span></td>
            <td>{{ Object.keys(source.variables).length }}</td>
            <td>
              <select :value="mappedTemplate(source)" :disabled="!canManage || busyKey === source.key" @change="map(source, $event)">
                <option value="">Use original email</option>
                <option v-for="template in templates" :key="template.id" :value="template.id" :disabled="!template.variant.activeRevisionId">{{ template.name }}{{ template.variant.activeRevisionId ? '' : ' (draft)' }}</option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
