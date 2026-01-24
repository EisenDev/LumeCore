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
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Top up Credits
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Select a credit bundle to purchase. Credits are used for AI Audits.
            </p>

            <div class="mt-6 space-y-4">
                <div
                    v-for="tier in tiers"
                    :key="tier.credits"
                    @click="selectTier(tier.credits)"
                    class="relative cursor-pointer rounded-lg border p-4 transition-all"
                    :class="[
                        selectedTier === tier.credits
                            ? 'border-brand-primary bg-brand-primary/5 ring-1 ring-brand-primary'
                            : 'border-gray-200 hover:border-brand-primary/50 dark:border-gray-700 dark:hover:border-brand-primary/50'
                    ]"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ tier.credits }} Credits
                            </span>
                            <span
                                v-if="tier.badge"
                                class="ml-2 rounded bg-brand-secondary/10 px-2 py-0.5 text-xs font-bold text-brand-secondary"
                            >
                                {{ tier.badge }}
                            </span>
                        </div>
                        <span class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            ${{ tier.price.toFixed(2) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeModal"> Cancel </SecondaryButton>

                <PrimaryButton
                    class="ml-3"
                    :class="{ 'opacity-25': form.processing || !selectedTier }"
                    :disabled="form.processing || !selectedTier"
                    @click="submitPurchase"
                >
                    {{ form.processing ? 'Processing...' : 'Confirm Purchase' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
