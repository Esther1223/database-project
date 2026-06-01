<script setup>
import { computed } from 'vue';

const props = defineProps({
    slot: {
        type: Object,
        required: true,
    },
});

const slotState = computed(() => props.slot.state || 'unknown');

const slotStyles = computed(() => {
    if (slotState.value === 'reserved') {
        return 'border-amber-200 bg-amber-50 text-amber-950';
    }

    if (slotState.value === 'disabled') {
        return 'border-slate-200 bg-slate-100 text-slate-400';
    }

    if (slotState.value === 'expired') {
        return 'border-slate-200 bg-slate-50 text-slate-400';
    }

    if (slotState.value !== 'available') {
        return 'border-slate-200 bg-white text-slate-500';
    }

    return 'border-emerald-200 bg-emerald-50 text-emerald-950';
});

const slotBadgeStyles = computed(() => {
    if (slotState.value === 'reserved') {
        return 'bg-amber-100 text-amber-700';
    }

    if (slotState.value === 'disabled') {
        return 'bg-slate-200 text-slate-500';
    }

    if (slotState.value === 'expired') {
        return 'bg-slate-200 text-slate-500';
    }

    if (slotState.value !== 'available') {
        return 'bg-slate-100 text-slate-500';
    }

    return 'bg-emerald-100 text-emerald-700';
});

const slotLabel = computed(() => props.slot.time_slot?.label || props.slot.time_slot_id || '--');

const statusLabel = computed(() => {
    if (slotState.value === 'reserved') {
        return '已預約';
    }

    if (slotState.value === 'disabled') {
        return '不可借用';
    }

    if (slotState.value === 'expired') {
        return '已過時';
    }

    if (slotState.value !== 'available') {
        return '未知狀態';
    }

    return '可借用';
});
</script>

<template>
    <article class="flex h-full flex-col justify-between rounded-2xl border p-4 shadow-sm transition" :class="slotStyles">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold">{{ slotLabel }}</p>
                <p class="mt-1 text-xs opacity-80">{{ slot.date }}</p>
            </div>

            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="slotBadgeStyles">
                {{ statusLabel }}
            </span>
        </div>

        <div class="mt-4 space-y-2 text-sm">
            <p class="font-medium">
                狀態：{{ statusLabel }}
            </p>
            <p class="text-xs opacity-70">
                NT$ {{ slot.time_slot?.price ?? 0 }}
            </p>
        </div>
    </article>
</template>
