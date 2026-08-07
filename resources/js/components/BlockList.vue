<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Sortable from 'sortablejs'
import { ChevronDown, ChevronUp, GripVertical, Plus, Trash2 } from '@lucide/vue'
import type { BlockType, EmailBlock } from '../types'

const props = defineProps<{ blocks: EmailBlock[]; selectedId: string | null; disabled?: boolean }>()
const emit = defineEmits<{
  'update:blocks': [blocks: EmailBlock[]]
  select: [id: string]
}>()

const list = ref<HTMLElement | null>(null)
let sortable: Sortable | null = null

const blockLabels: Record<BlockType, string> = {
  heading: 'Heading', richText: 'Rich text', button: 'Button', image: 'Image', callout: 'Callout', divider: 'Divider', keyValue: 'Key/value list',
}
const newType = ref<BlockType>('richText')

function createBlock(type: BlockType): EmailBlock {
  const defaults: Record<BlockType, Record<string, any>> = {
    heading: { text: 'New heading', level: 2 },
    richText: { html: '<p>Write your message.</p>' },
    button: { label: 'Continue', url: 'https://example.com' },
    image: { url: '', alt: '' },
    callout: { title: 'Good to know', text: 'Add supporting information.' },
    divider: { spacing: 24 },
    keyValue: { items: [{ label: 'Label', value: 'Value' }] },
  }
  return { id: `${type}-${crypto.randomUUID()}`, type, data: defaults[type] }
}

function add() {
  const block = createBlock(newType.value)
  emit('update:blocks', [...props.blocks, block])
  emit('select', block.id)
}

function remove(id: string) {
  emit('update:blocks', props.blocks.filter(block => block.id !== id))
}

function move(index: number, direction: -1 | 1) {
  const target = index + direction
  if (target < 0 || target >= props.blocks.length) return
  const blocks = [...props.blocks]
  const [block] = blocks.splice(index, 1)
  blocks.splice(target, 0, block)
  emit('update:blocks', blocks)
}

function initialize() {
  sortable?.destroy()
  if (!list.value || props.disabled) return
  sortable = Sortable.create(list.value, {
    animation: 140,
    handle: '.ms-block__handle',
    ghostClass: 'ms-block--ghost',
    onEnd(event) {
      if (event.oldIndex == null || event.newIndex == null || event.oldIndex === event.newIndex) return
      const blocks = [...props.blocks]
      const [block] = blocks.splice(event.oldIndex, 1)
      blocks.splice(event.newIndex, 0, block)
      emit('update:blocks', blocks)
    },
  })
}

onMounted(initialize)
onBeforeUnmount(() => sortable?.destroy())
watch(() => props.disabled, () => nextTick(initialize))
const options = computed(() => Object.entries(blockLabels) as [BlockType, string][])
</script>

<template>
  <div class="ms-block-list">
    <div ref="list" class="ms-block-list__items">
      <div
        v-for="(block, index) in blocks"
        :key="block.id"
        class="ms-block"
        :class="{ 'ms-block--selected': selectedId === block.id }"
      >
        <button type="button" class="ms-block__select" @click="emit('select', block.id)">
          <GripVertical class="ms-block__handle" :size="17" aria-hidden="true" />
          <span class="ms-block__label">{{ blockLabels[block.type] }}</span>
        </button>
        <span class="ms-block__actions">
          <button type="button" class="ms-icon-btn" title="Move up" :disabled="disabled || index === 0" @click="move(index, -1)"><ChevronUp :size="15" /></button>
          <button type="button" class="ms-icon-btn" title="Move down" :disabled="disabled || index === blocks.length - 1" @click="move(index, 1)"><ChevronDown :size="15" /></button>
          <button type="button" class="ms-icon-btn ms-icon-btn--danger" title="Delete block" :disabled="disabled" @click="remove(block.id)"><Trash2 :size="15" /></button>
        </span>
      </div>
    </div>
    <div class="ms-add-block">
      <select v-model="newType" class="ms-select" :disabled="disabled">
        <option v-for="[value, label] in options" :key="value" :value="value">{{ label }}</option>
      </select>
      <button type="button" class="ms-btn ms-btn--secondary" :disabled="disabled" @click="add"><Plus :size="16" /> Add</button>
    </div>
  </div>
</template>
