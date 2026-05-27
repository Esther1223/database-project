<script setup>
import axios from "axios";
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import AuthenticatedLayout from "../../Layouts/AuthenticatedLayout.vue";

const summary = ref(null);
const loading = ref(true);
const errorMessage = ref("");

const todayCards = computed(() => [
    {
        label: "總預約數",
        value: summary.value?.today?.reservations || 0,
        tone: "text-slate-950",
    },
    {
        label: "待審核數",
        value: summary.value?.today?.pending || 0,
        tone: "text-amber-700",
    },
    {
        label: "已核准數",
        value: summary.value?.today?.approved || 0,
        tone: "text-emerald-700",
    },
    {
        label: "已取消數",
        value: summary.value?.today?.cancelled || 0,
        tone: "text-rose-700",
    },
]);

const taskItems = computed(() => {
    const tasks = summary.value?.tasks || {};

    return [
        {
            label: "待付款",
            value: tasks.unpaid_orders,
            href: summary.value?.permissions?.can_view_revenue
                ? "/admin/payments"
                : "/reservations",
        },
        {
            label: "即將開始的預約",
            value: tasks.upcoming_reservations,
            href: "/reservations",
        },
        {
            label: "帳號待啟用",
            value: tasks.inactive_accounts,
            href: "/admin/users",
        },
    ].filter((item) => item.value !== null && item.value !== undefined);
});

const canViewManagementStats = computed(
    () => summary.value?.permissions?.can_view_management_stats,
);

const monthItems = computed(() => {
    const month = summary.value?.month || {};

    return [
        {
            label: "本月借用次數",
            value: month.borrow_count || 0,
            suffix: "次",
        },
        {
            label: "本月收入",
            value: month.revenue,
            prefix: "NT$ ",
            money: true,
        },
        {
            label: "本月未收款金額",
            value: month.unpaid_amount,
            prefix: "NT$ ",
            money: true,
        },
        {
            label: "本月最常被借用空間",
            value: month.top_room?.name || "-",
            detail: month.top_room
                ? `${month.top_room.borrow_count} 次 · ${[
                      month.top_room.type,
                      month.top_room.building,
                  ]
                      .filter(Boolean)
                      .join(" · ")}`
                : "目前沒有資料",
        },
    ].filter((item) => item.value !== null && item.value !== undefined);
});

const formatAmount = (amount) =>
    new Intl.NumberFormat("zh-TW").format(amount || 0);

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

const reservationStatusLabel = (status) => {
    const labels = {
        pending: "待審核",
        success: "已核准",
        cancelled: "已取消",
        rejected: "已拒絕",
    };

    return labels[status] || status || "-";
};

const paymentStatusLabel = (status) => {
    const labels = {
        paid: "已付款",
        unpaid: "未付款",
    };

    return labels[status] || status || "-";
};

const approvalDecisionLabel = (decision) => {
    const labels = {
        approved: "已核准",
        rejected: "已拒絕",
    };

    return labels[decision] || decision || "-";
};

const loadSummary = async () => {
    loading.value = true;
    errorMessage.value = "";

    try {
        const response = await axios.get("/dashboard/summary");
        summary.value = response.data;
    } catch (error) {
        console.error("載入儀表板失敗：", error.response?.data || error.message);
        errorMessage.value =
            error.response?.data?.message || "儀表板資料載入失敗，請稍後再試。";
    } finally {
        loading.value = false;
    }
};

