<script setup>
import axios from "axios";
import { Head, router } from "@inertiajs/vue3";
import { computed, reactive, ref, nextTick } from "vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";

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
    search: props.filters.search || "",
    type: props.filters.type || "",
    building: props.filters.building || "",
});

const roomForm = reactive({
    name: "",
    type: "",
    capacity: 1,
    building: "",
    affiliation_id: "",
    information: "",
    need_approval: false,
    is_open_access: false,
    open_access_all: false,
    open_access_affiliations: [],
    _temp_open_affiliation_id: "",
});

const roomErrors = reactive({});
const roomEditingId = ref(null);
const roomSubmitting = ref(false);
const busyKey = ref("");
const notice = ref("");
const showFormModal = ref(false);
const priceModalRoom = ref(null);
const slotPrices = reactive({});
const slotPriceErrors = reactive({});

const pageNumbers = computed(() => {
    const lastPage = props.rooms.meta.last_page || 1;

    return Array.from({ length: lastPage }, (_, index) => index + 1);
});

const clearErrors = (errors) => {
    Object.keys(errors).forEach((key) => {
        delete errors[key];
    });
};

const buildParams = () => {
    const params = {};

    if (form.search.trim() !== "") {
        params.search = form.search.trim();
    }

    if (form.type !== "") {
        params.type = form.type;
    }

    if (form.building !== "") {
        params.building = form.building;
    }

    return params;
};

const pageUrl = (page) => {
    const params = new URLSearchParams();
    const filters = buildParams();

    Object.entries(filters).forEach(([key, value]) => {
        params.set(key, value);
    });

    params.set("page", page);

    return `/admin/rooms?${params.toString()}`;
};

