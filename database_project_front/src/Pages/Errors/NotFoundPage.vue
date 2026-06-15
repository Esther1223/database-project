<script setup>
import { RouterLink } from 'vue-router';
import Head from '../../support/HeadTitle.vue';
</script>

<template>
    <div class="min-h-screen bg-slate-50 px-6 py-16 text-slate-900">
        <Head title="找不到頁面" />

        <main class="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">404</p>
            <h1 class="mt-3 text-3xl font-semibold">找不到這個頁面</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">
                網址可能已經改變，或這個功能尚未開放。
            </p>
            <RouterLink
                to="/dashboard"
                class="mt-6 inline-flex rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
            >
                回到儀表板
            </RouterLink>
        </main>
    </div>
</template>
