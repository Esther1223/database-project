<script setup>
import axios from "axios";
import { Head, router } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    users: {
        type: Array,
        required: true,
    },
    roles: {
        type: Array,
        required: true,
    },
    affiliations: {
        type: Array,
        required: true,
    },
});

const userForm = reactive({
    name: "",
    email: "",
    affiliation_id: "",
    password: "",
    role_ids: [],
});
const userErrors = reactive({});
const userEditingId = ref(null);
const userSubmitting = ref(false);
const busyKey = ref("");
const notice = ref("");
const showFormModal = ref(false);
const searchKeyword = ref("");

const userCount = computed(() => props.users.length);
const filteredUsers = computed(() => {
    const keyword = searchKeyword.value.trim().toLowerCase();

    if (!keyword) {
        return props.users;
    }

    return props.users.filter((user) => {
        const haystack = [
            user.name,
            user.email,
            user.affiliation_name,
            ...(user.roles || []).map((role) => role.role_type),
        ]
            .join(" ")
            .toLowerCase();

        return haystack.includes(keyword);
    });
});

const clearErrors = (errors) => {
    Object.keys(errors).forEach((key) => {
        delete errors[key];
    });
};

const resetUserForm = () => {
    userForm.name = "";
    userForm.email = "";
    userForm.affiliation_id = "";
    userForm.password = "";
    userForm.role_ids = [];
    userEditingId.value = null;
    clearErrors(userErrors);
};

const openUserForm = (user = null) => {
    clearErrors(userErrors);
    showFormModal.value = true;

    if (!user) {
        resetUserForm();
        return;
    }

    userForm.name = user.name;
    userForm.email = user.email;
    userForm.affiliation_id = user.affiliation_id ?? "";
    userForm.password = "";
    userForm.role_ids = user.roles.map((role) => role.id);
    userEditingId.value = user.id;
};

const closeUserForm = () => {
    showFormModal.value = false;
    resetUserForm();
};

const saveUser = async () => {
    userSubmitting.value = true;
    notice.value = "";
    clearErrors(userErrors);

    const payload = {
        name: userForm.name,
        email: userForm.email,
        affiliation_id: userForm.affiliation_id,
        role_ids: userForm.role_ids,
    };

    if (userForm.password) {
        payload.password = userForm.password;
    }

    try {
        if (userEditingId.value) {
            await axios.put(`/admin/users/${userEditingId.value}`, payload);
            notice.value = "使用者已更新";
        } else {
            await axios.post("/admin/users", payload);
            notice.value = "使用者已建立";
        }

        closeUserForm();
        router.reload({ preserveScroll: true });
    } catch (error) {
        if (error.response?.status === 422) {
            Object.assign(userErrors, error.response.data?.errors || {});
        } else {
            notice.value = error.response?.data?.message || "使用者儲存失敗";
        }
    } finally {
        userSubmitting.value = false;
    }
};

const deleteUser = async (user) => {
    // Prevent deleting admin users from the UI
    if (isAdmin(user)) {
        notice.value = "無法刪除管理員帳號";
        return;
    }

    if (!window.confirm(`確定刪除使用者「${user.name}」嗎？`)) {
        return;
    }

    busyKey.value = `delete-user-${user.id}`;
    notice.value = "";

    try {
        await axios.delete(`/admin/users/${user.id}`);
        notice.value = "使用者已刪除";
        if (userEditingId.value === user.id) {
            closeUserForm();
        }
        router.reload({ preserveScroll: true });
    } catch (error) {
        notice.value = error.response?.data?.message || "使用者刪除失敗";
    } finally {
        busyKey.value = "";
    }
};

const isAdmin = (user) => {
    if (!user || !Array.isArray(user.roles)) return false;
    return user.roles.some((role) => {
        const t = (role.role_type || "").toString().toLowerCase();
        return t === "admin" || t === "administrator" || t === "管理員";
    });
};

</script>

