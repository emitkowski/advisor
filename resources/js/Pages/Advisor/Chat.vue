<script setup>
import { ref, computed, nextTick, watch, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import MarkdownMessage from '@/Components/MarkdownMessage.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    session: Object,
    model:   String,
    pricing: Object,
});

const messages = ref(props.session.thread ?? []);
const streamingText = ref('');
const isStreaming = ref(false);
const isSearching = ref(false);
const isActive = ref(!props.session.ended_at);
const isClosing = ref(false);
const isExtracting = ref(false);
const extractionDone = ref(!!props.session.learnings_extracted_at);

// --- Sharing ---
const shareUrl = ref(props.session.share_token
    ? `${window.location.origin}/shared/${props.session.share_token}`
    : null
);
const showSharePopover = ref(false);
const isSharingLoading = ref(false);
const copyConfirmed = ref(false);

async function enableSharing() {
    isSharingLoading.value = true;
    try {
        const { data } = await window.axios.post(`/api/v1/advisor/sessions/${props.session.id}/share`);
        shareUrl.value = data.share_url;
    } finally {
        isSharingLoading.value = false;
    }
}

async function revokeSharing() {
    if (!confirm('Revoke this link? Anyone with it will lose access.')) return;
    isSharingLoading.value = true;
    try {
        await window.axios.delete(`/api/v1/advisor/sessions/${props.session.id}/share`);
        shareUrl.value = null;
        showSharePopover.value = false;
    } finally {
        isSharingLoading.value = false;
    }
}

async function copyShareUrl() {
    await navigator.clipboard.writeText(shareUrl.value);
    copyConfirmed.value = true;
    setTimeout(() => { copyConfirmed.value = false; }, 2000);
}

// --- Export as Markdown ---
const exportConfirmed = ref(false);

function buildMarkdown() {
    const title   = sessionTitle.value || 'Untitled Session';
    const agent   = props.session.agent?.name ?? null;
    const date    = new Date(props.session.started_at ?? Date.now())
        .toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

    const lines = [`# ${title}`, ''];
    if (agent) lines.push(`**Agent:** ${agent}  `);
    lines.push(`**Date:** ${date}`, '', '---', '');

    for (const msg of messages.value) {
        const label = msg.role === 'user' ? '**You**' : `**${agent ?? 'Advisor'}**`;
        lines.push(`${label}\n\n${msg.content}`, '');
    }

    return lines.join('\n');
}

async function copyAsMarkdown() {
    await navigator.clipboard.writeText(buildMarkdown());
    exportConfirmed.value = true;
    setTimeout(() => { exportConfirmed.value = false; }, 2000);
}
const error = ref(null);
const lastFailedMessage = ref(null);
const input = ref('');
const messagesEl = ref(null);
const inputEl = ref(null);

/** Maps message index → rating (1–10) for messages rated this session. */
const ratings = ref({});

// --- Inline title editing ---
const sessionTitle   = ref(props.session.title ?? '');
const isEditingTitle = ref(false);
const isSavingTitle  = ref(false);
const titleInputEl   = ref(null);

async function startEditingTitle() {
    isEditingTitle.value = true;
    await nextTick();
    titleInputEl.value?.select();
}

async function saveTitle() {
    const trimmed = sessionTitle.value.trim();
    if (!trimmed) {
        sessionTitle.value = props.session.title ?? '';
        isEditingTitle.value = false;
        return;
    }

    isEditingTitle.value = false;
    isSavingTitle.value  = true;
    try {
        await window.axios.patch(`/api/v1/advisor/sessions/${props.session.id}`, { title: trimmed });
    } catch {
        // Non-critical — title just stays locally updated
    } finally {
        isSavingTitle.value = false;
    }
}

function cancelEditingTitle() {
    sessionTitle.value   = props.session.title ?? '';
    isEditingTitle.value = false;
}

function handleTitleKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        saveTitle();
    } else if (e.key === 'Escape') {
        cancelEditingTitle();
    }
}
const sessionTokens = ref({
    input: props.session.input_tokens ?? 0,
    output: props.session.output_tokens ?? 0,
});

