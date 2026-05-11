<script setup>
import axios from 'axios';
import { ref } from 'vue';

defineProps({
    title: {
        type: String,
        default: 'Dashboard',
    },
});

const loggingOut = ref(false);

const logout = async () => {
    if (loggingOut.value) {
        return;
    }

    loggingOut.value = true;

    try {
        await axios.post('/api/logout');
        window.location.href = '/login';
    } finally {
        loggingOut.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">database project</p>
                    <h1 class="text-xl font-semibold">{{ title }}</h1>
                </div>
                <button
                    type="button"
                    class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="loggingOut"
                    @click="logout"
                >
                    {{ loggingOut ? '登出中...' : '登出' }}
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-10">
            <slot />
        </main>
    </div>
</template>