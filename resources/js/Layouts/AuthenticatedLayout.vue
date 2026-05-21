<script setup>
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

defineProps({
    title: {
        type: String,
        default: 'Dashboard',
    },
});

const currentUser = ref(null);
const loggingOut = ref(false);

const isAdmin = computed(() => currentUser.value?.roles?.some((role) => role.role_type === '管理員'));

const loadCurrentUser = async () => {
    try {
        const response = await axios.get('/api/user');
        currentUser.value = response.data;
    } catch {
        currentUser.value = null;
    }
};

onMounted(() => {
    loadCurrentUser();
});

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
            <div class="mx-auto flex max-w-6xl flex-col gap-4 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">{{ title }}</p>
                    <h1 class="text-xl font-semibold">校園教室預約系統</h1>
                    <p v-if="currentUser" class="mt-1 text-sm text-slate-500">
                        {{ currentUser.name }} · {{ currentUser.email }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a
                        href="/dashboard"
                        class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                    >
                        儀表板
                    </a>
                    <a
                        v-if="isAdmin"
                        href="/admin/users"
                        class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                    >
                        使用者管理
                    </a>
                    <button
                        type="button"
                        class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="loggingOut"
                        @click="logout"
                    >
                        {{ loggingOut ? '登出中...' : '登出' }}
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-10">
            <slot />
        </main>
    </div>
</template>