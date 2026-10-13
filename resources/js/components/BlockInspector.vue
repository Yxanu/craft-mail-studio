<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue'
import RichTextEditor from './RichTextEditor.vue'
import type { EmailBlock, SourceDefinition } from '../types'

const props = defineProps<{ block: EmailBlock | null; source: SourceDefinition | null; disabled?: boolean }>()
const emit = defineEmits<{ change: [] }>()

function insertToken(field: string, token: string) {
  if (!props.block) return
  const current = String(props.block.data[field] ?? '')
  props.block.data[field] = `${current}${current ? ' ' : ''}{{ ${token} }}`
  emit('change')
}

function addItem() {
  if (!props.block) return
  props.block.data.items ??= []
  props.block.data.items.push({ label: 'Label', value: 'Value' })
  emit('change')
}
</script>

<template>
  <aside class="ms-inspector">
    <template v-if="block">
      <div class="ms-panel-heading">
        <span>Block settings</span>
        <code>{{ block.type }}</code>
      </div>

      <div v-if="block.type === 'heading'" class="ms-field-stack">
        <label class="ms-field"><span>Text</span><input v-model="block.data.text" :disabled="disabled" @input="emit('change')"></label>
        <label class="ms-field"><span>Level</span><select v-model.number="block.data.level" :disabled="disabled" @change="emit('change')"><option :value="1">H1</option><option :value="2">H2</option><option :value="3">H3</option></select></label>
      </div>

      <div v-else-if="block.type === 'richText'" class="ms-field-stack">
        <label class="ms-field"><span>Content</span></label>
        <RichTextEditor v-model="block.data.html" :disabled="disabled" @update:model-value="emit('change')" />
      </div>

      <div v-else-if="block.type === 'button'" class="ms-field-stack">
        <label class="ms-field"><span>Label</span><input v-model="block.data.label" :disabled="disabled" @input="emit('change')"></label>
        <label class="ms-field"><span>URL</span><input v-model="block.data.url" type="url" :disabled="disabled" @input="emit('change')"></label>
      </div>

      <div v-else-if="block.type === 'image'" class="ms-field-stack">
        <label class="ms-field"><span>Public image URL</span><input v-model="block.data.url" type="url" :disabled="disabled" @input="emit('change')"></label>
        <label class="ms-field"><span>Alternative text</span><input v-model="block.data.alt" :disabled="disabled" @input="emit('change')"></label>
      </div>

      <div v-else-if="block.type === 'callout'" class="ms-field-stack">
        <label class="ms-field"><span>Title</span><input v-model="block.data.title" :disabled="disabled" @input="emit('change')"></label>
        <label class="ms-field"><span>Text</span><textarea v-model="block.data.text" rows="4" :disabled="disabled" @input="emit('change')" /></label>
      </div>

      <div v-else-if="block.type === 'divider'" class="ms-field-stack">
        <label class="ms-field"><span>Spacing: {{ block.data.spacing }} px</span><input v-model.number="block.data.spacing" type="range" min="8" max="64" step="4" :disabled="disabled" @input="emit('change')"></label>
      </div>

      <div v-else-if="block.type === 'keyValue'" class="ms-field-stack">
        <div v-for="(item, index) in block.data.items" :key="index" class="ms-key-value-row">
          <input v-model="item.label" aria-label="Label" :disabled="disabled" @input="emit('change')">
          <input v-model="item.value" aria-label="Value" :disabled="disabled" @input="emit('change')">
          <button type="button" class="ms-icon-btn ms-icon-btn--danger" title="Delete row" :disabled="disabled" @click="block.data.items.splice(index, 1); emit('change')"><Trash2 :size="15" /></button>
        </div>
        <button type="button" class="ms-btn ms-btn--secondary" :disabled="disabled" @click="addItem"><Plus :size="16" /> Add row</button>
      </div>

      <div v-if="source && !['divider', 'image'].includes(block.type)" class="ms-token-panel">
        <div class="ms-panel-heading">Available tokens</div>
        <div class="ms-token-list">
          <button
            v-for="(definition, token) in source.variables"
            :key="token"
            type="button"
            class="ms-token"
            :title="`Insert ${token}`"
            :disabled="disabled"
            @click="insertToken(block.type === 'richText' ? 'html' : block.type === 'button' ? 'label' : block.type === 'callout' ? 'text' : 'text', token)"
          >{{ definition.label || token }}</button>
        </div>
      </div>
    </template>
    <div v-else class="ms-empty-small">Select a block to edit its content.</div>
  </aside>
</template>
