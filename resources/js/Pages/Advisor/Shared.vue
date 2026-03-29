<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import MarkdownMessage from '@/Components/MarkdownMessage.vue';

const props = defineProps({
    session: Object,
    meta:    Object,
});

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

const exportConfirmed = ref(false);

function buildMarkdown() {
    const title = props.session.title || 'Untitled Session';
    const agent = props.session.agent?.name ?? null;
    const date  = formatDate(props.session.created_at);

    const lines = [`# ${title}`, ''];
    if (agent) lines.push(`**Agent:** ${agent}  `);
    lines.push(`**Date:** ${date}`, '');
    if (props.session.summary) lines.push(`> ${props.session.summary}`, '');
    lines.push('---', '');

    for (const msg of props.session.thread) {
        const label = msg.role === 'user' ? '**User**' : `**${agent ?? 'Advisor'}**`;
        lines.push(`${label}\n\n${msg.content}`, '');
    }

    return lines.join('\n');
}

async function copyAsMarkdown() {
    await navigator.clipboard.writeText(buildMarkdown());
    exportConfirmed.value = true;
    setTimeout(() => { exportConfirmed.value = false; }, 2000);
}
</script>

<template>
    <Head :title="meta.title">
        <!-- Open Graph -->
        <meta head-key="og:type"        property="og:type"        :content="'article'" />
        <meta head-key="og:site_name"   property="og:site_name"   :content="meta.site_name" />
        <meta head-key="og:title"       property="og:title"       :content="meta.title" />
        <meta head-key="og:url"         property="og:url"         :content="meta.url" />
        <meta v-if="meta.description" head-key="og:description" property="og:description" :content="meta.description" />
        <!-- Twitter / X -->
        <meta head-key="twitter:card"  name="twitter:card"  content="summary" />
        <meta head-key="twitter:title" name="twitter:title" :content="meta.title" />
        <meta v-if="meta.description" head-key="twitter:description" name="twitter:description" :content="meta.description" />
        <!-- Canonical -->
        <link head-key="canonical" rel="canonical" :href="meta.url" />
    </Head>

    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span
                        v-if="session.agent"
                        class="text-xs px-2 py-0.5 rounded-full font-medium"
                        :style="{ backgroundColor: (session.agent.color || '#6B7280') + '20', color: session.agent.color || '#6B7280' }"
                    >
                        {{ session.agent.name }}
                    </span>
                    <h1 class="font-semibold text-gray-900">
                        {{ session.title ?? 'Untitled session' }}
                    </h1>
                </div>
                <div class="flex items-center gap-3 text-xs text-gray-400">
                    <span>{{ formatDate(session.created_at) }} &middot; {{ session.message_count }} messages</span>
                    <button
                        @click="copyAsMarkdown"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 border rounded-md transition"
                        :class="exportConfirmed
                            ? 'text-green-600 border-green-200 bg-green-50'
                            : 'text-gray-500 border-gray-200 hover:bg-gray-50'"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        {{ exportConfirmed ? 'Copied!' : 'Copy as Markdown' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary card -->
        <div v-if="session.summary" class="max-w-3xl mx-auto px-4 sm:px-6 pt-6">
            <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 shadow-sm">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1.5">Summary</p>
                <p class="text-sm text-gray-700 leading-relaxed">{{ session.summary }}</p>
            </div>
        </div>

        <!-- Thread -->
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-8 space-y-6">
            <div
                v-for="(msg, index) in session.thread"
                :key="index"
                class="flex flex-col"
                :class="msg.role === 'user' ? 'items-end' : 'items-start'"
            >
                <div
                    class="max-w-[85%] rounded-2xl px-4 py-3"
                    :class="msg.role === 'user'
                        ? 'bg-gray-800 text-white rounded-br-sm'
                        : 'bg-white border border-gray-200 text-gray-800 rounded-bl-sm shadow-sm'"
                >
                    <MarkdownMessage :content="msg.content" />
                </div>
            </div>

            <div v-if="!session.thread.length" class="text-center text-gray-400 text-sm py-16">
                No messages in this session.
            </div>
        </div>

        <!-- Footer -->
        <div class="max-w-3xl mx-auto px-4 sm:px-6 pb-12 text-center text-xs text-gray-400">
            Shared via Advisor
            <template v-if="session.ended_at">
                &middot; Session ended {{ formatDate(session.ended_at) }}
            </template>
        </div>
    </div>
</template>
