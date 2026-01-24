<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VaultUploader from '@/Components/VaultUploader.vue';
import WalletCard from '@/Components/WalletCard.vue';
import AuditReportModal from '@/Components/AuditReportModal.vue';
import DocumentReportModal from '@/Components/DocumentReportModal.vue';
import ProjectForensicModal from '@/Components/ProjectForensicModal.vue';
import CreditPurchaseModal from '@/Components/CreditPurchaseModal.vue';
import AssetHistoryModal from '@/Components/AssetHistoryModal.vue';
import DeepScanConfirmationModal from '@/Components/DeepScanConfirmationModal.vue';
import ProjectAnalystModal from '@/Components/ProjectAnalystModal.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import type { VaultAsset, DownloadUrlResponse, DeleteAssetResponse } from '@/types/vault';

// Wallet data interface
interface WalletData {
    id: string;
    balance: number;
    currency: string;
    credits: number;
}

// Props from server
interface Props {
    initialAssets: VaultAsset[];
    wallet: WalletData | null;
}

const props = withDefaults(defineProps<Props>(), {
    initialAssets: () => [],
    wallet: null,
});

// State for assets - initialized with server data
const recentAssets = ref<VaultAsset[]>([]);

const isDownloading = ref<string | null>(null);
const isDeleting = ref<string | null>(null);

// Modal state
const showAuditModal = ref(false);
const showPurchaseModal = ref(false);
const selectedAsset = ref<VaultAsset | null>(null);
const showHistoryModal = ref(false);
const historyAsset = ref<VaultAsset | null>(null);

// AI & Deep Audit Modals
const showDeepScanConfirmation = ref(false);
const showProjectAnalystModal = ref(false);

watch(() => props.initialAssets, (newAssets) => {
    if (!newAssets || !Array.isArray(newAssets)) {
        recentAssets.value = [];
        return;
    }
    recentAssets.value = [...newAssets];
    // Update selectedAsset reference so modal gets the new status
    if (selectedAsset.value) {
        const currentId = selectedAsset.value.id;
        const found = recentAssets.value.find(a => a.id === currentId);
        if (found) selectedAsset.value = found;
    }
}, { immediate: true, deep: true });

// Audit progress state for real-time updates
const auditProgress = ref<{ step: string; progress: number } | null>(null);

// Active tab state (synced from VaultUploader)
const activeTab = ref<'document' | 'project'>('document');

// Computed: Filtered assets based on active tab
const filteredAssets = computed(() => {
    return recentAssets.value.filter(asset => {
        const auditType = asset.metadata?.audit_type;
        if (activeTab.value === 'document') {
            return auditType === 'document' || !auditType; // Default to document if not set
        } else {
            return auditType === 'project' || auditType === 'design';
        }
    });
});

// Tab-reactive text
const listTitle = computed(() => 
    activeTab.value === 'document' ? 'Recently Uploaded Assets' : 'Recently Scanned Projects'
);

const emptyStateText = computed(() => ({
    icon: activeTab.value === 'document' ? 'document' : 'code',
    title: activeTab.value === 'document' ? 'No documents uploaded yet' : 'No codebases scanned yet',
    subtitle: activeTab.value === 'document' 
        ? 'Upload your first document using the uploader above' 
        : 'Scan a website or GitHub repository to get started'
}));

/**
 * On create, set up real-time observers
 */
