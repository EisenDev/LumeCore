<script setup lang="ts">
/**
 * WalletCard Component
 * Displays the user's Account Balance prominently
 */

// TypeScript interface for wallet data
interface WalletData {
    id: string;
    balance: number;
    currency: string;
}

interface Props {
    wallet: WalletData | null;
}

const props = defineProps<Props>();

/**
 * Format balance as currency string
 */
function formatBalance(balance: number): string {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(balance);
}
</script>

<template>
    <div class="rounded-lume bg-brand-dark p-6 text-white shadow-lg">
        <!-- Header -->
        <div class="mb-2 flex items-center justify-between">
            <h3 class="text-sm font-medium uppercase tracking-wider text-gray-400">
                Account Balance
            </h3>
            <span
                v-if="props.wallet"
                class="rounded-full bg-brand-primary/20 px-3 py-1 text-xs font-semibold text-brand-primary"
            >
                {{ props.wallet.currency }}
            </span>
        </div>

        <!-- Balance Display -->
        <div v-if="props.wallet" class="mt-4">
            <div class="flex items-baseline gap-2">
                <span class="text-4xl font-bold tracking-tight">
                    ${{ formatBalance(props.wallet.balance) }}
                </span>
                <span class="text-lg text-gray-400">
                    {{ props.wallet.currency }}
                </span>
            </div>
        </div>

        <!-- No Wallet State -->
        <div v-else class="mt-4 text-center">
            <p class="text-gray-400">No wallet found</p>
        </div>

        <!-- Quick Stats -->
        <div class="mt-6 grid grid-cols-2 gap-4 border-t border-gray-700 pt-4">
            <div>
                <p class="text-xs text-gray-500">Available</p>
                <p class="text-lg font-semibold text-brand-secondary">
                    ${{ props.wallet ? formatBalance(props.wallet.balance) : '0.00' }}
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Pending</p>
                <p class="text-lg font-semibold text-yellow-400">
                    $0.00
                </p>
            </div>
        </div>
    </div>
</template>
