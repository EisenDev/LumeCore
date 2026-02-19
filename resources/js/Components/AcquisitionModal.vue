<script setup lang="ts">
import { computed, ref } from 'vue';
import type { VaultAsset } from '@/types/vault';

interface Props {
    show: boolean;
    asset: VaultAsset;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'confirm'): void;
}>();

const isProcessing = ref(false);

const prices = computed(() => {
    const base = Number(props.asset.price || 0);
    const fee = base * 0.05;
    return {
        base,
        fee,
        total: base + fee
    };
});

const handlePayment = () => {
    isProcessing.value = true;
    // Simulate payment delay
    setTimeout(() => {
        emit('confirm');
        isProcessing.value = false;
    }, 2000);
};

// Helper for currency
const fmt = (n: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n);
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/90 backdrop-blur-md" @click="$emit('close')"></div>
        
        <!-- Modal -->
        <div class="relative w-full max-w-lg bg-[#0a0f1a]/90 border border-indigo-500/30 rounded-2xl shadow-2xl overflow-hidden flex flex-col backdrop-blur-xl">
            
            <!-- Header -->
            <div class="px-6 py-4 border-b border-indigo-500/20 bg-indigo-900/10 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-500/20 rounded-lg border border-indigo-500/30">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                    </div>
                    <h2 class="text-lg font-bold text-white tracking-wide">Secure Asset Acquisition</h2>
                </div>
                <button @click="$emit('close')" class="text-slate-500 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="p-6 space-y-6">
                
                <!-- Asset Summary Card -->
                <div class="flex gap-4 p-4 bg-slate-900/50 rounded-xl border border-slate-700/50">
                    <div class="w-16 h-16 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center text-2xl">
                        {{ asset.mime_type === 'image/png' ? '🖼️' : '📦' }}
                    </div>
                    <div>
                        <h3 class="font-bold text-white text-sm mb-1">{{ asset.metadata?.custom_name || asset.file_name }}</h3>
                        <div class="flex gap-2 text-[10px] text-slate-400 font-mono uppercase">
                            <span class="px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700">SHA-256 Verified</span>
                            <span class="px-1.5 py-0.5 rounded bg-emerald-900/30 text-emerald-400 border border-emerald-500/30">Score: {{ asset.metadata?.score || 0 }}</span>
                        </div>
                    </div>
                </div>

                <!-- Price Breakdown -->
                <div class="space-y-3">
                    <div class="flex justify-between text-sm text-slate-400">
                        <span>Asset Price</span>
                        <span class="font-mono text-white">{{ fmt(prices.base) }}</span>
                    </div>
                    <div class="flex justify-between text-sm text-slate-400">
                        <span>LUME Escrow Fee (5%)</span>
                        <span class="font-mono text-white">{{ fmt(prices.fee) }}</span>
                    </div>
                    <div class="h-px bg-slate-700/50 my-2"></div>
                    <div class="flex justify-between items-end">
                        <span class="text-sm font-bold text-slate-200 uppercase tracking-widest">Total</span>
                        <span class="text-2xl font-black text-indigo-400 font-mono">{{ fmt(prices.total) }}</span>
                    </div>
                </div>

                <!-- Payment Placeholder -->
                <div class="p-4 bg-slate-950 rounded-xl border border-slate-800 space-y-3">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-xs font-bold text-slate-500 uppercase">Secure Gateway Connection</span>
                    </div>
                    <!-- Fake Stripe Input -->
                    <div class="h-10 bg-slate-900 border border-slate-700 rounded px-3 flex items-center text-sm text-slate-500 font-mono tracking-wider">
                        •••• •••• •••• 4242
                    </div>
                    <div class="flex gap-3">
                        <div class="h-10 flex-1 bg-slate-900 border border-slate-700 rounded px-3 flex items-center text-sm text-slate-500 font-mono">
                            MM / YY
                        </div>
                        <div class="h-10 w-24 bg-slate-900 border border-slate-700 rounded px-3 flex items-center text-sm text-slate-500 font-mono">
                            CVC
                        </div>
                    </div>
                    <div class="flex justify-center gap-4 mt-2 opacity-50 grayscale hover:grayscale-0 transition-all">
                        <span class="text-xs font-bold italic text-slate-600">VISA</span>
                        <span class="text-xs font-bold italic text-slate-600">Mastercard</span>
                        <span class="text-xs font-bold italic text-slate-600">Stripe</span>
                    </div>
                </div>

                <!-- Guarantee -->
                <div class="p-3 bg-indigo-500/10 border border-indigo-500/20 rounded-lg flex gap-3 items-start">
                    <svg class="w-5 h-5 text-indigo-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                    <p class="text-xs text-indigo-300 leading-relaxed">
                        <strong class="text-indigo-200">LUME Guarantee:</strong> Funds are held in 7-Day Escrow. If the source code does not match the LUME Audit, you are entitled to a full refund.
                    </p>
                </div>
                
                <!-- Action -->
                <button @click="handlePayment" :disabled="isProcessing"
                    class="w-full py-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-sm uppercase tracking-wider shadow-lg shadow-indigo-500/25 transition-all flex items-center justify-center gap-2 relative overflow-hidden group">
                    <div class="absolute inset-0 bg-white/10 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                    <span v-if="!isProcessing">Confirm Secure Payment</span>
                    <span v-else class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Processing Transaction...
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
