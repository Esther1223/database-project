<script setup>
defineProps({
    title: {
        type: String,
        default: 'Welcome',
    },
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <div class="mx-auto flex min-h-screen w-full max-w-6xl items-center px-6 py-10">
            <div class="w-full">
                <div class="mb-8 text-sm uppercase tracking-[0.35em] text-slate-500">database project</div>
                <h1 class="text-4xl font-semibold tracking-tight sm:text-6xl">{{ title }}</h1>
                <div class="mt-10">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>