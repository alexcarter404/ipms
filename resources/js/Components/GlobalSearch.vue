<script setup>
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const input = ref(null);
const query = ref('');
const groups = ref([]);
const open = ref(false);
const loading = ref(false);
const activeIndex = ref(-1);

let timeout = null;
let lastRequest = 0;

const flat = computed(() =>
    groups.value.flatMap((group) => group.items.map((item) => ({ ...item, type: group.type })))
);

const search = (value) => {
    clearTimeout(timeout);

    if (value.trim().length < 2) {
        groups.value = [];
        open.value = Boolean(value.trim().length);
        loading.value = false;
        return;
    }

    loading.value = true;
    open.value = true;

    timeout = setTimeout(async () => {
        const requestId = ++lastRequest;
        try {
            const { data } = await window.axios.get(route('search'), {
                params: { q: value },
            });
            // Ignore responses that arrive out of order
            if (requestId !== lastRequest) return;
            groups.value = data.groups;
            activeIndex.value = data.groups.length ? 0 : -1;
        } finally {
            if (requestId === lastRequest) loading.value = false;
        }
    }, 250);
};

const onInput = (event) => {
    query.value = event.target.value;
    search(query.value);
};

const go = (item) => {
    if (!item) return;
    close();
    router.visit(item.url);
};

const close = () => {
    open.value = false;
    activeIndex.value = -1;
    query.value = '';
    groups.value = [];
    input.value?.blur();
};

const move = (delta) => {
    if (!flat.value.length) return;
    activeIndex.value = (activeIndex.value + delta + flat.value.length) % flat.value.length;
};

const onKeydown = (event) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        move(1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(-1);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        go(flat.value[activeIndex.value] ?? flat.value[0]);
    } else if (event.key === 'Escape') {
        close();
    }
};

// Ctrl/Cmd+K focuses the search from anywhere
const shortcut = (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        input.value?.focus();
    }
};

onMounted(() => document.addEventListener('keydown', shortcut));
onBeforeUnmount(() => {
    document.removeEventListener('keydown', shortcut);
    clearTimeout(timeout);
});

// index of an item within the flattened list, for highlight tracking
const flatIndexOf = (groupIdx, itemIdx) =>
    groups.value.slice(0, groupIdx).reduce((n, g) => n + g.items.length, 0) + itemIdx;

/** Split a label into parts so the matched substring can be bolded. */
const highlight = (text) => {
    const q = query.value.trim();
    if (!q) return [{ text, match: false }];
    const idx = text.toLowerCase().indexOf(q.toLowerCase());
    if (idx === -1) return [{ text, match: false }];
    return [
        { text: text.slice(0, idx), match: false },
        { text: text.slice(idx, idx + q.length), match: true },
        { text: text.slice(idx + q.length), match: false },
    ].filter((part) => part.text !== '');
};
</script>

<template>
    <div class="relative w-full max-w-xs" @focusout="(e) => !e.currentTarget.contains(e.relatedTarget) && (open = false)">
        <div class="relative">
            <svg
                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
            >
                <path
                    fill-rule="evenodd"
                    d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z"
                    clip-rule="evenodd"
                />
            </svg>
            <input
                ref="input"
                :value="query"
                type="search"
                placeholder="Search everything…  (Ctrl+K)"
                autocomplete="off"
                class="w-full rounded-md border-gray-300 py-1.5 pl-9 pr-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                aria-label="Global search"
                @input="onInput"
                @keydown="onKeydown"
                @focus="query.trim().length >= 2 && (open = true)"
            />
        </div>

        <!-- Results dropdown -->
        <div
            v-if="open"
            data-testid="global-search-results"
            class="absolute left-0 right-0 z-50 mt-1 max-h-[28rem] overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg sm:w-[28rem]"
        >
            <p v-if="loading" class="px-4 py-3 text-sm text-gray-500">Searching…</p>

            <p
                v-else-if="query.trim().length < 2"
                class="px-4 py-3 text-sm text-gray-500"
            >
                Type at least 2 characters…
            </p>

            <p v-else-if="!groups.length" class="px-4 py-3 text-sm text-gray-500">
                No results for “{{ query }}”.
            </p>

            <template v-else>
                <div v-for="(group, gi) in groups" :key="group.type">
                    <p class="px-4 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        {{ group.type }}
                    </p>
                    <button
                        v-for="(item, ii) in group.items"
                        :key="item.url + item.label"
                        type="button"
                        class="block w-full px-4 py-2 text-left"
                        :class="
                            flatIndexOf(gi, ii) === activeIndex
                                ? 'bg-indigo-50'
                                : 'hover:bg-gray-50'
                        "
                        @mouseenter="activeIndex = flatIndexOf(gi, ii)"
                        @mousedown.prevent="go(item)"
                    >
                        <span class="block truncate text-sm text-gray-800">
                            <template v-for="(part, pi) in highlight(item.label)" :key="pi">
                                <strong v-if="part.match" class="font-semibold text-indigo-700">{{ part.text }}</strong>
                                <template v-else>{{ part.text }}</template>
                            </template>
                        </span>
                        <span v-if="item.sublabel" class="block truncate text-xs text-gray-500">
                            {{ item.sublabel }}
                        </span>
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
