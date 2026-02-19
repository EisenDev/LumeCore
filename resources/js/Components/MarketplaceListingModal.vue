<script setup lang="ts">
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps<{
    show: boolean;
    assetName: string;
    isSubscriber?: boolean;
    isAlreadyListed?: boolean;
}>();

const emit = defineEmits(['close', 'confirm']);

const isProcessing = ref(false);
const price = ref(99.00);
const productName = ref(props.assetName || '');

const handleList = () => {
    isProcessing.value = true;
    emit('confirm', { price: price.value, name: productName.value });
};
</script>

<template>
    <Modal :show="show" @close="emit('close')" maxWidth="xl">
        <div class="relative overflow-hidden bg-[#0A0A0B] rounded-[2.5rem] border border-white/10 shadow-2xl ring-1 ring-white/5">
            <!-- Grid Background Overlay -->
            <div class="absolute inset-0 bg-grid-white/[0.02] bg-[length:30px_30px] pointer-events-none"></div>
            
            <!-- Ambient Glow -->
            <div class="absolute -top-32 -right-32 w-64 h-64 bg-emerald-500/10 rounded-full blur-[80px] pointer-events-none"></div>
            <div class="absolute -bottom-32 -left-32 w-64 h-64 bg-teal-500/5 rounded-full blur-[80px] pointer-events-none"></div>

            <div class="p-10 relative z-10">
                <!-- Header -->
                <div class="flex flex-col items-center mb-10 text-center">
                    <div class="mb-6 relative w-20 h-20 rounded-3xl bg-gradient-to-br from-emerald-500/10 to-transparent border border-white/10 flex items-center justify-center shadow-inner group overflow-hidden">
                        <div class="absolute inset-0 bg-emerald-500/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500 blur-xl"></div>
                        <svg class="h-8 w-8 text-emerald-400 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    
                    <h2 class="text-3xl font-black text-white tracking-tighter uppercase italic leading-none mb-3">
                        Secure <span class="bg-gradient-to-r from-emerald-400 to-teal-400 bg-clip-text text-transparent">Escrow</span>
                    </h2>
                    <div class="flex items-center gap-3 justify-center">
                         <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_10px_#10b981] animate-pulse"></span>
                        <p class="text-[10px] font-black text-gray-500 uppercase tracking-[0.25em]">Zero-Trust Verification</p>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="bg-emerald-500/5 border border-emerald-500/10 rounded-[1.5rem] p-6 mb-8 relative overflow-hidden group">
                     <div class="absolute left-0 top-0 bottom-0 w-1 bg-emerald-500/20 group-hover:bg-emerald-500/50 transition-colors"></div>
                    <div class="flex gap-5">
                        <div class="shrink-0 mt-0.5">
                            <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div class="space-y-3">
                             <p class="text-xs text-gray-300 font-medium leading-relaxed">
                                Your intellectual property is protected by our <span class="text-emerald-400 font-bold">zero-trust protocol</span>.
                             </p>
                             <div class="text-[10px] text-gray-500 font-mono border-t border-white/5 pt-3 mt-1">
                                Access rights transferred via smart contract settlement.
                             </div>
                        </div>
                    </div>
                </div>

                <!-- Product Name Input -->
                <div class="mb-6 space-y-3">
                    <label class="flex justify-between items-center text-[10px] font-black text-gray-500 uppercase tracking-[0.2em]">
                        <span>Asset Identifier</span>
                    </label>
                    <div class="relative group">
                         <div class="absolute -inset-0.5 bg-gradient-to-r from-emerald-500 to-teal-500 rounded-2xl opacity-0 group-focus-within:opacity-20 transition duration-500 blur-sm"></div>
                        <input 
                            v-model="productName"
                            type="text" 
                            class="relative w-full bg-[#050505] border border-white/10 rounded-xl px-5 py-4 text-sm text-gray-200 placeholder-gray-600 focus:outline-none focus:border-emerald-500/50 focus:ring-0 transition-all font-medium tracking-wide"
                            placeholder="Designate Asset Name"
                        />
                    </div>
                </div>

                <!-- Price Input -->
                <div class="mb-10 space-y-3">
                    <label class="flex justify-between items-center text-[10px] font-black text-gray-500 uppercase tracking-[0.2em]">
                        <span>Listing Valuation</span>
                        <span class="text-emerald-400" v-if="price == 0">FREE ASSET</span>
                    </label>
                    <div class="relative group">
                        <div class="absolute -inset-0.5 bg-gradient-to-r from-emerald-500 to-teal-500 rounded-2xl opacity-0 group-focus-within:opacity-20 transition duration-500 blur-sm"></div>
                        <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none z-20">
                            <span class="text-emerald-500 font-mono text-lg">$</span>
                        </div>
                        <input 
                            v-model.number="price"
                            type="number" 
                            min="0.00" 
                            step="0.01"
                            class="relative w-full bg-[#050505] border border-white/10 rounded-xl py-4 pl-10 pr-5 text-gray-200 font-mono text-lg focus:outline-none focus:border-emerald-500/50 focus:ring-0 transition-all placeholder-gray-600"
                            placeholder="0.00"
                        />
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-4">
                    <button 
                        @click="emit('close')" 
                        class="flex-1 py-4 text-[10px] font-black text-gray-500 uppercase tracking-[0.25em] hover:text-white hover:bg-white/5 rounded-xl transition-all border border-transparent hover:border-white/10"
                    >
                        Terminate
                    </button>
                    <button 
                        @click="handleList" 
                        :disabled="isProcessing || price === null || price < 0"
                        class="flex-[2] py-4 bg-white text-black font-black uppercase tracking-[0.25em] text-[10px] rounded-xl hover:scale-[1.02] active:scale-[0.98] transition-all shadow-[0_0_20px_rgba(255,255,255,0.1)] hover:shadow-[0_0_30px_rgba(255,255,255,0.2)] disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none flex items-center justify-center gap-3 relative overflow-hidden group"
                    >
                        <span class="relative z-10" v-if="!isProcessing">
                            Initialize Listing <span class="opacity-50 ml-1" v-if="price > 0">${{ price?.toFixed(2) }}</span>
                            <span class="opacity-50 ml-1" v-else>(Free)</span>
                        </span>
                        <span class="relative z-10 flex items-center gap-2" v-else>
                             <svg class="animate-spin h-4 w-4 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Processing Protocol
                        </span>
                        <div class="absolute inset-0 bg-gradient-to-r from-emerald-500/10 to-teal-500/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
