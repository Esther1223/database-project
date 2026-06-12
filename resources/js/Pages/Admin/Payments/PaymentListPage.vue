<script setup>
import axios from "axios";
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";
import PaymentStatusBadge from "../../../Components/Payment/PaymentStatusBadge.vue";

const payments = ref([]);
const loading = ref(false);
const message = ref("");
const errorMessage = ref("");
const activeTab = ref("all");
const updatingId = ref(null);
const paymentToConfirm = ref(null);

const tabs = [
    { key: "all", label: "全部" },
    { key: "unpaid", label: "未付款" },
    { key: "paid", label: "已付款" },
    { key: "cancelled", label: "已取消" },
];

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

const formatAmount = (amount) => {
    if (amount === null || amount === undefined) {
        return "-";
    }

    return new Intl.NumberFormat("zh-TW").format(amount);
};

const filteredPayments = computed(() => {
    if (activeTab.value === "all") {
        return payments.value;
    }

    return payments.value.filter(
        (payment) => payment.payment_status === activeTab.value,
    );
});

const loadPayments = async () => {
    loading.value = true;
    errorMessage.value = "";

    try {
        const res = await axios.get("/admin/payments/list");
        payments.value = res.data.data || [];
    } catch (e) {
        console.error("載入付款清單失敗：", e.response?.data || e.message);
        errorMessage.value = e.response?.data?.message || "付款清單載入失敗";
    } finally {
        loading.value = false;
    }
};

const switchTab = (tab) => {
    activeTab.value = tab;
};

const openPaymentConfirm = (payment) => {
    if (!payment || updatingId.value !== null) {
        return;
    }

    if (payment.payment_status !== "unpaid") {
        return;
    }

    paymentToConfirm.value = payment;
};

const closePaymentConfirm = () => {
    if (updatingId.value !== null) {
        return;
    }

    paymentToConfirm.value = null;
};

const confirmPaidStatus = async () => {
    const payment = paymentToConfirm.value;

    if (!payment || updatingId.value !== null || payment.payment_status !== "unpaid") {
        return;
    }

    updatingId.value = payment.id;
    message.value = "";
    errorMessage.value = "";

    try {
        await axios.patch(`/admin/payments/${payment.id}/status`, {
            payment_status: "paid",
        });

        payment.payment_status = "paid";
        paymentToConfirm.value = null;
        message.value = "付款狀態已更新";
    } catch (e) {
        console.error("更新付款狀態失敗：", e.response?.data || e.message);
        errorMessage.value = e.response?.data?.message || "付款狀態更新失敗";
    } finally {
        updatingId.value = null;
    }
};

const roomName = (payment) =>
    [
        ...new Set(
            (payment.slots || []).map((slot) => slot?.room?.name).filter(Boolean),
        ),
    ].join("、") ||
    payment.reservation?.room?.name ||
    "未知空間";

const roomInfo = (payment) => {
    const room = payment.reservation?.room;
    if (!room) return "-";

    return [room.type, room.building].filter(Boolean).join(" · ") || "-";
};

const userName = (payment) =>
    payment.reservation?.user?.name || payment.reservation?.user?.email || "-";

onMounted(loadPayments);
</script>

