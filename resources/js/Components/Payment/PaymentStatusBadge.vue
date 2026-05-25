<script setup>
import { computed } from "vue";

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

const statusMap = {
    unpaid: {
        text: "未付款",
        class: "bg-rose-100 text-rose-700",
    },
    paid: {
        text: "已付款",
        class: "bg-emerald-100 text-emerald-700",
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
    <span
        class="inline-flex rounded-full px-3 py-1 text-xs font-bold"
        :class="badge.class"
    >
        {{ badge.text }}
    </span>
</template>
