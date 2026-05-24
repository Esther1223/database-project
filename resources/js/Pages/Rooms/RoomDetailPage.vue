<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';

defineProps({
    room: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="room.name" />

    <AuthenticatedLayout :title="room.name">
        <section class="space-y-6">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">Room Detail</p>
                        <h2 class="mt-3 text-3xl font-semibold text-slate-950">{{ room.name }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ room.type }} · {{ room.building }}</p>
                    </div>

                    <Link href="/rooms" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                        返回列表
                    </Link>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">容量</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ room.capacity }} 人</p>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">費率</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">NT$ {{ room.rate }} / 小時</p>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">預約規則</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ room.need_approval ? '需審核' : '直接預約' }}</p>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">空間編號</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">#{{ room.id }}</p>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">設備資訊</p>
                    <p class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-700">{{ room.information || '尚未提供設備資訊' }}</p>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">基本資料</p>

                    <dl class="mt-4 space-y-4 text-sm">
                        <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                            <dt class="text-slate-500">空間類型</dt>
                            <dd class="font-medium text-slate-900">{{ room.type }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                            <dt class="text-slate-500">所在建築</dt>
                            <dd class="font-medium text-slate-900">{{ room.building }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                            <dt class="text-slate-500">費率</dt>
                            <dd class="font-medium text-slate-900">NT$ {{ room.rate }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500">審核狀態</dt>
                            <dd class="font-medium text-slate-900">{{ room.need_approval ? '需要' : '不需要' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>