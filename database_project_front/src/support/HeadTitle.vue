<script setup>
import { watchEffect } from 'vue';

const props = defineProps({
    title: {
        type: String,
        default: '',
    },
});

watchEffect(() => {
    document.title = props.title
        ? `${props.title} - 校園教室預約系統`
        : '校園教室預約系統';
});
</script>

<template></template>
