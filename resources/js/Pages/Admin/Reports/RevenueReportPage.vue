<script setup>
import axios from "axios";
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, reactive, ref } from "vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    rooms: {
        type: Array,
        default: () => [],
    },
    paymentStatuses: {
        type: Array,
        default: () => [],
    },
});

const filters = reactive({
    start_date: "",
    end_date: "",
    room_id: "",
    payment_status: "",
});

const report = ref(null);
const loading = ref(false);
const errorMessage = ref("");

const maxMonthlyAmount = computed(() =>
    Math.max(...(report.value?.monthly || []).map((month) => month.amount), 1),
);

const params = () =>
    Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== ""),
    );

const formatAmount = (amount) =>
    new Intl.NumberFormat("zh-TW").format(amount || 0);

const loadData = async () => {
    loading.value = true;
    errorMessage.value = "";

    try {
        const response = await axios.get("/admin/reports/revenue/monthly", {
            params: params(),
        });

        report.value = response.data;
        filters.start_date = report.value.filters?.start_date || "";
        filters.end_date = report.value.filters?.end_date || "";
    } catch (error) {
        console.error("載入收入報表失敗：", error.response?.data || error.message);
        errorMessage.value =
            error.response?.data?.message || "收入報表載入失敗，請稍後再試。";
    } finally {
        loading.value = false;
    }
};

const resetFilters = () => {
    filters.start_date = "";
    filters.end_date = "";
    filters.room_id = "";
    filters.payment_status = "";
    loadData();
};

const paymentStatusLabel = (status) =>
    props.paymentStatuses.find((item) => item.value === status)?.label ||
    status ||
    "-";

onMounted(loadData);
</script>

<template>
    <div>
        <Head title="收入統計" />

        <AuthenticatedLayout title="收入統計">
            <section class="space-y-6">
                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                                Revenue Report
                            </p>
                            <h2 class="mt-3 text-3xl font-semibold text-slate-950">
                                收入統計
                            </h2>
                            <p class="mt-2 text-sm text-slate-600">
                                依月份、空間與付款狀態統計收款與待收金額。
                            </p>
                        </div>

                        <a
                            href="/admin/reports/reservations"
                            class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                        >
                            使用紀錄
                        </a>
                    </div>

                    <form class="mt-6 grid gap-4 lg:grid-cols-5" @submit.prevent="loadData">
                        <label class="space-y-2 text-sm font-semibold text-slate-700">
                            起始日期
                            <input
                                v-model="filters.start_date"
                                type="date"
                                class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm font-medium text-slate-800 outline-none transition focus:border-slate-500"
                            />
                        </label>
                        <label class="space-y-2 text-sm font-semibold text-slate-700">
                            結束日期
                            <input
                                v-model="filters.end_date"
                                type="date"
                                class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm font-medium text-slate-800 outline-none transition focus:border-slate-500"
                            />
                        </label>
                        <label class="space-y-2 text-sm font-semibold text-slate-700">
                            空間
                            <select
                                v-model="filters.room_id"
                                class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm font-medium text-slate-800 outline-none transition focus:border-slate-500"
                            >
                                <option value="">全部空間</option>
                                <option v-for="room in rooms" :key="room.id" :value="room.id">
                                    {{ room.name }} · {{ room.building }}
                                </option>
                            </select>
                        </label>
                        <label class="space-y-2 text-sm font-semibold text-slate-700">
                            付款狀態
                            <select
                                v-model="filters.payment_status"
                                class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm font-medium text-slate-800 outline-none transition focus:border-slate-500"
                            >
                                <option value="">全部狀態</option>
                                <option v-for="status in paymentStatuses" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </option>
                            </select>
                        </label>
                        <div class="flex items-end gap-2">
                            <button
                                type="submit"
                                class="flex-1 rounded-2xl border border-slate-900 bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="loading"
                            >
                                {{ loading ? "查詢中..." : "查詢" }}
                            </button>
                            <button
                                type="button"
                                class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                @click="resetFilters"
                            >
                                重設
                            </button>
                        </div>
                    </form>
                </div>

                <p
                    v-if="errorMessage"
                    class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-semibold text-rose-700"
                >
                    {{ errorMessage }}
                </p>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">總金額</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-950">
                            NT$ {{ formatAmount(report?.summary?.total_amount) }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">已收款</p>
                        <p class="mt-2 text-3xl font-semibold text-emerald-700">
                            NT$ {{ formatAmount(report?.summary?.paid_amount) }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">待收款</p>
                        <p class="mt-2 text-3xl font-semibold text-amber-700">
                            NT$ {{ formatAmount(report?.summary?.unpaid_amount) }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">付款筆數</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-950">
                            {{ report?.summary?.payment_count || 0 }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-xl font-semibold text-slate-950">月收入統計</h3>
                        <div class="mt-6 space-y-4">
                            <div v-for="month in report?.monthly || []" :key="month.key" class="grid grid-cols-[72px_minmax(0,1fr)_120px] items-center gap-3">
                                <span class="text-sm font-medium text-slate-600">{{ month.label }}</span>
                                <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        class="h-full rounded-full bg-emerald-700"
                                        :style="{ width: `${(month.amount / maxMonthlyAmount) * 100}%` }"
                                    ></div>
                                </div>
                                <span class="text-right text-sm font-semibold text-slate-900">
                                    NT$ {{ formatAmount(month.amount) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-xl font-semibold text-slate-950">空間收入排名</h3>
                        <div class="mt-5 space-y-4">
                            <div v-for="room in report?.room_totals || []" :key="room.room_id" class="flex items-center justify-between gap-4">
                                <span class="min-w-0 truncate text-sm font-medium text-slate-700">{{ room.room_name }}</span>
                                <span class="text-sm font-semibold text-slate-950">NT$ {{ formatAmount(room.amount) }}</span>
                            </div>
                            <p v-if="!report?.room_totals?.length" class="text-sm text-slate-500">
                                目前沒有統計資料。
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-xl font-semibold text-slate-950">付款狀態統計</h3>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div
                            v-for="status in report?.status_totals || []"
                            :key="status.status"
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-5"
                        >
                            <p class="text-sm font-medium text-slate-500">
                                {{ paymentStatusLabel(status.status) }}
                            </p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">
                                NT$ {{ formatAmount(status.amount) }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ status.count }} 筆
                            </p>
                        </div>
                        <p v-if="!report?.status_totals?.length" class="text-sm text-slate-500">
                            目前沒有付款資料。
                        </p>
                    </div>
                </div>
            </section>
        </AuthenticatedLayout>
    </div>
</template>
