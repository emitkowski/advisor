<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    token:      String,
    sessionId:  Number,
    title:      String,
    agentName:  String,
    agentColor: String,
    ownerName:  String,
});

const isJoining = ref(false);

function join() {
    isJoining.value = true;
    router.post(route('advisor.join.accept', props.token));
}
</script>

<template>
    <Head title="Join Session" />

    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
        <div class="w-full max-w-md">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-8 py-10">

                <div class="text-center mb-6">
                    <div
                        class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4"
                        :style="{ backgroundColor: (agentColor || '#6B7280') + '20' }"
                    >
                        <svg class="w-6 h-6" :style="{ color: agentColor || '#6B7280' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <h1 class="text-lg font-semibold text-gray-800">You've been invited to join a session</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        <span class="font-medium text-gray-700">{{ ownerName }}</span> has invited you to join their conversation.
                    </p>
                </div>

                <div class="bg-gray-50 rounded-lg px-4 py-4 mb-6 space-y-2">
                    <div class="flex items-start gap-3">
                        <span class="text-xs text-gray-400 w-14 shrink-0 pt-0.5">Session</span>
                        <span class="text-sm text-gray-800 font-medium">{{ title }}</span>
                    </div>
                    <div v-if="agentName" class="flex items-center gap-3">
                        <span class="text-xs text-gray-400 w-14 shrink-0">Agent</span>
                        <span
                            class="inline-flex items-center gap-1.5 text-xs px-2 py-0.5 rounded-full font-medium"
                            :style="{ backgroundColor: (agentColor || '#6B7280') + '20', color: agentColor || '#6B7280' }"
                        >
                            {{ agentName }}
                        </span>
                    </div>
                </div>

                <p class="text-xs text-gray-500 text-center mb-5">
                    You'll be able to send messages and see the full conversation.
                </p>

                <button
                    @click="join"
                    :disabled="isJoining"
                    class="w-full py-2.5 bg-gray-800 text-white text-sm font-medium rounded-lg hover:bg-gray-700 disabled:opacity-50 transition"
                >
                    {{ isJoining ? 'Joining…' : 'Join Session' }}
                </button>

                <p class="text-xs text-gray-400 text-center mt-3">
                    <a :href="route('advisor.index')" class="underline hover:text-gray-600">Back to my sessions</a>
                </p>
            </div>
        </div>
    </div>
</template>
