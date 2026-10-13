<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Check, Clock3, Globe2, LoaderCircle, Mail, Maximize2, Monitor, Plus, Save, Send, Smartphone, Trash2, X } from '@lucide/vue'
import { ApiClient } from '../api'
import { cloneDto } from '../clone'
import type { BrandProfile, EmailBlock, EmailTemplate, SiteDefinition, SourceDefinition, TemplateVariant } from '../types'
import BlockInspector from './BlockInspector.vue'
import BlockList from './BlockList.vue'

const props = defineProps<{
  template: EmailTemplate
  brands: BrandProfile[]
  sources: SourceDefinition[]
  permissions: { edit: boolean; publish: boolean; test: boolean; manage: boolean }
  isPro: boolean
  sites: SiteDefinition[]
  defaultLocale: string
  api: ApiClient
}>()
const emit = defineEmits<{ refresh: [selectId?: number]; deleted: []; 'dirty-change': [dirty: boolean] }>()

const draft = ref<EmailTemplate>(cloneDto(props.template))
const selectedBlockId = ref<string | null>(draft.value.variant.document.blocks[0]?.id ?? null)
const sourceKey = ref<string>(draft.value.mappings[0]?.sourceKey ?? props.sources[0]?.key ?? '')
const previewHtml = ref('')
const previewText = ref('')
const previewMode = ref<'desktop' | 'mobile'>('desktop')
const showText = ref(false)
const busy = ref(false)
const dirty = ref(false)
const previewing = ref(false)
const message = ref('')
const error = ref('')
const previewStage = ref<HTMLElement | null>(null)
const previewBounds = ref({ width: 0, height: 0 })
const testDialog = ref<HTMLDialogElement | null>(null)
const variantDialog = ref<HTMLDialogElement | null>(null)
const previewDialog = ref<HTMLDialogElement | null>(null)
const testEmail = ref('admin@example.test')
const activeVariantIdentity = ref(variantIdentity(draft.value.variant))
const newVariantSiteId = ref<number | null>(props.sites[0]?.id ?? null)
const newVariantLocale = ref(props.sites[0]?.language ?? props.defaultLocale)
let previewTimer: number | undefined
let previewRequest = 0
let previewObserver: ResizeObserver | null = null

watch(() => props.template, template => {
  const keepPreviewSource = template.id === draft.value.id && props.sources.some(item => item.key === sourceKey.value)
  const preferred = activeVariantIdentity.value
  const variant = template.variants.find(item => variantIdentity(item) === preferred) ?? template.variant
  draft.value = cloneDto(template)
  draft.value.variant = cloneDto(variant)
  activeVariantIdentity.value = variantIdentity(variant)
  selectedBlockId.value = draft.value.variant.document.blocks[0]?.id ?? null
  if (!keepPreviewSource) {
    sourceKey.value = draft.value.mappings[0]?.sourceKey ?? props.sources[0]?.key ?? ''
  }
  dirty.value = false
  nextTick(renderPreview)
})

const selectedBlock = computed(() => draft.value.variant.document.blocks.find(block => block.id === selectedBlockId.value) ?? null)
const source = computed(() => props.sources.find(item => item.key === sourceKey.value) ?? null)
const previewTargetWidth = computed(() => previewMode.value === 'mobile' ? 390 : 640)
const previewScale = computed(() => {
  if (!previewBounds.value.width) return 1
  return Math.min(1, previewBounds.value.width / previewTargetWidth.value)
})
const previewTargetHeight = computed(() => Math.max(620, Math.ceil((previewBounds.value.height || 620) / previewScale.value)))
const previewCanvasStyle = computed(() => ({
  width: `${Math.round(previewTargetWidth.value * previewScale.value)}px`,
  height: `${Math.round(previewTargetHeight.value * previewScale.value)}px`,
}))
const previewFrameStyle = computed(() => ({
  width: `${previewTargetWidth.value}px`,
  height: `${previewTargetHeight.value}px`,
  transform: `scale(${previewScale.value})`,
}))

watch(dirty, value => emit('dirty-change', value))

function variantIdentity(variant: Pick<TemplateVariant, 'siteId' | 'locale'>) {
  return `${variant.siteId ?? 'global'}:${variant.locale ?? 'default'}`
}

function variantLabel(variant: TemplateVariant) {
  const site = props.sites.find(item => item.id === variant.siteId)
  if (!site && !variant.locale) return 'Global default'
  if (!site) return `Global / ${variant.locale}`
  return `${site.name} / ${variant.locale || site.language}`
}

