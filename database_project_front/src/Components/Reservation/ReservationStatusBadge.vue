<script setup>
import { computed } from "vue";

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

const statusMap = {
    pending: {
        text: "審核中",
        class: "bg-amber-100 text-amber-800",
    },
    success: {
        text: "預約成功",
        class: "bg-emerald-100 text-emerald-800",
    },
    approved: {
        text: "已核准",
        class: "bg-emerald-100 text-emerald-800",
    },
    cancelled: {
        text: "已取消",
        class: "bg-slate-200 text-slate-600",
    },
    rejected: {
        text: "已拒絕",
        class: "bg-rose-100 text-rose-700",
    },
};

const badge = computed(
    () =>
        statusMap[props.status] || {
            text: props.status,
            class: "bg-slate-100 text-slate-700",
        },
);
</script>

<template>
    <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold" :class="badge.class">
        {{ badge.text }}
    </span>
</template>
