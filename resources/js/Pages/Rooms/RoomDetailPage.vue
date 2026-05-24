<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';

import axios from 'axios';

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
const timeSlotMap = {
    'TS_0000': '08:00 - 09:00',
    'TS_0100': '09:00 - 10:00',
    'TS_0200': '10:00 - 11:00',
    'TS_0300': '11:00 - 12:00',
    'TS_0400': '12:00 - 13:00',
    'TS_0500': '13:00 - 14:00',
    'TS_0600': '14:00 - 15:00',
    'TS_0700': '15:00 - 16:00',
    'TS_0800': '16:00 - 17:00',
    'TS_0900': '17:00 - 18:00',
    'TS_1000': '18:00 - 19:00',
    'TS_1100': '19:00 - 20:00',
    'TS_1200': '20:00 - 21:00',
};

const formatTimeSlot = (id) => {
    return timeSlotMap[id] || id;
};
const reserveTimeSlot = async (section) => {
    if (section.status !== 'available' || section.time_slot?.status === 'disable' || section.my_reservation) {
        return;
    }

    const timeText = formatTimeSlot(section.time_slot_id);
    
    const confirmText = props.room.need_approval 
        ? `這間教室需要行政審核，確定要送出 ${section.date} ${timeText} 的借用申請嗎？`
        : `確定要直接預約 ${section.date} ${timeText} 嗎？`;

    if (!confirm(confirmText)) {
        return; 
    }

    try {
        const response = await axios.post('/reservations', {
            room_id: props.room.id,
            section_id: section.id
        });

        alert(response.data.message);

        router.reload({ only: ['sections'] });

    } catch (error) {
        console.error('預約失敗：', error.response?.data || error.message);
        alert(error.response?.data?.message || '預約失敗了，請稍後再試！');
    }
};


</script>

<template>
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

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
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

                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">空間編號</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">#{{ room.id }}</p>
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
                    這一天目前沒有可用的時段資料。
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <div v-for="section in sections" :key="section.id" 
                         @click="reserveTimeSlot(section)" 
                         class="flex items-center justify-between rounded-2xl border p-4 transition-all"
                         :class="{
                            'border-slate-100 bg-slate-50 opacity-60 cursor-not-allowed': section.time_slot?.status === 'disable' || section.status === 'unavailable' || (section.status === 'reserved' && !section.my_reservation),
                            'border-orange-200 bg-orange-50 opacity-90 cursor-not-allowed': section.my_reservation === 'pending',
                            'border-green-200 bg-green-50 opacity-90 cursor-not-allowed': section.my_reservation === 'success',
                            'border-blue-100 bg-blue-50/50 hover:border-blue-300 hover:shadow-md cursor-pointer': section.status === 'available' && section.time_slot?.status !== 'disable' && !section.my_reservation
                         }">
                        
                        <div>
                           <p class="text-sm font-semibold text-slate-900">{{ section.date }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ formatTimeSlot(section.time_slot_id) }}</p>
                        </div>

                        <div>
                            <span v-if="section.time_slot?.status === 'disable' || section.status === 'unavailable'" 
                                  class="text-xs font-semibold text-slate-400">暫停開放</span>
                            
                            <span v-else-if="section.my_reservation === 'pending'" 
                                  class="rounded-full bg-orange-100 px-3 py-1 text-xs font-bold text-orange-600">你的申請審核中</span>
                            
                            <span v-else-if="section.my_reservation === 'success'" 
                                  class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700">預約成功</span>
                            
                            <span v-else-if="section.status === 'reserved'" 
                                  class="text-xs font-semibold text-slate-400">已被預約</span>
                            
                            <span v-else-if="section.status === 'available'" 
                                  class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">可預約</span>
                        </div>
                    </div>
                </div>
            </div> </section>
    </AuthenticatedLayout>
</template>