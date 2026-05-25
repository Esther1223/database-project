<script setup>
import axios from "axios";
import { Head, Link } from "@inertiajs/vue3";
import { computed, onMounted, ref, watch } from "vue";
import AuthenticatedLayout from "../../Layouts/AuthenticatedLayout.vue";
import ReservationForm from "../../Components/Reservation/ReservationForm.vue";

const props = defineProps({
    rooms: {
        type: Array,
        required: true,
    },
    initialDate: {
        type: String,
        required: true,
    },
});

const dateOnly = (value) => String(value || "").slice(0, 10);

const selectedRoomId = ref(props.rooms[0]?.id ?? "");
const selectedDate = ref(dateOnly(props.initialDate));
const selectedSectionKey = ref(null);
const sections = ref([]);
const loadingSections = ref(false);
const submitting = ref(false);
const message = ref("");
const errorMessage = ref("");
const successDialog = ref(null);

const timeSlotMap = {
    TS_0000: "08:00 - 09:00",
    TS_0100: "09:00 - 10:00",
    TS_0200: "10:00 - 11:00",
    TS_0300: "11:00 - 12:00",
    TS_0400: "12:00 - 13:00",
    TS_0500: "13:00 - 14:00",
    TS_0600: "14:00 - 15:00",
    TS_0700: "15:00 - 16:00",
    TS_0800: "16:00 - 17:00",
    TS_0900: "17:00 - 18:00",
    TS_1000: "18:00 - 19:00",
    TS_1100: "19:00 - 20:00",
    TS_1200: "20:00 - 21:00",
    TS_1300: "21:00 - 22:00",
    TS_1400: "22:00 - 23:00",
    TS_1500: "23:00 - 00:00",
    TS_1600: "00:00 - 01:00",
};

const selectedRoom = computed(() =>
    props.rooms.find((room) => room.id === Number(selectedRoomId.value)),
);

const selectedSection = computed(() =>
    sections.value.find((section) => sectionKey(section) === selectedSectionKey.value),
);

const bookableSections = computed(() =>
    sections.value.filter((section) => isSelectableSection(section)),
);

const formatTimeSlot = (section) =>
    section?.time_slot?.label || timeSlotMap[section?.time_slot_id] || section?.time_slot_id || "-";

const isSelectableSection = (section) =>
    section.is_bookable;

const sectionKey = (section) =>
    section.id ?? `${section.room_id}-${section.date}-${section.time_slot_id}`;

const isSelectedSection = (section) =>
    selectedSectionKey.value !== null && selectedSectionKey.value === sectionKey(section);

const sectionStatusText = (section) => {
    if (
        section.time_slot?.status === "disable" ||
        section.status === "unavailable"
    ) {
        return "暫停開放";
    }

    if (section.status === "reserved") {
        return "已被預約";
    }

    return "可預約";
};

const loadSections = async () => {
    selectedSectionKey.value = null;
    message.value = "";
    errorMessage.value = "";
    sections.value = [];

    if (!selectedRoomId.value || !selectedDate.value) {
        return;
    }

    loadingSections.value = true;

    try {
        const response = await axios.get(
            `/rooms/${selectedRoomId.value}/available-sections`,
            {
                params: {
                    date: selectedDate.value,
                },
            },
        );

        sections.value = response.data.data || [];
    } catch (error) {
        console.error("載入時段失敗：", error.response?.data || error.message);
        errorMessage.value =
            error.response?.data?.message || "時段載入失敗，請稍後再試。";
    } finally {
        loadingSections.value = false;
    }
};

const submitReservation = async () => {
    message.value = "";
    errorMessage.value = "";
    successDialog.value = null;

    if (!selectedRoomId.value || selectedSection.value === undefined) {
        errorMessage.value = "請選擇教室與時段。";
        return;
    }

    submitting.value = true;

    try {
        const payload = {
            room_id: selectedRoomId.value,
        };

        if (selectedSection.value.id) {
            payload.section_id = selectedSection.value.id;
        } else {
            payload.date = selectedSection.value.date;
            payload.time_slot_id = selectedSection.value.time_slot_id;
        }

        const response = await axios.post("/reservations", payload);

        successDialog.value = {
            message: response.data.message || "預約已送出。",
            roomName: selectedRoom.value?.name || "已選空間",
            date: selectedSection.value.date,
            time: formatTimeSlot(selectedSection.value),
            status: response.data.data?.reservation_status || null,
        };
        await loadSections();
    } catch (error) {
        console.error("預約失敗：", error.response?.data || error.message);
        errorMessage.value =
            error.response?.data?.message || "預約失敗，請稍後再試。";
    } finally {
        submitting.value = false;
    }
};

const closeSuccessDialog = () => {
    successDialog.value = null;
};

watch([selectedRoomId, selectedDate], loadSections);

