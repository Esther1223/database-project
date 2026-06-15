<script setup>
import axios from "axios";
import { ref, onMounted } from "vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";
import ApprovalActionButtons from "../../../Components/Approval/ApprovalActionButtons.vue";
import Head from "../../../support/HeadTitle.vue";

const approvals = ref([]);
const histories = ref([]);
const activeTab = ref("pending");
const loading = ref(false);
const message = ref("");
const errorMessage = ref("");
const detailItem = ref(null);
const detailMode = ref("pending");

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

const decisionLabel = (decision) => {
    const labels = {
        approved: "已核准",
        rejected: "已拒絕",
    };

    return labels[decision] || decision || "-";
};

const decisionClass = (decision) => {
    return decision === "approved"
        ? "bg-emerald-50 text-emerald-700 ring-emerald-200"
        : "bg-rose-50 text-rose-700 ring-rose-200";
};

const statusLabel = (status) => {
    const labels = {
        pending: "待審核",
        success: "已核准",
        cancelled: "已取消",
        rejected: "已拒絕",
    };

    return labels[status] || status || "-";
};

const slotRangeLabel = (slot) => {
    const start = formatDateTime(slot.start_time);
    const end = formatDateTime(slot.end_time);

    return `${start} 至 ${end}`;
};

const slotRoomLabel = (slot) =>
    [slot?.room?.name, slot?.room?.building].filter(Boolean).join(" · ");

const reservationRoomsLabel = (item) =>
    [
        ...new Set(
            (item?.slots || []).map((slot) => slot?.room?.name).filter(Boolean),
        ),
    ].join("、") ||
    item?.room?.name ||
    item?.room?.room_name ||
    item?.reservation?.room?.name ||
    item?.reservation?.room?.room_name ||
    "未知空間";

const openDetail = (item, mode) => {
    detailItem.value = item;
    detailMode.value = mode;
};

const closeDetail = () => {
    detailItem.value = null;
};

const loadPending = async () => {
    loading.value = true;
    errorMessage.value = "";

    try {
        const res = await axios.get("/approvals/pending");
        approvals.value = res.data.data || [];
    } catch (e) {
        console.error("載入待審清單失敗：", e.response?.data || e.message);
        errorMessage.value = e.response?.data?.message || "待審清單載入失敗";
    } finally {
        loading.value = false;
    }
};

const loadHistory = async () => {
    loading.value = true;
    errorMessage.value = "";

    try {
        const res = await axios.get("/approvals/history");
        histories.value = res.data.data || [];
    } catch (e) {
        console.error("載入審核歷史失敗：", e.response?.data || e.message);
        errorMessage.value = e.response?.data?.message || "審核歷史載入失敗";
    } finally {
        loading.value = false;
    }
};

const switchTab = (tab) => {
    activeTab.value = tab;
    message.value = "";
    errorMessage.value = "";

    if (tab === "history") {
        loadHistory();
        return;
    }

    loadPending();
};

const handleUpdated = (updatedReservation, successMessage) => {
    message.value = successMessage || "審核狀態已更新";
    errorMessage.value = "";
    loadPending();
    loadHistory();
};

const handleFailed = (failureMessage) => {
    message.value = "";
    errorMessage.value = failureMessage || "審核操作失敗";
};

onMounted(loadPending);
</script>

