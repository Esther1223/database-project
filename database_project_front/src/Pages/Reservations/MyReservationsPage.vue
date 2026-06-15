<script setup>
import axios from "axios";
import { computed, onMounted, ref } from "vue";
import { RouterLink as Link } from "vue-router";
import AuthenticatedLayout from "../../Layouts/AuthenticatedLayout.vue";
import ReservationStatusBadge from "../../Components/Reservation/ReservationStatusBadge.vue";
import Head from "../../support/HeadTitle.vue";

const reservations = ref([]);
const loading = ref(false);
const cancellingId = ref(null);
const reservationToCancel = ref(null);
const detailReservation = ref(null);
const message = ref("");
const errorMessage = ref("");

const totalCount = computed(() => reservations.value.length);

const activeCount = computed(
    () =>
        reservations.value.filter((reservation) =>
            ["pending", "success"].includes(reservation.reservation_status),
        ).length,
);

const formatDateTime = (value) => {
    if (!value) return "-";

    const raw = String(value).trim();
    const cleaned = raw.replace(/(Z|[+-]\d{2}:?\d{2})$/i, "").trim();
    const normalized = cleaned.replace(" ", "T");

    const parts = normalized.split("T");
    if (parts.length >= 2) {
        const datePart = parts[0].replaceAll("-", "/");
        const timePart = parts[1].slice(0, 5);
        return `${datePart} ${timePart}`;
    }

    return cleaned;
};

const dateOnly = (value) => {
    if (!value) return "-";
    const raw = String(value).trim();
    const cleaned = raw.replace(/(Z|[+-]\d{2}:?\d{2})$/i, "").trim();
    const parts = cleaned.replace(" ", "T").split("T");
    return parts[0] ? parts[0].replaceAll("-", "/") : cleaned;
};

const slotRangeLabel = (startValue, endValue) => {
    if (!startValue || !endValue) return "-";
    const clean = (v) => String(v).trim().replace(/(Z|[+-]\d{2}:?\d{2})$/i, "").trim();
    const s = clean(startValue).replace(" ", "T");
    const e = clean(endValue).replace(" ", "T");
    const sParts = s.split("T");
    const eParts = e.split("T");
    const sTime = sParts[1] ? sParts[1].slice(0, 5) : "";
    const eTime = eParts[1] ? eParts[1].slice(0, 5) : "";
    if (!sTime || !eTime) return `${s} - ${e}`;
    return `${sTime} - ${eTime}`;
};

const slotRoomLabel = (slot) =>
    [slot?.room?.name, slot?.room?.building].filter(Boolean).join(" · ");

const startsInFuture = (value) => {
    if (!value) return false;

    const date = new Date(String(value).replace(" ", "T"));

    return !Number.isNaN(date.getTime()) && date > new Date();
};

const reservationSlotLabels = (reservation) => {
    if (!reservation.slots?.length) {
        return [slotRangeLabel(reservation.start_time, reservation.end_time)];
    }

    return reservation.slots.map(
        (slot) => {
            const room = slotRoomLabel(slot);
            const dateTime = `${String(slot.date || "").replaceAll("-", "/")} ${slotRangeLabel(
                slot.start_time,
                slot.end_time,
            )}`;

            return room ? `${room} ${dateTime}` : dateTime;
        },
    );
};

const reservationSlotSummary = (reservation) => {
    const labels = reservationSlotLabels(reservation);

    if (labels.length <= 2) {
        return labels;
    }

    return [labels[0], `另 ${labels.length - 1} 個時段`];
};

const roomName = (reservation) =>
    [
        ...new Set(
            (reservation.slots || [])
                .map((slot) => slot?.room?.name)
                .filter(Boolean),
        ),
    ].join("、") ||
    reservation.room?.name ||
    reservation.room?.room_name ||
    "未知空間";

const roomType = (reservation) =>
    reservation.room?.type || reservation.room?.room_type || "-";

const roomBuilding = (reservation) => reservation.room?.building || "-";

const paymentStatusLabel = (status) => {
    const labels = {
        paid: "已付款",
        unpaid: "未付款",
    };

    return labels[status] || status || "-";
};

const paymentStatusClass = (status) => {
    if (status === "paid") {
        return "bg-emerald-100 text-emerald-800";
    }

    if (status === "unpaid") {
        return "bg-rose-100 text-rose-800";
    }

    return "bg-slate-100 text-slate-600";
};