<template>
    <div>
        <Head title="使用者管理" />

        <AuthenticatedLayout title="使用者管理">
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
                                Users
                            </p>
                            <h2
                                class="mt-3 text-3xl font-semibold text-slate-950"
                            >
                                使用者管理
                            </h2>
                        </div>

                        <button
                            type="button"
                            class="rounded-2xl border border-slate-900 bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
                            @click="openUserForm()"
                        >
                            新增使用者
                        </button>
                    </div>

                    <div class="mt-5 grid gap-4 lg:grid-cols-[1fr_auto]">
                        <input
                            v-model="searchKeyword"
                            type="text"
                            class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                            placeholder="搜尋姓名、信箱、單位或角色"
                        />
                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600 lg:min-w-40 lg:text-center"
                        >
                            共 {{ filteredUsers.length }} 筆
                        </div>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="grid grid-cols-[1.5fr_1.2fr_1fr_120px] gap-4 border-b border-slate-200 bg-slate-50 px-6 py-4 text-sm font-semibold text-slate-600"
                    >
                        <div>使用者資料</div>
                        <div>所屬單位</div>
                        <div>角色</div>
                        <div class="text-right">操作</div>
                    </div>

                    <div
                        v-if="filteredUsers.length"
                        class="divide-y divide-slate-200"
                    >
                        <div
                            v-for="user in filteredUsers"
                            :key="user.id"
                            class="grid grid-cols-1 gap-4 px-6 py-5 lg:grid-cols-[1.5fr_1.2fr_1fr_120px] lg:items-center"
                        >
                            <div>
                                <p class="text-lg font-semibold text-slate-950">
                                    {{ user.name }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ user.email }}
                                </p>
                            </div>

                            <div class="text-sm text-slate-700">
                                {{
                                    user.affiliation_name ||
                                    user.affiliation ||
                                    "—"
                                }}
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="role in user.roles"
                                    :key="role.id"
                                    class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700"
                                    >{{ role.role_type }}</span
                                >
                                <span
                                    v-if="!user.roles.length"
                                    class="text-sm text-slate-400"
                                    >未設定</span
                                >
                            </div>

                            <div
                                class="flex justify-start gap-2 lg:justify-end"
                            >
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                    @click="openUserForm(user)"
                                >
                                    修改
                                </button>
                                <!-- 不顯示管理員的刪除按鈕 -->
                                <button
                                    v-if="!isAdmin(user)"
                                    type="button"
                                    :class="[
                                        'rounded-xl px-3 py-2 text-sm font-semibold transition',
                                        busyKey === `delete-user-${user.id}`
                                            ? 'opacity-70 cursor-wait'
                                            : '',
                                        'border border-rose-200 text-rose-700 hover:bg-rose-50',
                                    ]"
                                    :disabled="
                                        busyKey === `delete-user-${user.id}`
                                    "
                                    @click="deleteUser(user)"
                                >
                                    {{
                                        busyKey === `delete-user-${user.id}`
                                            ? "..."
                                            : "刪除"
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="px-6 py-16 text-center text-slate-500">
                        沒有符合條件的資料
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
                    v-if="showFormModal"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-8"
                    @click.self="closeUserForm"
                >
                    <div
                        class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-[2rem] bg-white p-6 shadow-2xl"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p
                                    class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500"
                                >
                                    表單
                                </p>
                                <h3
                                    class="mt-2 text-2xl font-semibold text-slate-950"
                                >
                                    {{
                                        userEditingId
                                            ? "修改使用者"
                                            : "新增使用者"
                                    }}
                                </h3>
                            </div>
                            <button
                                type="button"
                                class="rounded-full border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                @click="closeUserForm"
                            >
                                關閉
                            </button>
                        </div>

                        <form class="mt-6 space-y-4" @submit.prevent="saveUser">
                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-slate-700"
                                    for="name"
                                    >姓名</label
                                >
                                <input
                                    id="name"
                                    v-model="userForm.name"
                                    type="text"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                                    placeholder="請輸入姓名"
                                />
                                <p
                                    v-if="userErrors.name"
                                    class="mt-2 text-sm text-rose-700"
                                >
                                    {{ userErrors.name[0] }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-slate-700"
                                    for="email"
                                    >電子郵件</label
                                >
                                <input
                                    id="email"
                                    v-model="userForm.email"
                                    type="email"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                                    placeholder="請輸入電子郵件"
                                />
                                <p
                                    v-if="userErrors.email"
                                    class="mt-2 text-sm text-rose-700"
                                >
                                    {{ userErrors.email[0] }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-slate-700"
                                    for="affiliation"
                                    >所屬單位</label
                                >
                                <select
                                    id="affiliation"
                                    v-model="userForm.affiliation_id"
                                    required
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                                >
                                    <option value="" disabled>
                                        請選擇所屬單位（必填）
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
                                    v-if="userErrors.affiliation_id"
                                    class="mt-2 text-sm text-rose-700"
                                >
                                    {{ userErrors.affiliation_id[0] }}
                                </p>
                                <p v-else class="mt-2 text-xs text-slate-500">
                                    此欄位為必填，將作為使用者的所屬單位。
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-slate-700"
                                    for="password"
                                    >密碼</label
                                >
                                <input
                                    id="password"
                                    v-model="userForm.password"
                                    type="password"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                                    :placeholder="
                                        userEditingId
                                            ? '留空表示不變更密碼'
                                            : '請輸入密碼'
                                    "
                                />
                                <p
                                    v-if="userErrors.password"
                                    class="mt-2 text-sm text-rose-700"
                                >
                                    {{ userErrors.password[0] }}
                                </p>
                            </div>

                            <div>
                                <p
                                    class="mb-2 block text-sm font-medium text-slate-700"
                                >
                                    角色
                                </p>
                                <div
                                    class="grid grid-cols-1 gap-2 sm:grid-cols-2"
                                >
                                    <label
                                        v-for="role in roles"
                                        :key="role.id"
                                        class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
                                    >
                                        <input
                                            v-model="userForm.role_ids"
                                            type="checkbox"
                                            :value="role.id"
                                            class="h-4 w-4 rounded border-slate-300 text-slate-900"
                                        />
                                        <span
                                            class="text-sm font-medium text-slate-700"
                                            >{{ role.role_type }}</span
                                        >
                                    </label>
                                </div>
                                <p
                                    v-if="userErrors.role_ids"
                                    class="mt-2 text-sm text-rose-700"
                                >
                                    {{ userErrors.role_ids[0] }}
                                </p>
                                <p
                                    v-else-if="userForm.role_ids.length === 0"
                                    class="mt-2 text-sm text-rose-700"
                                >
                                    請至少選擇一個角色。
                                </p>
                            </div>

                            <div class="flex gap-3 pt-2">
                                <button
                                    type="submit"
                                    class="flex-1 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="
                                        userSubmitting ||
                                        userForm.role_ids.length === 0
                                    "
                                >
                                    {{
                                        userSubmitting
                                            ? "儲存中..."
                                            : userEditingId
                                              ? "更新使用者"
                                              : "建立使用者"
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                    @click="closeUserForm"
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