onMounted(loadSections);
</script>

<template>
    <div>
        <Head title="建立預約" />

        <AuthenticatedLayout title="建立預約">
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
                            Reservation
                        </p>
                        <h2 class="mt-3 text-3xl font-semibold text-slate-950">
                            建立預約
                        </h2>
                        <p class="mt-2 text-sm text-slate-600">
                            選擇教室、日期與可用時段後送出申請。
                        </p>
                    </div>

                    <Link
                        href="/rooms"
                        class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        空間列表
                    </Link>
                </div>
            </div>

	            <div class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
                <ReservationForm
                    :room-id="selectedRoomId"
                    :date="selectedDate"
	                    :rooms="rooms"
	                    :selected-room="selectedRoom"
	                    :selected-section="selectedSection"
                    :message="message"
	                    :error-message="errorMessage"
	                    :submitting="submitting"
                    @update:room-id="selectedRoomId = $event"
                    @update:date="selectedDate = $event"
	                    @submit="submitReservation"
                    @remove-selected="selectedSectionKey = null"
	                />

                <div
                    class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500"
                            >
                                Time Slots
                            </p>
                            <h3
                                class="mt-2 text-xl font-semibold text-slate-950"
                            >
                                可預約時段
                            </h3>
                        </div>
                        <p class="text-sm font-medium text-slate-500">
                            共 {{ bookableSections.length }} 個可選
                        </p>
                    </div>

                    <div
                        v-if="loadingSections"
                        class="py-16 text-center text-sm font-medium text-slate-500"
                    >
                        載入時段中...
                    </div>

                    <div
                        v-else-if="sections.length === 0"
                        class="py-16 text-center text-sm font-medium text-slate-500"
                    >
                        這一天目前沒有可預約時段。
                    </div>

                    <div v-else class="mt-5 space-y-3">
                        <button
                            v-for="section in sections"
                            :key="sectionKey(section)"
                            type="button"
                            class="flex w-full items-center justify-between gap-4 rounded-2xl border px-5 py-4 text-left transition disabled:cursor-not-allowed disabled:opacity-60"
                            :class="
                                isSelectedSection(section)
                                    ? 'border-slate-900 bg-slate-900 text-white'
	                                    : isSelectableSection(section)
	                                      ? 'border-blue-100 bg-blue-50 text-slate-900 hover:border-blue-300 hover:shadow-sm'
	                                      : 'border-slate-200 bg-slate-50 text-slate-500'
	                            "
                            :disabled="!isSelectableSection(section)"
                            @click="selectedSectionKey = sectionKey(section)"
                        >
                            <span class="min-w-0">
                                <span class="block text-base font-semibold">
                                    {{ formatTimeSlot(section) }}
                                </span>
                            </span>
                            <span
                                class="shrink-0 rounded-full px-3 py-1 text-xs font-bold"
                                :class="
                                    isSelectedSection(section)
                                        ? 'bg-white text-slate-900'
	                                        : isSelectableSection(section)
	                                          ? 'bg-blue-100 text-blue-700'
	                                          : 'bg-slate-200 text-slate-500'
                                "
                            >
                                {{ sectionStatusText(section) }}
                            </span>
                        </button>
                    </div>
                </div>
	            </div>
	            <teleport to="body">
	                <div
	                    v-if="successDialog"
	                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-8"
	                    @click.self="closeSuccessDialog"
	                >
	                    <div class="w-full max-w-md rounded-[2rem] border border-slate-200 bg-white p-6 shadow-xl">
	                        <div class="flex items-start justify-between gap-4">
	                            <div>
	                                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
	                                    Reservation
	                                </p>
	                                <h3 class="mt-3 text-2xl font-semibold text-slate-950">
	                                    {{ successDialog.status === "pending" ? "申請已送出" : "預約成功" }}
	                                </h3>
	                            </div>
	                            <button
	                                type="button"
	                                class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-lg font-semibold leading-none text-slate-600 transition hover:bg-slate-100"
	                                aria-label="關閉"
	                                @click="closeSuccessDialog"
	                            >
	                                ×
	                            </button>
	                        </div>

	                        <p class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
	                            {{ successDialog.message }}
	                        </p>

	                        <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
	                            <p class="font-semibold text-slate-950">{{ successDialog.roomName }}</p>
	                            <p class="mt-2">{{ successDialog.date }}</p>
	                            <p class="mt-1 text-slate-500">{{ successDialog.time }}</p>
	                        </div>

	                        <div class="mt-6 flex justify-end">
	                            <button
	                                type="button"
	                                class="rounded-2xl border border-slate-900 bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
	                                @click="closeSuccessDialog"
	                            >
	                                完成
	                            </button>
	                        </div>
	                    </div>
	                </div>
	            </teleport>
	            </section>
        </AuthenticatedLayout>
    </div>
</template>
