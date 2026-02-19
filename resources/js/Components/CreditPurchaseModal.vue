<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps<{
    show: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const selectedTier = ref<number | null>(null);

const tiers = [
    { credits: 10, price: 1.00, badge: null },
    { credits: 50, price: 5.00, badge: null },
    { credits: 100, price: 10.00, badge: 'Standard' },
    { credits: 500, price: 50.00, badge: 'Best Value' },
    { credits: 1000, price: 80.00, badge: '20% OFF' },
];

const form = useForm({
    amount: 0,
});

const closeModal = () => {
    emit('close');
    selectedTier.value = null;
    form.reset();
};

const selectTier = (credits: number) => {
    selectedTier.value = credits;
};

const submitPurchase = () => {
    if (!selectedTier.value) return;

    form.amount = selectedTier.value;
    form.post(route('credits.add-test'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <Modal :show="show" @close="closeModal">
        <div class="p-8 bg-[#0A0A0B]/90 backdrop-blur-3xl border border-white/5 rounded-[2rem] relative overflow-hidden">
            <div class="absolute -right-24 -top-24 w-48 h-48 bg-emerald-500/10 blur-[100px] rounded-full pointer-events-none"></div>
            
            <header class="relative z-10">
                <h2 class="text-2xl font-black italic tracking-tighter text-emerald-400 uppercase">
                    Top up Credits
                </h2>

                <p class="mt-2 text-[10px] font-bold text-gray-500 uppercase tracking-widest leading-none">
                    Select a credit bundle to purchase for your operations.
                </p>
            </header>

            <div class="mt-8 space-y-3 relative z-10">
                <div
                    v-for="tier in tiers"
                    :key="tier.credits"
                    @click="selectTier(tier.credits)"
                    class="relative cursor-pointer rounded-2xl border p-5 transition-all duration-300 group overflow-hidden"
                    :class="[
                        selectedTier === tier.credits
                            ? 'border-emerald-500/50 bg-emerald-500/5 shadow-[0_0_20px_rgba(16,185,129,0.1)]'
                            : 'border-white/5 bg-white/5 hover:border-emerald-500/30 hover:bg-white/[0.07]'
                    ]"
                >
                    <div class="flex items-center justify-between relative z-10">
                        <div class="flex items-center gap-3">
                            <span class="text-xl font-black italic tracking-tighter text-white uppercase">
                                {{ tier.credits }} Credits
                            </span>
                            <span
                                v-if="tier.badge"
                                class="rounded-lg bg-emerald-500/10 px-3 py-1 text-[8px] font-black uppercase tracking-widest text-emerald-400 border border-emerald-500/20"
                            >
                                {{ tier.badge }}
                            </span>
                        </div>
                        <span class="text-xl font-black italic tracking-tighter text-white">
                            ${{ tier.price.toFixed(2) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-10 flex justify-end gap-4 relative z-10">
                <SecondaryButton 
                    @click="closeModal"
                    class="bg-white/5 border-white/10 text-white text-[10px] font-black uppercase tracking-widest px-6 py-3 rounded-xl hover:bg-white/10"
                > 
                    Cancel 
                </SecondaryButton>

                <PrimaryButton
                    class="bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black italic tracking-tighter uppercase px-8 py-3 rounded-xl transition-all active:scale-95 shadow-lg shadow-emerald-500/10"
                    :class="{ 'opacity-25': form.processing || !selectedTier }"
                    :disabled="form.processing || !selectedTier"
                    @click="submitPurchase"
                >
                    {{ form.processing ? 'Processing' : 'Confirm Purchase' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