const sessionCost = computed(() => {
    const price = props.pricing?.[props.model] ?? Object.values(props.pricing ?? {})[0];
    if (!price) return null;
    const cost = (sessionTokens.value.input  / 1_000_000) * price.input_per_million
               + (sessionTokens.value.output / 1_000_000) * price.output_per_million;
    return cost < 0.01 ? '<$0.01' : `$${cost.toFixed(2)}`;
});

const canSend = computed(() => input.value.trim().length > 0 && !isStreaming.value && isActive.value);

function getCsrfToken() {
    return decodeURIComponent(
        document.cookie.split('; ')
            .find(row => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? ''
    );
}

function scrollToBottom() {
    nextTick(() => {
        if (messagesEl.value) {
            messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
        }
    });
}

watch(() => streamingText.value, scrollToBottom);
watch(() => messages.value.length, scrollToBottom);
onMounted(scrollToBottom);

function onDocumentClick() { showSharePopover.value = false; }
onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));

async function sendMessage() {
    if (!canSend.value) return;

    error.value = null;
    lastFailedMessage.value = null;
    const content = input.value.trim();
    const idempotencyKey = crypto.randomUUID();
    input.value = '';

    isStreaming.value = true;
    streamingText.value = '';
    const optimisticIndex = messages.value.length;
    messages.value.push({ role: 'user', content, timestamp: new Date().toISOString() });
    await nextTick();

    const controller = new AbortController();
    let timeoutId = setTimeout(() => controller.abort(), 30000);

    function resetTimeout() {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => controller.abort(), 30000);
    }

    try {
        const response = await fetch(`/api/v1/advisor/sessions/${props.session.id}/message`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ content, idempotency_key: idempotencyKey }),
            signal: controller.signal,
        });

        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            const message = body.errors
                ? Object.values(body.errors).flat().join(' ')
                : (body.message ?? `Request failed: ${response.status}`);
            throw new Error(message);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            resetTimeout();

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop();

            for (const line of lines) {
                if (!line.startsWith('data: ')) continue;
                let data;
                try {
                    data = JSON.parse(line.slice(6));
                } catch {
                    console.error('[advisor] Failed to parse SSE event:', line.slice(6));
                    continue;
                }
                if (data.searching !== undefined) {
                    isSearching.value = data.searching;
                }
                if (data.text) {
                    isSearching.value = false;
                    streamingText.value += data.text;
                }
                if (data.done) {
                    messages.value.push({
                        role: 'assistant',
                        content: streamingText.value,
                        timestamp: new Date().toISOString(),
                    });
                    streamingText.value = '';
                    sessionTokens.value.input  += data.input_tokens  ?? 0;
                    sessionTokens.value.output += data.output_tokens ?? 0;
                }
                if (data.error) {
                    throw new Error(data.error);
                }
            }
        }
    } catch (e) {
        if (e.name === 'AbortError') {
            error.value = 'The advisor took too long to respond. Please try again.';
        } else {
            error.value = e.message;
        }
        streamingText.value = '';
        lastFailedMessage.value = content;
        messages.value.splice(optimisticIndex, 1);
    } finally {
        clearTimeout(timeoutId);
        isStreaming.value = false;
        isSearching.value = false;
        nextTick(() => inputEl.value?.focus());
    }
}

function retryMessage() {
    if (!lastFailedMessage.value) return;
    input.value = lastFailedMessage.value;
    lastFailedMessage.value = null;
    sendMessage();
}

async function closeSession() {
    if (!confirm('End this session? The advisor will extract learnings from this conversation.')) return;

    isClosing.value = true;
    try {
        await window.axios.post(`/api/v1/advisor/sessions/${props.session.id}/close`);
        isActive.value = false;
        isExtracting.value = true;
        pollForExtraction();
    } catch (e) {
        error.value = 'Failed to close session.';
    } finally {
        isClosing.value = false;
    }
}

