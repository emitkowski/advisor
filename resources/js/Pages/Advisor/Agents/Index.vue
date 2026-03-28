<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    agents: Array,
});

const deletingId = ref(null);

async function deleteAgent(agent) {
    if (!confirm(`Delete "${agent.name}"? This cannot be undone.`)) {
        return;
    }

    deletingId.value = agent.id;
    try {
        await window.axios.delete(`/api/v1/advisor/agents/${agent.id}`);
        window.location.reload();
    } catch {
        deletingId.value = null;
    }
}
</script>

<template>
    <AppLayout title="Agents">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('advisor.index')" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Agents</h2>
                </div>
                <Link
                    :href="route('advisor.agents.create')"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700 transition"
                >
                    New Agent
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div
                        v-for="agent in agents"
                        :key="agent.id"
                        class="flex items-start justify-between px-6 py-4 border-b border-gray-100 last:border-b-0"
                        :style="{ backgroundColor: (agent.color || '#6B7280') + '10' }"
                    >
                        <div class="flex items-start gap-3 min-w-0 flex-1">
                            <div
                                class="shrink-0 w-3 h-3 rounded-full mt-1.5"
                                :style="{ backgroundColor: agent.color || '#6B7280' }"
                            />
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-800 text-base">{{ agent.name }}</span>
                                    <span
                                        v-if="agent.sort_order === 1"
                                        class="text-xs px-2 py-0.5 rounded-full font-medium"
                                        :style="{ backgroundColor: agent.color + '20', color: agent.color }"
                                    >default</span>
                                    <span v-else-if="agent.is_preset" class="text-xs px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full">preset</span>
                                </div>
                                <p class="text-sm text-gray-500 mt-0.5 leading-relaxed">{{ agent.description }}</p>
                            </div>
                        </div>
                        <div class="shrink-0 flex items-center gap-2 ml-4">
                            <Link
                                :href="route('advisor.agents.edit', agent.id)"
                                class="text-sm text-gray-500 hover:text-gray-800 transition px-2 py-1"
                            >
                                Edit
                            </Link>
                            <button
                                @click="deleteAgent(agent)"
                                :disabled="deletingId === agent.id"
                                class="text-sm text-red-400 hover:text-red-600 disabled:opacity-40 transition px-2 py-1"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
