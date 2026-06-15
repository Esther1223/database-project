<script setup>
import axios from "axios";
import { computed, inject, reactive, ref } from "vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";
import Head from "../../../support/HeadTitle.vue";

const props = defineProps({
    affiliations: {
        type: Array,
        required: true,
    },
});

const affiliationForm = reactive({
    name: "",
});
const affiliationErrors = reactive({});
const affiliationEditingId = ref(null);
const affiliationSubmitting = ref(false);
const notice = ref("");
const showFormModal = ref(false);
const deleteConfirm = reactive({ show: false, affiliation: null });
const reloadPageData = inject("reloadPageData", async () => {});

const affiliationCount = computed(() => props.affiliations.length);

const clearErrors = (errors) => {
    Object.keys(errors).forEach((key) => {
        delete errors[key];
    });
};

const resetAffiliationForm = () => {
    affiliationForm.name = "";
    affiliationEditingId.value = null;
    clearErrors(affiliationErrors);
};

const openAffiliationForm = (affiliation = null) => {
    clearErrors(affiliationErrors);
    showFormModal.value = true;

    if (!affiliation) {
        resetAffiliationForm();
        return;
    }

    affiliationForm.name = affiliation.name;
    affiliationEditingId.value = affiliation.id;
};

const closeAffiliationForm = () => {
    showFormModal.value = false;
    resetAffiliationForm();
};

const saveAffiliation = async () => {
    affiliationSubmitting.value = true;
    notice.value = "";
    clearErrors(affiliationErrors);

    const payload = {
        name: affiliationForm.name,
    };

    try {
        if (affiliationEditingId.value) {
            await axios.put(
                `/admin/affiliations/${affiliationEditingId.value}`,
                payload,
            );
            notice.value = "單位已更新";
        } else {
            await axios.post("/admin/affiliations", payload);
            notice.value = "單位已建立";
        }

        closeAffiliationForm();
        await reloadPageData();
    } catch (error) {
        if (error.response?.status === 422) {
            Object.assign(affiliationErrors, error.response.data?.errors || {});
        } else {
            notice.value = error.response?.data?.message || "單位儲存失敗";
        }
    } finally {
        affiliationSubmitting.value = false;
    }
};

const deleteAffiliation = (affiliation) => {
    deleteConfirm.affiliation = affiliation;
    deleteConfirm.show = true;
};

const confirmDeleteAffiliation = async () => {
    const affiliation = deleteConfirm.affiliation;
    if (!affiliation) return;

    deleteConfirm.show = false;
    notice.value = "";

    try {
        await axios.delete(`/admin/affiliations/${affiliation.id}`);
        notice.value = "單位已刪除";
        if (affiliationEditingId.value === affiliation.id) {
            closeAffiliationForm();
        }
        await reloadPageData();
    } catch (error) {
        notice.value = error.response?.data?.message || "單位刪除失敗";
    } finally {
        deleteConfirm.affiliation = null;
    }
};

const cancelDeleteAffiliation = () => {
    deleteConfirm.show = false;
    deleteConfirm.affiliation = null;
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
                                Affiliations
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
                            @click="openAffiliationForm()"
                        >
                            新增單位
                        </button>
                    </div>

                    <div
                        class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600"
                    >
                        共 {{ affiliationCount }} 筆
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
                        v-if="affiliations.length"
                        class="divide-y divide-slate-200"
                    >
                        <div
                            v-for="affiliation in affiliations"
                            :key="affiliation.id"
                            class="grid grid-cols-1 gap-4 px-6 py-5 lg:grid-cols-[1fr_160px] lg:items-center"
                        >
                            <div>
                                <p class="text-lg font-semibold text-slate-950">
                                    {{ affiliation.name }}
                                </p>
                            </div>
                            <div
                                class="flex justify-start gap-2 lg:justify-end"
                            >
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                    @click="openAffiliationForm(affiliation)"
                                >
                                    修改
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50"
                                    @click="deleteAffiliation(affiliation)"
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
                    v-if="deleteConfirm.show"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-8"
                    @click.self="cancelDeleteAffiliation"
                >
                    <div class="w-full max-w-sm rounded-[2rem] bg-white p-6 shadow-2xl">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                                    確認刪除
                                </p>
                                <h3 class="mt-2 text-xl font-semibold text-slate-950">
                                    刪除單位
                                </h3>
                            </div>
                            <button
                                type="button"
                                class="rounded-full border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                @click="cancelDeleteAffiliation"
                            >
                                關閉
                            </button>
                        </div>
                        <p class="mt-4 text-sm text-slate-600">
                            確定要刪除單位「<span class="font-semibold text-slate-950">{{ deleteConfirm.affiliation?.name }}</span>」嗎？此操作無法復原。
                        </p>
                        <div class="mt-6 flex gap-3">
                            <button
                                type="button"
                                class="flex-1 rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                @click="cancelDeleteAffiliation"
                            >
                                取消
                            </button>
                            <button
                                type="button"
                                class="flex-1 rounded-2xl bg-rose-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-rose-700"
                                @click="confirmDeleteAffiliation"
                            >
                                確認刪除
                            </button>
                        </div>
                    </div>
                </div>
            </teleport>

            <teleport to="body">
                <div
                    v-if="showFormModal"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-8"
                    @click.self="closeAffiliationForm"
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
                                        affiliationEditingId
                                            ? "修改單位"
                                            : "新增單位"
                                    }}
                                </h3>
                            </div>
                            <button
                                type="button"
                                class="rounded-full border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                @click="closeAffiliationForm"
                            >
                                關閉
                            </button>
                        </div>

                        <form
                            class="mt-6 space-y-4"
                            @submit.prevent="saveAffiliation"
                        >
                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-slate-700"
                                    for="name"
                                    >單位名稱</label
                                >
                                <input
                                    id="name"
                                    v-model="affiliationForm.name"
                                    type="text"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-900"
                                    placeholder="例如：資訊工程學系"
                                />
                                <p
                                    v-if="affiliationErrors.name"
                                    class="mt-2 text-sm text-rose-700"
                                >
                                    {{ affiliationErrors.name[0] }}
                                </p>
                            </div>

                            <div class="flex gap-3 pt-2">
                                <button
                                    type="submit"
                                    class="flex-1 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="affiliationSubmitting"
                                >
                                    {{
                                        affiliationSubmitting
                                            ? "儲存中..."
                                            : affiliationEditingId
                                              ? "更新單位"
                                              : "建立單位"
                                    }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </teleport>
        </AuthenticatedLayout>
    </div>
</template>