const formatMoney = (value) => {
    const amount = Number(value || 0);

    return `NT$ ${new Intl.NumberFormat("zh-TW").format(amount)}`;
};

const unpaidAmount = (reservation) => {
    if (reservation?.payment?.payment_status !== "unpaid") {
        return 0;
    }

    return Number(reservation.payment?.amount || 0);
};

const shouldShowPaymentStatus = (reservation) =>
    reservation?.reservation_status !== "cancelled" && reservation?.payment;

const canCancel = (reservation) =>
    (reservation?.slots?.length
        ? reservation.slots.some((slot) => canCancelSlot(slot))
        : ["pending", "success"].includes(reservation?.reservation_status));

const canCancelSlot = (slot) =>
    ["pending", "success"].includes(slot?.reservation_status) &&
    startsInFuture(slot?.start_time);

const openCancelDialog = (reservation, slot = null) => {
    if (!canCancel(reservation) || cancellingId.value !== null) {
        return;
    }

    reservationToCancel.value = { reservation, slot };
};

const closeCancelDialog = () => {
    if (cancellingId.value !== null) {
        return;
    }

    reservationToCancel.value = null;
};

const openDetailDialog = (reservation) => {
    detailReservation.value = reservation;
};

const closeDetailDialog = () => {
    detailReservation.value = null;
};

const loadReservations = async () => {
    loading.value = true;
    message.value = "";
    errorMessage.value = "";

    try {
        const response = await axios.get("/reservations/my");
        reservations.value = response.data.data || [];
    } catch (error) {
        console.error("載入預約失敗：", error.response?.data || error.message);
        errorMessage.value =
            error.response?.data?.message || "預約資料載入失敗，請稍後再試。";
    } finally {
        loading.value = false;
    }
};

const cancelReservation = async () => {
    const target = reservationToCancel.value;
    const reservation = target?.reservation;
    const slot = target?.slot;

    if (!canCancel(reservation) || cancellingId.value !== null) {
        return;
    }

    const cancelId = slot?.id ?? reservation.id;
    cancellingId.value = cancelId;
    message.value = "";
    errorMessage.value = "";

    try {
        const response = await axios.patch(
            slot
                ? `/reservations/${slot.id}/cancel-single`
                : `/reservations/${reservation.id}/cancel`,
        );
        message.value = response.data.message || "預約已取消。";
        reservationToCancel.value = null;
        detailReservation.value = null;
        await loadReservations();
    } catch (error) {
        console.error("取消預約失敗：", error.response?.data || error.message);
        errorMessage.value =
            error.response?.data?.message || "取消預約失敗，請稍後再試。";
    } finally {
        cancellingId.value = null;
    }
};

onMounted(loadReservations);
</script>

