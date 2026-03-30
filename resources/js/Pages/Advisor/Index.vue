<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    sessions: Object,
    agents: Array,
    filters: Object,
});

const showPicker = ref(false);
const selectedAgentId = ref(null);
const localSessions = ref([...props.sessions.data]);
const deletingIds = ref(new Set());

// Sync local list when the server returns updated results (filter/pagination changes)
watch(() => props.sessions.data, (data) => {
    localSessions.value = [...data];
});

const form = useForm({ agent_id: null });

// --- Filters ---
const search = ref(props.filters.search ?? '');
const filterAgentId = ref(props.filters.agent_id ?? '');
const filterStatus = ref(props.filters.status ?? '');

let searchTimer = null;

function applyFilters() {
    const params = {};
    if (search.value)        params.search   = search.value;
    if (filterAgentId.value) params.agent_id = filterAgentId.value;
    if (filterStatus.value)  params.status   = filterStatus.value;

    router.get(route('advisor.index'), params, { preserveState: true, replace: true });
}

function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
}

function clearFilters() {
    search.value        = '';
    filterAgentId.value = '';
    filterStatus.value  = '';
    router.get(route('advisor.index'), {}, { preserveState: true, replace: true });
}

const hasFilters = () => search.value || filterAgentId.value || filterStatus.value;

// --- Session management ---
function openPicker() {
    selectedAgentId.value = null;
    showPicker.value = true;
}

function startSession(agentId = null) {
    form.agent_id = agentId;
    form.post(route('advisor.store'), {
        onFinish: () => { showPicker.value = false; },
    });
}