onMounted(loadSummary);
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
                        儀表板
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        快速掌握截至今日預約、待付款與即將開始的預約、本月統計與近期紀錄。
                    </p>
                </div>

                <p
                    v-if="errorMessage"
                    class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-semibold text-rose-700"
                >
                    {{ errorMessage }}
                </p>

                <div
                    v-if="loading"
                    class="rounded-2xl border border-slate-200 bg-white px-5 py-16 text-center text-sm font-medium text-slate-500 shadow-sm"
                >
                    載入儀表板中...
                </div>

                <template v-else>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div
                            v-for="card in todayCards"
                            :key="card.label"
                            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <p class="text-sm font-medium text-slate-500">
                                {{ card.label }}
                            </p>
                            <p class="mt-2 text-3xl font-semibold" :class="card.tone">
                                {{ card.value }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="grid gap-6"
                        :class="canViewManagementStats ? 'xl:grid-cols-2' : 'xl:grid-cols-1'"
                    >
                        <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="text-xl font-semibold text-slate-950">待付款</h3>
                            <div class="mt-5">
                                <div v-if="summary.tasks?.unpaid_orders_list?.length > 0" class="space-y-2">
                                    <a
                                        v-for="payment in summary.tasks.unpaid_orders_list"
                                        :key="payment.id"
                                        :href="`/payments/${payment.id}`"
                                        class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 transition hover:bg-white"
                                    >
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-950">
                                                {{ payment.room?.name || '未指定教室' }}
                                            </p>
                                            <p class="mt-1 text-xs text-slate-500">
                                                {{ formatDateTime(payment.start_time) }}
                                            </p>
                                        </div>
                                        <span class="shrink-0 text-sm font-semibold text-slate-950">
                                            NT$ {{ formatAmount(payment.amount) }}
                                        </span>
                                    </a>
                                </div>
                                <p v-else class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                    目前沒有待付款項目。
                                </p>
                            </div>
                        </div>

                        <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="text-xl font-semibold text-slate-950">即將開始的預約</h3>
                            <div class="mt-5">
                                <div v-if="summary.tasks?.upcoming_reservations_list?.length > 0" class="space-y-2">
                                    <div
                                        v-for="res in summary.tasks.upcoming_reservations_list"
                                        :key="res.id"
                                        class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
                                    >
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-950">
                                                {{ res.room?.name || '未知教室' }}
                                            </p>
                                            <p class="mt-1 text-xs text-slate-500">
                                                {{ res.date }} · {{ formatDateTime(res.start_time) }} - {{ formatDateTime(res.end_time) }}
                                            </p>
                                        </div>
                                        <a :href="`/reservations/${res.id}`" class="shrink-0 text-sm font-semibold text-slate-600">詳情</a>
                                    </div>
                                </div>
                                <p v-else class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                    目前沒有即將開始的預約。
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="canViewManagementStats"
                            class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <h3 class="text-xl font-semibold text-slate-950">
                                本月統計
                            </h3>
                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div
                                    v-for="item in monthItems"
                                    :key="item.label"
                                    class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                                >
                                    <p class="text-sm font-medium text-slate-500">
                                        {{ item.label }}
                                    </p>
                                    <p class="mt-2 text-xl font-semibold text-slate-950">
                                        <template v-if="item.money">
                                            {{ item.prefix }}{{ formatAmount(item.value) }}
                                        </template>
                                        <template v-else>
                                            {{ item.prefix || "" }}{{ item.value }}{{ item.suffix || "" }}
                                        </template>
                                    </p>
                                    <p v-if="item.detail" class="mt-1 text-sm text-slate-500">
                                        {{ item.detail }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="grid gap-6"
                        :class="
                            canViewManagementStats
                                ? 'xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]'
                                : 'xl:grid-cols-1'
                        "
                    >
                        <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="text-xl font-semibold text-slate-950">
                                近期紀錄
                            </h3>

                            <div class="mt-5 space-y-6">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-500">
                                        最近 5 筆預約
                                    </h4>
                                    <div class="mt-3 divide-y divide-slate-200 rounded-2xl border border-slate-200">
                                        <div
                                            v-for="reservation in summary.recent.reservations"
                                            :key="reservation.id"
                                            class="px-4 py-3"
                                        >
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-semibold text-slate-950">
                                                        {{ reservation.room_name }}
                                                    </p>
                                                    <p class="mt-1 text-xs text-slate-500">
                                                        {{ reservation.user_name || "-" }} · {{ formatDateTime(reservation.start_time) }}
                                                    </p>
                                                    <p class="mt-1 text-xs text-slate-500">
                                                        共 {{ reservation.slot_count || 1 }} 個時段
                                                    </p>
                                                </div>
                                                <span class="shrink-0 text-xs font-semibold text-slate-500">
                                                    {{ reservationStatusLabel(reservation.status) }}
                                                </span>
                                            </div>
                                        </div>
                                        <p
                                            v-if="summary.recent.reservations.length === 0"
                                            class="px-4 py-8 text-center text-sm text-slate-500"
                                        >
                                            目前沒有預約紀錄。
                                        </p>
                                    </div>
                                </div>

                                <div v-if="summary.permissions.can_view_revenue">
                                    <h4 class="text-sm font-semibold text-slate-500">
                                        最近 5 筆付款
                                    </h4>
                                    <div class="mt-3 divide-y divide-slate-200 rounded-2xl border border-slate-200">
                                        <div
                                            v-for="payment in summary.recent.payments"
                                            :key="payment.id"
                                            class="px-4 py-3"
                                        >
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-semibold text-slate-950">
                                                        {{ payment.room_name }}
                                                    </p>
                                                    <p class="mt-1 text-xs text-slate-500">
                                                        {{ payment.user_name || "-" }} · {{ paymentStatusLabel(payment.status) }}
                                                    </p>
                                                    <p class="mt-1 text-xs text-slate-500">
                                                        共 {{ payment.slot_count || 1 }} 個時段
                                                    </p>
                                                </div>
                                                <span class="shrink-0 text-sm font-semibold text-slate-950">
                                                    NT$ {{ formatAmount(payment.amount) }}
                                                </span>
                                            </div>
                                        </div>
                                        <p
                                            v-if="summary.recent.payments.length === 0"
                                            class="px-4 py-8 text-center text-sm text-slate-500"
                                        >
                                            目前沒有付款紀錄。
                                        </p>
                                    </div>
                                </div>

                                <div v-if="summary.permissions.can_review_approvals">
                                    <h4 class="text-sm font-semibold text-slate-500">
                                        最近 5 筆審核結果
                                    </h4>
                                    <div class="mt-3 divide-y divide-slate-200 rounded-2xl border border-slate-200">
                                        <div
                                            v-for="approval in summary.recent.approvals"
                                            :key="approval.id"
                                            class="px-4 py-3"
                                        >
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-semibold text-slate-950">
                                                        {{ approval.room_name }}
                                                    </p>
                                                    <p class="mt-1 text-xs text-slate-500">
                                                        {{ approval.user_name || "-" }} · {{ approval.approver_name || "-" }}
                                                    </p>
                                                    <p class="mt-1 text-xs text-slate-500">
                                                        共 {{ approval.slot_count || 1 }} 個時段
                                                    </p>
                                                </div>
                                                <span class="shrink-0 text-xs font-semibold text-slate-500">
                                                    {{ approvalDecisionLabel(approval.decision) }}
                                                </span>
                                            </div>
                                        </div>
                                        <p
                                            v-if="summary.recent.approvals.length === 0"
                                            class="px-4 py-8 text-center text-sm text-slate-500"
                                        >
                                            目前沒有審核紀錄。
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="canViewManagementStats"
                            class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <h3 class="text-xl font-semibold text-slate-950">
                                空間使用排行
                            </h3>
                            <div class="mt-5 space-y-4">
                                <div
                                    v-for="room in summary.room_rankings"
                                    :key="room.id || room.name"
                                    class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-950">
                                            {{ room.name }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ [room.type, room.building].filter(Boolean).join(" · ") || "-" }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 text-sm font-semibold text-slate-950">
                                        {{ room.borrow_count }} 次
                                    </span>
                                </div>
                                <p
                                    v-if="summary.room_rankings.length === 0"
                                    class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                                >
                                    目前沒有排行資料。
                                </p>
                            </div>
                        </div>
                    </div>
                </template>
            </section>
        </AuthenticatedLayout>
    </div>
</template>
