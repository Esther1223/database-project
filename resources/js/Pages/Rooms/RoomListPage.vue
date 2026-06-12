<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    rooms: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    roomTypes: {
        type: Array,
        required: true,
    },
    buildings: {
        type: Array,
        required: true,
    },
    affiliations: {
        type: Array,
        required: true,
    },
});

const form = reactive({
    search: props.filters.search || '',
    type: props.filters.type || '',
    building: props.filters.building || '',
    capacity_min: props.filters.capacity_min || '',
    affiliation_id: props.filters.affiliation_id || '',
    open_access: props.filters.open_access || '',
    date: props.filters.date || '',
    period: props.filters.period || '',
});

const pageNumbers = computed(() => {
    const lastPage = props.rooms.meta.last_page || 1;

    return Array.from({ length: lastPage }, (_, index) => index + 1);
});

const buildParams = () => {
    const params = {};

    if (form.search.trim() !== '') {
        params.search = form.search.trim();
    }

    if (form.type !== '') {
        params.type = form.type;
    }

    if (form.building !== '') {
        params.building = form.building;
    }

    if (form.capacity_min !== '') {
        params.capacity_min = form.capacity_min;
    }

    if (form.affiliation_id !== '') {
        params.affiliation_id = form.affiliation_id;
    }

    if (form.open_access !== '') {
        params.open_access = form.open_access;
    }

    if (form.date !== '') {
        params.date = form.date;
    }

    if (form.period !== '') {
        params.period = form.period;
    }

    return params;
};

const pageUrl = (page) => {
    const params = new URLSearchParams();
    const filters = buildParams();

    Object.entries(filters).forEach(([key, value]) => {
        params.set(key, value);
    });

    params.set('page', page);

    return `/rooms?${params.toString()}`;
};

const applyFilters = () => {
    router.get('/rooms', buildParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    form.search = '';
    form.type = '';
    form.building = '';
    form.capacity_min = '';
    form.affiliation_id = '';
    form.open_access = '';
    form.date = '';
    form.period = '';
    applyFilters();
};

const goToPage = (url) => {
    if (!url) {
        return;
    }

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const slotPriceSummary = (room) => {
    const prices = [
        ...new Set((room.time_slots || []).map((slot) => Number(slot.price || 0))),
    ];

    if (prices.length === 0) return "尚未建立時段";
    if (prices.length === 1) return `NT$ ${prices[0]}`;

    return `NT$ ${Math.min(...prices)} - ${Math.max(...prices)}`;
};
</script>

<template>
    <div>
        <Head title="空間列表" />

        <AuthenticatedLayout title="空間列表">
        <section class="space-y-6">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">Room List</p>
                        <h2 class="mt-3 text-3xl font-semibold text-slate-950">空間列表</h2>
                    </div>

                    <a href="/dashboard" class="rounded-2xl border border-slate-900 bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700">
                        返回儀表板
                    </a>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-4">
                    <input v-model="form.search" type="text" class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900" placeholder="搜尋名稱、類型、建築或設備資訊">
                    <select v-model="form.type" class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900">
                        <option value="">全部類型</option>
                        <option v-for="type in roomTypes" :key="type" :value="type">{{ type }}</option>
                    </select>
                    <select v-model="form.building" class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900">
                        <option value="">全部建築</option>
                        <option v-for="building in buildings" :key="building" :value="building">{{ building }}</option>
                    </select>
                    <input v-model="form.capacity_min" type="number" min="1" class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900" placeholder="最低容量">
                    <select v-model="form.affiliation_id" class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900">
                        <option value="">全部單位</option>
                        <option v-for="affiliation in affiliations" :key="affiliation.id" :value="affiliation.id">{{ affiliation.name }}</option>
                    </select>
                    <select v-model="form.open_access" class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900">
                        <option value="">全部開放狀態</option>
                        <option value="1">開放跨單位</option>
                        <option value="0">僅所屬單位</option>
                    </select>
                    <input v-model="form.date" type="date" class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900">
                    <select v-model="form.period" class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900">
                        <option value="">全部時段</option>
                        <option v-for="period in 14" :key="period + 7" :value="period + 7">
                            {{ String(period + 7).padStart(2, '0') }}:00 - {{ String(period + 8).padStart(2, '0') }}:00
                        </option>
                    </select>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700" @click="applyFilters">搜尋</button>
                        <button type="button" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" @click="resetFilters">重設</button>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_auto]">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600 lg:text-center">
                        共 {{ rooms.meta.total }} 筆
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600 lg:text-center">
                        第 {{ rooms.meta.current_page }} / {{ rooms.meta.last_page }} 頁
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                <div class="grid grid-cols-[1.5fr_1fr_1fr_180px] gap-4 border-b border-slate-200 bg-slate-50 px-6 py-4 text-sm font-semibold text-slate-600">
                    <div>空間資料</div>
                    <div>容量 / 建築</div>
                    <div>費率</div>
                    <div></div>
                </div>

                <div v-if="rooms.data.length" class="divide-y divide-slate-200">
                    <div v-for="room in rooms.data" :key="room.id" class="grid grid-cols-1 gap-4 px-6 py-5 lg:grid-cols-[1.5fr_1fr_1fr_180px] lg:items-center">
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <p class="text-lg font-semibold text-slate-950">{{ room.name }}</p>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="room.need_approval ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'">
                                    {{ room.need_approval ? '需審核' : '可直接預約' }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500">{{ room.type }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ room.information || '尚未提供設備資訊' }}</p>
                        </div>

                        <div class="text-sm text-slate-700">
                            <p>容量：{{ room.capacity }} 人</p>
                            <p class="mt-1">建築：{{ room.building }}</p>
                        </div>

                        <div class="text-sm text-slate-700">
                            <p>費率：{{ slotPriceSummary(room) }}</p>
                        </div>

                        <div class="flex justify-start gap-2 lg:justify-end">
                            <Link :href="`/rooms/${room.id}`" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                                查看詳情
                            </Link>
                        </div>
                    </div>
                </div>

                <div v-else class="px-6 py-16 text-center text-slate-500">
                    沒有符合條件的空間
                </div>
            </div>

            <div v-if="rooms.meta.last_page > 1" class="flex flex-wrap items-center justify-between gap-4 rounded-[2rem] border border-slate-200 bg-white px-6 py-4 shadow-sm">
                <p class="text-sm text-slate-600">
                    顯示 {{ rooms.meta.from || 0 }} - {{ rooms.meta.to || 0 }} 筆，共 {{ rooms.meta.total }} 筆
                </p>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40" :disabled="!rooms.meta.prev_page_url" @click="goToPage(rooms.meta.prev_page_url)">
                        上一頁
                    </button>

                    <button v-for="page in pageNumbers" :key="page" type="button" class="rounded-xl px-3 py-2 text-sm font-semibold transition" :class="page === rooms.meta.current_page ? 'bg-slate-900 text-white' : 'border border-slate-300 text-slate-700 hover:bg-slate-100'" @click="goToPage(pageUrl(page))">
                        {{ page }}
                    </button>

                    <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40" :disabled="!rooms.meta.next_page_url" @click="goToPage(rooms.meta.next_page_url)">
                        下一頁
                    </button>
                </div>
            </div>
        </section>
        </AuthenticatedLayout>
    </div>
</template>