<template>
    <div>
        <Head title="我的預約" />

        <AuthenticatedLayout title="我的預約">
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
                                My Reservations
                            </p>
                            <h2
                                class="mt-3 text-3xl font-semibold text-slate-950"
                            >
                                我的預約
                            </h2>
                            <p class="mt-2 text-sm text-slate-600">
                                查看目前預約狀態，並取消尚在審核中的申請。
                            </p>
                        </div>

                        <Link
                            to="/reservations/create"
                            class="rounded-2xl border border-slate-900 bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
                        >
                            建立預約
                        </Link>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600"
                        >
                            全部預約：{{ totalCount }} 筆
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600"
                        >
                            進行中：{{ activeCount }} 筆
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
                        class="grid grid-cols-[1.35fr_1fr_0.8fr_120px_140px] gap-4 border-b border-slate-200 bg-slate-50 px-6 py-4 text-sm font-semibold text-slate-600"
                    >
                        <div>空間</div>
                        <div>時段</div>
                        <div>狀態</div>
                        <div class="text-left">詳細資料</div>
                        <div class="text-left">操作</div>
                    </div>

                    <div
                        v-if="loading"
                        class="px-6 py-16 text-center text-sm font-medium text-slate-500"
                    >
                        載入預約中...
                    </div>

                    <div
                        v-else-if="reservations.length === 0"
                        class="px-6 py-16 text-center text-sm font-medium text-slate-500"
                    >
                        目前沒有預約資料。
                    </div>

                    <div v-else class="divide-y divide-slate-200">
                        <div
                            v-for="reservation in reservations"
                            :key="reservation.id"
                            class="grid grid-cols-1 gap-4 px-6 py-5 lg:grid-cols-[1.35fr_1fr_0.8fr_120px_140px] lg:items-center"
                        >
                            <div>
                                <p class="text-lg font-semibold text-slate-950">
                                    {{ roomName(reservation) }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ roomType(reservation) }} ·
                                    {{ roomBuilding(reservation) }}
                                </p>
                            </div>

                            <div class="text-sm text-slate-700">
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="slot in reservationSlotSummary(reservation)"
                                        :key="slot"
                                        class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600"
                                    >
                                        {{ slot }}
                                    </span>
                                </div>
                            </div>

                            <div>
                                <ReservationStatusBadge
                                    :status="reservation.reservation_status"
                                />
                                <span
                                    v-if="shouldShowPaymentStatus(reservation)"
                                    class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="
                                        paymentStatusClass(
                                            reservation.payment.payment_status,
                                        )
                                    "
                                >
                                    {{
                                        paymentStatusLabel(
                                            reservation.payment.payment_status,
                                        )
                                    }}
                                </span>
                            </div>

                            <div class="flex justify-start">
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                    @click="openDetailDialog(reservation)"
                                >
                                    詳細資料
                                </button>
                            </div>

                            <div class="flex justify-start">
                                <button
                                    v-if="canCancel(reservation)"
                                    type="button"
                                    class="rounded-xl border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="cancellingId === reservation.id"
                                    @click="openCancelDialog(reservation)"
                                >
                                    {{
                                        cancellingId === reservation.id
                                            ? "取消中..."
                                            : "全部取消"
                                    }}
                                </button>

                                <span
                                    v-else
                                    class="text-sm font-medium text-slate-400"
                                    >-</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
                <teleport to="body">
                    <div
                        v-if="detailReservation"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-8"
                        @click.self="closeDetailDialog"
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
                                    @click="closeDetailDialog"
                                >
                                    ×
                                </button>
                            </div>

                            <div
                                class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"
                            >
                                <p class="text-lg font-semibold text-slate-950">
                                    {{ roomName(detailReservation) }}
                                </p>
                                <p class="mt-1">
                                    {{ roomType(detailReservation) }} ·
                                    {{ roomBuilding(detailReservation) }}
                                </p>
                                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                    <div
                                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3"
                                    >
                                        <p class="text-xs font-semibold text-slate-500">
                                            預約狀態
                                        </p>
                                        <div class="mt-2">
                                            <ReservationStatusBadge
                                                :status="detailReservation.reservation_status"
                                            />
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3"
                                    >
                                        <p class="text-xs font-semibold text-slate-500">
                                            付款狀態
                                        </p>
                                        <span
                                            v-if="
                                                shouldShowPaymentStatus(
                                                    detailReservation,
                                                )
                                            "
                                            class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                            :class="
                                                paymentStatusClass(
                                                    detailReservation.payment
                                                        .payment_status,
                                                )
                                            "
                                        >
                                            {{
                                                paymentStatusLabel(
                                                    detailReservation.payment
                                                        .payment_status,
                                                )
                                            }}
                                        </span>
                                        <span
                                            v-else
                                            class="mt-2 inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600"
                                        >
                                            無須付款
                                        </span>
                                    </div>

                                    <div
                                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3"
                                    >
                                        <p class="text-xs font-semibold text-slate-500">
                                            待付金額
                                        </p>
                                        <p class="mt-2 text-lg font-semibold text-slate-950">
                                            {{
                                                formatMoney(
                                                    unpaidAmount(
                                                        detailReservation,
                                                    ),
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <p class="mt-3">
                                    共 {{ detailReservation.slots?.length || 1 }}
                                    個時段
                                </p>
                            </div>

                            <div
                                class="mt-5 overflow-hidden rounded-2xl border border-slate-200"
                            >
                                <div
                                    class="grid grid-cols-[1fr_100px_110px] gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600"
                                >
                                    <div>時段</div>
                                    <div>狀態</div>
                                    <div>操作</div>
                                </div>
                                <div class="divide-y divide-slate-200">
                                    <div
                                        v-for="slot in detailReservation.slots || []"
                                        :key="slot.id"
                                        class="grid grid-cols-[1fr_100px_110px] gap-4 px-4 py-3 text-sm"
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
                                        <div>
                                            <ReservationStatusBadge
                                                :status="slot.reservation_status"
                                            />
                                        </div>
                                        <div>
                                            <button
                                                v-if="canCancelSlot(slot)"
                                                type="button"
                                                class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
                                                :disabled="cancellingId === slot.id"
                                                @click="
                                                    openCancelDialog(
                                                        detailReservation,
                                                        slot,
                                                    )
                                                "
                                            >
                                                {{
                                                    cancellingId === slot.id
                                                        ? "取消中..."
                                                        : "取消"
                                                }}
                                            </button>
                                            <span
                                                v-else
                                                class="text-sm font-medium text-slate-400"
                                                >-</span
                                            >
                                        </div>
                                    </div>
                                    <div
                                        v-if="!detailReservation.slots?.length"
                                        class="grid grid-cols-[1fr_100px_110px] gap-4 px-4 py-3 text-sm"
                                    >
                                        <div class="text-slate-800">
                                            {{
                                                `${dateOnly(detailReservation.start_time)} ${slotRangeLabel(
                                                    detailReservation.start_time,
                                                    detailReservation.end_time,
                                                )}`
                                            }}
                                        </div>
                                        <div>
                                            <ReservationStatusBadge
                                                :status="detailReservation.reservation_status"
                                            />
                                        </div>
                                        <div>
                                            <button
                                                v-if="canCancel(detailReservation)"
                                                type="button"
                                                class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
                                                :disabled="
                                                    cancellingId ===
                                                    detailReservation.id
                                                "
                                                @click="
                                                    openCancelDialog(
                                                        detailReservation,
                                                    )
                                                "
                                            >
                                                取消
                                            </button>
                                            <span
                                                v-else
                                                class="text-sm font-medium text-slate-400"
                                                >-</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="reservationToCancel"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-8"
                        @click.self="closeCancelDialog"
                    >
                        <div
                            class="w-full max-w-md rounded-[2rem] border border-slate-200 bg-white p-6 shadow-xl"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p
                                        class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500"
                                    >
                                        Cancel
                                    </p>
                                    <h3
                                        class="mt-3 text-2xl font-semibold text-slate-950"
                                    >
                                        確認取消預約
                                    </h3>
                                </div>
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-lg font-semibold leading-none text-slate-600 transition hover:bg-slate-100"
                                    :disabled="cancellingId !== null"
                                    aria-label="關閉"
                                    @click="closeCancelDialog"
                                >
                                    ×
                                </button>
                            </div>

                            <div
                                class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"
                            >
                                <p class="font-semibold text-slate-950">
                                    {{
                                        reservationToCancel.slot
                                            ? slotRoomLabel(
                                                  reservationToCancel.slot,
                                              )
                                            : roomName(
                                                  reservationToCancel.reservation,
                                              )
                                    }}
                                </p>
                                <p class="mt-2">
                                    {{
                                        reservationToCancel.slot
                                            ? `${String(
                                                  reservationToCancel.slot
                                                      .date || "",
                                              ).replaceAll(
                                                  "-",
                                                  "/",
                                              )} ${slotRangeLabel(
                                                  reservationToCancel.slot
                                                      .start_time,
                                                  reservationToCancel.slot
                                                      .end_time,
                                              )}`
                                            : formatDateTime(
                                                  reservationToCancel
                                                      .reservation.start_time,
                                              )
                                    }}
                                </p>
                                <p
                                    v-if="!reservationToCancel.slot"
                                    class="mt-1 text-slate-500"
                                >
                                    至
                                    {{
                                        formatDateTime(
                                            reservationToCancel.reservation
                                                .end_time,
                                        )
                                    }}
                                </p>
                            </div>

                            <p class="mt-4 text-sm leading-6 text-slate-600">
                                {{
                                    reservationToCancel.slot
                                        ? "取消後，只有這個時段會改為已取消；若已預約成功，該時段會重新開放。"
                                        : "取消後，這筆預約底下所有可取消時段都會改為已取消；若已預約成功，時段會重新開放。"
                                }}
                            </p>

                            <div
                                class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                            >
                                <button
                                    type="button"
                                    class="rounded-2xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="cancellingId !== null"
                                    @click="closeCancelDialog"
                                >
                                    保留預約
                                </button>
                                <button
                                    type="button"
                                    class="rounded-2xl border border-rose-600 bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="cancellingId !== null"
                                    @click="cancelReservation"
                                >
                                    {{
                                        cancellingId !== null
                                            ? "取消中..."
                                            : "確認取消"
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