onMounted(() => {
    const page = usePage();
    const user = page.props.auth.user;

    if (user?.id) {
        if (window.Echo) {
            console.log('🔌 Connecting to WebSocket channel:', `App.Models.User.${user.id}`);
            
            window.Echo.private(`App.Models.User.${user.id}`)
                // Listen for audit progress updates (real-time loading screen)
                // Note: .listen() with broadcastAs() needs dot prefix
                .listen('.AuditProgressUpdated', (e: { asset_id: string; step: string; progress: number }) => {
                    console.log('📊 Progress update received:', e);
                    // Update progress if this is the asset we're watching
                    if (selectedAsset.value?.id === e.asset_id) {
                        auditProgress.value = { step: e.step, progress: e.progress };
                    }
                })
                // Listen for final status updates (audit complete)
                .listen('.AssetStatusUpdated', (e: { asset: VaultAsset }) => {
                    console.log('✅ Asset status updated:', e.asset.status);
                    // Reset progress when audit completes
                    if (selectedAsset.value?.id === e.asset.id) {
                        auditProgress.value = null;
                    }
                    
                    // Find and update the asset in the list
                    const index = recentAssets.value.findIndex(a => a.id === e.asset.id);
                    if (index !== -1) {
                        recentAssets.value[index] = e.asset;
                        
                        // Also update selectedAsset if open in modal
                        if (selectedAsset.value?.id === e.asset.id) {
                            selectedAsset.value = e.asset;
                        }
                    }
                });
        } else {
            console.warn('Laravel Echo not initialized. Real-time updates disabled.');
        }
    }
});

/**
 * Handle successful upload of a single asset
 */
const handleUploadSuccess = (asset: VaultAsset) => {
    // Add to beginning of list (most recent first)
    recentAssets.value.unshift(asset);
    
    // Open the audit modal immediately with the fresh data
    // The asset object here ALREADY contains the AI analysis results from the backend response
    openAuditModal(asset);
};

/**
 * Handle all uploads complete
 */
function handleAllUploadsComplete(assets: VaultAsset[]): void {
    // Open modal for the first asset if available
    if (assets.length > 0) {
        openAuditModal(assets[0]);
    }
}

/**
 * Open audit modal for an asset
 */
function openAuditModal(asset: VaultAsset): void {
    selectedAsset.value = asset;
    showAuditModal.value = true;
}

/**
 * Close audit modal
 */
function closeAuditModal(): void {
    showAuditModal.value = false;
    selectedAsset.value = null;
}

/**
 * Handle deep audit request from ProjectForensicModal
 */
function handleDeepAudit(): void {
    console.log('Opening Deep Scan Confirmation for:', selectedAsset.value?.id);
    showDeepScanConfirmation.value = true;
}

/**
 * Handle AI Assistant request
 */
function handleOpenAiAssistant(): void {
    console.log('Opening AI Assistant for:', selectedAsset.value?.id);
    showProjectAnalystModal.value = true;
}

/**
 * Open history modal for an asset
 */
function openHistoryModal(asset: VaultAsset): void {
    historyAsset.value = asset;
    showHistoryModal.value = true;
}

/**
 * Close history modal
 */
function closeHistoryModal(): void {
    showHistoryModal.value = false;
    historyAsset.value = null;
}

/**
 * Refresh selected asset data from server (fallback when WebSocket isn't working)
 */
async function refreshSelectedAsset(): Promise<void> {
    if (!selectedAsset.value) return;
    
    try {
        const response = await axios.get(`/api/vault/assets/${selectedAsset.value.id}`);
        const updatedAsset = response.data.asset;
        
        // Update the selected asset
        selectedAsset.value = updatedAsset;
        
        // Also update in the list
        const index = recentAssets.value.findIndex(a => a.id === updatedAsset.id);
        if (index !== -1) {
            recentAssets.value[index] = updatedAsset;
        }
    } catch (error) {
        console.error('Failed to refresh asset:', error);
    }
}

/**
 * Format file size to human readable string
 */
function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

/**
 * Format date to locale string
 */
function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleString();
}

/**
 * Get status badge classes based on asset status
 */
function getStatusClasses(status: VaultAsset['status']): string {
    const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium';
    switch (status) {
        case 'pending':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300`;
        case 'uploaded':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300`;
        case 'processing':
            return `${baseClasses} bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300`;
        case 'ready':
        case 'verified':
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300`;
        case 'verified_private':
            return `${baseClasses} bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-300`;
        case 'flagged':
            return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300`;
        case 'action_required':
            return `${baseClasses} bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300`;
    }
}

/**
 * Get human-readable label for status
 */
function getStatusLabel(status: string): string {
    switch (status) {
        case 'verified_private':
            return 'Verified (Private)';
        case 'action_required':
            return 'Action Required';
        case 'payment_required':
            return 'Payment Required';
        default:
            return status.charAt(0).toUpperCase() + status.slice(1);
    }
}

