<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    room: {
        type: Object,
        required: true,
    },
    sections: {
        type: Array,
        default: () => [],
    },
    currentDate: { 
        type: String,
        required: true,
    }
});
const selectedDate = ref(props.currentDate);
watch(selectedDate, (newDate) => {
    router.get(`/rooms/${props.room.id}`, { date: newDate }, {
        preserveState: true,
        preserveScroll: true,
        only: ['sections', 'currentDate'],
    });
});
</script>

<template>
    <div>
        <Head :title="room.name" />

        <AuthenticatedLayout :title="room.name">
        <section class="space-y-6">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">Room Detail</p>
                        <h2 class="mt-3 text-3xl font-semibold text-slate-950">{{ room.name }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ room.type }} · {{ room.building }}</p>
                    </div>

                    <Link href="/rooms" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                        返回列表
                    </Link>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">容量</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ room.capacity }} 人</p>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">費率</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">NT$ {{ room.rate }} / 小時</p>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">預約規則</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ room.need_approval ? '需審核' : '直接預約' }}</p>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">設備資訊</p>
                    <p class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-700">{{ room.information || '尚未提供設備資訊' }}</p>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">基本資料</p>

                    <dl class="mt-4 space-y-4 text-sm">
                        <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                            <dt class="text-slate-500">空間類型</dt>
                            <dd class="font-medium text-slate-900">{{ room.type }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                            <dt class="text-slate-500">所在建築</dt>
                            <dd class="font-medium text-slate-900">{{ room.building }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                            <dt class="text-slate-500">費率</dt>
                            <dd class="font-medium text-slate-900">NT$ {{ room.rate }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500">審核狀態</dt>
                            <dd class="font-medium text-slate-900">{{ room.need_approval ? '需要' : '不需要' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

           <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                
                <div class="flex items-center justify-between mb-6 border-b border-slate-100 pb-4">
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                        可預約時段
                    </p>
                    <div class="flex items-center gap-3">
                        <label for="date-picker" class="text-sm font-medium text-slate-600">選擇日期：</label>
                        <input 
                            id="date-picker"
                            type="date" 
                            v-model="selectedDate" 
                            class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                    </div>
                </div>

                <div v-if="sections.length === 0" class="text-center text-slate-500 py-8">
                    這一天目前沒有時段資料。
                </div>

                <div v-else class="space-y-3">
                    <div v-for="section in sections" :key="section.time_slot_id"
                         class="flex items-center justify-between gap-4 rounded-2xl border px-5 py-4 transition-all"
                         :class="{
                            'border-slate-200 bg-slate-50 text-slate-500': section.state === 'disabled',
                            'border-slate-200 bg-slate-50 text-slate-500': section.state === 'reserved',
                            'border-blue-100 bg-blue-50/50': section.state === 'available'
                        }">
                        
                        <div class="min-w-0">
                           <p class="text-base font-semibold">{{ section.time_label }}</p>
                        </div>

                        <div class="shrink-0">
                            <span v-if="section.state === 'disabled'"
                                class="rounded-full bg-slate-200 px-3 py-1 text-xs font-bold text-slate-500">暫停開放</span>
                            
                            <span v-else-if="section.state === 'reserved'"
                                class="rounded-full bg-slate-200 px-3 py-1 text-xs font-bold text-slate-500">已被預約</span>

                            <span v-else-if="section.state === 'expired'"
                                class="rounded-full bg-slate-200 px-3 py-1 text-xs font-bold text-slate-500">已過時</span>
                            
                            <span v-else 
                                  class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">可預約</span>
                        </div>
                    </div>
                </div>
            </div> </section>
        </AuthenticatedLayout>
    </div>
</template>
