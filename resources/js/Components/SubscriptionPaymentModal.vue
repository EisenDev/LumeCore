<script setup lang="ts">
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps<{
    show: boolean;
    plan: {
        name: string;
        price: string;
        badge?: string;
        type?: string; 
    } | null;
}>();

const emit = defineEmits(['close']);

const isProcessing = ref(false);
const isSuccess = ref(false);

const cardHolder = ref('');
const cardNumber = ref('');
const expiry = ref('');
const cvc = ref('');

// Input formatting
watch(cardNumber, (val) => {
    // Remove all non-digits
    let digits = val.replace(/\D/g, '');
    // Limit to 16 digits
    digits = digits.substring(0, 16);
    // Add spaces every 4 digits
    cardNumber.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ');
});

watch(expiry, (val) => {
    // Remove all non-digits
    let digits = val.replace(/\D/g, '');
    // Limit to 4 digits (MMYY)
    digits = digits.substring(0, 4);
    
    if (digits.length >= 2) {
        expiry.value = digits.substring(0, 2) + ' / ' + digits.substring(2);
    } else {
        expiry.value = digits;
    }
});

watch(cvc, (val) => {
    // Remove all non-digits
    let digits = val.replace(/\D/g, '');
    // Limit to 3 digits
    cvc.value = digits.substring(0, 3);
});

const handlePayment = () => {
    const planType = props.plan?.type;
    if (!planType) return;

    isProcessing.value = true;
    
    // Simulate encryption/delay
    setTimeout(() => {
        router.post(route('billing.subscribe'), {
            plan_type: planType
        }, {
            onSuccess: () => {
                isProcessing.value = false;
                isSuccess.value = true;
            },
            onError: () => {
                isProcessing.value = false;
                alert('Payment verification failed. Check carrier uplink.');
            }
        });
    }, 1500);
};

const close = () => {
    if (isSuccess.value) {
        // Force reload to ensure all badges update
        router.reload();
    }
    isSuccess.value = false;
    emit('close');
};
</script>

<template>
    <Modal :show="show" maxWidth="md" @close="close">
        <div class="p-8 bg-[#0A0A0B]/90 backdrop-blur-3xl border border-white/5 rounded-[2.5rem] relative overflow-hidden">
            <div class="absolute -right-24 -top-24 w-64 h-64 bg-[#F3E7C9]/10 blur-[120px] rounded-full pointer-events-none"></div>
            
            <div v-if="!isSuccess" class="relative z-10">
                <div class="flex items-center justify-between mb-8">
                    <h2 class="text-2xl font-bold tracking-tight text-white uppercase mt-1">Secure Payment</h2>
                    <button @click="close" class="text-slate-500 hover:text-white transition-colors">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="mb-8 p-6 rounded-2xl bg-white/5 border border-white/5">
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Selected Plan</span>
                        <span v-if="plan?.badge" class="px-2 py-0.5 rounded bg-[#F3E7C9]/10 text-[#F3E7C9] text-[8px] font-bold tracking-wider border border-[#F3E7C9]/20">{{ plan.badge }}</span>
                    </div>
                    <div class="flex justify-between items-end">
                        <h3 class="text-xl font-bold tracking-tight text-white uppercase">{{ plan?.name }}</h3>
                        <div class="text-right">
                            <span class="text-3xl font-bold tracking-tight text-white uppercase">{{ plan?.price }}</span>
                            <span class="text-[9px] font-black text-slate-600 block uppercase tracking-widest mt-1">per month</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-5">
                    <div>
                        <label class="block text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-2 ml-1">Card Holder</label>
                        <input type="text" v-model="cardHolder" placeholder="EISEN LUME" class="w-full bg-white/5 border border-white/5 rounded-xl px-4 py-3 text-sm font-bold tracking-tight text-white focus:outline-none focus:border-[#F3E7C9] focus:bg-white/[0.08] transition-all uppercase">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-2 ml-1">Card Number</label>
                        <div class="relative">
                            <input type="text" v-model="cardNumber" placeholder="0000 0000 0000 0000" class="w-full bg-white/5 border border-white/5 rounded-xl px-4 py-3 text-sm font-bold tracking-tight text-white focus:outline-none focus:border-[#F3E7C9] focus:bg-white/[0.08] transition-all">
                             <div class="absolute right-4 top-1/2 -translate-y-1/2 flex gap-1.5">
                                <div class="w-7 h-5 bg-white/10 rounded-md"></div>
                                <div class="w-7 h-5 bg-white/5 rounded-md"></div>
                             </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-2 ml-1">Expiry</label>
                            <input type="text" v-model="expiry" placeholder="MM / YY" class="w-full bg-white/5 border border-white/5 rounded-xl px-4 py-3 text-sm font-bold tracking-tight text-white focus:outline-none focus:border-[#F3E7C9] focus:bg-white/[0.08] transition-all">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-2 ml-1">CVC</label>
                            <input type="text" v-model="cvc" placeholder="000" class="w-full bg-white/5 border border-white/5 rounded-xl px-4 py-3 text-sm font-bold tracking-tight text-white focus:outline-none focus:border-[#F3E7C9] focus:bg-white/[0.08] transition-all uppercase">
                        </div>
                    </div>
                </div>

                <div class="mt-10">
                    <button 
                        @click="handlePayment"
                        :disabled="isProcessing || !cardNumber || !expiry || !cvc"
                        class="w-full py-4 rounded-xl bg-[#F3E7C9] hover:bg-[#F3E7C9] text-slate-950 font-bold tracking-tight text-sm transition-all disabled:opacity-50 flex items-center justify-center gap-2 shadow-lg shadow-[#F3E7C9]/10 active:scale-95"
                    >
                        <span v-if="isProcessing" class="h-4 w-4 border-2 border-slate-950/30 border-t-slate-950 rounded-full animate-spin"></span>
                        {{ isProcessing ? 'Processing Transaction' : 'Authorize Transaction' }}
                    </button>
                    <p class="mt-4 text-[9px] text-slate-600 text-center uppercase tracking-widest font-bold">
                        Encrypted and secured via LUME Bridge.
                    </p>
                </div>
            </div>

            <!-- Success State -->
            <div v-else class="py-12 text-center relative z-10 transition-all duration-500">
                <div class="w-24 h-24 bg-[#CBB48A]/20 border border-[#CBB48A]/30 rounded-full flex items-center justify-center mx-auto mb-8 shadow-[0_0_50px_rgba(203, 180, 138, 0.2)]">
                    <svg class="w-10 h-10 text-[#CBB48A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                </div>
                <h2 class="text-3xl font-bold tracking-tight text-white uppercase mb-4">Access Granted</h2>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-10 max-w-[300px] mx-auto leading-relaxed">
                    Your subscription to the <span class="text-[#F3E7C9]">{{ plan?.name }}</span> has been successfully activated on your account.
                </p>
                <button 
                    @click="close"
                    class="px-10 py-3 rounded-xl border border-white/5 bg-white/5 hover:bg-white/10 text-white text-[10px] font-bold tracking-wider transition-all"
                >
                    Dismiss Console
                </button>
            </div>
        </div>
    </Modal>
</template>