function selectVariant(identity: string) {
  if (identity === activeVariantIdentity.value) return
  if (dirty.value && !window.confirm('Discard unsaved changes and switch variant?')) return
  const variant = props.template.variants.find(item => variantIdentity(item) === identity)
  if (!variant) return
  draft.value = cloneDto(props.template)
  draft.value.variant = cloneDto(variant)
  activeVariantIdentity.value = identity
  selectedBlockId.value = variant.document.blocks[0]?.id ?? null
  dirty.value = false
  nextTick(renderPreview)
}

function openVariantDialog() {
  newVariantSiteId.value = props.sites[0]?.id ?? null
  newVariantLocale.value = props.sites.find(item => item.id === newVariantSiteId.value)?.language ?? props.defaultLocale
  variantDialog.value?.showModal()
}

async function createVariant() {
  const identity = `${newVariantSiteId.value ?? 'global'}:${newVariantLocale.value || 'default'}`
  const existing = props.template.variants.find(item => variantIdentity(item) === identity)
  if (existing) {
    variantDialog.value?.close()
    selectVariant(identity)
    return
  }

  const variant = cloneDto(draft.value.variant)
  variant.id = 0
  variant.siteId = newVariantSiteId.value
  variant.locale = newVariantLocale.value || null
  variant.activeRevisionId = null
  variant.revisions = []
  draft.value.variant = variant
  activeVariantIdentity.value = identity
  dirty.value = true
  variantDialog.value?.close()
  await save()
}

function payload() {
  return {
    name: draft.value.name,
    description: draft.value.description,
    brandProfileId: draft.value.brandProfileId,
    siteId: draft.value.variant.siteId,
    locale: draft.value.variant.locale,
    subject: draft.value.variant.subject,
    preheader: draft.value.variant.preheader,
    document: draft.value.variant.document,
  }
}

function changed() {
  dirty.value = true
  window.clearTimeout(previewTimer)
  previewTimer = window.setTimeout(renderPreview, 300)
}

function setBlocks(blocks: EmailBlock[]) {
  draft.value.variant.document.blocks = blocks
  if (selectedBlockId.value && !blocks.some(block => block.id === selectedBlockId.value)) {
    selectedBlockId.value = blocks[0]?.id ?? null
  }
  changed()
}

async function renderPreview() {
  const request = ++previewRequest
  error.value = ''
  previewing.value = true
  try {
    const result = await props.api.post<{ html: string; text: string }>(`/templates/${draft.value.id}/preview`, {
      ...payload(),
      variables: source.value?.sampleData ?? {},
    })
    if (request !== previewRequest) return
    previewHtml.value = result.html
    previewText.value = result.text
  } catch (exception) {
    if (request !== previewRequest) return
    error.value = (exception as Error).message
  } finally {
    if (request === previewRequest) previewing.value = false
  }
}

async function save() {
  busy.value = true
  error.value = ''
  message.value = ''
  try {
    await props.api.put(`/templates/${draft.value.id}`, payload())
    dirty.value = false
    message.value = 'Draft saved.'
    emit('refresh', draft.value.id)
  } catch (exception) {
    error.value = (exception as Error).message
  } finally {
    busy.value = false
  }
}

async function publish() {
  busy.value = true
  error.value = ''
  try {
    await props.api.put(`/templates/${draft.value.id}`, payload())
    await props.api.post(`/templates/${draft.value.id}/publish`, {
      siteId: draft.value.variant.siteId,
      locale: draft.value.variant.locale,
    })
    dirty.value = false
    message.value = 'Template published.'
    emit('refresh', draft.value.id)
  } catch (exception) {
    error.value = (exception as Error).message
  } finally {
    busy.value = false
  }
}

async function sendTest() {
  busy.value = true
  error.value = ''
  try {
    await props.api.post(`/templates/${draft.value.id}/test`, {
      ...payload(),
      email: testEmail.value,
      variables: source.value?.sampleData ?? {},
    })
    testDialog.value?.close()
    message.value = `Test email sent to ${testEmail.value}.`
  } catch (exception) {
    error.value = (exception as Error).message
  } finally {
    busy.value = false
  }
}

