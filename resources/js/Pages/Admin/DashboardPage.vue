<script setup>
import axios from "axios";
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import AuthenticatedLayout from "../../Layouts/AuthenticatedLayout.vue";

const summary = ref(null);
const loading = ref(true);
const errorMessage = ref("");
const upcomingDetail = ref(null);

const permissions = computed(() => summary.value?.permissions || {});

const todayCards = computed(() =>
    [
        {
            label: "預約總數",
            value: summary.value?.today?.reservations,
            tone: "text-slate-950",
        },
        {
            label: "待審核",
            value: summary.value?.today?.pending,
            tone: "text-amber-700",
            show: permissions.value.can_reserve || permissions.value.can_review_approvals,
        },
        {
            label: "已核准",
            value: summary.value?.today?.approved,
            tone: "text-emerald-700",
        },
        {
            label: "已取消",
            value: summary.value?.today?.cancelled,
            tone: "text-rose-700",
        },
    ].filter(
        (card) =>
            card.value !== null &&
            card.value !== undefined &&
            card.show !== false,
    ),
);

const systemCards = computed(() =>
    [
        {
            label: "空間數",
            value: summary.value?.system?.rooms,
            href: "/admin/rooms",
        },
        {
            label: "使用者數",
            value: summary.value?.system?.users,
            href: "/admin/users",
        },
        {
            label: "停用帳號",
            value: summary.value?.system?.inactive_accounts,
            href: "/admin/users",
            tone: "text-amber-700",
        },
        {
            label: "帳號待啟用",
            value: summary.value?.system?.pending_accounts,
            href: "/admin/users",
            tone: "text-amber-700",
        },
    ].filter((card) => card.value !== null && card.value !== undefined),
);

const taskItems = computed(() => {
    const tasks = summary.value?.tasks || {};

    return [
        {
            label: "待審核申請",
            value: tasks.pending_reservations,
            href: "/approvals",
            show: summary.value?.permissions?.can_review_approvals,
        },
        {
            label: "待處理付款",
            value: tasks.unpaid_orders,
            href: "/admin/payments",
            show: summary.value?.permissions?.can_view_revenue,
        },
        {
            label: "即將開始的預約",
            value: tasks.upcoming_reservations,
            href: "/reservations",
            show: summary.value?.permissions?.can_reserve,
        },
    ].filter(
        (item) =>
            item.value !== null &&
            item.value !== undefined &&
            item.show !== false,
    );
});

const canViewManagementStats = computed(
    () => summary.value?.permissions?.can_view_management_stats,
);

const canViewReservations = computed(
    () => summary.value?.permissions?.can_reserve,
);

const dashboardIntro = computed(() => {
    if (summary.value?.permissions?.can_manage_users) {
        return "查看系統設定、空間管理與權限維護相關狀態。";
    }

    if (summary.value?.permissions?.can_view_operations) {
        return "查看審核、空間管理、付款處理與預約營運狀態。";
    }

    return "查看自己的預約狀態與即將開始的預約。";
});

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

const slotRangeLabel = (startValue, endValue) => {
    if (!startValue || !endValue) return "-";

    const clean = (value) =>
        String(value)
            .trim()
            .replace(/(Z|[+-]\d{2}:?\d{2})$/i, "")
            .trim();
    const start = clean(startValue).replace(" ", "T");
    const end = clean(endValue).replace(" ", "T");
    const startParts = start.split("T");
    const endParts = end.split("T");
    const startTime = startParts[1] ? startParts[1].slice(0, 5) : "";
    const endTime = endParts[1] ? endParts[1].slice(0, 5) : "";

    if (!startTime || !endTime) return `${start} - ${end}`;

    return `${startTime} - ${endTime}`;
};

const slotRoomLabel = (slot) =>
    [slot?.room?.name, slot?.room?.building].filter(Boolean).join(" · ");

const openUpcomingDetail = (reservation) => {
    upcomingDetail.value = reservation;
};

const closeUpcomingDetail = () => {
    upcomingDetail.value = null;
};

