<script setup lang="ts">
import { watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import { Bold, Italic, Link as LinkIcon, List, ListOrdered, Redo2, Undo2 } from '@lucide/vue'

const props = defineProps<{ modelValue: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({ heading: false, blockquote: false, code: false, codeBlock: false }),
    Link.configure({ openOnClick: false, autolink: true, HTMLAttributes: { rel: 'noopener noreferrer' } }),
  ],
  onUpdate: ({ editor }) => emit('update:modelValue', editor.getHTML()),
})

watch(() => props.modelValue, value => {
  if (editor.value && editor.value.getHTML() !== value) editor.value.commands.setContent(value, { emitUpdate: false })
})

function setLink() {
  if (!editor.value) return
  const current = editor.value.getAttributes('link').href || ''
  const href = window.prompt('Link URL', current)
  if (href === null) return
  if (!href) editor.value.chain().focus().unsetLink().run()
  else editor.value.chain().focus().extendMarkRange('link').setLink({ href }).run()
}
</script>

<template>
  <div class="ms-richtext">
    <div class="ms-richtext__toolbar" role="toolbar" aria-label="Text formatting">
      <button type="button" class="ms-icon-btn" title="Bold" @click="editor?.chain().focus().toggleBold().run()"><Bold :size="16" /></button>
      <button type="button" class="ms-icon-btn" title="Italic" @click="editor?.chain().focus().toggleItalic().run()"><Italic :size="16" /></button>
      <button type="button" class="ms-icon-btn" title="Bulleted list" @click="editor?.chain().focus().toggleBulletList().run()"><List :size="16" /></button>
      <button type="button" class="ms-icon-btn" title="Numbered list" @click="editor?.chain().focus().toggleOrderedList().run()"><ListOrdered :size="16" /></button>
      <button type="button" class="ms-icon-btn" title="Link" @click="setLink"><LinkIcon :size="16" /></button>
      <span class="ms-toolbar-separator" />
      <button type="button" class="ms-icon-btn" title="Undo" @click="editor?.chain().focus().undo().run()"><Undo2 :size="16" /></button>
      <button type="button" class="ms-icon-btn" title="Redo" @click="editor?.chain().focus().redo().run()"><Redo2 :size="16" /></button>
    </div>
    <EditorContent :editor="editor" class="ms-richtext__content" />
  </div>
</template>
