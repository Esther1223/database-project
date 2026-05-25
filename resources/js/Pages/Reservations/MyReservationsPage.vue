<script setup>
import axios from "axios";
import { Head, Link } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import AuthenticatedLayout from "../../Layouts/AuthenticatedLayout.vue";
import ReservationStatusBadge from "../../Components/Reservation/ReservationStatusBadge.vue";

const reservations = ref([]);
const loading = ref(false);
const cancellingId = ref(null);
const reservationToCancel = ref(null);
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
    if (!value) {
        return "-";
    }

    const normalized = String(value).replace("T", " ");

    if (normalized.length >= 16) {
        return normalized.slice(0, 16).replaceAll("-", "/");
    }

    return normalized;
};

const roomName = (reservation) =>
    reservation.room?.name || reservation.room?.room_name || "未知空間";

const roomType = (reservation) =>
    reservation.room?.type || reservation.room?.room_type || "-";

const roomBuilding = (reservation) => reservation.room?.building || "-";

const canCancel = (reservation) =>
    ["pending", "success"].includes(reservation.reservation_status);

const openCancelDialog = (reservation) => {
    if (!canCancel(reservation) || cancellingId.value !== null) {
        return;
    }

    reservationToCancel.value = reservation;
};

const closeCancelDialog = () => {
    if (cancellingId.value !== null) {
        return;
    }

    reservationToCancel.value = null;
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
    const reservation = reservationToCancel.value;

    if (!canCancel(reservation) || cancellingId.value !== null) {
        return;
    }

    cancellingId.value = reservation.id;
    message.value = "";
    errorMessage.value = "";

    try {
        const response = await axios.patch(
            `/reservations/${reservation.id}/cancel`,
        );
        message.value = response.data.message || "預約已取消。";
        reservationToCancel.value = null;
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
                            Reservations
                        </p>
                        <h2 class="mt-3 text-3xl font-semibold text-slate-950">
                            我的預約
                        </h2>
                        <p class="mt-2 text-sm text-slate-600">
                            查看目前預約狀態，並取消尚在審核中的申請。
                        </p>
                    </div>

                    <Link
                        href="/reservations/create"
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
                    class="grid grid-cols-[1.4fr_1fr_1fr_140px] gap-4 border-b border-slate-200 bg-slate-50 px-6 py-4 text-sm font-semibold text-slate-600"
                >
                    <div>空間</div>
                    <div>時段</div>
                    <div>狀態</div>
                    <div class="text-right">操作</div>
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
                        class="grid grid-cols-1 gap-4 px-6 py-5 lg:grid-cols-[1.4fr_1fr_1fr_140px] lg:items-center"
                    >
                        <div>
                            <p class="text-lg font-semibold text-slate-950">
                                {{ roomName(reservation) }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ roomType(reservation) }} ·
                                {{ roomBuilding(reservation) }}
                            </p>
                            <p class="mt-2 text-sm text-slate-600">
                                預約編號 #{{ reservation.id }}
                            </p>
                        </div>

                        <div class="text-sm text-slate-700">
                            <p>{{ formatDateTime(reservation.start_time) }}</p>
                            <p class="mt-1 text-slate-500">
                                至 {{ formatDateTime(reservation.end_time) }}
                            </p>
                        </div>

                        <div>
                            <ReservationStatusBadge :status="reservation.reservation_status" />
                        </div>

                        <div class="flex justify-start lg:justify-end">
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
	                </div>
	            </div>
	            <teleport to="body">
	                <div
	                    v-if="reservationToCancel"
	                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-8"
	                    @click.self="closeCancelDialog"
	                >
	                    <div class="w-full max-w-md rounded-[2rem] border border-slate-200 bg-white p-6 shadow-xl">
	                        <div class="flex items-start justify-between gap-4">
	                            <div>
	                                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
	                                    Cancel
	                                </p>
	                                <h3 class="mt-3 text-2xl font-semibold text-slate-950">
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

	                        <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
	                            <p class="font-semibold text-slate-950">
	                                {{ roomName(reservationToCancel) }}
	                            </p>
	                            <p class="mt-2">
	                                {{ formatDateTime(reservationToCancel.start_time) }}
	                            </p>
	                            <p class="mt-1 text-slate-500">
	                                至 {{ formatDateTime(reservationToCancel.end_time) }}
	                            </p>
	                        </div>

	                        <p class="mt-4 text-sm leading-6 text-slate-600">
	                            取消後，這筆預約狀態會改為已取消；若已預約成功，該時段會重新開放。
	                        </p>

	                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
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
	                                {{ cancellingId !== null ? "取消中..." : "確認取消" }}
	                            </button>
	                        </div>
	                    </div>
	                </div>
	            </teleport>
	            </section>
	        </AuthenticatedLayout>
	    </div>
</template>