<template>
    <div>
        <Head title="付款管理" />

        <AuthenticatedLayout title="付款管理">
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
                                Payments
                            </p>
                            <h2
                                class="mt-3 text-3xl font-semibold text-slate-950"
                            >
                                付款管理
                            </h2>
                            <p class="mt-2 text-sm text-slate-600">
                                行政可在此檢視付款清單並手動調整付款狀態。
                            </p>
                        </div>
                        <div
                            class="flex rounded-2xl border border-slate-200 bg-slate-50 p-1"
                        >
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                type="button"
                                class="rounded-xl px-4 py-2 text-sm font-semibold transition"
                                :class="
                                    activeTab === tab.key
                                        ? 'bg-white text-slate-950 shadow-sm'
                                        : 'text-slate-500 hover:text-slate-800'
                                "
                                @click="switchTab(tab.key)"
                            >
                                {{ tab.label }}
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
                    class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="hidden gap-4 border-b border-slate-200 bg-slate-50 px-6 py-4 text-sm font-semibold text-slate-600 lg:grid lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.9fr)_minmax(0,0.7fr)_160px]"
                    >
                        <div>空間 / 申請者</div>
                        <div>時段</div>
                        <div>金額 / 狀態</div>
                        <div class="text-center">操作</div>
                    </div>

                    <div
                        v-if="loading"
                        class="px-6 py-16 text-center text-sm font-medium text-slate-500"
                    >
                        載入付款清單中...
                    </div>

                    <div
                        v-else-if="filteredPayments.length === 0"
                        class="px-6 py-16 text-center text-sm font-medium text-slate-500"
                    >
                        目前沒有付款資料。
                    </div>

                    <div v-else class="divide-y divide-slate-200">
                        <div
                            v-for="payment in filteredPayments"
                            :key="payment.id"
                            class="grid grid-cols-1 gap-4 px-6 py-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.9fr)_minmax(0,0.7fr)_160px] lg:items-center"
                        >
                            <div>
                                <p class="text-lg font-semibold text-slate-950">
                                    {{ roomName(payment) }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ roomInfo(payment) }}
                                </p>
                                <p class="mt-2 text-sm text-slate-600">
                                    申請者：{{ userName(payment) }} 
                                </p>
                            </div>

                            <div class="text-sm text-slate-700">
                                <p>
                                    {{
                                        formatDateTime(
                                            payment.reservation?.start_time,
                                        )
                                    }}
                                </p>
                                <p class="mt-1 text-slate-500">
                                    至
                                    {{
                                        formatDateTime(
                                            payment.reservation?.end_time,
                                        )
                                    }}
                                </p>
                                <p class="mt-1 text-slate-500">
                                    共 {{ payment.slot_count || 1 }} 個時段
                                </p>
                            </div>

                            <div class="text-sm text-slate-700">
                                <p
                                    class="text-base font-semibold text-slate-900"
                                >
                                    NT$ {{ formatAmount(payment.amount) }}
                                </p>
                                <div class="mt-2">
                                    <PaymentStatusBadge
                                        :status="payment.payment_status"
                                    />
                                </div>
                            </div>

                            <div class="flex justify-start lg:justify-center">
                                <button
                                    v-if="payment.payment_status === 'unpaid'"
                                    type="button"
                                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="updatingId === payment.id"
                                    @click="openPaymentConfirm(payment)"
                                >
                                    {{
                                        updatingId === payment.id
                                            ? "更新中..."
                                            : "標記已付"
                                    }}
                                </button>
                                <span
                                    v-else
                                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-400"
                                >
                                    {{
                                        payment.payment_status === "paid"
                                            ? "已付款"
                                            : "已取消"
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <teleport to="body">
                    <div
                        v-if="paymentToConfirm"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-8"
                        @click.self="closePaymentConfirm"
                    >
                        <div
                            class="w-full max-w-md rounded-[2rem] border border-slate-200 bg-white p-6 shadow-xl"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p
                                        class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500"
                                    >
                                        Payment
                                    </p>
                                    <h3
                                        class="mt-3 text-2xl font-semibold text-slate-950"
                                    >
                                        確認標記已付款
                                    </h3>
                                </div>
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-lg font-semibold leading-none text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="updatingId !== null"
                                    aria-label="關閉"
                                    @click="closePaymentConfirm"
                                >
                                    ×
                                </button>
                            </div>

                            <div
                                class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"
                            >
                                <p class="font-semibold text-slate-950">
                                    {{ roomName(paymentToConfirm) }}
                                </p>
                                <p class="mt-2">
                                    申請者：{{ userName(paymentToConfirm) }}
                                </p>
                                <p class="mt-1">
                                    金額：NT$
                                    {{ formatAmount(paymentToConfirm.amount) }}
                                </p>
                                <p class="mt-1">
                                    共 {{ paymentToConfirm.slot_count || 1 }}
                                    個時段
                                </p>
                            </div>

                            <p class="mt-4 text-sm leading-6 text-slate-600">
                                標記為已付款後，系統不允許再改回未付款。
                            </p>

                            <div
                                class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                            >
                                <button
                                    type="button"
                                    class="rounded-2xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="updatingId !== null"
                                    @click="closePaymentConfirm"
                                >
                                    取消
                                </button>
                                <button
                                    type="button"
                                    class="rounded-2xl border border-slate-900 bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="updatingId !== null"
                                    @click="confirmPaidStatus"
                                >
                                    {{
                                        updatingId !== null
                                            ? "更新中..."
                                            : "確認已付款"
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </teleport>
            </section>
        </AuthenticatedLayout>
    </div>
</template>
