<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps<{
    show: boolean;
    subscription: any;
}>();

const emit = defineEmits(['close']);

const isCancelling = ref(false);
const isUpdating = ref(false);
const autoRenew = ref(props.subscription?.auto_renew ?? true);

const close = () => {
    emit('close');
};

const toggleAutoRenew = () => {
    isUpdating.value = true;
    router.post(route('billing.toggle-auto-renew'), { 
        auto_renew: autoRenew.value 
    }, {
        onFinish: () => {
            isUpdating.value = false;
        },
        preserveScroll: true
    });
};

const cancelSubscription = () => {
    if (!confirm('Are you sure you want to cancel your subscription? You will lose access to premium features at the end of your billing cycle.')) {
        return;
    }
    isCancelling.value = true;
    router.post(route('billing.cancel'), {}, {
        onFinish: () => {
            isCancelling.value = false;
            close();
        }
    });
};

const formatDate = (date: string) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    });
};
</script>

<template>
    <Modal :show="show" @close="close" max-width="lg">
        <div class="p-0 overflow-hidden bg-[#0A0A0B]/90 backdrop-blur-3xl border border-white/5 rounded-[2.5rem] relative">
            <div class="absolute -right-24 -top-24 w-64 h-64 bg-[#F3E7C9]/10 blur-[120px] rounded-full pointer-events-none"></div>
            
            <!-- Header with Gradient Area -->
            <div class="p-10 pb-8 bg-white/[0.02] border-b border-white/5 relative overflow-hidden">
                <div class="relative">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 rounded-2xl bg-[#F3E7C9]/10 border border-[#F3E7C9]/20 flex items-center justify-center text-[#F3E7C9]">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold tracking-tight text-white uppercase mt-1">Manage Subscription</h2>
                    </div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest leading-none ml-1">View and adjust your subscription settings below.</p>
                </div>
            </div>

            <div class="p-10 pt-8 space-y-8 relative z-10">
                <!-- 1. Active Plan Section -->
                <div class="grid grid-cols-2 gap-6">
                    <div class="p-5 rounded-2xl bg-white/5 border border-white/5">
                        <span class="text-[9px] uppercase font-black text-slate-500 tracking-widest block mb-2">Current Plan</span>
                        <div class="flex items-center gap-3">
                            <span class="text-lg font-bold tracking-tight text-white uppercase">{{ subscription?.plan_type }}</span>
                            <span class="px-2 py-0.5 rounded bg-[#CBB48A]/10 text-[#CBB48A] text-[8px] font-bold tracking-wider border border-[#CBB48A]/20 mt-0.5">ACTIVE</span>
                        </div>
                    </div>
                    <div class="p-5 rounded-2xl bg-white/5 border border-white/5">
                        <span class="text-[9px] uppercase font-black text-slate-500 tracking-widest block mb-2">Next Renewal</span>
                        <span class="text-lg font-bold tracking-tight text-white uppercase">{{ formatDate(subscription?.ends_at) }}</span>
                    </div>
                </div>

                <!-- 2. Auto Renew Logic -->
                <div class="flex items-center justify-between p-5 rounded-2xl bg-white/5 border border-white/5">
                    <div>
                        <h4 class="text-sm font-bold tracking-tight text-white uppercase">Auto Renewal</h4>
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-1">Keep your account active without interruptions</p>
                    </div>
                    <button 
                        @click="autoRenew = !autoRenew; toggleAutoRenew()"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ring-offset-2 ring-offset-[#0A0A0B] ring-[#F3E7C9]"
                        :class="autoRenew ? 'bg-[#F3E7C9]' : 'bg-white/10'"
                    >
                        <span class="sr-only">Toggle Auto Renewal</span>
                        <span 
                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                            :class="autoRenew ? 'translate-x-5' : 'translate-x-0'"
                        />
                    </button>
                </div>

                <!-- 3. Payment Method -->
                <div>
                    <h4 class="text-[9px] uppercase font-black text-slate-500 tracking-widest mb-4">Payment Method</h4>
                    <div class="flex items-center justify-between p-5 rounded-2xl bg-white/5 border border-white/5 group hover:border-[#F3E7C9]/30 transition-all cursor-pointer">
                        <div class="flex items-center gap-5">
                            <div class="h-12 w-16 rounded-xl bg-[#0A0A0B] border border-white/10 flex items-center justify-center text-[10px] font-black text-slate-400 italic">VISA</div>
                            <div>
                                <p class="text-sm font-bold tracking-tight text-white uppercase">Visa ending in 4242</p>
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-1">Expires 12/2026</p>
                            </div>
                        </div>
                        <button class="text-[10px] font-bold tracking-wider text-[#F3E7C9] hover:text-[#F3E7C9] transition-colors">UPDATE</button>
                    </div>
                </div>

                <!-- 4. Billing Address -->
                <div>
                    <h4 class="text-[9px] uppercase font-black text-slate-500 tracking-widest mb-4">Billing Address</h4>
                    <div class="p-5 rounded-2xl bg-white/5 border border-white/5 flex items-center justify-between">
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest leading-relaxed max-w-[200px]">
                            123 Forensic Street, Sovereign Plaza<br>
                            San Francisco, CA 94103
                        </p>
                        <button class="text-[10px] font-bold tracking-wider text-slate-600 hover:text-white transition-colors">EDIT</button>
                    </div>
                </div>

                <!-- 5. Quick Links & Cancel -->
                <div class="pt-6 border-t border-white/5 flex items-center justify-between">
                    <a :href="route('billing.invoice.show', 'INV-LAST')" target="_blank" class="flex items-center gap-3 text-[10px] font-bold tracking-wider text-slate-600 hover:text-[#F3E7C9] transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                        </svg>
                        Download Last Receipt
                    </a>
                    
                    <button 
                        @click="cancelSubscription"
                        class="text-[10px] font-bold tracking-wider text-rose-500/70 hover:text-rose-500 transition-colors"
                    >
                        Cancel Subscription
                    </button>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-6 bg-white/[0.02] border-t border-white/5 flex justify-end">
                <button 
                    @click="close"
                    class="px-8 py-3 rounded-xl bg-[#F3E7C9] text-slate-950 text-sm font-bold tracking-tight hover:bg-[#F3E7C9] transition-all shadow-lg shadow-[#F3E7C9]/10"
                >
                    Save and Close
                </button>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
.scale-animation {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: .7; transform: scale(1.1); }
}
</style>
