<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Image from '@tiptap/extension-image'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import TextAlign from '@tiptap/extension-text-align'
import Underline from '@tiptap/extension-underline'
import { watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Write article content here…' },
})

const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Underline,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Link.configure({ openOnClick: false, autolink: true }),
        Image.configure({ inline: false, allowBase64: true }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    editorProps: {
        handleDrop(view, event, slice, moved) {
            const files = event.dataTransfer?.files
            if (files?.length) {
                Array.from(files).forEach(file => {
                    if (file.type.startsWith('image/')) {
                        event.preventDefault()
                        uploadImage(file)
                        return true
                    }
                })
            }
            return false
        },
        handlePaste(view, event) {
            const items = event.clipboardData?.items
            if (items) {
                for (const item of items) {
                    if (item.type.startsWith('image/')) {
                        const file = item.getAsFile()
                        if (file) {
                            event.preventDefault()
                            uploadImage(file)
                            return true
                        }
                    }
                }
            }
            return false
        },
    },
    onUpdate({ editor }) {
        emit('update:modelValue', editor.getHTML())
    },
})

watch(() => props.modelValue, (val) => {
    if (editor.value && editor.value.getHTML() !== val) {
        editor.value.commands.setContent(val, false)
    }
})

async function uploadImage(file) {
    const fd = new FormData()
    fd.append('image', file)
    try {
        const { data } = await axios.post('/admin/kb/images', fd)
        editor.value?.chain().focus().setImage({ src: data.url }).run()
    } catch {
        alert('Image upload failed.')
    }
}

function triggerImageUpload() {
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = 'image/*'
    input.onchange = (e) => {
        const file = e.target.files?.[0]
        if (file) uploadImage(file)
    }
    input.click()
}

function setLink() {
    const prev = editor.value?.getAttributes('link').href ?? ''
    const url  = window.prompt('URL', prev)
    if (url === null) return
    if (url === '') {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
    } else {
        editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
    }
}
</script>

