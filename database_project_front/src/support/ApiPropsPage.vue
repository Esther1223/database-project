<script setup>
import axios from 'axios';
import { computed, provide, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const loading = ref(true);
const error = ref('');
const payload = ref(null);

const pageComponent = computed(() => route.meta.component);

const endpoint = computed(() => {
    const value = route.meta.endpoint;

    return typeof value === 'function' ? value(route) : value;
});

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const response = await axios.get(endpoint.value, {
            params: route.query,
        });
        payload.value = response.data;
    } catch (exception) {
        error.value = exception.response?.data?.message || '資料載入失敗';
        payload.value = null;
    } finally {
        loading.value = false;
    }
};

provide('reloadPageData', load);

watch(
    () => [route.fullPath, endpoint.value],
    load,
    { immediate: true },
);
</script>

<template>
    <div v-if="loading" class="min-h-screen bg-slate-50 px-6 py-10 text-slate-700">
        載入中...
    </div>
    <div v-else-if="error" class="min-h-screen bg-slate-50 px-6 py-10">
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-700">
            {{ error }}
        </div>
    </div>
    <component :is="pageComponent" v-else v-bind="payload" />
</template>
