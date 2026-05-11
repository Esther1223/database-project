<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout.vue';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout title="儀表板">
        <section class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
            <h2 class="text-2xl font-semibold text-slate-950">登入成功</h2>
            <p class="mt-3 max-w-2xl text-slate-600">
                您已成功登入系統，現在可以開始使用各項功能。
            </p>
        </section>
    </AuthenticatedLayout>
</template>