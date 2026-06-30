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
        <div class="p-8 relative overflow-hidden">
            <!-- Glow background highlight -->
            <div class="absolute -right-24 -top-24 w-48 h-48 bg-[#CBB48A]/5 blur-[80px] rounded-full pointer-events-none"></div>
            
            <header class="relative z-10">
                <h2 class="text-lg font-bold tracking-tight text-white">
                    Top up credits
                </h2>
                <p class="mt-1 text-xs text-gray-400 font-medium">
                    Select a credit bundle to purchase for your operations.
                </p>
            </header>

            <div class="mt-6 space-y-2 relative z-10">
                <div
                    v-for="tier in tiers"
                    :key="tier.credits"
                    @click="selectTier(tier.credits)"
                    class="relative cursor-pointer rounded-xl border p-4 transition-all duration-300 group overflow-hidden"
                    :class="[
                        selectedTier === tier.credits
                            ? 'border-[#CBB48A]/40 bg-[#CBB48A]/[0.02] shadow-[0_0_15px_rgba(203,180,138,0.05)]'
                            : 'border-white/5 bg-white/[0.01] hover:border-white/10 hover:bg-white/[0.03]'
                    ]"
                >
                    <div class="flex items-center justify-between relative z-10">
                        <div class="flex items-center gap-3">
                            <span 
                                class="text-sm font-bold transition-colors"
                                :class="selectedTier === tier.credits ? 'text-[#CBB48A]' : 'text-white'"
                            >
                                {{ tier.credits }} credits
                            </span>
                            <span
                                v-if="tier.badge"
                                class="rounded-full bg-[#CBB48A]/10 px-2 py-0.5 text-[8px] font-bold text-[#CBB48A] border border-[#CBB48A]/25 uppercase tracking-wider"
                            >
                                {{ tier.badge }}
                            </span>
                        </div>
                        <span 
                            class="text-sm font-bold transition-colors"
                            :class="selectedTier === tier.credits ? 'text-white' : 'text-gray-400'"
                        >
                            ${{ tier.price.toFixed(2) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3 relative z-10">
                <button
                    type="button"
                    @click="closeModal"
                    class="px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-xs font-bold text-gray-300 hover:text-white transition-all duration-300 focus:outline-none"
                > 
                    Cancel 
                </button>

                <button
                    type="button"
                    class="px-6 py-2.5 rounded-xl bg-[#CBB48A] hover:bg-[#CBB48A]/90 text-slate-950 text-xs font-black transition-all duration-300 focus:outline-none active:scale-95 disabled:opacity-20 shadow-lg shadow-[#CBB48A]/10"
                    :disabled="form.processing || !selectedTier"
                    @click="submitPurchase"
                >
                    {{ form.processing ? 'Processing...' : 'Confirm purchase' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
