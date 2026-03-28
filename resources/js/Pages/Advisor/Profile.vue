<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    personalityTraits: Array,
    profileObservations: Array,
    learnings: Array,
    projects: Array,
});

const LEARNING_CATEGORIES = {
    blind_spot:     { label: 'Blind Spots',       description: 'Gaps or weaknesses the advisor has noticed' },
    pattern:        { label: 'Patterns',           description: 'Recurring behaviours across conversations' },
    follow_through: { label: 'Follow-through',     description: 'How you act on commitments and plans' },
    value:          { label: 'Values',             description: 'What matters most to you' },
    reaction:       { label: 'Reactions',          description: 'How you respond to challenge or feedback' },
    domain:         { label: 'Domain Knowledge',   description: 'Your technical or contextual expertise' },
};

const PROJECT_STATUSES = {
    active:    { label: 'Active',     color: 'bg-green-100 text-green-800' },
    paused:    { label: 'Paused',     color: 'bg-yellow-100 text-yellow-800' },
    completed: { label: 'Completed',  color: 'bg-blue-100 text-blue-800' },
    abandoned: { label: 'Abandoned',  color: 'bg-red-100 text-red-800' },
    unclear:   { label: 'Unclear',    color: 'bg-gray-100 text-gray-600' },
};

// --- Personality traits ---

/** Current slider values, keyed by trait name */
const traitValues = reactive(
    Object.fromEntries(props.personalityTraits.map((t) => [t.trait, t.value]))
);
/** Traits currently being saved */
const savingTraits = ref(new Set());

function traitChanged(traitName) {
    return traitValues[traitName] !== props.personalityTraits.find((t) => t.trait === traitName)?.value;
}

async function saveTrait(traitName) {
    savingTraits.value = new Set([...savingTraits.value, traitName]);
    try {
        await window.axios.patch(`/api/v1/advisor/personality-traits/${traitName}`, {
            value: traitValues[traitName],
        });
    } finally {
        const next = new Set(savingTraits.value);
        next.delete(traitName);
        savingTraits.value = next;
    }
}

// --- Profile observations ---

const localObservations = ref([...props.profileObservations]);
const deletingObservations = ref(new Set());

async function deleteObservation(obs) {
    deletingObservations.value = new Set([...deletingObservations.value, obs.id]);
    try {
        await window.axios.delete(`/api/v1/advisor/profile-observations/${obs.id}`);
        localObservations.value = localObservations.value.filter((o) => o.id !== obs.id);
    } finally {
        const next = new Set(deletingObservations.value);
        next.delete(obs.id);
        deletingObservations.value = next;
    }
}

// --- Learnings ---

const localLearnings = ref([...props.learnings]);
const deletingLearnings = ref(new Set());

const learningsByCategory = computed(() => {
    const grouped = {};
    for (const learning of localLearnings.value) {
        if (!grouped[learning.category]) {
            grouped[learning.category] = [];
        }
        grouped[learning.category].push(learning);
    }
    return grouped;
});

async function deleteLearning(learning) {
    deletingLearnings.value = new Set([...deletingLearnings.value, learning.id]);
    try {
        await window.axios.delete(`/api/v1/advisor/learnings/${learning.id}`);
        localLearnings.value = localLearnings.value.filter((l) => l.id !== learning.id);
    } finally {
        const next = new Set(deletingLearnings.value);
        next.delete(learning.id);
        deletingLearnings.value = next;
    }
}

// --- Projects ---

const projectsByStatus = computed(() => {
    const grouped = {};
    for (const project of props.projects) {
        if (!grouped[project.status]) {
            grouped[project.status] = [];
        }
        grouped[project.status].push(project);
    }
    return grouped;
});

// --- Helpers ---

function confidenceLabel(confidence) {
    if (confidence >= 0.8) return 'High';
    if (confidence >= 0.5) return 'Medium';
    return 'Low';
}

function confidenceColor(confidence) {
    if (confidence >= 0.8) return 'bg-green-100 text-green-700';
    if (confidence >= 0.5) return 'bg-yellow-100 text-yellow-700';
    return 'bg-gray-100 text-gray-500';
}

