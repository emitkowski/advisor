<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    agent: Object,
    stats: Object,
});
</script>

<template>
    <AppLayout :title="agent.name">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('advisor.agents')" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <div class="flex items-center gap-2.5">
                        <div class="w-3 h-3 rounded-full shrink-0" :style="{ backgroundColor: agent.color || '#6B7280' }" />
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ agent.name }}</h2>
                        <span
                            v-if="agent.sort_order === 1"
                            class="text-xs px-2 py-0.5 rounded-full font-medium"
                            :style="{ backgroundColor: (agent.color || '#6B7280') + '20', color: agent.color || '#6B7280' }"
                        >default</span>
                        <span v-else-if="agent.is_preset" class="text-xs px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full">preset</span>
                    </div>
                </div>
                <Link
                    :href="route('advisor.agents.edit', agent.id)"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700 transition"
                >
                    Edit
                </Link>
            </div>
        </template>

        <div class="py-6 sm:py-12">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Stats row -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-white rounded-lg shadow px-5 py-4 text-center">
                        <p class="text-2xl font-semibold text-gray-800">{{ stats.total_sessions }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Sessions</p>
                    </div>
                    <div class="bg-white rounded-lg shadow px-5 py-4 text-center">
                        <p class="text-2xl font-semibold text-gray-800">{{ stats.total_messages }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Messages</p>
                    </div>
                    <div class="bg-white rounded-lg shadow px-5 py-4 text-center">
                        <p class="text-2xl font-semibold text-gray-800">
                            {{ stats.avg_rating !== null ? stats.avg_rating : '—' }}
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5">Avg Rating</p>
                    </div>
                </div>

                <!-- Identity -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800">Identity</h3>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Description</p>
                            <p class="text-sm text-gray-700">{{ agent.description }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Badge Color</p>
                            <div class="flex items-center gap-2">
                                <div class="w-5 h-5 rounded border border-gray-200" :style="{ backgroundColor: agent.color }" />
                                <span class="text-sm font-mono text-gray-600">{{ agent.color }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Prompt Preamble -->
                <div v-if="agent.system_prompt_preamble" class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800">System Prompt Preamble</h3>
                    </div>
                    <div class="px-6 py-5">
                        <pre class="text-sm text-gray-700 font-mono whitespace-pre-wrap leading-relaxed">{{ agent.system_prompt_preamble }}</pre>
                    </div>
                </div>

                <!-- Algorithm -->
                <div v-if="agent.algorithm" class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800">Algorithm</h3>
                        <p class="text-sm text-gray-400 mt-0.5">Cognitive process injected after the preamble</p>
                    </div>
                    <div class="px-6 py-5">
                        <pre class="text-sm text-gray-700 font-mono whitespace-pre-wrap leading-relaxed">{{ agent.algorithm }}</pre>
                    </div>
                </div>

                <!-- Personality Traits -->
                <div v-if="agent.personality && agent.personality.length" class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800">Personality Traits</h3>
                        <p class="text-sm text-gray-400 mt-0.5">Injected into the system prompt on each message</p>
                    </div>
                    <div class="divide-y divide-gray-50">
                        <div
                            v-for="trait in agent.personality"
                            :key="trait.trait"
                            class="px-6 py-4"
                        >
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-sm font-mono font-medium text-gray-700">{{ trait.trait }}</span>
                                <span class="text-sm tabular-nums text-gray-500">{{ trait.value }}/100</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-1.5 mb-1.5">
                                <div
                                    class="h-1.5 rounded-full transition-all"
                                    :style="{ width: trait.value + '%', backgroundColor: agent.color || '#6B7280' }"
                                />
                            </div>
                            <p v-if="trait.description" class="text-xs text-gray-400">{{ trait.description }}</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
