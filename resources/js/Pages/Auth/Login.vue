<script setup>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import GuestLayout from '../../Layouts/GuestLayout.vue';

const form = reactive({
    email: '',
    password: '',
});

const loading = ref(false);
const error = ref('');

const submit = async () => {
    loading.value = true;
    error.value = '';

    try {
        await axios.post('/api/login', {
            email: form.email,
            password: form.password,
        });

        window.location.href = '/dashboard';
    } catch (exception) {
        error.value = exception.response?.data?.message || '登入失敗，請重試';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Head title="Login" />

    <GuestLayout title="登入">
        <div class="max-w-md rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="email">電子郵件</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none ring-0 transition focus:border-slate-900"
                        placeholder="請輸入電子郵件"
                    >
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="password">密碼</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none ring-0 transition focus:border-slate-900"
                        placeholder="請輸入密碼"
                    >
                </div>

                <p v-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ error }}
                </p>

                <button
                    type="submit"
                    class="w-full rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="loading"
                >
                    {{ loading ? '登入中...' : '登入' }}
                </button>
            </form>
        </div>
    </GuestLayout>
</template>