<script setup lang="ts">
import { ref, watch } from 'vue'
import { Plus, Save, Trash2, X } from '@lucide/vue'
import { ApiClient } from '../api'
import { cloneDto } from '../clone'
import type { BrandProfile } from '../types'

const props = defineProps<{ brands: BrandProfile[]; canManage: boolean; isPro: boolean; api: ApiClient }>()
const emit = defineEmits<{ refresh: [] }>()
const selectedId = ref(props.brands[0]?.id ?? 0)
const draft = ref<BrandProfile | null>(props.brands[0] ? cloneDto(props.brands[0]) : null)
const busy = ref(false)
const message = ref('')
const error = ref('')
const createDialog = ref<HTMLDialogElement | null>(null)
const createName = ref('')

watch(selectedId, id => {
  const brand = props.brands.find(item => item.id === id)
  draft.value = brand ? cloneDto(brand) : null
})
watch(() => props.brands, brands => {
  const brand = brands.find(item => item.id === selectedId.value) ?? brands[0]
  if (brand) draft.value = cloneDto(brand)
})

async function save() {
  if (!draft.value) return
  busy.value = true
  message.value = ''
  error.value = ''
  try {
    await props.api.put(`/brands/${draft.value.id}`, { name: draft.value.name, config: draft.value.config })
    message.value = 'Brand profile saved.'
    emit('refresh')
  } catch (exception) {
    error.value = (exception as Error).message
  } finally {
    busy.value = false
  }
}

async function create() {
  busy.value = true
  error.value = ''
  try {
    const response = await props.api.post<{ brand: BrandProfile }>('/brands', { name: createName.value })
    selectedId.value = response.brand.id
    draft.value = cloneDto(response.brand)
    createDialog.value?.close()
    emit('refresh')
  } catch (exception) {
    error.value = (exception as Error).message
  } finally {
    busy.value = false
  }
}

async function remove() {
  if (!draft.value || draft.value.isDefault || !window.confirm(`Delete brand profile “${draft.value.name}”?`)) return
  busy.value = true
  try {
    await props.api.delete(`/brands/${draft.value.id}`)
    selectedId.value = props.brands.find(item => item.isDefault)?.id ?? props.brands[0]?.id ?? 0
    emit('refresh')
  } catch (exception) {
    error.value = (exception as Error).message
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <section class="ms-section-view">
    <header class="ms-section-header">
      <div><h2>Brand profiles</h2><p>Shared visual shells for transactional email templates.</p></div>
      <div class="ms-actions">
        <select v-if="brands.length > 1" v-model="selectedId" class="ms-header-select" aria-label="Brand profile"><option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option></select>
        <button v-if="isPro" type="button" class="ms-icon-btn ms-icon-btn--bordered" title="Create brand profile" :disabled="!canManage || busy" @click="createName = ''; createDialog?.showModal()"><Plus :size="17" /></button>
        <button v-if="isPro && draft && !draft.isDefault" type="button" class="ms-icon-btn ms-icon-btn--bordered ms-icon-btn--danger" title="Delete brand profile" :disabled="!canManage || busy" @click="remove"><Trash2 :size="17" /></button>
        <button type="button" class="ms-btn ms-btn--primary" :disabled="!canManage || busy || !draft" @click="save"><Save :size="16" /> Save</button>
      </div>
    </header>
    <div v-if="error" class="ms-alert ms-alert--error">{{ error }}</div>
    <div v-else-if="message" class="ms-alert ms-alert--success">{{ message }}</div>
    <div v-if="draft" class="ms-brand-layout">
      <div class="ms-form-section">
        <h3>Identity</h3>
        <label class="ms-field"><span>Profile name</span><input v-model="draft.name" :disabled="!canManage"></label>
        <label class="ms-field"><span>Sender name</span><input v-model="draft.config.senderName" :disabled="!canManage"></label>
        <label class="ms-field"><span>Logo URL</span><input v-model="draft.config.logoUrl" type="url" :disabled="!canManage"></label>
        <label class="ms-field"><span>Footer text</span><textarea v-model="draft.config.footerText" rows="3" :disabled="!canManage" /></label>
        <div class="ms-field-row"><label class="ms-field"><span>Support email</span><input v-model="draft.config.supportEmail" type="email" :disabled="!canManage"></label><label class="ms-field"><span>Support phone</span><input v-model="draft.config.supportPhone" :disabled="!canManage"></label></div>
      </div>
      <div class="ms-form-section">
        <h3>Colors and width</h3>
        <div class="ms-color-grid">
          <label v-for="field in ['backgroundColor','surfaceColor','textColor','mutedColor','primaryColor','borderColor']" :key="field" class="ms-color-field"><span>{{ field.replace('Color','').replace(/([A-Z])/g, ' $1') }}</span><input v-model="draft.config[field]" type="color" :disabled="!canManage"><code>{{ draft.config[field] }}</code></label>
        </div>
        <label class="ms-field"><span>Container width: {{ draft.config.containerWidth }} px</span><input v-model.number="draft.config.containerWidth" type="range" min="480" max="720" step="20" :disabled="!canManage"></label>
      </div>
      <div class="ms-brand-sample" :style="{ background: draft.config.backgroundColor }">
        <div :style="{ background: draft.config.surfaceColor, color: draft.config.textColor, borderColor: draft.config.borderColor }">
          <strong>{{ draft.config.senderName || 'Sender' }}</strong>
          <h3>Transactional email</h3>
          <p :style="{ color: draft.config.mutedColor }">This preview reflects the shared visual tokens.</p>
          <button type="button" :style="{ background: draft.config.primaryColor }">Primary action</button>
        </div>
      </div>
    </div>

    <dialog ref="createDialog" class="ms-dialog">
      <form @submit.prevent="create">
        <header><h2>Create brand profile</h2><button type="button" class="ms-icon-btn" title="Close" @click="createDialog?.close()"><X :size="18" /></button></header>
        <label class="ms-field"><span>Name</span><input v-model="createName" required maxlength="120" autofocus placeholder="Product brand"></label>
        <footer><button type="button" class="ms-btn ms-btn--secondary" @click="createDialog?.close()">Cancel</button><button type="submit" class="ms-btn ms-btn--primary" :disabled="busy || !createName.trim()"><Plus :size="16" /> Create</button></footer>
      </form>
    </dialog>
  </section>
</template>