function formatDate(dateStr) {
    if (!dateStr) return null;
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
</script>

<template>
    <AppLayout title="What I Know About You">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('advisor.index')" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    What I Know About You
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

                <!-- Empty state -->
                <div
                    v-if="!personalityTraits.length && !localObservations.length && !localLearnings.length && !projects.length"
                    class="bg-white rounded-lg shadow p-16 text-center"
                >
                    <p class="text-gray-500 text-lg mb-2">Nothing here yet.</p>
                    <p class="text-gray-400 text-sm">Start a session and close it — the advisor will extract what it learns about you.</p>
                    <Link :href="route('advisor.index')" class="mt-6 inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700 transition">
                        Go to Sessions
                    </Link>
                </div>

                <!-- Personality -->
                <div v-if="personalityTraits.length" class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800">Advisor Personality</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Drag to adjust how the advisor works with you</p>
                    </div>
                    <div class="divide-y divide-gray-50">
                        <div v-for="trait in personalityTraits" :key="trait.trait" class="px-6 py-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700 capitalize">{{ trait.trait.replace(/_/g, ' ') }}</span>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm tabular-nums text-gray-500 w-12 text-right">{{ traitValues[trait.trait] }}/100</span>
                                    <button
                                        v-if="traitChanged(trait.trait)"
                                        @click="saveTrait(trait.trait)"
                                        :disabled="savingTraits.has(trait.trait)"
                                        class="text-xs px-2 py-0.5 bg-gray-800 text-white rounded hover:bg-gray-700 disabled:opacity-50 transition"
                                    >
                                        {{ savingTraits.has(trait.trait) ? 'Saving…' : 'Save' }}
                                    </button>
                                </div>
                            </div>
                            <input
                                type="range"
                                min="0"
                                max="100"
                                v-model.number="traitValues[trait.trait]"
                                class="w-full h-1.5 accent-gray-800 cursor-pointer"
                            />
                            <p v-if="trait.description" class="text-xs text-gray-400 mt-1.5">{{ trait.description }}</p>
                        </div>
                    </div>
                </div>

                <!-- Profile observations -->
                <div v-if="localObservations.length" class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800">Observed Traits</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Stable characteristics inferred across your conversations</p>
                    </div>
                    <div class="divide-y divide-gray-50">
                        <div v-for="obs in localObservations" :key="obs.id" class="px-6 py-3 flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <span class="text-sm font-medium text-gray-600 capitalize">{{ obs.key.replace(/_/g, ' ') }}</span>
                                <p class="text-sm text-gray-800 mt-0.5">{{ obs.value }}</p>
                            </div>
                            <div class="shrink-0 flex items-center gap-2 text-xs">
                                <span
                                    class="px-2 py-0.5 rounded-full font-medium"
                                    :class="confidenceColor(obs.confidence)"
                                >
                                    {{ confidenceLabel(obs.confidence) }}
                                </span>
                                <span class="text-gray-400">×{{ obs.observation_count }}</span>
                                <button
                                    @click="deleteObservation(obs)"
                                    :disabled="deletingObservations.has(obs.id)"
                                    title="Remove this observation"
                                    class="text-gray-300 hover:text-red-400 disabled:opacity-40 transition"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Learnings -->
                <div v-if="localLearnings.length" class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800">Learnings</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Patterns and insights extracted from your sessions</p>
                    </div>
                    <div
                        v-for="(items, category) in learningsByCategory"
                        :key="category"
                        class="border-b border-gray-100 last:border-b-0"
                    >
                        <div class="px-6 py-3 bg-gray-50">
                            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                {{ LEARNING_CATEGORIES[category]?.label ?? category }}
                            </div>
                            <div v-if="LEARNING_CATEGORIES[category]?.description" class="text-xs text-gray-400 mt-0.5">
                                {{ LEARNING_CATEGORIES[category].description }}
                            </div>
                        </div>
                        <div class="divide-y divide-gray-50">
                            <div v-for="learning in items" :key="learning.id" class="px-6 py-3 flex items-start justify-between gap-4">
                                <p class="text-sm text-gray-800 leading-relaxed">{{ learning.content }}</p>
                                <div class="shrink-0 flex items-center gap-2 text-xs">
                                    <span
                                        class="px-2 py-0.5 rounded-full font-medium"
                                        :class="confidenceColor(learning.confidence)"
                                    >
                                        {{ confidenceLabel(learning.confidence) }}
                                    </span>
                                    <span v-if="learning.reinforcement_count > 1" class="text-gray-400">×{{ learning.reinforcement_count }}</span>
                                    <button
                                        @click="deleteLearning(learning)"
                                        :disabled="deletingLearnings.has(learning.id)"
                                        title="Remove this learning"
                                        class="text-gray-300 hover:text-red-400 disabled:opacity-40 transition"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Projects -->
                <div v-if="projects.length" class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800">Projects</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Ideas and initiatives you've mentioned</p>
                    </div>
                    <div
                        v-for="(items, status) in projectsByStatus"
                        :key="status"
                        class="border-b border-gray-100 last:border-b-0"
                    >
                        <div class="px-6 py-3 bg-gray-50">
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                                :class="PROJECT_STATUSES[status]?.color ?? 'bg-gray-100 text-gray-600'"
                            >
                                {{ PROJECT_STATUSES[status]?.label ?? status }}
                            </span>
                        </div>
                        <div class="divide-y divide-gray-50">
                            <div v-for="project in items" :key="project.name" class="px-6 py-3">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">{{ project.name }}</p>
                                        <p v-if="project.description" class="text-xs text-gray-500 mt-0.5">{{ project.description }}</p>
                                        <p v-if="project.notes" class="text-xs text-gray-400 mt-0.5 italic">{{ project.notes }}</p>
                                    </div>
                                    <div v-if="project.last_seen_at" class="shrink-0 text-xs text-gray-400 whitespace-nowrap">
                                        Last seen {{ formatDate(project.last_seen_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
