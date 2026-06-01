<script setup>
import axios from "axios";
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, reactive, ref } from "vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";
import ReservationStatusBadge from "../../../Components/Reservation/ReservationStatusBadge.vue";

const props = defineProps({
    rooms: {
        type: Array,
        default: () => [],
    },
    statuses: {
        type: Array,
        default: () => [],
    },
});

const filters = reactive({
    start_date: "",
    end_date: "",
    room_id: "",
    status: "",
});

const report = ref(null);
const histories = ref([]);
const loading = ref(false);
const errorMessage = ref("");

const maxMonthlyCount = computed(() =>
    Math.max(...(report.value?.monthly || []).map((month) => month.count), 1),
);

const params = () =>
    Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== ""),
    );

const formatDateTime = (value) => {
    if (!value) return "-";

    const raw = String(value).trim();
    const normalized = raw.replace(" ", "T");
    const hasTimezone = /[zZ]|[+-]\d{2}:?\d{2}$/.test(normalized);

    if (hasTimezone) {
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) return raw;

        return date
            .toLocaleString("sv-SE", { timeZone: "Asia/Taipei", hour12: false })
            .slice(0, 16)
            .replaceAll("-", "/");
    }

    const parts = normalized.split("T");
    if (parts.length === 2) {
        const datePart = parts[0].replaceAll("-", "/");
        const timePart = parts[1].slice(0, 5);
        return `${datePart} ${timePart}`;
    }

    return raw;
};

const loadData = async () => {
    loading.value = true;
    errorMessage.value = "";

    try {
        const query = params();
        const [reportResponse, historyResponse] = await Promise.all([
            axios.get("/admin/reports/reservations/monthly", { params: query }),
            axios.get("/admin/reservations/history", { params: query }),
        ]);

        report.value = reportResponse.data;
        histories.value = historyResponse.data.data || [];

        filters.start_date = report.value.filters?.start_date || "";
        filters.end_date = report.value.filters?.end_date || "";
    } catch (error) {
        console.error("載入預約報表失敗：", error.response?.data || error.message);
        errorMessage.value =
            error.response?.data?.message || "預約報表載入失敗，請稍後再試。";
    } finally {
        loading.value = false;
    }
};

const resetFilters = () => {
    filters.start_date = "";
    filters.end_date = "";
    filters.room_id = "";
    filters.status = "";
    loadData();
};

const statusLabel = (status) =>
    props.statuses.find((item) => item.value === status)?.label || status || "-";

onMounted(loadData);
</script>

<template>
    <div>
        <Head title="預約使用報表" />

        <AuthenticatedLayout title="預約使用報表">
            <section class="space-y-6">
                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                                Reservation Report
                            </p>
                            <h2 class="mt-3 text-3xl font-semibold text-slate-950">
                                使用紀錄與管理查詢
                            </h2>
                            <p class="mt-2 text-sm text-slate-600">
                                依月份、空間與狀態統計借用次數，並查詢歷史預約紀錄。
                            </p>
                        </div>

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
                            狀態
                            <select
                                v-model="filters.status"
                                class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm font-medium text-slate-800 outline-none transition focus:border-slate-500"
                            >
                                <option value="">全部狀態</option>
                                <option v-for="status in statuses" :key="status.value" :value="status.value">
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
                        <p class="text-sm font-medium text-slate-500">總借用次數</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-950">
                            {{ report?.summary?.total || 0 }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">已核准</p>
                        <p class="mt-2 text-3xl font-semibold text-emerald-700">
                            {{ report?.summary?.success || 0 }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">待審核</p>
                        <p class="mt-2 text-3xl font-semibold text-amber-700">
                            {{ report?.summary?.pending || 0 }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">取消 / 拒絕</p>
                        <p class="mt-2 text-3xl font-semibold text-rose-700">
                            {{ (report?.summary?.cancelled || 0) + (report?.summary?.rejected || 0) }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-xl font-semibold text-slate-950">月借用次數</h3>
                        <div class="mt-6 space-y-4">
                            <div v-for="month in report?.monthly || []" :key="month.key" class="grid grid-cols-[72px_minmax(0,1fr)_48px] items-center gap-3">
                                <span class="text-sm font-medium text-slate-600">{{ month.label }}</span>
                                <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        class="h-full rounded-full bg-slate-900"
                                        :style="{ width: `${(month.count / maxMonthlyCount) * 100}%` }"
                                    ></div>
                                </div>
                                <span class="text-right text-sm font-semibold text-slate-900">{{ month.count }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-xl font-semibold text-slate-950">熱門空間</h3>
                        <div class="mt-5 space-y-4">
                            <div v-for="room in report?.room_totals || []" :key="room.room_id" class="flex items-center justify-between gap-4">
                                <span class="min-w-0 truncate text-sm font-medium text-slate-700">{{ room.room_name }}</span>
                                <span class="text-sm font-semibold text-slate-950">{{ room.count }} 次</span>
                            </div>
                            <p v-if="!report?.room_totals?.length" class="text-sm text-slate-500">
                                目前沒有統計資料。
                            </p>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                        <h3 class="text-lg font-semibold text-slate-950">歷史預約紀錄</h3>
                    </div>

                    <div class="hidden gap-4 border-b border-slate-200 bg-slate-50 px-6 py-4 text-sm font-semibold text-slate-600 lg:grid lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)_minmax(0,0.9fr)_120px]">
                        <div>空間 / 申請者</div>
                        <div>時段</div>
                        <div>付款</div>
                        <div>狀態</div>
                    </div>

                    <div v-if="loading" class="px-6 py-16 text-center text-sm font-medium text-slate-500">
                        載入紀錄中...
                    </div>
                    <div v-else-if="histories.length === 0" class="px-6 py-16 text-center text-sm font-medium text-slate-500">
                        目前沒有符合條件的紀錄。
                    </div>
                    <div v-else class="divide-y divide-slate-200">
                        <div
                            v-for="reservation in histories"
                            :key="reservation.id"
                            class="grid grid-cols-1 gap-4 px-6 py-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)_minmax(0,0.9fr)_120px] lg:items-center"
                        >
                            <div>
                                <p class="text-lg font-semibold text-slate-950">
                                    {{ reservation.room?.name || "未知空間" }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ reservation.room?.type || "-" }} · {{ reservation.room?.building || "-" }}
                                </p>
                                <p class="mt-2 text-sm text-slate-600">
                                    申請者：{{ reservation.user?.name || reservation.user?.email || "-" }}
                                </p>
                            </div>
                            <div class="text-sm text-slate-700">
                                <p>{{ formatDateTime(reservation.start_time) }}</p>
                                <p class="mt-1 text-slate-500">至 {{ formatDateTime(reservation.end_time) }}</p>
                            </div>
                            <div class="text-sm text-slate-700">
                                <p>NT$ {{ reservation.payment?.amount ?? 0 }}</p>
                                <p class="mt-1 text-slate-500">
                                    {{ reservation.payment?.payment_status === "paid" ? "已付款" : reservation.payment ? "未付款" : "免付款" }}
                                </p>
                            </div>
                            <div>
                                <ReservationStatusBadge :status="reservation.reservation_status" />
                                <p class="mt-2 text-xs text-slate-500">
                                    {{ statusLabel(reservation.reservation_status) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </AuthenticatedLayout>
    </div>
</template>