<template>
    <div>
        <Head title="審核管理" />

        <AuthenticatedLayout title="審核管理">
            <section class="space-y-6">
                <div
                    class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div>
                            <p
                                class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500"
                            >
                                Approvals
                            </p>
                            <h2
                                class="mt-3 text-3xl font-semibold text-slate-950"
                            >
                                審核管理
                            </h2>
                            <p class="mt-2 text-sm text-slate-600">
                                行政可以在此查看待審申請，也能追蹤已核准或拒絕的審核紀錄。
                            </p>
                        </div>
                        <div
                            class="flex rounded-2xl border border-slate-200 bg-slate-50 p-1"
                        >
                            <button
                                type="button"
                                class="rounded-xl px-4 py-2 text-sm font-semibold transition"
                                :class="
                                    activeTab === 'pending'
                                        ? 'bg-white text-slate-950 shadow-sm'
                                        : 'text-slate-500 hover:text-slate-800'
                                "
                                @click="switchTab('pending')"
                            >
                                待審核
                            </button>
                            <button
                                type="button"
                                class="rounded-xl px-4 py-2 text-sm font-semibold transition"
                                :class="
                                    activeTab === 'history'
                                        ? 'bg-white text-slate-950 shadow-sm'
                                        : 'text-slate-500 hover:text-slate-800'
                                "
                                @click="switchTab('history')"
                            >
                                審核歷史
                            </button>
                        </div>
                    </div>
                </div>

                <p
                    v-if="message"
                    class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-700"
                >
                    {{ message }}
                </p>
                <p
                    v-if="errorMessage"
                    class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-semibold text-rose-700"
                >
                    {{ errorMessage }}
                </p>

                <div
                    v-if="activeTab === 'pending'"
                    class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="hidden gap-4 border-b border-slate-200 bg-slate-50 px-6 py-4 text-sm font-semibold text-slate-600 lg:grid lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.85fr)_minmax(0,0.85fr)_120px_180px]"
                    >
                        <div>空間 / 申請者</div>
                        <div>時段</div>
                        <div>申請時間</div>
                        <div class="text-center">詳細資料</div>
                        <div class="text-center">操作</div>
                    </div>

                    <div
                        v-if="loading"
                        class="px-6 py-16 text-center text-sm font-medium text-slate-500"
                    >
                        載入待審清單中...
                    </div>

                    <div
                        v-else-if="approvals.length === 0"
                        class="px-6 py-16 text-center text-sm font-medium text-slate-500"
                    >
                        目前沒有待審申請。
                    </div>

                    <div v-else class="divide-y divide-slate-200">
                        <div
                            v-for="reservation in approvals"
                            :key="reservation.id"
                            class="grid grid-cols-1 gap-4 px-6 py-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.85fr)_minmax(0,0.85fr)_120px_180px] lg:items-center"
                        >
                            <div>
                                <p class="text-lg font-semibold text-slate-950">
                                    {{ reservationRoomsLabel(reservation) }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{
                                        reservation.room?.type ||
                                        reservation.room?.room_type ||
                                        "-"
                                    }}
                                    · {{ reservation.room?.building || "-" }}
                                </p>
                                <p class="mt-2 text-sm text-slate-600">
                                    申請者：{{
                                        reservation.user?.name ||
                                        reservation.user?.email ||
                                        "-"
                                    }}
                                </p>
                            </div>

                            <div class="text-sm text-slate-700">
                                <p>
                                    {{ formatDateTime(reservation.start_time) }}
                                </p>
                                <p class="mt-1 text-slate-500">
                                    至
                                    {{ formatDateTime(reservation.end_time) }}
                                </p>
                                <p class="mt-1 text-slate-500">
                                    共 {{ reservation.slot_count || 1 }} 個時段
                                </p>
                            </div>

                            <div class="text-sm text-slate-700">
                                <p>
                                    {{ formatDateTime(reservation.created_at) }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    狀態：{{ reservation.reservation_status }}
                                </p>
                            </div>

                            <div class="flex justify-start lg:justify-center">
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                    @click="openDetail(reservation, 'pending')"
                                >
                                    詳細資料
                                </button>
                            </div>

                            <div class="flex justify-start lg:justify-center">
                                <ApprovalActionButtons
                                    :reservationId="reservation.id"
                                    @updated="handleUpdated"
                                    @failed="handleFailed"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="hidden gap-4 border-b border-slate-200 bg-slate-50 px-6 py-4 text-sm font-semibold text-slate-600 lg:grid lg:grid-cols-[minmax(0,1.25fr)_minmax(0,0.85fr)_minmax(0,0.85fr)_minmax(0,0.85fr)_120px]"
                    >
                        <div>空間 / 申請者</div>
                        <div>審核結果</div>
                        <div>審核者</div>
                        <div>審核時間</div>
                        <div class="text-center">詳細資料</div>
                    </div>

                    <div
                        v-if="loading"
                        class="px-6 py-16 text-center text-sm font-medium text-slate-500"
                    >
                        載入審核歷史中...
                    </div>

                    <div
                        v-else-if="histories.length === 0"
                        class="px-6 py-16 text-center text-sm font-medium text-slate-500"
                    >
                        目前沒有審核紀錄。
                    </div>

                    <div v-else class="divide-y divide-slate-200">
                        <div
                            v-for="approval in histories"
                            :key="approval.id"
                            class="grid grid-cols-1 gap-4 px-6 py-5 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,0.85fr)_minmax(0,0.85fr)_minmax(0,0.85fr)_120px] lg:items-center"
                        >
                            <div>
                                <p class="text-lg font-semibold text-slate-950">
                                    {{ reservationRoomsLabel(approval) }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{
                                        approval.reservation?.room?.type ||
                                        approval.reservation?.room?.room_type ||
                                        "-"
                                    }}
                                    ·
                                    {{
                                        approval.reservation?.room?.building ||
                                        "-"
                                    }}
                                </p>
                                <p class="mt-2 text-sm text-slate-600">
                                    申請者：{{
                                        approval.reservation?.user?.name ||
                                        approval.reservation?.user?.email ||
                                        "-"
                                    }}
                                </p>
                            </div>

                            <div>
                                <span
                                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                                    :class="decisionClass(approval.decision)"
                                >
                                    {{ decisionLabel(approval.decision) }}
                                </span>
                                <p class="mt-2 text-sm text-slate-500">
                                    狀態：{{
                                        approval.reservation
                                            ?.reservation_status || "-"
                                    }}
                                </p>
                            </div>

                            <div class="text-sm text-slate-700">
                                <p>
                                    {{
                                        approval.approver?.name ||
                                        approval.approver?.email ||
                                        "-"
                                    }}
                                </p>
                            </div>

	                            <div class="text-sm text-slate-700">
	                                <p>
	                                    {{ formatDateTime(approval.decision_time) }}
	                                </p>
	                            </div>

	                            <div class="flex justify-start lg:justify-center">
	                                <button
	                                    type="button"
	                                    class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
	                                    @click="openDetail(approval, 'history')"
	                                >
	                                    詳細資料
	                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <teleport to="body">
                <div
                    v-if="detailItem"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-8"
                    @click.self="closeDetail"
                >
                    <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-[2rem] bg-white p-6 shadow-2xl">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                                    Approval Detail
                                </p>
                                <h3 class="mt-2 text-2xl font-semibold text-slate-950">
                                    預約詳細資料
                                </h3>
                            </div>
                            <button
                                type="button"
                                class="rounded-full border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                @click="closeDetail"
                            >
                                關閉
                            </button>
                        </div>

                        <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-lg font-semibold text-slate-950">
                                {{
                                    (detailMode === "history"
                                        ? detailItem.reservation?.room?.name || detailItem.reservation?.room?.room_name
                                        : detailItem.room?.name || detailItem.room?.room_name) || "未知空間"
                                }}
                            </p>
                            <p class="mt-1 text-sm text-slate-600">
                                申請者：{{
                                    (detailMode === "history"
                                        ? detailItem.reservation?.user?.name || detailItem.reservation?.user?.email
                                        : detailItem.user?.name || detailItem.user?.email) || "-"
                                }}
                            </p>
                            <p class="mt-1 text-sm text-slate-600">
                                共 {{ detailItem.slot_count || detailItem.slots?.length || 1 }} 個時段
                            </p>
                        </div>

                        <div v-if="detailMode === 'history'" class="mt-4 rounded-2xl border border-slate-200 p-4 text-sm text-slate-700">
                            <p>
                                審核結果：{{ decisionLabel(detailItem.decision) }}
                            </p>
                            <p class="mt-1">
                                審核者：{{ detailItem.approver?.name || detailItem.approver?.email || "-" }}
                            </p>
                            <p class="mt-1">
                                審核時間：{{ formatDateTime(detailItem.decision_time) }}
                            </p>
                        </div>

                        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                            <div class="grid grid-cols-[1fr_100px] gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600">
                                <div>時段</div>
                                <div>狀態</div>
                            </div>
                            <div class="divide-y divide-slate-200">
                                <div
                                    v-for="slot in detailItem.slots || []"
                                    :key="slot.id"
                                    class="grid grid-cols-[1fr_100px] gap-4 px-4 py-3 text-sm"
                                >
                                    <div class="text-slate-800">
                                        <p
                                            v-if="slotRoomLabel(slot)"
                                            class="font-semibold text-slate-950"
                                        >
                                            {{ slotRoomLabel(slot) }}
                                        </p>
                                        <p>{{ slotRangeLabel(slot) }}</p>
                                    </div>
                                    <div class="font-semibold text-slate-600">
                                        {{ statusLabel(slot.reservation_status) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </teleport>
        </AuthenticatedLayout>
    </div>
</template>

<style scoped></style>
