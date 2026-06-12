<script setup>
import axios from "axios";
import { Head, router } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    roles: {
        type: Array,
        required: true,
    },
});

const roleForm = reactive({
    role_type: "",
});
const roleErrors = reactive({});
const roleEditingId = ref(null);
const roleSubmitting = ref(false);
const notice = ref("");
const showFormModal = ref(false);

const roleCount = computed(() => props.roles.length);

const clearErrors = () => {
    Object.keys(roleErrors).forEach((key) => {
        delete roleErrors[key];
    });
};

const resetRoleForm = () => {
    roleForm.role_type = "";
    roleEditingId.value = null;
    clearErrors();
};

const openRoleForm = (role = null) => {
    clearErrors();
    showFormModal.value = true;

    if (!role) {
        resetRoleForm();
        return;
    }

    roleForm.role_type = role.role_type;
    roleEditingId.value = role.id;
};

const closeRoleForm = () => {
    showFormModal.value = false;
    resetRoleForm();
};

const saveRole = async () => {
    roleSubmitting.value = true;
    notice.value = "";
    clearErrors();

    try {
        if (roleEditingId.value) {
            await axios.put(`/admin/roles/${roleEditingId.value}`, roleForm);
            notice.value = "角色已更新";
        } else {
            await axios.post("/admin/roles", roleForm);
            notice.value = "角色已建立";
        }

        closeRoleForm();
        router.reload({ preserveScroll: true, preserveState: true });
    } catch (error) {
        if (error.response?.status === 422) {
            Object.assign(roleErrors, error.response.data?.errors || {});
        } else {
            notice.value = error.response?.data?.message || "角色儲存失敗";
        }
    } finally {
        roleSubmitting.value = false;
    }
};

const deleteRole = async (role) => {
    if (!window.confirm(`確定刪除角色「${role.role_type}」嗎？`)) {
        return;
    }

    notice.value = "";

    try {
        await axios.delete(`/admin/roles/${role.id}`);
        notice.value = "角色已刪除";
        router.reload({ preserveScroll: true, preserveState: true });
    } catch (error) {
        notice.value = error.response?.data?.message || "角色刪除失敗";
    }
};
</script>

<template>
    <div>
        <Head title="角色管理" />

        <AuthenticatedLayout title="角色管理">
            <section class="space-y-6">
                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                                Roles
                            </p>
                            <h2 class="mt-3 text-3xl font-semibold text-slate-950">
                                角色管理
                            </h2>
                        </div>

                        <button
                            type="button"
                            class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
                            @click="openRoleForm()"
                        >
                            新增角色
                        </button>
                    </div>

                    <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600">
                        共 {{ roleCount }} 筆
                    </div>
                </div>

                <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                    <div class="grid grid-cols-[1fr_140px_180px] gap-4 border-b border-slate-200 bg-slate-50 px-6 py-4 text-sm font-semibold text-slate-600">
                        <div>角色名稱</div>
                        <div>使用者數</div>
                        <div class="text-right">操作</div>
                    </div>

                    <div v-if="roles.length" class="divide-y divide-slate-200">
                        <div
                            v-for="role in roles"
                            :key="role.id"
                            class="grid grid-cols-1 gap-4 px-6 py-5 lg:grid-cols-[1fr_140px_180px] lg:items-center"
                        >
                            <p class="text-lg font-semibold text-slate-950">
                                {{ role.role_type }}
                            </p>
                            <p class="text-sm font-medium text-slate-600">
                                {{ role.users_count }} 人
                            </p>
                            <div class="flex justify-start gap-2 lg:justify-end">
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                    @click="openRoleForm(role)"
                                >
                                    修改
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="role.users_count > 0"
                                    @click="deleteRole(role)"
                                >
                                    刪除
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="px-6 py-16 text-center text-slate-500">
                        目前沒有角色資料。
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
                    @click.self="closeRoleForm"
                >
                    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-[2rem] bg-white p-6 shadow-2xl">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                                    表單
                                </p>
                                <h3 class="mt-2 text-2xl font-semibold text-slate-950">
                                    {{ roleEditingId ? "修改角色" : "新增角色" }}
                                </h3>
                            </div>
                            <button
                                type="button"
                                class="rounded-full border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                @click="closeRoleForm"
                            >
                                關閉
                            </button>
                        </div>

                        <form class="mt-6 space-y-4" @submit.prevent="saveRole">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-slate-700" for="role_type">
                                    角色名稱
                                </label>
                                <input
                                    id="role_type"
                                    v-model="roleForm.role_type"
                                    type="text"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                                    placeholder="例如：學生"
                                />
                                <p v-if="roleErrors.role_type" class="mt-2 text-sm text-rose-700">
                                    {{ roleErrors.role_type[0] }}
                                </p>
                            </div>

                            <div class="flex gap-3 pt-2">
                                <button
                                    type="submit"
                                    class="flex-1 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="roleSubmitting"
                                >
                                    {{ roleSubmitting ? "儲存中..." : roleEditingId ? "更新角色" : "建立角色" }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                    @click="closeRoleForm"
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