const loadSummary = async () => {
    loading.value = true;
    errorMessage.value = "";

    try {
        const response = await axios.get("/dashboard/summary");
        summary.value = response.data;
    } catch (error) {
        console.error(
            "載入儀表板失敗：",
            error.response?.data || error.message,
        );
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
                <div
                    class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <p
                        class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500"
                    >
                        Dashboard
                    </p>
                    <h2 class="mt-3 text-3xl font-semibold text-slate-950">
                        儀表板
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ dashboardIntro }}
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
                    <div
                        v-if="systemCards.length"
                        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        <a
                            v-for="card in systemCards"
                            :key="card.label"
                            :href="card.href"
                            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            <p class="text-sm font-medium text-slate-500">
                                {{ card.label }}
                            </p>
                            <p
                                class="mt-2 text-3xl font-semibold"
                                :class="card.tone || 'text-slate-950'"
                            >
                                {{ card.value }}
                            </p>
                        </a>
                    </div>

                    <div
                        v-if="todayCards.length"
                        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        <div
                            v-for="card in todayCards"
                            :key="card.label"
                            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <p class="text-sm font-medium text-slate-500">
                                {{ card.label }}
                            </p>
                            <p
                                class="mt-2 text-3xl font-semibold"
                                :class="card.tone"
                            >
                                {{ card.value }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="taskItems.length"
                        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        <a
                            v-for="item in taskItems"
                            :key="item.label"
                            :href="item.href"
                            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            <p class="text-sm font-medium text-slate-500">
                                {{ item.label }}
                            </p>
                            <p class="mt-2 text-3xl font-semibold text-slate-950">
                                {{ item.value }}
                            </p>
                        </a>
                    </div>

                    <div
                        v-if="
                            summary.permissions.can_view_revenue ||
                            summary.permissions.can_reserve ||
                            canViewManagementStats
                        "
                        class="grid gap-6"
                        :class="
                            canViewManagementStats
                                ? 'xl:grid-cols-2'
                                : 'xl:grid-cols-1'
                        "
                    >
                        <div
                            v-if="summary.permissions.can_view_revenue"
                            class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <h3 class="text-xl font-semibold text-slate-950">
                                待付款
                            </h3>
                            <div class="mt-5">
                                <div
                                    v-if="
                                        summary.tasks?.unpaid_orders_list
                                            ?.length > 0
                                    "
                                    class="space-y-2"
                                >
                                    <a
                                        v-for="payment in summary.tasks
                                            .unpaid_orders_list"
                                        :key="payment.id"
                                        href="/admin/payments"
                                        class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 transition hover:bg-white"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm font-semibold text-slate-950"
                                            >
                                                {{
                                                    payment.room?.name ||
                                                    "未指定教室"
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-slate-500"
                                            >
                                                {{
                                                    formatDateTime(
                                                        payment.start_time,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <span
                                            class="shrink-0 text-sm font-semibold text-slate-950"
                                        >
                                            NT$
                                            {{ formatAmount(payment.amount) }}
                                        </span>
                                    </a>
                                </div>
                                <p
                                    v-else
                                    class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                                >
                                    目前沒有待付款項目。
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="summary.permissions.can_reserve"
                            class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <h3 class="text-xl font-semibold text-slate-950">
                                即將開始的預約
                            </h3>
                            <div class="mt-5">
                                <div
                                    v-if="
                                        summary.tasks
                                            ?.upcoming_reservations_list
                                            ?.length > 0
                                    "
                                    class="space-y-2"
                                >
                                    <div
                                        v-for="res in summary.tasks
                                            .upcoming_reservations_list"
                                        :key="res.id"
                                        class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm font-semibold text-slate-950"
                                            >
                                                {{
                                                    res.room?.name || "未知教室"
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-slate-500"
                                            >
                                                {{ res.date }} ·
                                                {{
                                                    formatDateTime(
                                                        res.start_time,
                                                    )
                                                }}
                                                -
                                                {{
                                                    formatDateTime(res.end_time)
                                                }}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="shrink-0 text-sm font-semibold text-slate-600 transition hover:text-slate-950"
                                            @click="openUpcomingDetail(res)"
                                        >
                                            詳情
                                        </button>
                                    </div>
                                </div>
                                <p
                                    v-else
                                    class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                                >
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
                                    <p
                                        class="text-sm font-medium text-slate-500"
                                    >
                                        {{ item.label }}
                                    </p>
                                    <p
                                        class="mt-2 text-xl font-semibold text-slate-950"
                                    >
                                        <template v-if="item.money">
                                            {{ item.prefix
                                            }}{{ formatAmount(item.value) }}
                                        </template>
                                        <template v-else>
                                            {{ item.prefix || ""
                                            }}{{ item.value
                                            }}{{ item.suffix || "" }}
                                        </template>
                                    </p>
                                    <p
                                        v-if="item.detail"
                                        class="mt-1 text-sm text-slate-500"
                                    >
                                        {{ item.detail }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="
                            summary.permissions.can_reserve ||
                            summary.permissions.can_view_revenue ||
                            summary.permissions.can_review_approvals ||
                            canViewManagementStats
                        "
                        class="grid gap-6"
                        :class="
                            canViewManagementStats
                                ? 'xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]'
                                : 'xl:grid-cols-1'
                        "
                    >
                        <div
                            v-if="
                                summary.permissions.can_reserve ||
                                summary.permissions.can_view_revenue ||
                                summary.permissions.can_review_approvals
                            "
                            class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <h3 class="text-xl font-semibold text-slate-950">
                                近期紀錄
                            </h3>

                            <div class="mt-5 space-y-6">
                                <div v-if="summary.permissions.can_reserve">
                                    <h4
                                        class="text-sm font-semibold text-slate-500"
                                    >
                                        最近 5 筆預約
                                    </h4>
                                    <div
                                        class="mt-3 divide-y divide-slate-200 rounded-2xl border border-slate-200"
                                    >
                                        <div
                                            v-for="reservation in summary.recent
                                                .reservations"
                                            :key="reservation.id"
                                            class="px-4 py-3"
                                        >
                                            <div
                                                class="flex items-start justify-between gap-4"
                                            >
                                                <div class="min-w-0">
                                                    <p
                                                        class="truncate text-sm font-semibold text-slate-950"
                                                    >
                                                        {{
                                                            reservation.room_name
                                                        }}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-xs text-slate-500"
                                                    >
                                                        {{
                                                            reservation.user_name ||
                                                            "-"
                                                        }}
                                                        ·
                                                        {{
                                                            formatDateTime(
                                                                reservation.start_time,
                                                            )
                                                        }}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-xs text-slate-500"
                                                    >
                                                        共
                                                        {{
                                                            reservation.slot_count ||
                                                            1
                                                        }}
                                                        個時段
                                                    </p>
                                                </div>
                                                <span
                                                    class="shrink-0 text-xs font-semibold text-slate-500"
                                                >
                                                    {{
                                                        reservationStatusLabel(
                                                            reservation.status,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                        <p
                                            v-if="
                                                summary.recent.reservations
                                                    .length === 0
                                            "
                                            class="px-4 py-8 text-center text-sm text-slate-500"
                                        >
                                            目前沒有預約紀錄。
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="summary.permissions.can_view_revenue"
                                >
                                    <h4
                                        class="text-sm font-semibold text-slate-500"
                                    >
                                        最近 5 筆付款
                                    </h4>
                                    <div
                                        class="mt-3 divide-y divide-slate-200 rounded-2xl border border-slate-200"
                                    >
                                        <div
                                            v-for="payment in summary.recent
                                                .payments"
                                            :key="payment.id"
                                            class="px-4 py-3"
                                        >
                                            <div
                                                class="flex items-start justify-between gap-4"
                                            >
                                                <div class="min-w-0">
                                                    <p
                                                        class="truncate text-sm font-semibold text-slate-950"
                                                    >
                                                        {{ payment.room_name }}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-xs text-slate-500"
                                                    >
                                                        {{
                                                            payment.user_name ||
                                                            "-"
                                                        }}
                                                        ·
                                                        {{
                                                            paymentStatusLabel(
                                                                payment.status,
                                                            )
                                                        }}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-xs text-slate-500"
                                                    >
                                                        共
                                                        {{
                                                            payment.slot_count ||
                                                            1
                                                        }}
                                                        個時段
                                                    </p>
                                                </div>
                                                <span
                                                    class="shrink-0 text-sm font-semibold text-slate-950"
                                                >
                                                    NT$
                                                    {{
                                                        formatAmount(
                                                            payment.amount,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                        <p
                                            v-if="
                                                summary.recent.payments
                                                    .length === 0
                                            "
                                            class="px-4 py-8 text-center text-sm text-slate-500"
                                        >
                                            目前沒有付款紀錄。
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="
                                        summary.permissions.can_review_approvals
                                    "
                                >
                                    <h4
                                        class="text-sm font-semibold text-slate-500"
                                    >
                                        最近 5 筆審核結果
                                    </h4>
                                    <div
                                        class="mt-3 divide-y divide-slate-200 rounded-2xl border border-slate-200"
                                    >
                                        <div
                                            v-for="approval in summary.recent
                                                .approvals"
                                            :key="approval.id"
                                            class="px-4 py-3"
                                        >
                                            <div
                                                class="flex items-start justify-between gap-4"
                                            >
                                                <div class="min-w-0">
                                                    <p
                                                        class="truncate text-sm font-semibold text-slate-950"
                                                    >
                                                        {{ approval.room_name }}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-xs text-slate-500"
                                                    >
                                                        {{
                                                            approval.user_name ||
                                                            "-"
                                                        }}
                                                        ·
                                                        {{
                                                            approval.approver_name ||
                                                            "-"
                                                        }}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-xs text-slate-500"
                                                    >
                                                        共
                                                        {{
                                                            approval.slot_count ||
                                                            1
                                                        }}
                                                        個時段
                                                    </p>
                                                </div>
                                                <span
                                                    class="shrink-0 text-xs font-semibold text-slate-500"
                                                >
                                                    {{
                                                        approvalDecisionLabel(
                                                            approval.decision,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                        <p
                                            v-if="
                                                summary.recent.approvals
                                                    .length === 0
                                            "
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
                                        <p
                                            class="truncate text-sm font-semibold text-slate-950"
                                        >
                                            {{ room.name }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{
                                                [room.type, room.building]
                                                    .filter(Boolean)
                                                    .join(" · ") || "-"
                                            }}
                                        </p>
                                    </div>
                                    <span
                                        class="shrink-0 text-sm font-semibold text-slate-950"
                                    >
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

                <teleport to="body">
                    <div
                        v-if="upcomingDetail"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-8"
                        @click.self="closeUpcomingDetail"
                    >
                        <div
                            class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-[2rem] border border-slate-200 bg-white p-6 shadow-xl"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p
                                        class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500"
                                    >
                                        Reservation Detail
                                    </p>
                                    <h3
                                        class="mt-3 text-2xl font-semibold text-slate-950"
                                    >
                                        預約詳細資料
                                    </h3>
                                </div>
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-lg font-semibold leading-none text-slate-600 transition hover:bg-slate-100"
                                    aria-label="關閉"
                                    @click="closeUpcomingDetail"
                                >
                                    ×
                                </button>
                            </div>

                            <div
                                class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"
                            >
                                <p class="text-lg font-semibold text-slate-950">
                                    {{
                                        upcomingDetail.room?.name || "未知教室"
                                    }}
                                </p>
                                <p class="mt-1">
                                    {{ upcomingDetail.room?.type || "-" }} ·
                                    {{ upcomingDetail.room?.building || "-" }}
                                </p>
                                <p class="mt-3">
                                    狀態：{{
                                        reservationStatusLabel(
                                            upcomingDetail.reservation_status,
                                        )
                                    }}
                                </p>
                                <p class="mt-1">
                                    共
                                    {{
                                        upcomingDetail.slots?.length ||
                                        upcomingDetail.slot_count ||
                                        1
                                    }}
                                    個時段
                                </p>
                            </div>

                            <div
                                class="mt-5 overflow-hidden rounded-2xl border border-slate-200"
                            >
                                <div
                                    class="grid grid-cols-[1fr_110px] gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600"
                                >
                                    <div>時段</div>
                                    <div>狀態</div>
                                </div>
                                <div class="divide-y divide-slate-200">
                                    <div
                                        v-for="slot in upcomingDetail.slots ||
                                        []"
                                        :key="slot.id"
                                        class="grid grid-cols-[1fr_110px] gap-4 px-4 py-3 text-sm"
                                    >
                                        <div class="text-slate-800">
                                            <p
                                                v-if="slotRoomLabel(slot)"
                                                class="font-semibold text-slate-950"
                                            >
                                                {{ slotRoomLabel(slot) }}
                                            </p>
                                            <p>
                                                {{
                                                    `${String(slot.date || "").replaceAll("-", "/")} ${slotRangeLabel(
                                                        slot.start_time,
                                                        slot.end_time,
                                                    )}`
                                                }}
                                            </p>
                                        </div>
                                        <div
                                            class="font-semibold text-slate-600"
                                        >
                                            {{
                                                reservationStatusLabel(
                                                    slot.reservation_status,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </teleport>
            </section>
        </AuthenticatedLayout>
    </div>
</template>
