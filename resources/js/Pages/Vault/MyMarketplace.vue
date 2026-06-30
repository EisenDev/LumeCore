<script setup lang="ts">
/**
 * MyMarketplace Page
 * Private page for logged-in users to manage their marketplace listings
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { VaultAsset } from '@/types/vault';

interface Props {
    listings: VaultAsset[];
}

const props = defineProps<Props>();

// Toggle loading states
const togglingAssets = ref<Set<string>>(new Set());

/**
 * Toggle sale status for an asset
 */
function toggleSale(asset: VaultAsset): void {
    togglingAssets.value.add(asset.id);
    
    router.post(route('marketplace.toggle', { asset: asset.id }), {
        price: asset.price ?? 10.00,
        is_for_sale: !asset.is_for_sale,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['listings'] });
        },
        onError: (errors) => {
            alert(errors.asset || 'Failed to update listing');
        },
        onFinish: () => {
            togglingAssets.value.delete(asset.id);
        },
    });
}

/**
 * Calculate total revenue for an asset
 */
function calculateRevenue(asset: VaultAsset): number {
    return (asset.price ?? 0) * (asset.sale_count ?? 0);
}

/**
 * Format currency
 */
function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
}

/**
 * Get status badge classes
 */
function getStatusBadge(asset: VaultAsset): { text: string; class: string } {
    if (asset.is_for_sale) {
        return { text: 'Listed', class: 'bg-brand-secondary/20 text-brand-secondary ring-brand-secondary/30' };
    }
    return { text: 'Unlisted', class: 'bg-gray-500/20 text-gray-400 ring-gray-500/30' };
}
</script>

<template>
    <Head title="My Marketplace - Lume" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-200">
                    My Marketplace
                </h2>
                <Link
                    :href="route('marketplace.index')"
                    class="flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-gray-300 transition-colors hover:bg-white/10 hover:text-white"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    View Public Marketplace
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Stats Cards -->
                <div class="mb-8 grid gap-6 md:grid-cols-3">
                    <!-- Total Listed -->
                    <div class="rounded-2xl border border-white/10 bg-gradient-to-br from-gray-800/50 to-gray-900/50 p-6">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-primary/20 ring-1 ring-brand-primary/30">
                                <svg class="h-6 w-6 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Total Listed</p>
                                <p class="text-2xl font-bold text-white">{{ props.listings.filter(a => a.is_for_sale).length }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Total Sales -->
                    <div class="rounded-2xl border border-white/10 bg-gradient-to-br from-gray-800/50 to-gray-900/50 p-6">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-secondary/20 ring-1 ring-brand-secondary/30">
                                <svg class="h-6 w-6 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Total Sales</p>
                                <p class="text-2xl font-bold text-white">{{ props.listings.reduce((sum, a) => sum + (a.sale_count ?? 0), 0) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Total Revenue -->
                    <div class="rounded-2xl border border-white/10 bg-gradient-to-br from-gray-800/50 to-gray-900/50 p-6">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-500/20 ring-1 ring-purple-500/30">
                                <svg class="h-6 w-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Total Revenue</p>
                                <p class="text-2xl font-bold text-white">{{ formatCurrency(props.listings.reduce((sum, a) => sum + calculateRevenue(a), 0)) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Listings Table -->
                <div class="rounded-2xl border border-white/10 bg-gradient-to-br from-gray-800/50 to-gray-900/50 overflow-hidden">
                    <!-- Table Header -->
                    <div class="border-b border-white/10 bg-black/20 px-6 py-4">
                        <h3 class="font-semibold text-white">My Listings</h3>
                    </div>

                    <!-- Empty State -->
                    <div v-if="props.listings.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                        <div class="mb-4 rounded-full bg-gray-800 p-6">
                            <svg class="h-10 w-10 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <h4 class="text-lg font-medium text-white">No Marketplace Assets</h4>
                        <p class="mt-2 text-sm text-gray-400">Upload and verify assets to list them for sale.</p>
                        <Link
                            :href="route('overview')"
                            class="mt-6 rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-6 py-3 font-semibold text-white shadow-lg transition-all hover:brightness-110"
                        >
                            Go to Overview
                        </Link>
                    </div>

                    <!-- Table -->
                    <div v-else class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b border-white/10 bg-black/10">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Asset Name</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Price</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Sales</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Revenue</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Status</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-gray-400">Toggle</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr
                                    v-for="asset in props.listings"
                                    :key="asset.id"
                                    class="transition-colors hover:bg-white/5"
                                >
                                    <!-- Asset Name -->
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-800 text-lg">
                                                {{ asset.mime_type.includes('pdf') ? '📄' : asset.mime_type.includes('image') ? '🖼️' : '📁' }}
                                            </div>
                                            <div>
                                                <p class="font-medium text-white">{{ asset.file_name }}</p>
                                                <p class="text-xs text-gray-500">{{ asset.metadata?.category ?? asset.metadata?.document_type ?? 'Document' }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Price -->
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="font-semibold text-brand-secondary">{{ formatCurrency(asset.price ?? 0) }}</span>
                                    </td>

                                    <!-- Sales Count -->
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="text-white">{{ asset.sale_count ?? 0 }}</span>
                                    </td>

                                    <!-- Revenue -->
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="font-medium text-white">{{ formatCurrency(calculateRevenue(asset)) }}</span>
                                    </td>

                                    <!-- Status Badge -->
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1', getStatusBadge(asset).class]">
                                            {{ getStatusBadge(asset).text }}
                                        </span>
                                    </td>

                                    <!-- Toggle Switch -->
                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <button
                                            @click="toggleSale(asset)"
                                            :disabled="togglingAssets.has(asset.id)"
                                            :class="[
                                                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-brand-primary focus:ring-offset-2 focus:ring-offset-gray-900 disabled:cursor-not-allowed disabled:opacity-50',
                                                asset.is_for_sale ? 'bg-brand-primary' : 'bg-gray-600'
                                            ]"
                                        >
                                            <span
                                                :class="[
                                                    'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                                    asset.is_for_sale ? 'translate-x-5' : 'translate-x-0'
                                                ]"
                                            >
                                                <span v-if="togglingAssets.has(asset.id)" class="flex h-full w-full items-center justify-center">
                                                    <svg class="h-3 w-3 animate-spin text-gray-500" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                                    </svg>
                                                </span>
                                            </span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Help Text -->
                <div class="mt-6 rounded-xl border border-white/10 bg-gray-800/30 p-4">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 flex-shrink-0 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-sm text-gray-300">
                                <strong>Note:</strong> Only verified assets with a LUME Score above 80% and marketplace eligibility can be listed for sale.
                                Assets containing personal information (PII) cannot be sold to protect privacy.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
