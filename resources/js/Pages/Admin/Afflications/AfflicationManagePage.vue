<script setup>
import axios from "axios";
import { Head, router } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    afflications: {
        type: Array,
        required: true,
    },
});

const afflicationForm = reactive({
    name: "",
});
const afflicationErrors = reactive({});
const afflicationEditingId = ref(null);
const afflicationSubmitting = ref(false);
const notice = ref("");
const showFormModal = ref(false);

const afflicationCount = computed(() => props.afflications.length);

const clearErrors = (errors) => {
    Object.keys(errors).forEach((key) => {
        delete errors[key];
    });
};

const resetAfflicationForm = () => {
    afflicationForm.name = "";
    afflicationEditingId.value = null;
    clearErrors(afflicationErrors);
};

const openAfflicationForm = (afflication = null) => {
    clearErrors(afflicationErrors);
    showFormModal.value = true;

    if (!afflication) {
        resetAfflicationForm();
        return;
    }

    afflicationForm.name = afflication.name;
    afflicationEditingId.value = afflication.id;
};

const closeAfflicationForm = () => {
    showFormModal.value = false;
    resetAfflicationForm();
};

const saveAfflication = async () => {
    afflicationSubmitting.value = true;
    notice.value = "";
    clearErrors(afflicationErrors);

    const payload = {
        name: afflicationForm.name,
    };

    try {
        if (afflicationEditingId.value) {
            await axios.put(
                `/admin/afflications/${afflicationEditingId.value}`,
                payload,
            );
            notice.value = "單位已更新";
        } else {
            await axios.post("/admin/afflications", payload);
            notice.value = "單位已建立";
        }

        closeAfflicationForm();
        router.reload({ preserveScroll: true, preserveState: true });
    } catch (error) {
        if (error.response?.status === 422) {
            Object.assign(afflicationErrors, error.response.data?.errors || {});
        } else {
            notice.value = error.response?.data?.message || "單位儲存失敗";
        }
    } finally {
        afflicationSubmitting.value = false;
    }
};

const deleteAfflication = async (afflication) => {
    if (!window.confirm(`確定刪除單位「${afflication.name}」嗎？`)) {
        return;
    }

    notice.value = "";

    try {
        await axios.delete(`/admin/afflications/${afflication.id}`);
        notice.value = "單位已刪除";
        if (afflicationEditingId.value === afflication.id) {
            closeAfflicationForm();
        }
        router.reload({ preserveScroll: true, preserveState: true });
    } catch (error) {
        notice.value = error.response?.data?.message || "單位刪除失敗";
    }
};
</script>

<template>
    <div>
        <Head title="單位管理" />

        <AuthenticatedLayout title="單位管理">
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
                                Afflications
                            </p>
                            <h2
                                class="mt-3 text-3xl font-semibold text-slate-950"
                            >
                                單位管理
                            </h2>
                        </div>

                        <button
                            type="button"
                            class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
                            @click="openAfflicationForm()"
                        >
                            新增單位
                        </button>
                    </div>

                    <div
                        class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600"
                    >
                        共 {{ afflicationCount }} 筆
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="grid grid-cols-[1fr_160px] gap-4 border-b border-slate-200 bg-slate-50 px-6 py-4 text-sm font-semibold text-slate-600"
                    >
                        <div>單位名稱</div>
                        <div class="text-right">操作</div>
                    </div>

                    <div
                        v-if="afflications.length"
                        class="divide-y divide-slate-200"
                    >
                        <div
                            v-for="afflication in afflications"
                            :key="afflication.id"
                            class="grid grid-cols-1 gap-4 px-6 py-5 lg:grid-cols-[1fr_160px] lg:items-center"
                        >
                            <div>
                                <p class="text-lg font-semibold text-slate-950">
                                    {{ afflication.name }}
                                </p>
                            </div>
                            <div
                                class="flex justify-start gap-2 lg:justify-end"
                            >
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                    @click="openAfflicationForm(afflication)"
                                >
                                    修改
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50"
                                    @click="deleteAfflication(afflication)"
                                >
                                    刪除
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="px-6 py-16 text-center text-slate-500">
                        目前沒有單位資料。
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
                    @click.self="closeAfflicationForm"
                >
                    <div
                        class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-[2rem] bg-white p-6 shadow-2xl"
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
                                        afflicationEditingId
                                            ? "修改單位"
                                            : "新增單位"
                                    }}
                                </h3>
                            </div>
                            <button
                                type="button"
                                class="rounded-full border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                @click="closeAfflicationForm"
                            >
                                關閉
                            </button>
                        </div>

                        <form
                            class="mt-6 space-y-4"
                            @submit.prevent="saveAfflication"
                        >
                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-slate-700"
                                    for="name"
                                    >單位名稱</label
                                >
                                <input
                                    id="name"
                                    v-model="afflicationForm.name"
                                    type="text"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                                    placeholder="例如：資訊工程學系"
                                />
                                <p
                                    v-if="afflicationErrors.name"
                                    class="mt-2 text-sm text-rose-700"
                                >
                                    {{ afflicationErrors.name[0] }}
                                </p>
                            </div>

                            <div class="flex gap-3 pt-2">
                                <button
                                    type="submit"
                                    class="flex-1 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="afflicationSubmitting"
                                >
                                    {{
                                        afflicationSubmitting
                                            ? "儲存中..."
                                            : afflicationEditingId
                                              ? "更新單位"
                                              : "建立單位"
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                    @click="closeAfflicationForm"
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
