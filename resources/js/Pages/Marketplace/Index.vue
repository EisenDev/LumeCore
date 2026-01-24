<script setup lang="ts">
/**
 * Marketplace Index Page
 * Public listing of verified assets for sale with guest email modal
 */
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import type { VaultAsset } from '@/types/vault';
import AuditReportModal from '@/Components/AuditReportModal.vue';
import GlobalHelper from '@/Components/GlobalHelper.vue';

interface Props {
    listings: {
        data: VaultAsset[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    isAuthenticated: boolean;
    authUser: {
        id: string;
        name: string;
        email: string;
    } | null;
}

const props = withDefaults(defineProps<Props>(), {
    listings: () => ({ data: [], current_page: 1, last_page: 1, per_page: 20, total: 0 }),
    isAuthenticated: false,
    authUser: null,
});

// Guest Email Modal State
const showEmailModal = ref(false);
const guestEmail = ref('');
const selectedAsset = ref<VaultAsset | null>(null);
const emailError = ref('');
const isSubmitting = ref(false);

// Audit Report Modal State
const showAuditModal = ref(false);

/**
 * Handle Buy button click
 */
function handleBuyClick(asset: VaultAsset): void {
    selectedAsset.value = asset;
    
    if (props.isAuthenticated) {
        // Authenticated user - go directly to checkout
        proceedToCheckout(asset, props.authUser?.email ?? '');
    } else {
        // Guest - show email modal
        showEmailModal.value = true;
        guestEmail.value = '';
        emailError.value = '';
    }
}

/**
 * Handle Card Click - Open Audit Report
 */
function handleCardClick(asset: VaultAsset): void {
    selectedAsset.value = asset;
    showAuditModal.value = true;
}

/**
 * Handle Buy from Audit Modal
 */
function handleAuditBuy(): void {
    showAuditModal.value = false;
    if (selectedAsset.value) {
        handleBuyClick(selectedAsset.value);
    }
}

/**
 * Validate email and proceed to checkout
 */
function submitGuestEmail(): void {
    // Validate email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!guestEmail.value || !emailRegex.test(guestEmail.value)) {
        emailError.value = 'Please enter a valid email address';
        return;
    }
    
    if (selectedAsset.value) {
        proceedToCheckout(selectedAsset.value, guestEmail.value);
    }
}

/**
 * Proceed to payment gateway (placeholder)
 */
function proceedToCheckout(asset: VaultAsset, email: string): void {
    isSubmitting.value = true;
    
    // TODO: Integrate with payment gateway (Stripe, PayPal, etc.)
    console.log('Proceeding to checkout:', {
        assetId: asset.id,
        price: asset.price,
        email: email,
    });
    
    // For now, just close the modal and show alert
    setTimeout(() => {
        showEmailModal.value = false;
        isSubmitting.value = false;
        alert(`Payment gateway integration coming soon!\n\nAsset: ${asset.file_name}\nPrice: $${Number(asset.price || 0).toFixed(2)}\nEmail: ${email}`);
    }, 500);
}

/**
 * Close the email modal
 */
function closeEmailModal(): void {
    showEmailModal.value = false;
    selectedAsset.value = null;
    guestEmail.value = '';
    emailError.value = '';
}

/**
 * Get grade based on confidence score
 */
function getGrade(score: number): { letter: string; color: string; bg: string } {
    if (score >= 90) return { letter: 'A', color: 'text-brand-secondary', bg: 'bg-brand-secondary/20' };
    if (score >= 75) return { letter: 'B', color: 'text-blue-400', bg: 'bg-blue-500/20' };
    if (score >= 60) return { letter: 'C', color: 'text-yellow-400', bg: 'bg-yellow-500/20' };
    return { letter: 'F', color: 'text-red-400', bg: 'bg-red-500/20' };
}

/**
 * Format file size
 */
function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

/**
 * Get display icon based on mime type or project type
 */
function getDisplayIcon(asset: VaultAsset): string {
    const mime = asset.mime_type || '';
    if (asset.metadata?.audit_type === 'project' || asset.metadata?.audit_type === 'design') {
        return '🚀';
    }
    if (mime.includes('pdf')) return '📄';
    if (mime.includes('image')) return '🖼️';
    if (mime.includes('word') || mime.includes('document')) return '📝';
    return '📁';
}

/**
 * Get category badge color
 */
function getCategoryColor(category: string): string {
    const colors: Record<string, string> = {
        'Template': 'bg-purple-500/20 text-purple-400 ring-purple-500/30',
        'Dataset': 'bg-blue-500/20 text-blue-400 ring-blue-500/30',
        'Educational': 'bg-green-500/20 text-green-400 ring-green-500/30',
        'Creative Work': 'bg-pink-500/20 text-pink-400 ring-pink-500/30',
        'Public Audit': 'bg-yellow-500/20 text-yellow-400 ring-yellow-500/30',
    };
    return colors[category] ?? 'bg-gray-500/20 text-gray-400 ring-gray-500/30';
}

/**
 * Helper to safely get document breakdown from union type
 */
function getDocumentBreakdown(asset: VaultAsset): { formatting: number; content_quality: number; industry_relevance: number } | null {
    const breakdown = asset.metadata?.breakdown;
    if (!breakdown) return null;
    if ('formatting' in breakdown) {
        return breakdown as { formatting: number; content_quality: number; industry_relevance: number };
    }
    return null;
}

/**
 * Helper to safely get project breakdown
 */
function getProjectBreakdown(asset: VaultAsset): { tech_score: number; security_score: number; scalability_score: number } | null {
    const breakdown = asset.metadata?.breakdown;
    if (!breakdown) return null;
    if ('tech_score' in breakdown || 'tech_quality' in breakdown) {
        return {
            tech_score: (breakdown as any).tech_score ?? (breakdown as any).tech_quality ?? 0,
            security_score: (breakdown as any).security_score ?? 0,
            scalability_score: (breakdown as any).scalability_score ?? 0,
        };
    }
    return null;
}
</script>

<template>
    <Head title="Marketplace - LUME" />