async function pollForExtraction() {
    const maxAttempts = 20;
    const intervalMs = 2000;

    for (let i = 0; i < maxAttempts; i++) {
        await new Promise(resolve => setTimeout(resolve, intervalMs));
        try {
            const { data } = await window.axios.get(`/api/v1/advisor/sessions/${props.session.id}`);
            if (data.learnings_extracted_at) {
                extractionDone.value = true;
                isExtracting.value = false;
                router.reload({ only: ['session'] });
                return;
            }
        } catch {
            // Ignore poll errors — keep trying
        }
    }

    // Timed out — stop spinner, let user proceed
    isExtracting.value = false;
}

function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

async function rateMessage(index, rating) {
    if (ratings.value[index] !== undefined) return;

    // Optimistically store the rating before the request resolves
    ratings.value[index] = rating;

    const message = messages.value[index];

    try {
        await window.axios.post(`/api/v1/advisor/sessions/${props.session.id}/rate`, {
            rating,
            context: rating >= 7 ? 'User gave thumbs up' : 'User gave thumbs down',
            message_snippet: message?.content?.slice(0, 200) ?? null,
        });
    } catch {
        // Roll back on failure so they can retry
        delete ratings.value[index];
    }
}
</script>

<template>
    <AppLayout title="Advisor">
        <template #header>
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 sm:gap-4 min-w-0">
                    <Link :href="route('advisor.index')" class="text-gray-400 hover:text-gray-600 transition shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <!-- Active agent badge -->
                    <span
                        v-if="session.agent"
                        class="hidden sm:inline text-xs px-2 py-0.5 rounded-full font-medium shrink-0"
                        :style="{ backgroundColor: (session.agent.color || '#6B7280') + '20', color: session.agent.color || '#6B7280' }"
                    >
                        {{ session.agent.name }}
                    </span>

                    <!-- Inline title editing -->
                    <div class="group flex items-center gap-1.5 min-w-0">
                        <input
                            v-if="isEditingTitle"
                            ref="titleInputEl"
                            v-model="sessionTitle"
                            @keydown="handleTitleKeydown"
                            @blur="saveTitle"
                            maxlength="120"
                            class="font-semibold text-base sm:text-xl text-gray-800 leading-tight bg-transparent border-b border-gray-400 focus:border-gray-800 focus:outline-none w-40 sm:w-64"
                        />
                        <h2
                            v-else
                            class="font-semibold text-base sm:text-xl text-gray-800 leading-tight truncate"
                            :class="{ 'opacity-60': isSavingTitle }"
                        >
                            {{ sessionTitle || 'Untitled session' }}
                        </h2>
                        <button
                            v-if="!isEditingTitle"
                            @click="startEditingTitle"
                            title="Edit title"
                            class="opacity-0 group-hover:opacity-100 shrink-0 text-gray-400 hover:text-gray-600 transition"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <span v-if="sessionTokens.input > 0 || sessionTokens.output > 0" class="hidden sm:inline text-xs text-gray-400 tabular-nums">
                        {{ (sessionTokens.input + sessionTokens.output).toLocaleString() }} tokens · {{ sessionCost }}
                    </span>

                    <!-- Export as Markdown -->
                    <button
                        @click="copyAsMarkdown"
                        title="Copy as Markdown"
                        class="inline-flex items-center gap-1.5 px-2 sm:px-3 py-1.5 text-sm text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50 transition"
                        :class="{ 'text-green-600 border-green-200': exportConfirmed }"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <span class="hidden sm:inline">{{ exportConfirmed ? 'Copied!' : 'Copy' }}</span>
                    </button>

                    <!-- Share button + popover -->
                    <div class="relative">
                        <button
                            @click.stop="showSharePopover = !showSharePopover"
                            title="Share session"
                            class="inline-flex items-center gap-1.5 px-2 sm:px-3 py-1.5 text-sm text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50 transition"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                            </svg>
                            <span class="hidden sm:inline">Share</span>
                        </button>

                        <!-- Popover -->
                        <div
                            v-if="showSharePopover"
                            class="absolute right-0 top-full mt-2 w-[calc(100vw-1rem)] sm:w-80 max-w-sm bg-white rounded-xl shadow-lg border border-gray-200 p-4 z-20"
                            @click.stop
                        >
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-medium text-gray-800">Share this session</span>
                                <button @click="showSharePopover = false" class="text-gray-400 hover:text-gray-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div v-if="shareUrl">
                                <p class="text-xs text-gray-500 mb-2">Anyone with this link can view the conversation.</p>
                                <div class="flex items-center gap-2 mb-3">
                                    <input
                                        :value="shareUrl"
                                        readonly
                                        class="flex-1 text-xs bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-gray-600 truncate focus:outline-none"
                                    />
                                    <button
                                        @click="copyShareUrl"
                                        class="shrink-0 px-3 py-2 text-xs font-medium rounded-lg transition"
                                        :class="copyConfirmed ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                    >
                                        {{ copyConfirmed ? 'Copied!' : 'Copy' }}
                                    </button>
                                </div>
                                <button
                                    @click="revokeSharing"
                                    :disabled="isSharingLoading"
                                    class="text-xs text-red-500 hover:text-red-700 disabled:opacity-50 transition"
                                >
                                    Revoke link
                                </button>
                            </div>

                            <div v-else>
                                <p class="text-xs text-gray-500 mb-3">Create a read-only link to share this conversation.</p>
                                <button
                                    @click="enableSharing"
                                    :disabled="isSharingLoading"
                                    class="w-full py-2 text-sm font-medium bg-gray-800 text-white rounded-lg hover:bg-gray-700 disabled:opacity-50 transition"
                                >
                                    {{ isSharingLoading ? 'Generating…' : 'Create share link' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <button
                        v-if="isActive"
                        @click="closeSession"
                        :disabled="isClosing || isStreaming"
                        class="inline-flex items-center px-2 sm:px-3 py-1.5 text-sm text-red-600 border border-red-200 rounded-md hover:bg-red-50 disabled:opacity-50 transition"
                    >
                        <span class="sm:hidden">{{ isClosing ? '…' : 'End' }}</span>
                        <span class="hidden sm:inline">{{ isClosing ? 'Closing…' : 'End Session' }}</span>
                    </button>
                    <span v-else class="hidden sm:inline text-sm text-gray-400">Session closed</span>
                </div>
            </div>
        </template>

        <div class="py-2 sm:py-6">
            <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
                <div class="bg-white rounded-lg shadow-xl flex flex-col overflow-hidden h-[calc(100dvh-9rem)] sm:h-[calc(100dvh-14rem)]">

                    <!-- Messages -->
                    <div ref="messagesEl" class="flex-1 overflow-y-auto p-6 space-y-6">

                        <div v-if="messages.length === 0 && !isStreaming" class="h-full flex items-center justify-center text-gray-400 text-sm">
                            Start the conversation. The advisor is ready.
                        </div>

                        <div
                            v-for="(msg, index) in messages"
                            :key="`${msg.timestamp}-${msg.role}-${index}`"
                            class="flex flex-col"
                            :class="msg.role === 'user' ? 'items-end' : 'items-start'"
                        >
                            <div
                                class="max-w-[85%] rounded-2xl px-4 py-3"
                                :class="msg.role === 'user'
                                    ? 'bg-gray-800 text-white rounded-br-sm'
                                    : 'bg-gray-100 text-gray-800 rounded-bl-sm'"
                            >
                                <MarkdownMessage :content="msg.content" />
                            </div>

                            <!-- Rating buttons (assistant messages only) -->
                            <div
                                v-if="msg.role === 'assistant'"
                                class="flex items-center gap-1 mt-1 px-1"
                            >
                                <button
                                    @click="rateMessage(index, 8)"
                                    :disabled="ratings[index] !== undefined"
                                    :title="ratings[index] === 8 ? 'Helpful' : 'Mark as helpful'"
                                    class="p-1 rounded transition"
                                    :class="ratings[index] === 8
                                        ? 'text-green-600'
                                        : 'text-gray-300 hover:text-gray-500 disabled:cursor-default'"
                                >
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z" />
                                    </svg>
                                </button>
                                <button
                                    @click="rateMessage(index, 3)"
                                    :disabled="ratings[index] !== undefined"
                                    :title="ratings[index] === 3 ? 'Not helpful' : 'Mark as not helpful'"
                                    class="p-1 rounded transition"
                                    :class="ratings[index] === 3
                                        ? 'text-red-500'
                                        : 'text-gray-300 hover:text-gray-500 disabled:cursor-default'"
                                >
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M18 9.5a1.5 1.5 0 11-3 0v-6a1.5 1.5 0 013 0v6zM14 9.667v-5.43a2 2 0 00-1.105-1.79l-.05-.025A4 4 0 0011.055 2H5.64a2 2 0 00-1.962 1.608l-1.2 6A2 2 0 004.44 12H8v4a2 2 0 002 2 1 1 0 001-1v-.667a4 4 0 01.8-2.4l1.4-1.866a4 4 0 00.8-2.4z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Streaming response -->
                        <div v-if="isStreaming || streamingText" class="flex justify-start">
                            <div class="max-w-[85%] rounded-2xl rounded-bl-sm px-4 py-3 bg-gray-100 text-gray-800">
                                <MarkdownMessage v-if="streamingText" :content="streamingText" />
                                <span v-else-if="isSearching" class="inline-flex items-center gap-2 text-gray-500">
                                    <svg class="w-3.5 h-3.5 animate-spin shrink-0" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                    </svg>
                                    Searching the web…
                                </span>
                                <span v-else class="inline-flex gap-1">
                                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms" />
                                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms" />
                                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms" />
                                </span>
                            </div>
                        </div>

                    </div>

                    <!-- Error -->
                    <div v-if="error" class="px-6 py-2 bg-red-50 border-t border-red-100 flex items-center justify-between gap-4">
                        <span class="text-red-600 text-sm">{{ error }}</span>
                        <button
                            v-if="lastFailedMessage"
                            @click="retryMessage"
                            class="shrink-0 text-sm text-red-700 font-medium underline hover:no-underline"
                        >
                            Retry
                        </button>
                    </div>

                    <!-- Closed banner -->
                    <div v-if="!isActive" class="bg-gray-50 border-t border-gray-200">
                        <!-- Summary (if available) -->
                        <div v-if="session.summary && !isExtracting" class="px-6 py-4 border-b border-gray-100">
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Session Summary</p>
                            <p class="text-sm text-gray-600 leading-relaxed">{{ session.summary }}</p>
                        </div>
                        <!-- Status line -->
                        <div class="px-6 py-3 text-sm text-center">
                            <span v-if="isExtracting" class="inline-flex items-center gap-2 text-gray-500">
                                <svg class="w-3.5 h-3.5 animate-spin shrink-0" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                </svg>
                                Extracting learnings from this session…
                            </span>
                            <span v-else-if="extractionDone" class="inline-flex items-center gap-2 text-gray-600">
                                <svg class="w-3.5 h-3.5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Learnings extracted.
                                <Link :href="route('advisor.index')" class="text-gray-800 font-medium hover:underline">
                                    Start a new session →
                                </Link>
                            </span>
                            <span v-else class="text-gray-500">
                                This session is closed.
                                <Link :href="route('advisor.index')" class="text-gray-800 font-medium hover:underline ml-1">
                                    Start a new session →
                                </Link>
                            </span>
                        </div>
                    </div>

                    <!-- Input -->
                    <div v-if="isActive" class="border-t border-gray-200 p-3 sm:p-4">
                        <div class="flex items-end gap-2 sm:gap-3">
                            <textarea
                                ref="inputEl"
                                v-model="input"
                                @keydown="handleKeydown"
                                :disabled="isStreaming"
                                placeholder="Message the advisor…"
                                rows="2"
                                class="flex-1 resize-none rounded-xl border border-gray-300 px-3 sm:px-4 py-2 sm:py-3 text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 focus:border-transparent disabled:opacity-50 transition"
                            />
                            <button
                                @click="sendMessage"
                                :disabled="!canSend"
                                class="shrink-0 p-2.5 sm:p-3 bg-gray-800 text-white rounded-xl hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                            </button>
                        </div>
                        <p class="hidden sm:block text-xs text-gray-400 mt-2">Enter to send · Shift+Enter for new line</p>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>