<template>
    <div class="tiptap-editor border border-gray-300 rounded-lg overflow-hidden" :class="{ 'border-red-400': false }">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-0.5 px-2 py-1.5 bg-gray-50 border-b border-gray-200">
            <!-- Text style -->
            <button type="button" title="Bold"
                @click="editor?.chain().focus().toggleBold().run()"
                :class="['toolbar-btn', editor?.isActive('bold') ? 'active' : '']">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M15.6 10.8c.9-.7 1.4-1.7 1.4-2.8C17 5.3 15.1 4 13 4H6v16h8c2.2 0 4-1.8 4-4 0-1.7-1-3.1-2.4-3.8zM9 7h3.5c1 0 1.5.7 1.5 1.5S13.4 10 12.5 10H9V7zm4 10H9v-3h4c1 0 1.7.7 1.7 1.5 0 .9-.7 1.5-1.7 1.5z"/></svg>
            </button>
            <button type="button" title="Italic"
                @click="editor?.chain().focus().toggleItalic().run()"
                :class="['toolbar-btn', editor?.isActive('italic') ? 'active' : '']">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4v3h2.21l-3.42 10H6v3h8v-3h-2.21l3.42-10H18V4z"/></svg>
            </button>
            <button type="button" title="Underline"
                @click="editor?.chain().focus().toggleUnderline().run()"
                :class="['toolbar-btn', editor?.isActive('underline') ? 'active' : '']">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17c3.3 0 6-2.7 6-6V3h-2.5v8c0 1.9-1.6 3.5-3.5 3.5S8.5 12.9 8.5 11V3H6v8c0 3.3 2.7 6 6 6zm-7 2v2h14v-2H5z"/></svg>
            </button>
            <button type="button" title="Strike"
                @click="editor?.chain().focus().toggleStrike().run()"
                :class="['toolbar-btn', editor?.isActive('strike') ? 'active' : '']">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M10 19h4v-3h-4v3zM5 4v3h5v3h4V7h5V4H5zM3 14h18v-2H3v2z"/></svg>
            </button>

            <div class="toolbar-sep"></div>

            <!-- Headings -->
            <button type="button" title="Heading 1"
                @click="editor?.chain().focus().toggleHeading({ level: 1 }).run()"
                :class="['toolbar-btn font-bold text-xs', editor?.isActive('heading', { level: 1 }) ? 'active' : '']">H1</button>
            <button type="button" title="Heading 2"
                @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
                :class="['toolbar-btn font-bold text-xs', editor?.isActive('heading', { level: 2 }) ? 'active' : '']">H2</button>
            <button type="button" title="Heading 3"
                @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
                :class="['toolbar-btn font-bold text-xs', editor?.isActive('heading', { level: 3 }) ? 'active' : '']">H3</button>

            <div class="toolbar-sep"></div>

            <!-- Align -->
            <button type="button" title="Align left"
                @click="editor?.chain().focus().setTextAlign('left').run()"
                :class="['toolbar-btn', editor?.isActive({ textAlign: 'left' }) ? 'active' : '']">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M15 15H3v2h12v-2zm0-8H3v2h12V7zM3 13h18v-2H3v2zm0 8h18v-2H3v2zM3 3v2h18V3H3z"/></svg>
            </button>
            <button type="button" title="Align center"
                @click="editor?.chain().focus().setTextAlign('center').run()"
                :class="['toolbar-btn', editor?.isActive({ textAlign: 'center' }) ? 'active' : '']">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M7 15v2h10v-2H7zm-4 6h18v-2H3v2zm0-8h18v-2H3v2zm4-6v2h10V7H7zM3 3v2h18V3H3z"/></svg>
            </button>

            <div class="toolbar-sep"></div>

            <!-- Lists -->
            <button type="button" title="Bullet list"
                @click="editor?.chain().focus().toggleBulletList().run()"
                :class="['toolbar-btn', editor?.isActive('bulletList') ? 'active' : '']">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M4 10.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0-6c-.83 0-1.5.67-1.5 1.5S3.17 7.5 4 7.5 5.5 6.83 5.5 6 4.83 4.5 4 4.5zm0 12c-.83 0-1.5.68-1.5 1.5s.68 1.5 1.5 1.5 1.5-.68 1.5-1.5-.67-1.5-1.5-1.5zM7 19h14v-2H7v2zm0-6h14v-2H7v2zm0-8v2h14V5H7z"/></svg>
            </button>
            <button type="button" title="Ordered list"
                @click="editor?.chain().focus().toggleOrderedList().run()"
                :class="['toolbar-btn', editor?.isActive('orderedList') ? 'active' : '']">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M2 17h2v.5H3v1h1v.5H2v1h3v-4H2v1zm1-9h1V4H2v1h1v3zm-1 3h1.8L2 13.1v.9h3v-1H3.2L5 10.9V10H2v1zm5-6v2h14V5H7zm0 14h14v-2H7v2zm0-6h14v-2H7v2z"/></svg>
            </button>
            <button type="button" title="Blockquote"
                @click="editor?.chain().focus().toggleBlockquote().run()"
                :class="['toolbar-btn', editor?.isActive('blockquote') ? 'active' : '']">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z"/></svg>
            </button>
            <button type="button" title="Code block"
                @click="editor?.chain().focus().toggleCodeBlock().run()"
                :class="['toolbar-btn', editor?.isActive('codeBlock') ? 'active' : '']">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>
            </button>

            <div class="toolbar-sep"></div>

            <!-- Link + Image -->
            <button type="button" title="Link"
                @click="setLink"
                :class="['toolbar-btn', editor?.isActive('link') ? 'active' : '']">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M3.9 12c0-1.7 1.4-3.1 3.1-3.1h4V7H7C4.2 7 2 9.2 2 12s2.2 5 5 5h4v-1.9H7c-1.7 0-3.1-1.4-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.7 0 3.1 1.4 3.1 3.1s-1.4 3.1-3.1 3.1h-4V17h4c2.8 0 5-2.2 5-5s-2.2-5-5-5z"/></svg>
            </button>
            <button type="button" title="Insert image" @click="triggerImageUpload" class="toolbar-btn">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
            </button>

            <div class="toolbar-sep"></div>

            <!-- Undo / Redo -->
            <button type="button" title="Undo" @click="editor?.chain().focus().undo().run()" class="toolbar-btn">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/></svg>
            </button>
            <button type="button" title="Redo" @click="editor?.chain().focus().redo().run()" class="toolbar-btn">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M18.4 10.6C16.55 8.99 14.15 8 11.5 8c-4.65 0-8.58 3.03-9.96 7.22L3.9 16c1.05-3.19 4.05-5.5 7.6-5.5 1.95 0 3.73.72 5.12 1.88L13 16h9V7l-3.6 3.6z"/></svg>
            </button>
        </div>

        <!-- Editor area -->
        <EditorContent :editor="editor" class="tiptap-content" />
    </div>
