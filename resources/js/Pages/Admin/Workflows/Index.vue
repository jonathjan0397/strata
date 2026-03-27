<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

defineProps({
    workflows: Array,
});

function toggle(id) {
    router.post(route('admin.workflows.toggle', id));
}

function destroy(id) {
    if (confirm('Delete this workflow?')) {
        router.delete(route('admin.workflows.destroy', id));
    }
}

const triggerLabel = (t) => t.replace('.', ': ').replace(/\b\w/g, c => c.toUpperCase());
</script>

<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold text-gray-900">Automation Workflows <span class="text-xs font-normal text-amber-600 bg-amber-50 rounded px-1.5 py-0.5 ml-1">Premium ⭐</span></h1>
                <Link :href="route('admin.workflows.create')" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                    + New Workflow
                </Link>
            </div>
        </template>

        <div class="max-w-5xl mx-auto space-y-3">
            <div
                v-for="wf in workflows"
                :key="wf.id"
                class="bg-white rounded-lg border border-gray-200 px-5 py-4 flex items-center gap-4"
            >
                <!-- Active toggle -->
                <button
                    @click="toggle(wf.id)"
                    :class="['relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200', wf.is_active ? 'bg-indigo-600' : 'bg-gray-200']"
                    :title="wf.is_active ? 'Deactivate' : 'Activate'"
                >
                    <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200', wf.is_active ? 'translate-x-4' : 'translate-x-0']" />
                </button>

                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ wf.name }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Trigger: <span class="font-medium text-gray-700">{{ triggerLabel(wf.trigger) }}</span>
                        &nbsp;·&nbsp; {{ wf.conditions_count }} condition{{ wf.conditions_count !== 1 ? 's' : '' }}
                        &nbsp;·&nbsp; {{ wf.actions_count }} action{{ wf.actions_count !== 1 ? 's' : '' }}
                        &nbsp;·&nbsp; {{ wf.runs_count }} run{{ wf.runs_count !== 1 ? 's' : '' }}
                    </p>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <Link :href="route('admin.workflows.edit', wf.id)" class="text-sm text-indigo-600 hover:text-indigo-900 font-medium">Edit</Link>
                    <button @click="destroy(wf.id)" class="text-sm text-red-500 hover:text-red-700">Delete</button>
                </div>
            </div>

            <div v-if="!workflows.length" class="bg-white rounded-lg border border-gray-200 px-6 py-12 text-center">
                <p class="text-gray-500 text-sm">No workflows yet. Create one to automate actions based on events.</p>
                <Link :href="route('admin.workflows.create')" class="mt-3 inline-block rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Create your first workflow
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