    <div class="min-h-screen bg-brand-dark">
        <!-- Header -->
        <header class="border-b border-white/10 bg-gradient-to-r from-brand-dark via-gray-900 to-brand-dark">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div>
                        <Link href="/" class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand-primary to-brand-secondary">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <span class="text-xl font-bold text-white">LUME</span>
                        </Link>
                    </div>
                    <nav class="flex items-center gap-4">
                        <template v-if="isAuthenticated">
                            <Link
                                :href="route('dashboard')"
                                class="rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110"
                            >
                                Dashboard
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="rounded-lg px-4 py-2 text-sm font-medium text-gray-300 transition-colors hover:text-white"
                            >
                                Log in
                            </Link>
                            <Link
                                :href="route('register')"
                                class="rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110"
                            >
                                Get Started
                            </Link>
                        </template>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="relative overflow-hidden border-b border-white/10 bg-gradient-to-b from-brand-dark to-gray-900 py-16">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_50%,rgba(0,220,130,0.1),transparent_50%)]" />
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_70%_50%,rgba(0,200,150,0.08),transparent_50%)]" />
            
            <div class="relative mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                <h1 class="text-4xl font-bold tracking-tight text-white sm:text-5xl">
                    <span class="bg-gradient-to-r from-brand-primary to-brand-secondary bg-clip-text text-transparent">
                        Verified
                    </span>
                    Digital Assets
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-400">
                    Browse AI-verified professional documents. Every asset has been audited by LUME's Sovereign AI for quality, authenticity, and privacy compliance.
                </p>
                
                <!-- Stats -->
                <div class="mt-8 flex items-center justify-center gap-8">
                    <div class="text-center">
                        <p class="text-3xl font-bold text-white">{{ props.listings.total }}</p>
                        <p class="text-sm text-gray-400">Listed Assets</p>
                    </div>
                    <div class="h-8 w-px bg-white/20" />
                    <div class="text-center">
                        <p class="text-3xl font-bold text-brand-secondary">100%</p>
                        <p class="text-sm text-gray-400">AI Verified</p>
                    </div>
                    <div class="h-8 w-px bg-white/20" />
                    <div class="text-center">
                        <p class="text-3xl font-bold text-white">Privacy</p>
                        <p class="text-sm text-gray-400">Compliant</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Marketplace Grid -->
        <main class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <!-- Empty State -->
            <div v-if="props.listings.data.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                <div class="mb-4 rounded-full bg-gray-800 p-6">
                    <svg class="h-12 w-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-white">No Listings Yet</h3>
                <p class="mt-2 text-gray-400">Be the first to list a verified asset on the LUME Marketplace.</p>
                <Link
                    :href="route('register')"
                    class="mt-6 rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-6 py-3 font-semibold text-white shadow-lg transition-all hover:brightness-110"
                >
                    Start Uploading
                </Link>
            </div>

            <!-- Grid -->
            <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <div
                    v-for="asset in props.listings.data"
                    :key="asset.id"
                    @click="handleCardClick(asset)"
                    class="group relative cursor-pointer overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-gray-800/50 to-gray-900/50 backdrop-blur-sm transition-all duration-300 hover:border-brand-primary/50 hover:shadow-xl hover:shadow-brand-primary/10"
                >
                    <!-- Card Header -->
                    <div class="relative border-b border-white/10 bg-gradient-to-r from-brand-primary/10 to-brand-secondary/10 p-4">
                        <!-- Verified Badge -->
                        <div class="absolute right-3 top-3 flex items-center gap-1 rounded-full bg-brand-secondary/20 px-2 py-1 text-xs font-semibold text-brand-secondary ring-1 ring-brand-secondary/30">
                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            VERIFIED
                        </div>

                        <!-- Document Icon & Type -->
                        <div class="flex items-center gap-3">
                            <span class="text-3xl">{{ getDisplayIcon(asset) }}</span>
                            <div>
                                <p class="text-sm font-medium text-gray-400">
                                    {{ asset.metadata?.project_type ?? asset.metadata?.document_type ?? 'Asset' }}
                                </p>
                                <p class="text-xs text-gray-500">{{ formatFileSize(asset.file_size) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="p-4">
                        <!-- File Name -->
                        <h3 class="mb-2 truncate text-sm font-semibold text-white group-hover:text-brand-primary">
                            {{ asset.file_name }}
                        </h3>

                        <!-- Category Badge -->
                        <div v-if="asset.metadata?.category" class="mb-3">
                            <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1', getCategoryColor(asset.metadata.category)]">
                                {{ asset.metadata.category }}
                            </span>
                        </div>

                        <!-- Score & Grade -->
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div :class="['flex h-8 w-8 items-center justify-center rounded-lg text-sm font-bold', getGrade(asset.metadata?.confidence_score ?? 0).bg, getGrade(asset.metadata?.confidence_score ?? 0).color]">
                                    {{ getGrade(asset.metadata?.confidence_score ?? 0).letter }}
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400">LUME Score</p>
                                    <p class="text-sm font-semibold text-white">{{ asset.metadata?.confidence_score ?? 0 }}/100</p>
                                </div>
                            </div>

                            <!-- Sale Count -->
                            <div v-if="asset.sale_count && asset.sale_count > 0" class="text-right">
                                <p class="text-xs text-gray-400">Sold</p>
                                <p class="text-sm font-semibold text-white">{{ asset.sale_count }}x</p>
                            </div>
                        </div>

                        <!-- Project Breakdown Mini Bars -->
                        <div v-if="getProjectBreakdown(asset)" class="mb-4 space-y-1.5">
                            <div class="flex items-center gap-2">
                                <span class="w-16 text-xs text-gray-500">Tech</span>
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-700">
                                    <div class="h-full rounded-full bg-blue-500" :style="{ width: `${getProjectBreakdown(asset)?.tech_score}%` }" />
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-16 text-xs text-gray-500">Security</span>
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-700">
                                    <div class="h-full rounded-full bg-red-500" :style="{ width: `${getProjectBreakdown(asset)?.security_score}%` }" />
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-16 text-xs text-gray-500">Scale</span>
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-700">
                                    <div class="h-full rounded-full bg-purple-500" :style="{ width: `${getProjectBreakdown(asset)?.scalability_score}%` }" />
                                </div>
                            </div>
                        </div>

                        <!-- Document Breakdown Mini Bars -->
                        <div v-if="getDocumentBreakdown(asset)" class="mb-4 space-y-1.5">
                            <div class="flex items-center gap-2">
                                <span class="w-16 text-xs text-gray-500">Format</span>
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-700">
                                    <div class="h-full rounded-full bg-brand-primary" :style="{ width: `${getDocumentBreakdown(asset)?.formatting}%` }" />
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-16 text-xs text-gray-500">Content</span>
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-700">
                                    <div class="h-full rounded-full bg-brand-primary" :style="{ width: `${getDocumentBreakdown(asset)?.content_quality}%` }" />
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-16 text-xs text-gray-500">Industry</span>
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-700">
                                    <div class="h-full rounded-full bg-brand-primary" :style="{ width: `${getDocumentBreakdown(asset)?.industry_relevance}%` }" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer - Price & Buy -->
                    <div class="border-t border-white/10 bg-black/20 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-400">Price</p>
                                <p class="text-xl font-bold text-brand-secondary">
                                    ${{ Number(asset.price || 0).toFixed(2) }}
                                </p>
                            </div>
                            <button
                                @click.stop="handleBuyClick(asset)"
                                class="rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110"
                            >
                                Buy Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="props.listings.last_page > 1" class="mt-12 flex items-center justify-center gap-2">
                <span class="text-sm text-gray-400">
                    Page {{ props.listings.current_page }} of {{ props.listings.last_page }}
                </span>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-white/10 bg-gray-900/50 py-8">
            <div class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                <p class="text-sm text-gray-500">
                    © 2026 LUME. All assets are AI-verified for quality, authenticity, and privacy compliance.
                </p>
            </div>
        </footer>

        <!-- Guest Email Modal -->
        <Teleport to="body">
            <transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showEmailModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
                    <transition
                        enter-active-class="ease-out duration-300"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="ease-in duration-200"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div v-if="showEmailModal" class="relative w-full max-w-md rounded-2xl border border-white/10 bg-gradient-to-br from-gray-800 to-gray-900 p-6 shadow-2xl">
                             <!-- Close Button -->
                             <button
                                 @click="closeEmailModal"
                                 class="absolute right-4 top-4 text-gray-400 transition-colors hover:text-white"
                             >
                                 <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                 </svg>
                             </button>

                             <!-- Modal Header -->
                             <div class="mb-6 text-center">
                                 <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-brand-primary/20">
                                     <svg class="h-7 w-7 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                     </svg>
                                 </div>
                                 <h3 class="text-xl font-bold text-white">Enter Your Email</h3>
                                 <p class="mt-1 text-sm text-gray-400">We'll send your purchase receipt and download link here</p>
                             </div>

                             <!-- Selected Asset Info -->
                             <div v-if="selectedAsset" class="mb-6 rounded-xl border border-white/10 bg-gray-800/50 p-4">
                                 <div class="flex items-center justify-between">
                                     <div class="flex items-center gap-3">
                                         <span class="text-2xl">{{ getDisplayIcon(selectedAsset) }}</span>
                                         <div>
                                             <p class="text-sm font-medium text-white">{{ selectedAsset.file_name }}</p>
                                             <p class="text-xs text-gray-400">{{ selectedAsset.metadata?.category ?? 'Asset' }}</p>
                                         </div>
                                     </div>
                                     <p class="text-lg font-bold text-brand-secondary">${{ Number(selectedAsset?.price || 0).toFixed(2) }}</p>
                                 </div>
                             </div>

                             <!-- Email Input -->
                             <div class="mb-6">
                                 <label class="mb-2 block text-sm font-medium text-gray-300">Email Address</label>
                                 <input
                                     v-model="guestEmail"
                                     type="email"
                                     placeholder="you@example.com"
                                     class="w-full rounded-xl border border-gray-600 bg-gray-800 px-4 py-3 text-white placeholder-gray-500 focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"
                                     @keyup.enter="submitGuestEmail"
                                 />
                                 <p v-if="emailError" class="mt-2 text-sm text-red-400">{{ emailError }}</p>
                             </div>

                             <!-- Action Buttons -->
                             <div class="flex gap-3">
                                 <button
                                     @click="closeEmailModal"
                                     class="flex-1 rounded-xl border border-gray-600 bg-transparent px-4 py-3 font-semibold text-gray-300 transition-colors hover:bg-gray-700"
                                 >
                                     Cancel
                                 </button>
                                 <button
                                     @click="submitGuestEmail"
                                     :disabled="isSubmitting"
                                     class="flex-1 rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-4 py-3 font-semibold text-white shadow-lg transition-all hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50"
                                 >
                                     <span v-if="isSubmitting" class="flex items-center justify-center gap-2">
                                         <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                             <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                             <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                         </svg>
                                         Processing...
                                     </span>
                                     <span v-else>Continue to Payment</span>
                                 </button>
                             </div>

                             <!-- Login Link -->
                             <p class="mt-4 text-center text-sm text-gray-400">
                                 Already have an account?
                                 <Link :href="route('login')" class="font-medium text-brand-primary hover:underline">Log in</Link>
                             </p>
                        </div>
                    </transition>
                </div>
            </transition>
        </Teleport>

        <!-- Audit Report Modal -->
        <AuditReportModal
            :show="showAuditModal"
            :asset="selectedAsset"
            :is-public-view="true"
            @close="showAuditModal = false"
            @buy="handleAuditBuy"
        />

        <!-- Global AI Helper Bot -->
        <GlobalHelper />
    </div>
</template>
