<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';

const props = defineProps({
    agent:      Object, // null when creating
    userTeamId: Number, // null if user is not on a team
});

const isEdit = props.agent !== null;

const form = reactive({
    name:                   props.agent?.name ?? '',
    description:            props.agent?.description ?? '',
    color:                  props.agent?.color ?? '#3B82F6',
    system_prompt_preamble: props.agent?.system_prompt_preamble ?? '',
    algorithm:              props.agent?.algorithm ?? '',
    team_id:                props.agent?.team_id ?? null,
    personality:            props.agent?.personality
        ? props.agent.personality.map((t) => ({ ...t }))
        : [{ trait: '', value: 50, description: '' }],
});

const errors = ref({});
const saving = ref(false);

function addTrait() {
    form.personality.push({ trait: '', value: 50, description: '' });
}

function removeTrait(index) {
    form.personality.splice(index, 1);
}

async function submit() {
    errors.value = {};
    saving.value = true;

    try {
        if (isEdit) {
            await window.axios.patch(`/api/v1/advisor/agents/${props.agent.id}`, form);
        } else {
            await window.axios.post('/api/v1/advisor/agents', form);
        }
        router.visit(route('advisor.agents'));
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors ?? {};
        }
        saving.value = false;
    }
}
</script>

<template>
    <AppLayout :title="isEdit ? 'Edit Agent' : 'New Agent'">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('advisor.agents')" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ isEdit ? 'Edit Agent' : 'New Agent' }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">

                    <!-- Basic info -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h3 class="font-semibold text-gray-800">Identity</h3>
                        </div>
                        <div class="px-6 py-5 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-gray-800"
                                    placeholder="e.g. Devil's Advocate"
                                />
                                <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name[0] }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <input
                                    v-model="form.description"
                                    type="text"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-gray-800"
                                    placeholder="Short summary shown in the agent picker"
                                />
                                <p v-if="errors.description" class="mt-1 text-xs text-red-600">{{ errors.description[0] }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Badge Color</label>
                                <div class="flex items-center gap-3">
                                    <input
                                        v-model="form.color"
                                        type="color"
                                        class="h-9 w-14 rounded border border-gray-300 cursor-pointer p-0.5"
                                    />
                                    <input
                                        v-model="form.color"
                                        type="text"
                                        maxlength="7"
                                        class="w-28 border border-gray-300 rounded-md px-3 py-2 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-gray-800"
                                        placeholder="#3B82F6"
                                    />
                                    <span class="text-xs text-gray-400">Shown on the agent badge in sessions</span>
                                </div>
                                <p v-if="errors.color" class="mt-1 text-xs text-red-600">{{ errors.color[0] }}</p>
                            </div>
                            <div v-if="userTeamId">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :checked="form.team_id === userTeamId"
                                        @change="form.team_id = $event.target.checked ? userTeamId : null"
                                        class="w-4 h-4 rounded border-gray-300 text-gray-800 focus:ring-gray-800 cursor-pointer"
                                    />
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">Share with team</span>
                                        <p class="text-xs text-gray-400">All team members can use this agent in their sessions.</p>
                                    </div>
                                </label>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">System Prompt Preamble</label>
                                <p class="text-xs text-gray-400 mb-1.5">Defines the agent's identity and core rules. Memory context is always appended automatically.</p>
                                <textarea
                                    v-model="form.system_prompt_preamble"
                                    rows="10"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-gray-800"
                                    placeholder="# Your Identity&#10;&#10;You are..."
                                />
                                <p v-if="errors.system_prompt_preamble" class="mt-1 text-xs text-red-600">{{ errors.system_prompt_preamble[0] }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Algorithm</label>
                                <p class="text-xs text-gray-400 mb-1.5">Describes the cognitive process this agent uses when responding. Appended to the system prompt after the preamble.</p>
                                <textarea
                                    v-model="form.algorithm"
                                    rows="8"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-gray-800"
                                    placeholder="## Your Process&#10;&#10;When responding:&#10;1. ..."
                                />
                                <p v-if="errors.algorithm" class="mt-1 text-xs text-red-600">{{ errors.algorithm[0] }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Personality traits -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h3 class="font-semibold text-gray-800">Personality Traits</h3>
                                <p class="text-sm text-gray-500 mt-0.5">0–100 scale. Injected into the system prompt on each message.</p>
                            </div>
                            <button
                                type="button"
                                @click="addTrait"
                                class="text-sm text-gray-500 hover:text-gray-800 border border-gray-300 rounded-md px-3 py-1.5 hover:bg-gray-50 transition"
                            >
                                + Add Trait
                            </button>
                        </div>
                        <div class="divide-y divide-gray-50">
                            <div
                                v-for="(trait, index) in form.personality"
                                :key="index"
                                class="px-6 py-4 space-y-2"
                            >
                                <div class="flex items-center gap-3">
                                    <input
                                        v-model="trait.trait"
                                        type="text"
                                        placeholder="trait_name"
                                        class="flex-1 border border-gray-300 rounded-md px-3 py-1.5 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-gray-800"
                                    />
                                    <span class="text-sm tabular-nums text-gray-500 w-14 text-right shrink-0">{{ trait.value }}/100</span>
                                    <button
                                        type="button"
                                        @click="removeTrait(index)"
                                        class="text-gray-300 hover:text-red-400 transition shrink-0"
                                        title="Remove trait"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <input
                                    type="range"
                                    min="0"
                                    max="100"
                                    v-model.number="trait.value"
                                    class="w-full h-1.5 accent-gray-800 cursor-pointer"
                                />
                                <input
                                    v-model="trait.description"
                                    type="text"
                                    placeholder="Brief description of this trait…"
                                    class="w-full border border-gray-200 rounded-md px-3 py-1.5 text-sm text-gray-600 focus:outline-none focus:ring-1 focus:ring-gray-800"
                                />
                            </div>
                        </div>
                        <p v-if="errors.personality" class="px-6 pb-4 text-xs text-red-600">{{ errors.personality[0] }}</p>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3">
                        <Link
                            :href="route('advisor.agents')"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50 transition"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="saving"
                            class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700 disabled:opacity-50 transition"
                        >
                            {{ saving ? 'Saving…' : (isEdit ? 'Save Changes' : 'Create Agent') }}
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </AppLayout>
</template>
