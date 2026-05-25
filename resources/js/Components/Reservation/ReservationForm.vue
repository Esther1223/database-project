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

const formatTimeSlot = (section) =>
    section?.time_slot?.label ||
    timeSlotMap[section?.time_slot_id] ||
    section?.time_slot_id ||
    "-";
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
                <p class="mt-1">費率：NT$ {{ selectedRoom.rate }} / 小時</p>
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
                            {{ selectedSections[0].date }}
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
                        {{ formatTimeSlot(section) }}
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