/**
 * Download an asset
 */
async function handleDownload(asset: VaultAsset): Promise<void> {
    if (isDownloading.value) return;

    isDownloading.value = asset.id;

    try {
        const { data } = await axios.post<DownloadUrlResponse>('/vault/download', {
            asset_id: asset.id,
        });

        // Open the download URL in a new tab
        window.open(data.download_url, '_blank');
    } catch (error) {
        console.error('Download failed:', error);
        alert('Failed to download file. Please try again.');
    } finally {
        isDownloading.value = null;
    }
}

/**
 * Delete an asset
 */
async function handleDelete(asset: VaultAsset): Promise<void> {
    if (isDeleting.value) return;

    // Confirm deletion
    if (!confirm(`Are you sure you want to delete "${asset.file_name}"? This action cannot be undone.`)) {
        return;
    }

    isDeleting.value = asset.id;

    try {
        await axios.delete<DeleteAssetResponse>('/vault/delete', {
            data: { asset_id: asset.id },
        });

        // Remove from local state
        recentAssets.value = recentAssets.value.filter(a => a.id !== asset.id);
    } catch (error) {
        console.error('Delete failed:', error);
        alert('Failed to delete file. Please try again.');
    } finally {
        isDeleting.value = null;
    }
}
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
            >
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Credit Balance Card -->
                <div class="overflow-hidden rounded-lume bg-gradient-to-r from-brand-primary/20 to-brand-secondary/20 p-6 shadow-sm ring-1 ring-inset ring-brand-primary/10">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-brand-secondary">Available Credits</p>
                            <h3 class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                                {{ Number(props.wallet?.credits ?? 0).toFixed(2) }}
                                <span class="text-base font-normal text-gray-500">credits</span>
                            </h3>
                        </div>

                        <div class="flex items-center gap-4">
                            <button
                                @click="showPurchaseModal = true"
                                class="flex items-center rounded-lg bg-brand-primary px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:bg-brand-primary/90 hover:shadow-brand-primary/25"
                            >
                                <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Buy Credits
                            </button>
                            <div class="rounded-full bg-white/50 p-3 dark:bg-white/10">
                                <svg class="h-8 w-8 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Wallet Card -->
                <WalletCard :wallet="props.wallet" />

                <!-- Welcome Box with Uploader -->
                <div
                    class="overflow-hidden bg-white shadow-sm rounded-lume dark:bg-brand-dark"
                >
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="mb-4 text-lg font-medium">
                            Upload your assets to the Vault
                        </h3>

                        <!-- Vault Uploader Component -->
                        <VaultUploader
                            :max-file-size="100 * 1024 * 1024"
                            :allowed-file-types="['image/*', 'video/*', 'audio/*', '.pdf', '.zip']"
                            :max-number-of-files="10"
                            :credits="props.wallet?.credits ?? 0"
                            @upload-success="handleUploadSuccess"
                            @all-uploads-complete="handleAllUploadsComplete"
                            @tab-change="(tab: 'document' | 'project') => activeTab = tab"
                        />
                    </div>
                </div>

                <!-- Recently Uploaded Assets -->
                <div
                    class="overflow-hidden bg-white shadow-sm rounded-lume dark:bg-brand-dark"
                >
                    <div class="p-6">
                        <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ listTitle }}
                        </h3>

                        <!-- Empty State -->
                        <div
                            v-if="filteredAssets.length === 0"
                            class="py-8 text-center text-gray-500 dark:text-gray-400"
                        >
                            <!-- Document Icon -->
                            <svg
                                v-if="emptyStateText.icon === 'document'"
                                class="mx-auto h-12 w-12 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                                />
                            </svg>
                            <!-- Code Icon -->
                            <svg
                                v-else
                                class="mx-auto h-12 w-12 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"
                                />
                            </svg>
                            <p class="mt-2 text-sm">{{ emptyStateText.title }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                {{ emptyStateText.subtitle }}
                            </p>
                        </div>

                        <!-- Assets Table -->
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            File
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Size
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Status
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Uploaded
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr
                                        v-for="asset in filteredAssets"
                                        :key="asset.id"
                                        class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                        @click="openAuditModal(asset)"
                                    >
                                        <td class="whitespace-nowrap px-4 py-4">
                                            <div class="flex items-center gap-2">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {{ asset.file_name }}
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ asset.mime_type }}
                                                    </p>
                                                </div>
                                                <!-- Proof Count Badge -->
                                                <span 
                                                    v-if="asset.metadata?.visual_proofs?.length || asset.metadata?.audit_type === 'design'"
                                                    class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-300"
                                                >
                                                    🖼️ {{ asset.metadata?.visual_proofs?.length || 0 }} Proofs
                                                </span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatFileSize(asset.file_size) }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4">
                                            <span :class="getStatusClasses(asset.status)">
                                                {{ getStatusLabel(asset.status) }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatDate(asset.created_at) }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm">
                                            <div class="flex items-center justify-end gap-2">
                                                <!-- History Button -->
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                    @click.stop="openHistoryModal(asset)"
                                                >
                                                    <svg
                                                        class="-ml-0.5 mr-1.5 h-3 w-3"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    History
                                                </button>

                                                <!-- Delete Button -->
                                                <button
                                                    type="button"
                                                    :disabled="isDeleting === asset.id"
                                                    class="inline-flex items-center rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                                    @click="handleDelete(asset)"
                                                >
                                                    <svg
                                                        v-if="isDeleting === asset.id"
                                                        class="-ml-0.5 mr-1.5 h-3 w-3 animate-spin"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                    </svg>
                                                    <svg
                                                        v-else
                                                        class="-ml-0.5 mr-1.5 h-3 w-3"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Audit Report Modals - Route based on audit_type -->
    
    <!-- Document Modal: Clean corporate style for PDFs/Images (Only when done) -->
    <DocumentReportModal
        :show="showAuditModal && selectedAsset?.metadata?.audit_type === 'document' && !['processing', 'pending'].includes(selectedAsset?.status || '')"
        :asset="selectedAsset"
        @close="closeAuditModal"
    />
    
    <!-- Project Modal: Cyber-terminal style for Websites/GitHub (Only when done) -->
    <ProjectForensicModal
        :show="showAuditModal && (selectedAsset?.metadata?.audit_type === 'project' || selectedAsset?.metadata?.audit_type === 'design') && !['processing', 'pending'].includes(selectedAsset?.status || '')"
        :asset="selectedAsset"
        @close="closeAuditModal"
        @deep-audit="handleDeepAudit"
        @open-ai-assistant="handleOpenAiAssistant"
    />
    
    <!-- Audit Report Modal: Used for Processing/Analyzing states (with steps) AND Fallback -->
    <!-- This restores the "Analyzing..." modal with the step-by-step terminal UI -->
    <AuditReportModal
        v-if="showAuditModal && (
            ['processing', 'pending'].includes(selectedAsset?.status || '') || 
            !['document', 'project', 'design'].includes(selectedAsset?.metadata?.audit_type || '')
        )"
        :show="showAuditModal"
        :asset="selectedAsset"
        :progress="auditProgress"
        @close="closeAuditModal"
        @refresh="refreshSelectedAsset"
    />

    <!-- Credit Purchase Modal -->
    <CreditPurchaseModal
        :show="showPurchaseModal"
        @close="showPurchaseModal = false"
    />

    <!-- Asset History Modal -->
    <AssetHistoryModal
        :show="showHistoryModal"
        :asset="historyAsset"
        @close="closeHistoryModal"
    />

    <!-- Deep Scan Confirmation Modal -->
    <DeepScanConfirmationModal
        v-if="selectedAsset"
        :show="showDeepScanConfirmation"
        :asset="selectedAsset"
        @close="showDeepScanConfirmation = false"
    />

    <!-- Project Analyst Modal (AI Assistant) -->
    <ProjectAnalystModal
        v-if="selectedAsset"
        :show="showProjectAnalystModal"
        :asset="selectedAsset"
        @close="showProjectAnalystModal = false"
    />
    </AuthenticatedLayout>
</template>
