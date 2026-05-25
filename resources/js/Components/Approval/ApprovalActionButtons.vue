<template>
  <div class="flex gap-2">
    <button
      :disabled="loading"
      @click="onApprove"
      class="rounded-xl border border-emerald-200 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 disabled:opacity-60"
    >
      {{ loading && action === 'approve' ? '核准中...' : '核准' }}
    </button>

    <button
      :disabled="loading"
      @click="onReject"
      class="rounded-xl border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-60"
    >
      {{ loading && action === 'reject' ? '拒絕中...' : '拒絕' }}
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({
  reservationId: { type: Number, required: true },
})

const emit = defineEmits(['updated', 'failed'])

const loading = ref(false)
const action = ref(null)

async function onApprove() {
  loading.value = true
  action.value = 'approve'
  try {
    const res = await axios.patch('/approvals/approve', {
      reservation_id: props.reservationId,
      decision: 'approved',
    })
    emit('updated', res.data.data, res.data.message || '已核准')
  } catch (e) {
    emit('failed', e.response?.data?.message || '核准失敗')
  } finally {
    loading.value = false
    action.value = null
  }
}

async function onReject() {
  loading.value = true
  action.value = 'reject'
  try {
    const res = await axios.patch('/approvals/reject', {
      reservation_id: props.reservationId,
      decision: 'rejected',
    })
    emit('updated', res.data.data, res.data.message || '已拒絕')
  } catch (e) {
    emit('failed', e.response?.data?.message || '拒絕失敗')
  } finally {
    loading.value = false
    action.value = null
  }
}
</script>

<style scoped>
</style>
