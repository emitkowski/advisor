<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    sessions: Object,
});

const form = useForm({});

function newSession() {
    form.post(route('advisor.store'));
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatCost(session) {
    if (!session.cost_usd || session.cost_usd === 0) return null;
    return session.cost_usd < 0.01 ? '<$0.01' : `$${Number(session.cost_usd).toFixed(2)}`;
}
</script>

<template>
    <AppLayout title="Advisor">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Advisor
                </h2>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('advisor.profile')"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50 transition"
                    >
                        What I Know About You
                    </Link>
                    <button
                        @click="newSession"
                        :disabled="form.processing"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700 disabled:opacity-50 transition"
                    >
                        New Session
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

                <div v-if="sessions.data.length === 0" class="bg-white rounded-lg shadow p-16 text-center">
                    <p class="text-gray-500 text-lg mb-6">No sessions yet. Start a conversation with your advisor.</p>
                    <button
                        @click="newSession"
                        :disabled="form.processing"
                        class="inline-flex items-center px-6 py-3 bg-gray-800 text-white font-medium rounded-md hover:bg-gray-700 disabled:opacity-50 transition"
                    >
                        Start First Session
                    </button>
                </div>

                <div v-else class="bg-white rounded-lg shadow overflow-hidden">
                    <Link
                        v-for="session in sessions.data"
                        :key="session.id"
                        :href="route('advisor.show', session.id)"
                        class="flex items-center justify-between px-6 py-4 border-b border-gray-100 last:border-b-0 hover:bg-gray-50 transition"
                    >
                        <div class="flex items-center gap-4">
                            <div class="w-2 h-2 rounded-full" :class="session.ended_at ? 'bg-gray-300' : 'bg-green-400'" />
                            <div>
                                <div class="font-medium text-gray-800">
                                    {{ session.title ?? 'Untitled session' }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ formatDate(session.created_at) }}
                                    &middot;
                                    {{ session.message_count }} messages
                                    <template v-if="formatCost(session)">
                                        &middot; {{ formatCost(session) }}
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-sm text-gray-400">
                            <span v-if="session.avg_rating" class="font-medium text-gray-600">
                                {{ session.avg_rating }}/10
                            </span>
                            <span v-if="!session.ended_at" class="text-green-600 font-medium">Active</span>
                            <span v-else>Closed</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </Link>
                </div>

                <!-- Pagination -->
                <div v-if="sessions.last_page > 1" class="mt-4 flex items-center justify-between text-sm text-gray-500">
                    <span>Page {{ sessions.current_page }} of {{ sessions.last_page }}</span>
                    <div class="flex gap-2">
                        <Link
                            v-if="sessions.prev_page_url"
                            :href="sessions.prev_page_url"
                            class="px-3 py-1.5 border border-gray-300 rounded-md hover:bg-gray-50 transition"
                        >
                            Previous
                        </Link>
                        <Link
                            v-if="sessions.next_page_url"
                            :href="sessions.next_page_url"
                            class="px-3 py-1.5 border border-gray-300 rounded-md hover:bg-gray-50 transition"
                        >
                            Next
                        </Link>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
