<script setup>
import axios from 'axios';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';
import AvailabilitySlot from '../../Components/Room/AvailabilitySlot.vue';

const props = defineProps({
    rooms: {
        type: Array,
        default: () => [],
    },
    initialRoomId: {
        type: [String, Number],
        default: null,
    },
    initialDate: {
        type: String,
        default: () => new Date().toISOString().slice(0, 10),
    },
});

const selectedRoomId = ref(props.initialRoomId || props.rooms[0]?.id || null);
const selectedDate = ref(props.initialDate);
const loading = ref(false);
const errorMessage = ref('');
const slots = ref([]);

const selectedRoom = computed(() => props.rooms.find((room) => String(room.id) === String(selectedRoomId.value)) || null);

const slotOrder = (slot) => {
    const numericId = Number(slot.time_slot_id);

    return Number.isFinite(numericId) ? numericId : Number.MAX_SAFE_INTEGER;
};

const buildFallbackSlots = () => {
    return Array.from({ length: 13 }, (_, index) => {
        const hour = index + 8;

        return {
            id: null,
            room_id: selectedRoomId.value,
            date: selectedDate.value,
            time_slot_id: String(hour),
            status: 'available',
            state: 'available',
            time_slot: {
                id: null,
                time_slot_id: String(hour),
                status: 'enable',
                label: `${String(hour).padStart(2, '0')}:00 - ${String(hour + 1).padStart(2, '0')}:00`,
            },
        };
    });
};

const orderedSlots = computed(() => {
    const source = slots.value.length > 0 ? slots.value : buildFallbackSlots();

    return [...source].sort((left, right) => slotOrder(left) - slotOrder(right));
});

const counts = computed(() => ({
    available: orderedSlots.value.filter((slot) => slot.state === 'available').length,
    reserved: orderedSlots.value.filter((slot) => slot.state === 'reserved').length,
}));

const loadSlots = async () => {
    if (!selectedRoomId.value) {
        slots.value = [];
        return;
    }

    loading.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.get(`/rooms/${selectedRoomId.value}/available-sections`, { params: { date: selectedDate.value } });

        slots.value = response.data.data || buildFallbackSlots();
    } catch (error) {
        console.error('讀取可借時段失敗：', error);
        errorMessage.value = error.response?.data?.message || '讀取可借時段失敗，請稍後再試。';
        slots.value = buildFallbackSlots();
    } finally {
        loading.value = false;
    }
};

watch([selectedRoomId, selectedDate], loadSlots, { immediate: true });
</script>

<template>
    <Head title="教室可借時段" />

    <AuthenticatedLayout title="教室可借時段">
        <section class="space-y-6">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">Room Availability</p>
                        <h2 class="mt-3 text-3xl font-semibold text-slate-950">查詢可借時段</h2>
                        <p class="mt-2 text-sm text-slate-600">先選房間，再選日期，畫面會同步顯示所有時段的可借狀態。</p>
                    </div>

                    <Link href="/rooms" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                        回到空間列表
                    </Link>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-[1fr_220px_auto]">
                    <label class="space-y-2 text-sm font-medium text-slate-700">
                        <span>房間</span>
                        <select v-model="selectedRoomId" class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900">
                            <option v-for="room in rooms" :key="room.id" :value="room.id">
                                {{ room.name }}
                            </option>
                        </select>
                    </label>

                    <label class="space-y-2 text-sm font-medium text-slate-700">
                        <span>日期</span>
                        <input v-model="selectedDate" type="date" class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900">
                    </label>

                    <div class="flex items-end">
                        <button type="button" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" @click="loadSlots">
                            重新查詢
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-[2rem] border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                    <p class="text-sm text-emerald-700">可借用</p>
                    <p class="mt-2 text-3xl font-semibold text-emerald-950">{{ counts.available }}</p>
                </div>

                <div class="rounded-[2rem] border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <p class="text-sm text-amber-700">已預約</p>
                    <p class="mt-2 text-3xl font-semibold text-amber-950">{{ counts.reserved }}</p>
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">{{ selectedRoom?.name || '請先選擇房間' }}</p>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ selectedRoom?.building || '' }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs font-semibold text-slate-600">
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-700">可借用</span>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-700">已預約</span>
                        <span class="rounded-full bg-slate-200 px-3 py-1 text-slate-500">全域停用</span>
                    </div>
                </div>

                <div v-if="errorMessage" class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ errorMessage }}
                </div>

                <div v-else-if="loading" class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-12 text-center text-sm text-slate-500">
                    讀取中...
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    <AvailabilitySlot v-for="slot in orderedSlots" :key="slot.time_slot_id" :slot="slot" />
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>