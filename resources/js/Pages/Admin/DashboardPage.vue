<script setup>
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "../../Layouts/AuthenticatedLayout.vue";

const managementLinks = [
    {
        title: "使用紀錄與管理查詢",
        description: "查詢預約歷史，檢視月借用次數、空間排名與狀態分布。",
        href: "/admin/reports/reservations",
        roles: "管理員 / 行政人員",
    },
    {
        title: "收入統計",
        description: "依月份、空間與付款狀態統計已收款與待收款金額。",
        href: "/admin/reports/revenue",
        roles: "管理員 / 行政人員",
    },
    {
        title: "付款管理",
        description: "檢視付款清單並更新付款狀態。",
        href: "/admin/payments",
        roles: "管理員 / 行政人員",
    },
    {
        title: "審核管理",
        description: "處理待審核申請並查看審核歷史。",
        href: "/approvals",
        roles: "管理員 / 行政人員 / 教授",
    },
    {
        title: "空間管理",
        description: "維護教室、會議室、實驗室等可借用空間。",
        href: "/admin/rooms",
        roles: "管理員",
    },
    {
        title: "單位管理",
        description: "新增、修改與刪除所屬單位。",
        href: "/admin/departments",
        roles: "管理員",
    },
    {
        title: "使用者管理",
        description: "管理帳號、啟用狀態、所屬單位與角色。",
        href: "/admin/users",
        roles: "管理員",
    },
];
</script>

<template>
    <div>
        <Head title="儀表板" />

        <AuthenticatedLayout title="儀表板">
            <section class="space-y-6">
                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                        Dashboard
                    </p>
                    <h2 class="mt-3 text-3xl font-semibold text-slate-950">
                        管理功能清單
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        從這裡進入預約、付款、審核、空間、單位與使用者管理。
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <a
                        v-for="link in managementLinks"
                        :key="link.href"
                        :href="link.href"
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h3 class="text-lg font-semibold text-slate-950">
                                    {{ link.title }}
                                </h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">
                                    {{ link.description }}
                                </p>
                            </div>
                            <span class="shrink-0 text-xl font-semibold text-slate-400">
                                ›
                            </span>
                        </div>
                        <p class="mt-4 text-xs font-semibold text-slate-500">
                            {{ link.roles }}
                        </p>
                    </a>
                </div>
            </section>
        </AuthenticatedLayout>
    </div>
</template>
