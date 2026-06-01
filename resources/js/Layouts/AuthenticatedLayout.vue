<script setup>
import axios from "axios";
import { computed, onMounted, ref } from "vue";

defineProps({
    title: {
        type: String,
        default: "Dashboard",
    },
});

const currentUser = ref(null);
const loggingOut = ref(false);
const isNavOpen = ref(true);

const isAdmin = computed(() =>
    currentUser.value?.roles?.some((role) => role.role_type === "管理員"),
);
const isStaff = computed(() =>
    currentUser.value?.roles?.some((role) => role.role_type === "行政人員"),
);
const canReserve = computed(() =>
    currentUser.value?.roles?.some((role) =>
        ["行政人員", "教授", "學生"].includes(role.role_type),
    ),
);
const canManageRooms = computed(() => isAdmin.value || isStaff.value);
const canReviewApprovals = computed(() =>
    isStaff.value,
);
const canManagePayments = computed(() =>
    isStaff.value,
);

const loadCurrentUser = async () => {
    try {
        const response = await axios.get("/api/user");
        currentUser.value = response.data;
    } catch {
        currentUser.value = null;
    }
};

onMounted(() => {
    loadCurrentUser();
});

const logout = async () => {
    if (loggingOut.value) {
        return;
    }

    loggingOut.value = true;

    try {
        await axios.post("/api/logout");
        window.location.href = "/login";
    } finally {
        loggingOut.value = false;
    }
};

const toggleNavigation = () => {
    isNavOpen.value = !isNavOpen.value;
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white/90 backdrop-blur">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-4">
                    <button
                        type="button"
                        class="flex h-11 w-11 shrink-0 flex-col items-center justify-center gap-1.5 rounded-2xl border border-slate-300 text-slate-700 transition hover:bg-slate-100"
                        :aria-expanded="isNavOpen"
                        aria-label="切換導覽選單"
                        @click="toggleNavigation"
                    >
                        <span class="h-0.5 w-5 rounded-full bg-current"></span>
                        <span class="h-0.5 w-5 rounded-full bg-current"></span>
                        <span class="h-0.5 w-5 rounded-full bg-current"></span>
                    </button>

                    <div>
                        <h1 class="text-2xl font-semibold">校園教室預約系統</h1>
                        <p v-if="currentUser" class="mt-1 text-sm text-slate-500">
                            {{ currentUser.name }} · {{ currentUser.email }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a
                        href="/dashboard"
                        class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                    >
                        儀表板
                    </a>
                    <button
                        type="button"
                        class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="loggingOut"
                        @click="logout"
                    >
                        {{ loggingOut ? "登出中..." : "登出" }}
                    </button>
                </div>
            </div>
        </header>

        <main
            class="mx-auto flex max-w-7xl gap-6 px-6 py-10 transition-all duration-300 ease-out"
        >
            <aside
                class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm transition-all duration-300 ease-out lg:sticky lg:top-6 lg:h-fit"
                :class="
                    isNavOpen
                        ? 'w-full shrink-0 p-5 opacity-100 translate-x-0 lg:w-[260px]'
                        : 'w-0 shrink-0 border-transparent p-0 opacity-0 -translate-x-4'
                "
            >
                <div class="w-[220px] lg:w-[220px]">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500"
                    >
                        導覽
                    </p>

                    <nav class="mt-4 space-y-2">
                    <a
                        v-if="isAdmin"
                        href="/admin/users"
                        class="flex items-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        使用者管理
                    </a>
                    <a
                        v-if="isAdmin"
                        href="/admin/afflications"
                        class="flex items-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        單位管理
                    </a>
                    <a
                        v-if="canManageRooms"
                        href="/admin/rooms"
                        class="flex items-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        空間管理
                    </a>
                    <a
                        v-if="canReviewApprovals"
                        href="/approvals"
                        class="flex items-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        審核管理
                    </a>
                    <a
                        v-if="canManagePayments"
                        href="/admin/payments"
                        class="flex items-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        付款管理
                    </a>
                    <a
                        href="/rooms"
                        class="flex items-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        空間列表
                    </a>
                    <a
                        v-if="canReserve"
                        href="/reservations/create"
                        class="flex items-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        建立預約
                    </a>
                    <a
                        v-if="canReserve"
                        href="/reservations"
                        class="flex items-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        我的預約
                    </a>
                    </nav>
                </div>
            </aside>

            <div class="min-w-0 flex-1 transition-all duration-300 ease-out">
                <slot />
            </div>
        </main>
    </div>
</template>