async function deleteSession(event, session) {
    event.preventDefault();
    if (!confirm(`Delete "${session.title ?? 'Untitled session'}"? This cannot be undone.`)) {
        return;
    }
    deletingIds.value = new Set([...deletingIds.value, session.id]);
    try {
        await window.axios.delete(`/api/v1/advisor/sessions/${session.id}`);
        localSessions.value = localSessions.value.filter((s) => s.id !== session.id);
    } finally {
        const next = new Set(deletingIds.value);
        next.delete(session.id);
        deletingIds.value = next;
    }
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
                    Sessions
                </h2>
                <button
                    @click="openPicker"
                    :disabled="form.processing"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700 disabled:opacity-50 transition"
                >
                    New Session
                </button>
            </div>
        </template>

        <div class="py-6 sm:py-12">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Filters -->
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <div class="relative flex-1 min-w-48">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="search"
                            @input="onSearchInput"
                            type="text"
                            placeholder="Search sessions…"
                            class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-300 focus:border-transparent bg-white"
                        />
                    </div>

                    <select
                        v-model="filterAgentId"
                        @change="applyFilters"
                        class="shrink-0 text-sm border border-gray-300 rounded-lg pl-3 pr-10 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 bg-white text-gray-700"
                    >
                        <option value="">All agents</option>
                        <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                            {{ agent.name }}
                        </option>
                    </select>

                    <select
                        v-model="filterStatus"
                        @change="applyFilters"
                        class="shrink-0 text-sm border border-gray-300 rounded-lg pl-3 pr-10 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 bg-white text-gray-700"
                    >
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="closed">Closed</option>
                    </select>

                    <button
                        v-if="hasFilters()"
                        @click="clearFilters"
                        class="text-sm text-gray-500 hover:text-gray-700 transition underline"
                    >
                        Clear
                    </button>
                </div>

                <!-- Empty: no sessions at all -->
                <div v-if="localSessions.length === 0 && !hasFilters()" class="bg-white rounded-lg shadow p-16 text-center">
                    <p class="text-gray-500 text-lg mb-6">No sessions yet. Start a conversation with your advisor.</p>
                    <button
                        @click="openPicker"
                        :disabled="form.processing"
                        class="inline-flex items-center px-6 py-3 bg-gray-800 text-white font-medium rounded-md hover:bg-gray-700 disabled:opacity-50 transition"
                    >
                        Start First Session
                    </button>
                </div>

                <!-- Empty: filters returned no results -->
                <div v-else-if="localSessions.length === 0 && hasFilters()" class="bg-white rounded-lg shadow p-12 text-center text-gray-500 text-sm">
                    No sessions match your filters.
                    <button @click="clearFilters" class="ml-1 text-gray-700 underline hover:no-underline">Clear filters</button>
                </div>

                <div v-else class="bg-white rounded-lg shadow overflow-hidden">
                    <div
                        v-for="session in localSessions"
                        :key="session.id"
                        class="flex items-center justify-between px-6 py-4 border-b border-gray-100 last:border-b-0 hover:bg-gray-50 transition group"
                    >
                        <Link
                            :href="route('advisor.show', session.id)"
                            class="flex items-center gap-4 flex-1 min-w-0"
                        >
                            <div class="w-2 h-2 rounded-full shrink-0" :class="session.ended_at ? 'bg-gray-300' : 'bg-green-400'" />
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="font-medium text-gray-800 truncate">
                                        {{ session.title ?? 'Untitled session' }}
                                    </span>
                                    <span
                                        v-if="session.agent"
                                        class="hidden sm:inline shrink-0 text-xs px-2 py-0.5 rounded-full font-medium"
                                        :style="{ backgroundColor: (session.agent.color || '#6B7280') + '20', color: session.agent.color || '#6B7280' }"
                                    >
                                        {{ session.agent.name }}
                                    </span>
                                    <span v-if="session.is_participant && session.user" class="hidden sm:inline shrink-0 text-xs px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-full font-medium">
                                        {{ session.user.name }}'s session
                                    </span>
                                </div>
                                <div class="text-xs sm:text-sm text-gray-500 truncate">
                                    {{ formatDate(session.created_at) }}
                                    &middot;
                                    {{ session.message_count }} msg
                                    <template v-if="formatCost(session)">
                                        &middot; {{ formatCost(session) }}
                                    </template>
                                </div>
                            </div>
                        </Link>
                        <div class="flex items-center gap-2 sm:gap-3 text-sm text-gray-400 shrink-0 ml-2 sm:ml-4">
                            <span v-if="session.avg_rating" class="hidden sm:inline font-medium text-gray-600">
                                {{ session.avg_rating }}/10
                            </span>
                            <span v-if="!session.ended_at" class="text-green-600 font-medium text-xs sm:text-sm">Active</span>
                            <span v-else class="hidden sm:inline text-xs sm:text-sm">Closed</span>
                            <button
                                v-if="!session.is_participant"
                                @click="deleteSession($event, session)"
                                :disabled="deletingIds.has(session.id)"
                                title="Delete session"
                                class="opacity-0 group-hover:opacity-100 text-gray-300 hover:text-red-400 disabled:opacity-40 transition"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                            <Link :href="route('advisor.show', session.id)">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </div>
                    </div>
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

        <!-- Agent picker modal -->
        <Teleport to="body">
            <div
                v-if="showPicker"
                class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
                @click.self="showPicker = false"
            >
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col">
                    <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-lg text-gray-900">Choose an Advisor</h3>
                            <p class="text-sm text-gray-500 mt-0.5">Each advisor has a different focus and personality.</p>
                        </div>
                        <button @click="showPicker = false" class="text-gray-400 hover:text-gray-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="overflow-y-auto p-4 grid grid-cols-1 gap-3">
                        <button
                            v-for="agent in agents"
                            :key="agent.id"
                            @click="startSession(agent.id)"
                            :disabled="form.processing"
                            class="text-left p-4 rounded-lg border-2 transition disabled:opacity-50"
                            :class="selectedAgentId === agent.id
                                ? 'border-gray-800 bg-gray-50'
                                : 'border-gray-200 hover:border-gray-400 hover:bg-gray-50'"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="shrink-0 w-3 h-3 rounded-full mt-1"
                                    :style="{ backgroundColor: agent.color || '#6B7280' }"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-gray-900 text-sm">{{ agent.name }}</span>
                                        <span
                                            v-if="agent.sort_order === 1"
                                            class="text-xs px-2 py-0.5 rounded-full font-medium"
                                            :style="{ backgroundColor: agent.color + '20', color: agent.color }"
                                        >default</span>
                                    </div>
                                    <div class="text-sm text-gray-500 mt-0.5 leading-relaxed">{{ agent.description }}</div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
