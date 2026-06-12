<script setup>
defineProps({
    roomId: {
        type: [Number, String],
        default: "",
    },
    date: {
        type: String,
        default: "",
    },
    rooms: {
        type: Array,
        required: true,
    },
    selectedRoom: {
        type: Object,
        default: null,
    },
    selectedSections: {
        type: Array,
        default: () => [],
    },
    message: {
        type: String,
        default: "",
    },
    errorMessage: {
        type: String,
        default: "",
    },
    submitting: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    "submit",
    "remove-selected",
    "clear-selected",
    "update:roomId",
    "update:date",
]);

const today = (() => {
    const date = new Date();
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
})();

const formatTimeSlot = (section) => {
    if (section?.time_slot?.label) return section.time_slot.label;

    const period = section?.time_slot?.period;
    if (period === null || period === undefined) return "-";

    const startHour = Number(period);
    const endHour = startHour + 1;
    const pad = (n) => String(n).padStart(2, "0");

    return `${pad(startHour)}:00 - ${pad(endHour)}:00`;
};

const selectedDatesLabel = (sections) =>
    [...new Set(sections.map((section) => section.date))]
        .filter(Boolean)
        .join("、");

const sectionRoomLabel = (section) =>
    [section.room_name, section.room_building].filter(Boolean).join(" · ") ||
    "已選空間";
</script>

<template>
    <form
        class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
        @submit.prevent="emit('submit')"
    >
        <div class="space-y-5">
            <div>
                <label for="room" class="text-sm font-semibold text-slate-700"
                    >教室</label
                >
                <select
                    id="room"
                    :value="roomId"
                    class="mt-2 w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                    @change="emit('update:roomId', $event.target.value)"
                >
                    <option value="" disabled>請選擇教室</option>
                    <option
                        v-for="room in rooms"
                        :key="room.id"
                        :value="room.id"
                    >
                        {{ room.name }} · {{ room.building }}
                    </option>
                </select>
            </div>

            <div>
                <label for="date" class="text-sm font-semibold text-slate-700"
                    >日期</label
                >
                <input
                    id="date"
                    :value="date"
                    type="date"
                    :min="today"
                    class="mt-2 w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                    @input="emit('update:date', $event.target.value)"
                />
            </div>

            <div
                v-if="selectedRoom"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"
            >
                <p class="font-semibold text-slate-950">
                    {{ selectedRoom.name }}
                </p>
                <p class="mt-2">
                    {{ selectedRoom.type }} · 容量
                    {{ selectedRoom.capacity }} 人
                </p>
                <p class="mt-1">時段價格：{{ selectedRoom.price_label || "尚未建立時段" }}</p>
                <p class="mt-1">
                    {{ selectedRoom.need_approval ? "需審核" : "可直接預約" }}
                </p>
            </div>

            <div
                v-if="selectedSections.length"
                class="rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold">
                            已選時段（{{ selectedSections.length }}）
                        </p>
                        <p class="mt-1">
                            {{ selectedDatesLabel(selectedSections) }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-full border border-blue-200 px-3 py-1 text-xs font-semibold text-blue-800 transition hover:bg-blue-100"
                        @click="emit('clear-selected')"
                    >
                        清除全部
                    </button>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-for="section in selectedSections"
                        :key="
                            section.id ??
                            `${section.room_id}-${section.date}-${section.time_slot_id}`
                        "
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white px-3 py-1 text-xs font-semibold text-blue-800"
                        @click="emit('remove-selected', section)"
                    >
                        {{ sectionRoomLabel(section) }}
                        {{ formatTimeSlot(section) }}
                        <span class="text-blue-500">{{ section.date }}</span>
                        <span class="text-sm leading-none">×</span>
                    </button>
                </div>
            </div>

            <p
                v-if="message"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700"
            >
                {{ message }}
            </p>

            <p
                v-if="errorMessage"
                class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700"
            >
                {{ errorMessage }}
            </p>

            <button
                type="submit"
                class="w-full rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="submitting || !selectedSections.length"
            >
                {{ submitting ? "送出中..." : "送出預約" }}
            </button>
        </div>
    </form>
</template>