</template>

<style scoped>
.tiptap-content :deep(.ProseMirror) {
    min-height: 400px;
    padding: 1rem;
    outline: none;
    font-size: 0.875rem;
    color: #374151;
    line-height: 1.7;
}

.tiptap-content :deep(.ProseMirror p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    color: #9ca3af;
    float: left;
    pointer-events: none;
    height: 0;
}

.tiptap-content :deep(.ProseMirror h1) { font-size: 1.5rem; font-weight: 700; margin: 1rem 0 0.5rem; }
.tiptap-content :deep(.ProseMirror h2) { font-size: 1.25rem; font-weight: 600; margin: 0.875rem 0 0.4rem; }
.tiptap-content :deep(.ProseMirror h3) { font-size: 1.1rem; font-weight: 600; margin: 0.75rem 0 0.35rem; }
.tiptap-content :deep(.ProseMirror ul)  { list-style-type: disc; padding-left: 1.5rem; margin: 0.5rem 0; }
.tiptap-content :deep(.ProseMirror ol)  { list-style-type: decimal; padding-left: 1.5rem; margin: 0.5rem 0; }
.tiptap-content :deep(.ProseMirror blockquote) {
    border-left: 3px solid #e5e7eb;
    padding-left: 1rem;
    color: #6b7280;
    margin: 0.75rem 0;
}
.tiptap-content :deep(.ProseMirror code) {
    background: #f3f4f6;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-family: ui-monospace, monospace;
}
.tiptap-content :deep(.ProseMirror pre) {
    background: #1e1e2e;
    color: #cdd6f4;
    padding: 1rem;
    border-radius: 8px;
    overflow-x: auto;
    margin: 0.75rem 0;
}
.tiptap-content :deep(.ProseMirror pre code) {
    background: none;
    padding: 0;
    color: inherit;
    font-size: 0.82rem;
}
.tiptap-content :deep(.ProseMirror img) {
    max-width: 100%;
    height: auto;
    border-radius: 6px;
    margin: 0.5rem 0;
    cursor: default;
}
.tiptap-content :deep(.ProseMirror img.ProseMirror-selectednode) {
    outline: 2px solid #6366f1;
}
.tiptap-content :deep(.ProseMirror a) {
    color: #6366f1;
    text-decoration: underline;
    cursor: pointer;
}
.tiptap-content :deep(.ProseMirror hr) {
    border: none;
    border-top: 1px solid #e5e7eb;
    margin: 1rem 0;
}

.toolbar-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 1.75rem;
    height: 1.75rem;
    padding: 0 0.25rem;
    border-radius: 4px;
    color: #4b5563;
    transition: background 0.1s;
}
.toolbar-btn:hover { background: #e5e7eb; color: #111827; }
.toolbar-btn.active { background: #e0e7ff; color: #4f46e5; }

.toolbar-sep {
    width: 1px;
    height: 1.25rem;
    background: #e5e7eb;
    margin: 0 0.25rem;
}
</style>