async function rollback(revisionId: number) {
  if (!window.confirm('Publish a new revision from this snapshot?')) return
  busy.value = true
  try {
    await props.api.post(`/templates/${draft.value.id}/revisions/${revisionId}/rollback`, {})
    emit('refresh', draft.value.id)
  } catch (exception) {
    error.value = (exception as Error).message
  } finally {
    busy.value = false
  }
}

async function destroyTemplate() {
  if (!window.confirm(`Delete “${draft.value.name}” and all its revisions?`)) return
  await props.api.delete(`/templates/${draft.value.id}`)
  emit('deleted')
}

onMounted(async () => {
  await nextTick()
  if (previewStage.value) {
    previewObserver = new ResizeObserver(([entry]) => {
      previewBounds.value = {
        width: entry.contentRect.width,
        height: entry.contentRect.height,
      }
    })
    previewObserver.observe(previewStage.value)
  }
  renderPreview()
})
onBeforeUnmount(() => {
  window.clearTimeout(previewTimer)
  previewObserver?.disconnect()
  emit('dirty-change', false)
})
</script>

<template>
  <div class="ms-workspace">
    <header class="ms-workspace__toolbar">
      <div class="ms-title-fields">
        <input v-model="draft.name" class="ms-title-input" aria-label="Template name" :disabled="!permissions.edit" @input="changed">
        <span v-if="draft.variant.activeRevisionId" class="ms-status ms-status--published"><Check :size="14" /> Published</span>
        <span v-else class="ms-status">Draft only</span>
      </div>
      <div class="ms-actions">
        <button v-if="permissions.edit" type="button" class="ms-btn ms-btn--secondary" :disabled="busy || !dirty" @click="save"><Save :size="16" /> Save draft</button>
        <button v-if="permissions.test" type="button" class="ms-icon-btn ms-icon-btn--bordered" title="Send test email" :disabled="busy" @click="testDialog?.showModal()"><Mail :size="17" /></button>
        <button v-if="permissions.publish" type="button" class="ms-btn ms-btn--primary" :disabled="busy" @click="publish"><Send :size="16" /> Publish</button>
        <button v-if="permissions.edit" type="button" class="ms-icon-btn ms-icon-btn--bordered ms-icon-btn--danger" title="Delete template" :disabled="busy" @click="destroyTemplate"><Trash2 :size="17" /></button>
      </div>
    </header>

    <div v-if="error" class="ms-alert ms-alert--error">{{ error }}</div>
    <div v-else-if="message" class="ms-alert ms-alert--success">{{ message }}</div>

    <div class="ms-meta-bar">
      <div v-if="isPro" class="ms-compact-field ms-variant-field"><span>Site / language</span><div class="ms-inline-control"><select :value="activeVariantIdentity" @change="selectVariant(($event.target as HTMLSelectElement).value)"><option v-for="variant in template.variants" :key="variantIdentity(variant)" :value="variantIdentity(variant)">{{ variantLabel(variant) }}</option></select><button type="button" class="ms-icon-btn ms-icon-btn--bordered" title="Add localized variant" @click="openVariantDialog"><Plus :size="16" /></button></div></div>
      <label class="ms-compact-field"><span>Subject</span><input v-model="draft.variant.subject" :disabled="!permissions.edit" @input="changed"></label>
      <label class="ms-compact-field"><span>Preheader</span><input v-model="draft.variant.preheader" :disabled="!permissions.edit" @input="changed"></label>
      <label class="ms-compact-field ms-compact-field--short"><span>Preview source</span><select v-model="sourceKey" @change="renderPreview"><option v-for="item in sources" :key="item.key" :value="item.key">{{ item.label }}</option></select></label>
      <label class="ms-compact-field ms-compact-field--short"><span>Brand</span><select v-model.number="draft.brandProfileId" :disabled="!permissions.edit" @change="changed"><option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option></select></label>
    </div>

    <div class="ms-editor-grid">
      <section class="ms-builder-panel">
        <div class="ms-panel-heading">Content blocks <span>{{ draft.variant.document.blocks.length }}</span></div>
        <BlockList
          :blocks="draft.variant.document.blocks"
          :selected-id="selectedBlockId"
          :disabled="!permissions.edit"
          @update:blocks="setBlocks"
          @select="selectedBlockId = $event"
        />
      </section>

      <BlockInspector :block="selectedBlock" :source="source" :disabled="!permissions.edit" @change="changed" />

      <section class="ms-preview-panel">
        <div class="ms-preview-toolbar">
          <div class="ms-segmented">
            <button type="button" :class="{ active: !showText }" @click="showText = false">HTML</button>
            <button type="button" :class="{ active: showText }" @click="showText = true">Plain text</button>
          </div>
          <div class="ms-preview-toolbar__end">
            <span v-if="previewing" class="ms-preview-status"><LoaderCircle class="ms-spin" :size="14" /><span>Updating</span></span>
            <div v-if="!showText" class="ms-segmented ms-segmented--icons">
              <button type="button" title="Desktop preview" :class="{ active: previewMode === 'desktop' }" @click="previewMode = 'desktop'"><Monitor :size="16" /></button>
              <button type="button" title="Mobile preview" :class="{ active: previewMode === 'mobile' }" @click="previewMode = 'mobile'"><Smartphone :size="16" /></button>
            </div>
            <button v-if="!showText" type="button" class="ms-icon-btn ms-icon-btn--bordered ms-preview-expand" title="Open large preview" @click="previewDialog?.showModal()"><Maximize2 :size="16" /></button>
          </div>
        </div>
        <div v-if="showText" class="ms-text-preview"><pre>{{ previewText }}</pre></div>
        <div v-show="!showText" ref="previewStage" class="ms-preview-stage">
          <div class="ms-preview-canvas" :style="previewCanvasStyle">
            <iframe title="Email preview" class="ms-preview-frame" :style="previewFrameStyle" :srcdoc="previewHtml" sandbox="allow-popups" />
          </div>
        </div>
      </section>
    </div>

    <section v-if="isPro && draft.variant.revisions.length" class="ms-revisions">
      <div class="ms-panel-heading"><Clock3 :size="16" /> Revision history</div>
      <div class="ms-revision-list">
        <div v-for="revision in draft.variant.revisions" :key="revision.id" class="ms-revision">
          <span>Revision {{ revision.number }}</span>
          <time>{{ new Date(revision.createdAt).toLocaleString() }}</time>
          <span v-if="revision.active" class="ms-status ms-status--published">Active</span>
          <button v-else type="button" class="ms-btn ms-btn--quiet" :disabled="busy" @click="rollback(revision.id)">Restore</button>
        </div>
      </div>
    </section>

    <dialog ref="previewDialog" class="ms-dialog ms-preview-dialog">
      <div class="ms-preview-dialog__content">
        <header><div><h2>Email preview</h2><p>{{ draft.variant.subject }}</p></div><button type="button" class="ms-icon-btn" title="Close" @click="previewDialog?.close()"><X :size="18" /></button></header>
        <iframe title="Large email preview" :srcdoc="previewHtml" sandbox="allow-popups" />
      </div>
    </dialog>

    <dialog ref="testDialog" class="ms-dialog">
      <form method="dialog" @submit.prevent="sendTest">
        <header><h2>Send test email</h2><button type="button" class="ms-icon-btn" title="Close" @click="testDialog?.close()"><X :size="18" /></button></header>
        <label class="ms-field"><span>Recipient</span><input v-model="testEmail" type="email" required autofocus></label>
        <footer><button type="button" class="ms-btn ms-btn--secondary" @click="testDialog?.close()">Cancel</button><button type="submit" class="ms-btn ms-btn--primary" :disabled="busy"><Send :size="16" /> Send</button></footer>
      </form>
    </dialog>

    <dialog ref="variantDialog" class="ms-dialog">
      <form @submit.prevent="createVariant">
        <header><h2>Add localized variant</h2><button type="button" class="ms-icon-btn" title="Close" @click="variantDialog?.close()"><X :size="18" /></button></header>
        <label class="ms-field"><span>Site</span><select v-model="newVariantSiteId" @change="newVariantLocale = sites.find(site => site.id === Number(newVariantSiteId))?.language ?? defaultLocale"><option :value="null">Global</option><option v-for="site in sites" :key="site.id" :value="site.id">{{ site.name }}</option></select></label>
        <label class="ms-field"><span>Locale</span><input v-model="newVariantLocale" required maxlength="32" placeholder="de-DE"></label>
        <p class="ms-dialog-description"><Globe2 :size="16" /> The current draft is copied as the starting point.</p>
        <footer><button type="button" class="ms-btn ms-btn--secondary" @click="variantDialog?.close()">Cancel</button><button type="submit" class="ms-btn ms-btn--primary" :disabled="busy || !newVariantLocale.trim()"><Plus :size="16" /> Add variant</button></footer>
      </form>
    </dialog>
  </div>
</template>
