<script setup>
import { computed } from 'vue';
import { marked } from 'marked';
import hljs from 'highlight.js/lib/core';
import javascript from 'highlight.js/lib/languages/javascript';
import typescript from 'highlight.js/lib/languages/typescript';
import python from 'highlight.js/lib/languages/python';
import php from 'highlight.js/lib/languages/php';
import bash from 'highlight.js/lib/languages/bash';
import sql from 'highlight.js/lib/languages/sql';
import json from 'highlight.js/lib/languages/json';
import css from 'highlight.js/lib/languages/css';
import xml from 'highlight.js/lib/languages/xml';
import markdown from 'highlight.js/lib/languages/markdown';
import go from 'highlight.js/lib/languages/go';
import rust from 'highlight.js/lib/languages/rust';
import java from 'highlight.js/lib/languages/java';
import csharp from 'highlight.js/lib/languages/csharp';
import plaintext from 'highlight.js/lib/languages/plaintext';

hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('js', javascript);
hljs.registerLanguage('typescript', typescript);
hljs.registerLanguage('ts', typescript);
hljs.registerLanguage('python', python);
hljs.registerLanguage('py', python);
hljs.registerLanguage('php', php);
hljs.registerLanguage('bash', bash);
hljs.registerLanguage('sh', bash);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('json', json);
hljs.registerLanguage('css', css);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('html', xml);
hljs.registerLanguage('markdown', markdown);
hljs.registerLanguage('go', go);
hljs.registerLanguage('rust', rust);
hljs.registerLanguage('java', java);
hljs.registerLanguage('csharp', csharp);
hljs.registerLanguage('cs', csharp);
hljs.registerLanguage('plaintext', plaintext);

const props = defineProps({
    content: {
        type: String,
        default: '',
    },
});

// Configure marked with syntax highlighting
const renderer = new marked.Renderer();

renderer.code = ({ text, lang }) => {
    const language = lang && hljs.getLanguage(lang) ? lang : 'plaintext';
    const highlighted = hljs.highlight(text, { language }).value;
    const label = lang ? `<span class="code-lang">${lang}</span>` : '';
    return `<pre class="code-block">${label}<code class="hljs language-${language}">${highlighted}</code></pre>`;
};

marked.setOptions({ renderer, breaks: true, gfm: true });

const html = computed(() => marked.parse(props.content ?? ''));
</script>

<template>
    <div class="markdown-body" v-html="html" />
</template>

<style>
.markdown-body {
    font-size: 0.875rem;
    line-height: 1.65;
    word-break: break-word;
}

.markdown-body p {
    margin: 0 0 0.75em;
}

.markdown-body p:last-child {
    margin-bottom: 0;
}

.markdown-body h1,
.markdown-body h2,
.markdown-body h3,
.markdown-body h4 {
    font-weight: 600;
    margin: 1em 0 0.4em;
    line-height: 1.3;
}

.markdown-body h1 { font-size: 1.15em; }
.markdown-body h2 { font-size: 1.05em; }
.markdown-body h3 { font-size: 0.95em; }

.markdown-body ul,
.markdown-body ol {
    margin: 0 0 0.75em 1.25em;
    padding: 0;
}

.markdown-body ul { list-style: disc; }
.markdown-body ol { list-style: decimal; }

.markdown-body li {
    margin-bottom: 0.2em;
}

.markdown-body code:not(pre code) {
    font-family: ui-monospace, 'Cascadia Code', monospace;
    font-size: 0.85em;
    padding: 0.15em 0.35em;
    border-radius: 4px;
    background: rgba(0, 0, 0, 0.07);
}

.markdown-body blockquote {
    border-left: 3px solid #d1d5db;
    padding-left: 0.75em;
    margin: 0 0 0.75em;
    color: #6b7280;
}

.markdown-body hr {
    border: none;
    border-top: 1px solid #e5e7eb;
    margin: 1em 0;
}

.markdown-body a {
    color: #1d4ed8;
    text-decoration: underline;
}

/* Code blocks */
.code-block {
    position: relative;
    margin: 0.6em 0;
    border-radius: 8px;
    overflow: hidden;
    background: #1e1e2e;
}

.code-block:last-child {
    margin-bottom: 0;
}

.code-lang {
    display: block;
    font-family: ui-monospace, 'Cascadia Code', monospace;
    font-size: 0.7em;
    color: #a6adc8;
    padding: 0.4em 1em 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.code-block code {
    display: block;
    padding: 0.6em 1em 0.8em;
    overflow-x: auto;
    font-family: ui-monospace, 'Cascadia Code', Menlo, monospace;
    font-size: 0.8em;
    line-height: 1.6;
    color: #cdd6f4;
}

/* highlight.js Catppuccin-inspired theme */
.hljs-keyword,
.hljs-selector-tag,
.hljs-built_in,
.hljs-type { color: #cba6f7; }

.hljs-string,
.hljs-template-string,
.hljs-attr { color: #a6e3a1; }

.hljs-number,
.hljs-literal { color: #fab387; }

.hljs-comment,
.hljs-quote { color: #6c7086; font-style: italic; }

.hljs-variable,
.hljs-title,
.hljs-title.class_,
.hljs-title.function_ { color: #89b4fa; }

.hljs-params { color: #cdd6f4; }

.hljs-operator,
.hljs-punctuation { color: #89dceb; }

.hljs-property { color: #f38ba8; }

.hljs-meta { color: #f9e2af; }
</style>