const applyFilters = () => {
    router.get("/admin/rooms", buildParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    form.search = "";
    form.type = "";
    form.building = "";
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

const resetRoomForm = () => {
    roomForm.name = "";
    roomForm.type = "";
    roomForm.capacity = 1;
    roomForm.building = "";
    roomForm.affiliation_id = "";
    roomForm.information = "";
    roomForm.need_approval = false;
    roomForm.is_open_access = false;
    roomForm.open_access_all = false;
    roomForm.open_access_affiliations = [];
    roomForm._temp_open_affiliation_id = "";
    roomEditingId.value = null;
    clearErrors(roomErrors);
};

const openRoomForm = (room = null) => {
    clearErrors(roomErrors);
    showFormModal.value = true;

    if (!room) {
        resetRoomForm();
        return;
    }

    roomForm.name = room.name;
    roomForm.type = room.type;
    roomForm.capacity = room.capacity;
    roomForm.building = room.building;
    roomForm.affiliation_id = room.affiliation_id ?? "";
    roomForm.information = room.information || "";
    roomForm.need_approval = room.need_approval;
    roomForm.is_open_access = room.is_open_access;
    roomForm.open_access_all = room.open_access_all;
    roomForm.open_access_affiliations = room.open_access_all
        ? []
        : (room.open_access_affiliations || []).map((d) =>
              typeof d === "object" ? d.id : d,
          );
    roomForm._temp_open_affiliation_id = "";
    roomEditingId.value = room.id;
};

const closeRoomForm = () => {
    showFormModal.value = false;
    resetRoomForm();
};

const openSlotPriceModal = (room) => {
    priceModalRoom.value = room;
    Object.keys(slotPrices).forEach((key) => delete slotPrices[key]);
    clearErrors(slotPriceErrors);

    (room.time_slots || []).forEach((slot) => {
        slotPrices[slot.id] = slot.price ?? 0;
    });
};

const closeSlotPriceModal = () => {
    if (busyKey.value.startsWith("slot-price-")) {
        return;
    }

    priceModalRoom.value = null;
    Object.keys(slotPrices).forEach((key) => delete slotPrices[key]);
    clearErrors(slotPriceErrors);
};

const saveSlotPrice = async (slot) => {
    if (!slot?.id) {
        return;
    }

    const price = Number(slotPrices[slot.id] ?? 0);

    if (!Number.isInteger(price) || price < 0) {
        slotPriceErrors[slot.id] = "價格必須是 0 以上整數";
        return;
    }

    busyKey.value = `slot-price-${slot.id}`;
    delete slotPriceErrors[slot.id];
    notice.value = "";

    try {
        const response = await axios.patch(`/time-slots/${slot.id}/status`, {
            price,
        });
        slot.price = response.data.data?.price ?? price;
        slotPrices[slot.id] = slot.price;
        notice.value = "時段價格已更新";
    } catch (error) {
        slotPriceErrors[slot.id] =
            error.response?.data?.message || "時段價格更新失敗";
    } finally {
        busyKey.value = "";
    }
};

const saveRoom = async () => {
    roomSubmitting.value = true;
    notice.value = "";
    clearErrors(roomErrors);

    const payload = {
        name: roomForm.name,
        type: roomForm.type,
        capacity: roomForm.capacity,
        building: roomForm.building,
        affiliation_id: roomForm.affiliation_id || null,
        information: roomForm.information,
        need_approval: roomForm.need_approval,
        is_open_access: roomForm.is_open_access,
        open_access_all: roomForm.is_open_access
            ? roomForm.open_access_all
            : false,
        open_access_affiliations:
            roomForm.is_open_access && !roomForm.open_access_all
                ? roomForm.open_access_affiliations || []
                : [],
    };

    try {
        if (roomEditingId.value) {
            await axios.put(`/admin/rooms/${roomEditingId.value}`, payload);
            notice.value = "空間已更新";
        } else {
            await axios.post("/admin/rooms", payload);
            notice.value = "空間已建立";
        }

        closeRoomForm();
        router.reload({ preserveScroll: true, preserveState: true });
    } catch (error) {
        if (error.response?.status === 422) {
            Object.assign(roomErrors, error.response.data?.errors || {});
        } else {
            notice.value = error.response?.data?.message || "空間儲存失敗";
        }
    } finally {
        roomSubmitting.value = false;
    }
};

const addOpenAffiliation = () => {
    if (roomForm.open_access_all) return;

    const val = roomForm._temp_open_affiliation_id;

    if (!val) return;

    const id = Number(val);
    const affiliationId = Number(roomForm.affiliation_id);

    if (id === affiliationId) {
        roomForm._temp_open_affiliation_id = "";
        return;
    }

    if (!roomForm.open_access_affiliations.includes(id)) {
        roomForm.open_access_affiliations.push(id);
    }

    roomForm._temp_open_affiliation_id = "";
};

const removeOpenAffiliation = (id) => {
    roomForm.open_access_affiliations = roomForm.open_access_affiliations.filter(
        (x) => x !== id,
    );
};

const getAffiliationName = (id) => {
    const d = props.affiliations.find((item) => item.id === id);
    return d ? d.name : "未知單位";
};

const openDeptSelectRef = ref(null);

const onToggleOpenAccess = async () => {
    if (roomForm.is_open_access) {
        await nextTick();
        openDeptSelectRef.value?.focus?.();
    } else {
        roomForm.open_access_all = false;
        roomForm.open_access_affiliations = [];
        roomForm._temp_open_affiliation_id = "";
    }
};

const onToggleOpenAccessAll = () => {
    if (roomForm.open_access_all) {
        roomForm.open_access_affiliations = [];
        roomForm._temp_open_affiliation_id = "";
    }
};

const syncOpenAffiliations = () => {
    const affiliationId = Number(roomForm.affiliation_id);

    roomForm.open_access_affiliations = roomForm.open_access_affiliations.filter(
        (id) => Number(id) !== affiliationId,
    );

    if (Number(roomForm._temp_open_affiliation_id) === affiliationId) {
        roomForm._temp_open_affiliation_id = "";
    }
};

const deleteRoom = async (room) => {
    if (!window.confirm(`確定刪除空間「${room.name}」嗎？`)) {
        return;
    }

    busyKey.value = `delete-room-${room.id}`;
    notice.value = "";

    try {
        await axios.delete(`/admin/rooms/${room.id}`);
        notice.value = "空間已刪除";

        if (roomEditingId.value === room.id) {
            closeRoomForm();
        }

        router.reload({ preserveScroll: true, preserveState: true });
    } catch (error) {
        notice.value = error.response?.data?.message || "空間刪除失敗";
    } finally {
        busyKey.value = "";
    }
};

const affiliationName = (room) => {
    const affiliation = props.affiliations.find(
        (item) => item.id === room.affiliation_id,
    );

    return affiliation?.name || "未知單位";
};

const slotPriceSummary = (room) => {
    const prices = [
        ...new Set((room.time_slots || []).map((slot) => Number(slot.price || 0))),
    ];

    if (prices.length === 0) return "尚未建立時段";
    if (prices.length === 1) return `每時段 NT$ ${prices[0]}`;

    return `NT$ ${Math.min(...prices)} - ${Math.max(...prices)}`;
};
</script>

<template>
    <div>
        <Head title="空間管理" />

        <AuthenticatedLayout title="空間管理">
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
                                Rooms
                            </p>
                            <h2
                                class="mt-3 text-3xl font-semibold text-slate-950"
                            >
                                空間管理
                            </h2>
                        </div>

                        <button
                            type="button"
                            class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
                            @click="openRoomForm()"
                        >
                            新增空間
                        </button>
                    </div>

                    <div
                        class="mt-5 grid gap-4 lg:grid-cols-[1fr_1fr_1fr_auto]"
                    >
                        <input
                            v-model="form.search"
                            type="text"
                            class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                            placeholder="搜尋名稱、類型、建築或設備資訊"
                        />
                        <select
                            v-model="form.type"
                            class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                        >
                            <option value="">全部類型</option>
                            <option
                                v-for="type in roomTypes"
                                :key="type"
                                :value="type"
                            >
                                {{ type }}
                            </option>
                        </select>
                        <select
                            v-model="form.building"
                            class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                        >
                            <option value="">全部建築</option>
                            <option
                                v-for="building in buildings"
                                :key="building"
                                :value="building"
                            >
                                {{ building }}
                            </option>
                        </select>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
                                @click="applyFilters"
                            >
                                搜尋
                            </button>
                            <button
                                type="button"
                                class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                @click="resetFilters"
                            >
                                重設
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_auto]">
                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600 lg:text-center"
                        >
                            共 {{ rooms.meta.total }} 筆
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600 lg:text-center"
                        >
                            第 {{ rooms.meta.current_page }} /
                            {{ rooms.meta.last_page }} 頁
                        </div>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="grid grid-cols-[1.4fr_1fr_1fr_260px] gap-4 border-b border-slate-200 bg-slate-50 px-6 py-4 text-sm font-semibold text-slate-600"
                    >
                        <div>空間資料</div>
                        <div>容量 / 費率</div>
                        <div>建築 / 單位</div>
                        <div class="text-right">操作</div>
                    </div>

                    <div
                        v-if="rooms.data.length"
                        class="divide-y divide-slate-200"
                    >
                        <div
                            v-for="room in rooms.data"
                            :key="room.id"
                            class="grid grid-cols-1 gap-4 px-6 py-5 lg:grid-cols-[1.4fr_1fr_1fr_260px] lg:items-center"
                        >
                            <div>
                                <p class="text-lg font-semibold text-slate-950">
                                    {{ room.name }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ room.type }}
                                </p>
                                <p
                                    class="mt-2 line-clamp-2 text-sm text-slate-600"
                                >
                                    {{ room.information || "尚未提供設備資訊" }}
                                </p>
                            </div>

                            <div class="text-sm text-slate-700">
                                <p>容量：{{ room.capacity }} 人</p>
                                <p class="mt-1">
                                    費率：NT$ {{ room.rate }} / 小時
                                </p>
                                <p class="mt-1 text-slate-500">
                                    時段價格：{{ slotPriceSummary(room) }}
                                </p>
                            </div>

                            <div class="text-sm text-slate-700">
                                <p>{{ room.building }}</p>
                                <p class="mt-1 text-slate-500">
                                    {{ affiliationName(room) }}
                                </p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="
                                            room.need_approval
                                                ? 'bg-amber-100 text-amber-800'
                                                : 'bg-emerald-100 text-emerald-800'
                                        "
                                    >
                                        {{
                                            room.need_approval
                                                ? "需審核"
                                                : "可直接預約"
                                        }}
                                    </span>
                                    <span
                                        v-if="
                                            room.is_open_access &&
                                            !room.open_access_all &&
                                            room.open_access_affiliations
                                                ?.length > 0
                                        "
                                        class="inline-flex rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold text-slate-700"
                                    >
                                        白名單開放
                                    </span>
                                    <span
                                        v-if="room.open_access_all"
                                        class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700"
                                    >
                                        所有人可借用
                                    </span>
                                </div>
                            </div>

                            <div
                                class="flex flex-nowrap justify-start gap-2 lg:justify-end"
                            >
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                    @click="openSlotPriceModal(room)"
                                >
                                    時段價格
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                    @click="openRoomForm(room)"
                                >
                                    修改
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="
                                        busyKey === `delete-room-${room.id}`
                                    "
                                    @click="deleteRoom(room)"
                                >
                                    {{
                                        busyKey === `delete-room-${room.id}`
                                            ? "..."
                                            : "刪除"
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="px-6 py-16 text-center text-slate-500">
                        沒有符合條件的空間
                    </div>
                </div>

                <div
                    v-if="rooms.meta.last_page > 1"
                    class="flex flex-wrap items-center justify-between gap-4 rounded-[2rem] border border-slate-200 bg-white px-6 py-4 shadow-sm"
                >
                    <p class="text-sm text-slate-600">
                        顯示 {{ rooms.meta.from || 0 }} -
                        {{ rooms.meta.to || 0 }} 筆，共
                        {{ rooms.meta.total }} 筆
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="!rooms.meta.prev_page_url"
                            @click="goToPage(rooms.meta.prev_page_url)"
                        >
                            上一頁
                        </button>
                        <button
                            v-for="page in pageNumbers"
                            :key="page"
                            type="button"
                            class="rounded-xl px-3 py-2 text-sm font-semibold transition"
                            :class="
                                page === rooms.meta.current_page
                                    ? 'bg-slate-900 text-white'
                                    : 'border border-slate-300 text-slate-700 hover:bg-slate-100'
                            "
                            @click="goToPage(pageUrl(page))"
                        >
                            {{ page }}
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="!rooms.meta.next_page_url"
                            @click="goToPage(rooms.meta.next_page_url)"
                        >
                            下一頁
                        </button>
                    </div>
                </div>

                <p
                    v-if="notice"
                    class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700"
                >
                    {{ notice }}
                </p>
            </section>

            <teleport to="body">
                <div
                    v-if="priceModalRoom"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-8"
                    @click.self="closeSlotPriceModal"
                >
                    <div
                        class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-[2rem] bg-white p-6 shadow-2xl"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p
                                    class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500"
                                >
                                    Time Slot Prices
                                </p>
                                <h3
                                    class="mt-2 text-2xl font-semibold text-slate-950"
                                >
                                    {{ priceModalRoom.name }} 時段價格
                                </h3>
                            </div>
                            <button
                                type="button"
                                class="rounded-full border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="busyKey.startsWith('slot-price-')"
                                @click="closeSlotPriceModal"
                            >
                                關閉
                            </button>
                        </div>

                        <div
                            class="mt-5 overflow-hidden rounded-2xl border border-slate-200"
                        >
                            <div
                                class="grid grid-cols-[1fr_160px_96px] gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600"
                            >
                                <div>時段</div>
                                <div>價格</div>
                                <div class="text-right">操作</div>
                            </div>

                            <div class="divide-y divide-slate-200">
                                <div
                                    v-for="slot in priceModalRoom.time_slots || []"
                                    :key="slot.id"
                                    class="grid grid-cols-[1fr_160px_96px] gap-4 px-4 py-3 text-sm md:items-center"
                                >
                                    <div>
                                        <p class="font-semibold text-slate-950">
                                            {{ slot.label }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            period {{ slot.period }}
                                        </p>
                                    </div>
                                    <div>
                                        <input
                                            v-model.number="slotPrices[slot.id]"
                                            type="number"
                                            min="0"
                                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-slate-900 outline-none transition focus:border-slate-900"
                                        />
                                        <p
                                            v-if="slotPriceErrors[slot.id]"
                                            class="mt-1 text-xs font-semibold text-rose-700"
                                        >
                                            {{ slotPriceErrors[slot.id] }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <button
                                            type="button"
                                            class="rounded-xl border border-slate-900 bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="
                                                busyKey ===
                                                `slot-price-${slot.id}`
                                            "
                                            @click="saveSlotPrice(slot)"
                                        >
                                            {{
                                                busyKey ===
                                                `slot-price-${slot.id}`
                                                    ? "儲存中"
                                                    : "儲存"
                                            }}
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-if="!priceModalRoom.time_slots?.length"
                                    class="px-4 py-10 text-center text-sm text-slate-500"
                                >
                                    尚未建立時段資料。
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="showFormModal"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-8"
                    @click.self="closeRoomForm"
                >
                    <div
                        class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-[2rem] bg-white p-6 shadow-2xl"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p
                                    class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500"
                                >
                                    空間表單
                                </p>
                                <h3
                                    class="mt-2 text-2xl font-semibold text-slate-950"
                                >
                                    {{
                                        roomEditingId ? "修改空間" : "新增空間"
                                    }}
                                </h3>
                            </div>
                            <button
                                type="button"
                                class="rounded-full border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                @click="closeRoomForm"
                            >
                                關閉
                            </button>
                        </div>

                        <form class="mt-6 space-y-4" @submit.prevent="saveRoom">
                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-slate-700"
                                    for="name"
                                    >空間名稱</label
                                >
                                <input
                                    id="name"
                                    v-model="roomForm.name"
                                    type="text"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                                    placeholder="例如：A101 會議室"
                                />
                                <p
                                    v-if="roomErrors.name"
                                    class="mt-2 text-sm text-rose-700"
                                >
                                    {{ roomErrors.name[0] }}
                                </p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-2 block text-sm font-medium text-slate-700"
                                        for="type"
                                        >空間類型</label
                                    >
                                    <input
                                        id="type"
                                        v-model="roomForm.type"
                                        type="text"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                                        placeholder="例如：會議室"
                                    />
                                    <p
                                        v-if="roomErrors.type"
                                        class="mt-2 text-sm text-rose-700"
                                    >
                                        {{ roomErrors.type[0] }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        class="mb-2 block text-sm font-medium text-slate-700"
                                        for="building"
                                        >所在建築</label
                                    >
                                    <input
                                        id="building"
                                        v-model="roomForm.building"
                                        type="text"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                                        placeholder="例如：行政大樓"
                                    />
                                    <p
                                        v-if="roomErrors.building"
                                        class="mt-2 text-sm text-rose-700"
                                    >
                                        {{ roomErrors.building[0] }}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-slate-700"
                                    for="affiliation"
                                    >所屬單位</label
                                >
                                <select
                                    id="affiliation"
                                    v-model="roomForm.affiliation_id"
                                    @change="syncOpenAffiliations"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                                >
                                    <option value="" disabled>
                                        請選擇所屬單位
                                    </option>
                                    <option
                                        v-for="affiliation in affiliations"
                                        :key="affiliation.id"
                                        :value="affiliation.id"
                                    >
                                        {{ affiliation.name }}
                                    </option>
                                </select>
                                <p
                                    v-if="roomErrors.affiliation_id"
                                    class="mt-2 text-sm text-rose-700"
                                >
                                    {{ roomErrors.affiliation_id[0] }}
                                </p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-2 block text-sm font-medium text-slate-700"
                                        for="capacity"
                                        >容量</label
                                    >
                                    <input
                                        id="capacity"
                                        v-model.number="roomForm.capacity"
                                        type="number"
                                        min="1"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                                        placeholder="請輸入容量"
                                    />
                                    <p
                                        v-if="roomErrors.capacity"
                                        class="mt-2 text-sm text-rose-700"
                                    >
                                        {{ roomErrors.capacity[0] }}
                                    </p>
                                </div>

                            </div>

                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-slate-700"
                                    for="information"
                                    >設備資訊</label
                                >
                                <textarea
                                    id="information"
                                    v-model="roomForm.information"
                                    rows="4"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                                    placeholder="例如：含投影機、白板與視訊設備。"
                                ></textarea>
                                <p
                                    v-if="roomErrors.information"
                                    class="mt-2 text-sm text-rose-700"
                                >
                                    {{ roomErrors.information[0] }}
                                </p>
                            </div>

                            <label
                                class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
                            >
                                <input
                                    v-model="roomForm.need_approval"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-slate-900"
                                />
                                <span class="text-sm font-medium text-slate-700"
                                    >需要審核才能預約</span
                                >
                            </label>
                            <p
                                v-if="roomErrors.need_approval"
                                class="text-sm text-rose-700"
                            >
                                {{ roomErrors.need_approval[0] }}
                            </p>

                            <label
                                class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
                            >
                                <input
                                    v-model="roomForm.is_open_access"
                                    @change="onToggleOpenAccess"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-slate-900"
                                />
                                <span class="text-sm font-medium text-slate-700"
                                    >開放白名單單位借用</span
                                >
                            </label>
                            <p
                                v-if="roomErrors.is_open_access"
                                class="text-sm text-rose-700"
                            >
                                {{ roomErrors.is_open_access[0] }}
                            </p>

                            <div
                                v-show="roomForm.is_open_access"
                                class="space-y-3"
                            >
                                <label
                                    class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
                                >
                                    <input
                                        v-model="roomForm.open_access_all"
                                        @change="onToggleOpenAccessAll"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 text-slate-900"
                                    />
                                    <span
                                        class="text-sm font-medium text-slate-700"
                                        >所有人可借用</span
                                    >
                                </label>
                                <p
                                    v-if="roomForm.open_access_all"
                                    class="text-sm text-slate-600"
                                >
                                    勾選後，任何單位都可以借用此空間。
                                </p>
                                <template v-else>
                                    <p class="text-sm text-slate-600">
                                        選擇可借用此空間的其他單位：
                                    </p>
                                    <div class="flex items-center gap-2">
                                        <select
                                            ref="openDeptSelectRef"
                                            v-model="
                                                roomForm._temp_open_affiliation_id
                                            "
                                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                                        >
                                            <option value="">請選擇單位</option>
                                            <option
                                                v-for="affiliation in affiliations.filter(
                                                    (d) =>
                                                        String(d.id) !==
                                                        String(
                                                            roomForm.affiliation_id,
                                                        ),
                                                )"
                                                :key="affiliation.id"
                                                :value="affiliation.id"
                                            >
                                                {{ affiliation.name }}
                                            </option>
                                        </select>
                                        <button
                                            type="button"
                                            class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                            :disabled="
                                                !roomForm._temp_open_affiliation_id
                                            "
                                            @click="addOpenAffiliation"
                                        >
                                            +
                                        </button>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="deptId in roomForm.open_access_affiliations"
                                            :key="deptId"
                                            class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700"
                                        >
                                            <span>{{
                                                getAffiliationName(deptId)
                                            }}</span>
                                            <button
                                                type="button"
                                                class="rounded-full text-sm text-slate-500 hover:text-slate-700"
                                                @click="
                                                    removeOpenAffiliation(deptId)
                                                "
                                                aria-label="移除"
                                            >
                                                ×
                                            </button>
                                        </span>
                                        <p
                                            v-if="
                                                roomForm.open_access_affiliations
                                                    .length === 0
                                            "
                                            class="text-sm text-slate-500"
                                        >
                                            尚未選擇其他單位
                                        </p>
                                    </div>
                                    <p
                                        v-if="
                                            roomErrors.open_access_affiliations
                                        "
                                        class="mt-2 text-sm text-rose-700"
                                    >
                                        {{
                                            roomErrors
                                                .open_access_affiliations[0]
                                        }}
                                    </p>
                                </template>
                            </div>

                            <div class="flex gap-3 pt-2">
                                <button
                                    type="submit"
                                    class="flex-1 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="roomSubmitting"
                                >
                                    {{
                                        roomSubmitting
                                            ? "儲存中..."
                                            : roomEditingId
                                              ? "更新空間"
                                              : "建立空間"
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                    @click="closeRoomForm"
                                >
                                    取消
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </teleport>
        </AuthenticatedLayout>
    </div>
</template>
