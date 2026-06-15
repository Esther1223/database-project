<script setup>
import { RouterLink } from 'vue-router';
import Head from '../../support/HeadTitle.vue';
</script>

<template>
    <div class="min-h-screen bg-slate-50 px-6 py-16 text-slate-900">
        <Head title="沒有權限" />

        <main class="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">Unauthorized</p>
            <h1 class="mt-3 text-3xl font-semibold">沒有權限存取此頁面</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">
                目前登入的帳號沒有這個功能需要的角色。可以回到儀表板，或切換成有權限的帳號。
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                <RouterLink
                    to="/dashboard"
                    class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
                >
                    回到儀表板
                </RouterLink>
                <RouterLink
                    to="/login"
                    class="rounded-2xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                >
                    切換帳號
                </RouterLink>
            </div>
        </main>
    </div>
</template>
