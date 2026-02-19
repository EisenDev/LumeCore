<script setup lang="ts">
import Modal from '@/Components/Modal.vue';

defineProps<{
    show: boolean;
    title?: string;
    message?: string;
}>();
</script>

<template>
    <Modal :show="show" :maxWidth="'sm'" :closeable="false">
        <div class="p-8 bg-slate-900 text-center border border-indigo-500/30 rounded-lg">
            <!-- Hexagon Spinner -->
            <div class="relative w-20 h-20 mx-auto mb-6">
                 <div class="absolute inset-0 border-4 border-indigo-500/20 border-t-indigo-500 rounded-full animate-spin"></div>
                 <div class="absolute inset-2 border-4 border-emerald-500/20 border-b-emerald-500 rounded-full animate-spin-reverse"></div>
                 <div class="absolute inset-0 flex items-center justify-center">
                    <svg class="w-8 h-8 text-indigo-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                 </div>
            </div>

            <h3 class="text-xl font-bold text-white mb-2 tracking-widest uppercase">{{ title || 'Processing' }}</h3>
            <p class="text-slate-400 font-mono text-sm animate-pulse">{{ message || 'Analyzing Vector Divergence...' }}</p>
            
            <!-- Matrix Terminal Lines -->
            <div class="mt-6 text-xs font-mono text-left space-y-1 text-emerald-500/50 overflow-hidden h-16 bg-black/20 p-2 rounded">
                <div class="animate-pulse">> INIT_PROTOCOL_SYNC_V2</div>
                <div class="animate-pulse delay-75">> CALIBRATING_HASH_VECTORS...</div>
                <div class="animate-pulse delay-150">> FETCHING_GIT_HEAD...</div>
                <div class="animate-pulse delay-300">> COMPARING_DOM_TREES...</div>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
.animate-spin-reverse {
    animation: spin 3s linear infinite reverse;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
