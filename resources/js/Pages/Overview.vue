<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VaultUploader from '@/Components/VaultUploader.vue';
import WalletCard from '@/Components/WalletCard.vue';

import DocumentReportModal from '@/Components/DocumentReportModal.vue';
import ProjectForensicModal from '@/Components/ProjectForensicModal.vue';
import CreditPurchaseModal from '@/Components/CreditPurchaseModal.vue';

import AssetHistoryModal from '@/Components/AssetHistoryModal.vue';
import PenetrationAndAQTesting from '@/Components/PenetrationAndAQTesting.vue';
import QAPenetrationResultsModal from '@/Components/QAPenetrationResultsModal.vue';
import GithubRepositoryForensicModal from '@/Components/GithubRepositoryForensicModal.vue';
import WebURLandGitRepoSync from '@/Components/WebURLandGitRepoSync.vue';
import MarketplaceListingModal from '@/Components/MarketplaceListingModal.vue';
import SyncScanning from '@/Components/Scanner/SyncScanning.vue';
import WebsiteScanning from '@/Components/Scanner/WebsiteScanning.vue';
import RepositoryScanning from '@/Components/Scanner/RepositoryScanning.vue';
import SecurityScanning from '@/Components/Scanner/SecurityScanning.vue';
import DocumentScanning from '@/Components/Scanner/DocumentScanning.vue';
import LumeAISupport from '@/Components/LumeAISupport.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import type { VaultAsset, DownloadUrlResponse, DeleteAssetResponse } from '@/types/vault';
import Modal from '@/Components/Modal.vue';

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
    recentActivities: any[]; // ScanActivity[]
    wallet: WalletData | null;
    organizations: any[];
    activeOrganization: any | null;
}

const props = withDefaults(defineProps<Props>(), {
    initialAssets: () => [],
    recentActivities: () => [],
    wallet: null,
    organizations: () => [],
    activeOrganization: null,
});

// State for activities
const scanActivities = ref<any[]>([]);

// State for assets (Inventory)
const recentAssets = ref<VaultAsset[]>([...props.initialAssets]);

// Sync assets from props if they change (e.g. inertia reload)
watch(() => props.initialAssets, (newAssets) => {
    recentAssets.value = [...newAssets];
}, { deep: true });

// Search state
const searchQuery = ref('');

// Modal state
const showAuditModal = ref(false);
const showPurchaseModal = ref(false);
const selectedAsset = ref<VaultAsset | null>(null); // Defined early

const showHistoryModal = ref(false);
const historyAsset = ref<VaultAsset | null>(null);

// New Modals State
const showRepoModal = ref(false);
const selectedRepoAsset = ref<VaultAsset | null>(null);

const showMarketplaceModal = ref(false);
const showSyncModal = ref(false);
const showSyncScanningModal = ref(false);
const showDocumentScanningModal = ref(false); // NEW: Dedicated flag for document scans
const showWebsiteScanningModal = ref(false); // SEPARATE modal for individual website scans
const showRepositoryScanningModal = ref(false); // NEW: Dedicated flag for repo scans
const showSecurityScanningModal = ref(false); // SEPARATE modal for Security/Penetration scans
const showPentestAiModal = ref(false);
const selectedSyncWebAsset = ref<VaultAsset | null>(null);
const selectedSyncRepoAsset = ref<VaultAsset | null>(null);
const selectedSyncComparisonData = ref<any>(null);
const isAlreadyOwned = ref(false);


// Action state
const isDeleting = ref<string | null>(null);
const showDeleteModal = ref(false);
const activityToDelete = ref<any>(null);
const isDownloading = ref<string | null>(null);
const showDeepScanConfirmation = ref(false);
const showQAResultsModal = ref(false);
const showProjectAnalystModal = ref(false);
const showRescanConfirmModal = ref(false);
watch(() => props.recentActivities, (newActivities) => {
    if (!newActivities || !Array.isArray(newActivities)) {
        scanActivities.value = [];
        return;
    }
    scanActivities.value = [...newActivities];

    // TITAN V8.1: If the sync modal is open, find the newest activity for this pair and refresh comparison data
    if (showSyncModal.value && selectedSyncWebAsset.value && selectedSyncRepoAsset.value) {
        const latest = newActivities.find(a => 
            a.type === 'sync' && 
            a.primary_asset_id === selectedSyncWebAsset.value?.id && 
            a.secondary_asset_id === selectedSyncRepoAsset.value?.id
        );
        if (latest) {
            console.log('🔄 Dashboard: Auto-refreshing Sync Comparison Data from new props');
            // We can't call openSyncModal easily since it's mixed with UI logic, 
            // but we can re-resolve the data.
            try {
                const details = typeof latest.details === 'string' ? JSON.parse(latest.details) : latest.details;
                selectedSyncComparisonData.value = {
                    ...selectedSyncComparisonData.value,
                    ...details,
                    sync_score: latest.sync_confidence_score || details?.sync_score || 0,
                    batch_id: latest.batch_id // TITAN V8.5: Capture batch ID for re-scans
                };
            } catch (e) {
                console.error("Failed to parse latest sync details", e);
            }
        }
    }
}, { immediate: true, deep: true });



// Audit progress state for real-time updates
const auditProgress = ref<{ step: string; progress: number; details?: string } | null>(null);
const auditLogs = ref<string[]>([]);

// Active tab state (synced from VaultUploader)
const activeTab = ref<'all' | 'website' | 'repository' | 'document' | 'sync'>('all');

// Computed: Filtered activities
const filteredActivities = computed(() => {
    let result = scanActivities.value;

    // 1. Search Filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(activity => 
            (activity.urls_and_sync && activity.urls_and_sync.toLowerCase().includes(query)) ||
            (activity.display_name && activity.display_name.toLowerCase().includes(query))
        );
    }

    // REMOVED: Client-side deduplication. 
    // The Backend now handles grouping by (URL + BatchID), so every unique Batch is already a unique row.
    // We want to show ALL of them.
    
    result = result.sort((a, b) => b.id - a.id); // Sort by newness

    
    // 2. Tab Filter
    let filtered = result;
    if (activeTab.value !== 'all') {
        filtered = result.filter(activity => {
            const type = activity.type; // document, website, repository, sync
            if (activeTab.value === 'website') return type === 'website';
            if (activeTab.value === 'repository') return type === 'repository' || type === 'repository_scan';
            // Document tab should show explicit documents OR unknown types that might be docs
            if (activeTab.value === 'document') return type === 'document' || !type;
            if (activeTab.value === 'sync') return type === 'sync';
            return true;
        });
    }

    // CRITICAL: Merge in Pending/Uploaded Documents from recentAssets 
    // IF we are in 'all' or 'document' tab.
    // The user wants to see documents even if they haven't generated a "ScanActivity" yet.
    if (['all', 'document'].includes(activeTab.value)) {
        const potentialDocs = recentAssets.value.filter(a => {
            const auditType = a.metadata?.audit_type || 'document';
            // TITAN V8.2: Ensure we don't show ghost assets (null filenames)
            if (!a.file_name || a.file_name.trim() === '') return false;
            return ['document', 'pdf', 'contract'].includes(auditType);
        });

        // Map them to activity-like structure
        const mappedDocs = potentialDocs.map(asset => ({
            id: 'asset_' + asset.id, // Prefix to avoid collision
            type: 'document',
            primary_asset: asset, // The asset itself
            primary_asset_id: asset.id,
            scanned_at: asset.created_at, // Use creation time as "scan" time for pending
            docu_and_urls_status: asset.status,
            urls_and_sync: asset.file_name,
            display_name: asset.file_name,
            is_virtual: true // Marker
        }));

        // Filter out if already present in real activities (by primary asset id)
        const uniqueMapped = mappedDocs.filter(d => 
            !filtered.some(existing => existing.primary_asset?.id === d.primary_asset.id || existing.primary_asset_id === d.primary_asset.id)
        );

        // Add to result
        filtered = [...uniqueMapped, ...filtered];
        
        // Final Sort by time
        filtered.sort((a, b) => new Date(b.scanned_at).getTime() - new Date(a.scanned_at).getTime());
    }

    return filtered;
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
    // DEBUG: Catch Reload Trigger
    window.addEventListener('beforeunload', (event) => {
        console.warn('🚨 PAGE RELOAD DETECTED');
        console.trace('Reload Trace');
    });

    const page = usePage();
    const user = page.props.auth.user;

    if (user?.id) {
        if (window.Echo) {
            console.log('🔌 Connecting to WebSocket channel:', `App.Models.User.${user.id}`);
            
            window.Echo.private(`App.Models.User.${user.id}`)
                // Listen for audit progress updates (real-time loading screen)
                // Note: .listen() with broadcastAs() needs dot prefix
                .listen('.AuditProgressUpdated', (e: { asset_id: string; step: string; progress: number, details?: string }) => {
                    // Update progress if this is the asset we're watching (Primary OR Sync Peers)
                    const isRelevant = selectedAsset.value?.id === e.asset_id || 
                                     selectedSyncRepoAsset.value?.id === e.asset_id || 
                                     selectedSyncWebAsset.value?.id === e.asset_id;

                    if (isRelevant) {
                        auditProgress.value = { step: e.step, progress: e.progress, details: e.details };
                        // Log accumulation for terminal output
                        const timestamp = new Date().toLocaleTimeString([], { hour12: false });
                        auditLogs.value.push(`[${timestamp}] ${e.step} ${e.details ? ' | ' + e.details : ''}`);

                        // LATCH: Ensure Repository Modal stays open if we are scanning a repo
                        // This prevents it from disappearing if status changes or flickers
                        if (selectedAsset.value?.metadata?.audit_type === 'repository' || selectedAsset.value?.metadata?.audit_type === 'repository_scan') {
                             showRepositoryScanningModal.value = true;
                        }
                    }
                })
                // Listen for final status updates (audit complete)
                .listen('.AssetStatusUpdated', (e: { asset: VaultAsset }) => {
                    // Update main list
                    const index = recentAssets.value.findIndex(a => a.id === e.asset.id);
                    if (index !== -1) {
                        recentAssets.value[index] = e.asset;

                        // Update selectedAsset if active
                        if (selectedAsset.value?.id === e.asset.id) {
                            selectedAsset.value = e.asset;

                            // TITAN V9.0: Removed auto-close logic to allow for manual "VIEW RESULTS" overlay
                            // showDocumentScanningModal.value = false;
                            // showRepositoryScanningModal.value = false;
                            // showAuditModal.value = true;
                            
                            // Background refresh
                            setTimeout(() => refreshDashboardData(), 1000);
                        }

                        // Update Sync Assets
                        if (selectedSyncWebAsset.value?.id === e.asset.id) {
                            selectedSyncWebAsset.value = e.asset;
                        }
                        if (selectedSyncRepoAsset.value?.id === e.asset.id) {
                            selectedSyncRepoAsset.value = e.asset;
                        }
                    }
                });
        } else {
            console.warn('Laravel Echo not initialized. Real-time updates disabled.');
        }
    }
});

/**
 * TITAN V8.5: Background Data Refresh (Replaces Router Reload)
 */
const refreshDashboardData = async () => {
    try {
        console.log("Refreshing Dashboard Data (Background)...");
        const response = await axios.get('/dashboard/refresh');
        if (response.data) {
             // Update Refs directly
             if (response.data.recentActivities) {
                 scanActivities.value = response.data.recentActivities;
             }
             if (response.data.initialAssets) {
                 recentAssets.value = response.data.initialAssets;
             }
             console.log("Verified Dashboard Data Refreshed (Background)");
        }
    } catch (e) {
        console.error("Failed to refresh dashboard data", e);
    }
};

/**
 * Simulated Progress and Polling Fallback (Alternative to Broadcast)
 * Ensures a smooth 0-100% UI animation without waiting for choppy websockets.
 */
let progressSimulator: any = null;
watch(() => [showWebsiteScanningModal.value, showRepositoryScanningModal.value, showSyncScanningModal.value, showDocumentScanningModal.value, showSecurityScanningModal.value], (vals) => {
    const isAnyScanning = vals.some(v => v);
    if (isAnyScanning && selectedAsset.value && ['processing', 'pending', 'scanning'].includes(selectedAsset.value.status || '')) {
        // Init Mock Progress
        if (!auditProgress.value || auditProgress.value.progress >= 100 || auditProgress.value.progress === 0) {
             auditProgress.value = { step: 'Initializing Titan Protocols...', progress: 1 };
        }
        if (progressSimulator) clearInterval(progressSimulator);
        
        let ticks = 0;
        
        // Determine total duration based on which modal is open
        // Sync Scans take ~13 mins (780 seconds), others take ~5 mins (300 seconds)
        const isSyncScan = showSyncScanningModal.value;
        const totalDurationSecs = isSyncScan ? 780 : 300;
        // Ticks occur every 1 second. We want to reach 100% in `totalDurationSecs`.
        // So progress per tick is 100 / totalDurationSecs.
        const tickMultiplier = 100 / totalDurationSecs;

        progressSimulator = setInterval(() => {
             ticks++;
             
             // Smooth Progress Simulation
             if (auditProgress.value) {
                 let targetProgress = Math.floor(ticks * tickMultiplier);
                 if (targetProgress > 100) targetProgress = 100;

                 // Allow it to jump if actual progress (from earlier) is higher, 
                 // but normally we just control it here
                 if (auditProgress.value.progress < targetProgress) {
                     auditProgress.value.progress = targetProgress;
                 }
                 
                 if (auditProgress.value.progress < 100) {
                     if (isSyncScan) {
                         if (auditProgress.value.progress < 40) auditProgress.value.step = 'Ingesting Source Repository...';
                         else if (auditProgress.value.progress < 80) auditProgress.value.step = 'Crawling Website Endpoints...';
                         else auditProgress.value.step = 'Synchronizing Domain Vectors...';
                     } else if (showRepositoryScanningModal.value) {
                         if (auditProgress.value.progress < 20) auditProgress.value.step = 'Acquiring Repository...';
                         else if (auditProgress.value.progress < 40) auditProgress.value.step = 'Analyzing Branch Hierarchy...';
                         else if (auditProgress.value.progress < 60) auditProgress.value.step = 'Mapping Dependency Tree...';
                         else if (auditProgress.value.progress < 80) auditProgress.value.step = 'Extracting Tech Stack...';
                         else auditProgress.value.step = 'Generating Forensic Report...';
                     } else {
                         if (auditProgress.value.progress < 20) auditProgress.value.step = 'Acquiring Target...';
                         else if (auditProgress.value.progress < 40) auditProgress.value.step = 'Analyzing DOM Structure...';
                         else if (auditProgress.value.progress < 60) auditProgress.value.step = 'Mapping Internal Endpoints...';
                         else if (auditProgress.value.progress < 80) auditProgress.value.step = 'Extracting Tech Stack...';
                         else auditProgress.value.step = 'Generating Forensic Report...';
                     }
                 } else {
                     auditProgress.value.step = 'Finalizing... Waiting for Server Verification';
                 }
             }

             // Poll API every 5 ticks (~5 seconds) to catch actual completion without broadcast
             if (selectedAsset.value && ticks % 5 === 0) {
                 axios.get(`/api/vault/assets/${selectedAsset.value.id}`).then(res => {
                     const asset = res.data?.asset;
                     if (asset && ['verified', 'flagged'].includes(asset.status)) {
                         // It's complete! Snap to 100%
                         if (auditProgress.value) {
                             auditProgress.value.progress = 100;
                             auditProgress.value.step = 'Recon Complete';
                         }
                         
                         // Update state 
                         selectedAsset.value = asset;
                         // Update in list
                         const index = recentAssets.value.findIndex(a => a.id === asset.id);
                         if (index !== -1) recentAssets.value[index] = asset;
                         
                         clearInterval(progressSimulator);
                         
                         // Let user see 100% for a brief moment before background refresh
                         setTimeout(() => { refreshDashboardData(); }, 1500);
                     } else if (asset && asset.status === 'failed') {
                         if (auditProgress.value) {
                             auditProgress.value.step = 'Scan Failed';
                         }
                         clearInterval(progressSimulator);
                     }
                 }).catch(() => {});
             }
        }, 1000);
    } else {
        if (progressSimulator) clearInterval(progressSimulator);
    }
}, { deep: true });

/**
 * Handle successful upload of a single asset
 */
const handleUploadSuccess = (asset: VaultAsset) => {
    // Add to beginning of list (most recent first)
    recentAssets.value.unshift(asset);

    // ONLY open audit modal if:
    // 1. It is a 'processing' asset (like Project/Repo/Sync scans that auto-start)
    // 2. OR status is 'verified', 'flagged' (historical/completed)
    // 3. DO NOT open for 'uploaded' status (Manual Document Scan flow)
    if (asset.status !== 'uploaded') {
        openAuditModal(asset);
    }
};

/**
 * Handle manual scan start (from VaultUploader)
 */
const handleScanStarted = (asset: VaultAsset) => {
    // Find and update the asset in the list
    const index = recentAssets.value.findIndex(a => a.id === asset.id);
    if (index !== -1) {
        recentAssets.value[index] = asset;
    } else {
        recentAssets.value.unshift(asset);
    }

    // Set selectedAsset
    selectedAsset.value = asset;

    // Open Scanning Modal (using showSyncScanningModal as shared flag for documents)
    // TITAN V9.2: Accurate Fallback using is_project flat to prevent Document Modal sprawl
    const isProjectAsset = asset.metadata?.is_project || asset.metadata?.is_sync_scan || asset.metadata?.is_repo_scan;
    const auditType = asset.metadata?.audit_type || (isProjectAsset ? 'project' : 'document');
    
    if (['document', 'pdf', 'contract'].includes(auditType)) {
        showDocumentScanningModal.value = true;
        // Ensure other modals closed
        showAuditModal.value = false;
        showRepoModal.value = false;
        showSyncScanningModal.value = false;
        showRepositoryScanningModal.value = false;
        showWebsiteScanningModal.value = false;
        
        // Reset progress UI
        auditProgress.value = { step: 'Queueing Scan...', progress: 0, details: 'Initiating document forensic job...' };
    } else if (['repository', 'repository_scan', 'github'].includes(auditType)) {
        // TITAN V8.5: Explicitly open Repository Scanning Modal
        showRepositoryScanningModal.value = true;
        showAuditModal.value = false;
        showRepoModal.value = false;
        showSyncScanningModal.value = false;
        showDocumentScanningModal.value = false;
        showWebsiteScanningModal.value = false;
        auditProgress.value = { step: 'Queueing Repository Scan...', progress: 0 };
    } else if (['project', 'website', 'website_scan', 'design'].includes(auditType)) {
        // TITAN V9.2: Expanded website variants
        showWebsiteScanningModal.value = true;
        showAuditModal.value = false;
        showRepoModal.value = false;
        showSyncScanningModal.value = false;
        showDocumentScanningModal.value = false;
        showRepositoryScanningModal.value = false;
        auditProgress.value = { step: 'Queueing Website Recon...', progress: 0 };
    } else {
        // Fallback
        showAuditModal.value = true;
    }
};

/**
 * Handle all uploads complete
 */
function handleAllUploadsComplete(assets: VaultAsset[]): void {
    // Open modal for the first asset if available AND NOT 'uploaded' (manual scan pending)
    if (assets.length > 0) {
        if (assets[0].status !== 'uploaded') {
            openAuditModal(assets[0]);
        }
    }
}

/**
 * Open audit modal for an asset
 */
/**
 * Open audit modal for an asset
 */
function openAuditModal(asset: VaultAsset): void {
    selectedAsset.value = asset;

    // SMART ROUTING: Switch to specific scanning monitors if active or sync
    // Fixes the "Wrong Modal" issue for Sync Scans
    const auditStr = String(asset.metadata?.audit_type || '').toLowerCase();
    const isSync = asset.metadata?.is_sync_scan || auditStr === 'sync' || auditStr === 'sync_scan';
    const isDocument = ['document', 'pdf', 'contract'].includes(auditStr);
    const isRepository = ['repository', 'repository_scan', 'github'].includes(auditStr);
    const isWebsite = ['project', 'website', 'website_scan', 'design'].includes(auditStr);
    const isProcessing = ['processing', 'scanning', 'pending'].includes(asset.status || '');
    
    if ((isSync || isDocument || isRepository || isWebsite) && isProcessing) {
        if (isDocument) {
             showDocumentScanningModal.value = true;
        } else if (isRepository) {
             showRepositoryScanningModal.value = true;
             showAuditModal.value = false;
        } else if (isWebsite) {
             showWebsiteScanningModal.value = true;
             showAuditModal.value = false;
        } else {
             showSyncScanningModal.value = true;
        }
        return;
    }

    // Default to ProjectForensicModal (Results)
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
 * Handle confirmation of Deep Scan (Penetration Test)
 * Triggers the backend job via API
 */
async function handleDeepScanConfirm(customPrompt: string, isRescan: boolean = false): Promise<void> {
    if (!selectedAsset.value) return;

    console.log('🚀 Dashboard: handleDeepScanConfirm triggered for:', selectedAsset.value.id);

    try {
        const response = await axios.post('/api/vault/deep-audit', {
            asset_id: selectedAsset.value.id,
            custom_prompt: customPrompt,
            is_rescan: isRescan // Explicitly request discounted rate
        });
        
        // Reset logs for new scan
        auditLogs.value = [];

        // UI Transition: Close Result Modal, Open Security Scanning Monitor
        showQAResultsModal.value = false;
        showSecurityScanningModal.value = true; // CRITICAL: Open the SECURITY scanning modal to show progress
        
        console.log('🔐 SecurityScanning Modal State:', {
            showSecurityScanningModal: showSecurityScanningModal.value,
            selectedAsset: selectedAsset.value?.id,
            hasURL: !!(selectedAsset.value?.website_url || selectedAsset.value?.original_url)
        });
        
        // Optimistic UI Update: Force status to processing immediately
        if (selectedAsset.value) {
            selectedAsset.value = { ...selectedAsset.value, status: 'processing' };
        }
        
        console.log('✅ Dashboard: Deep Scan Triggered Successfully', response.data);

        // Current status update to show immediate feedback (optional, as Echo will also update)
    } catch (error: any) {
        console.error('❌ Dashboard: Failed to trigger deep scan:', error);
        const errorData = error.response?.data;
        const errorMsg = errorData?.error || errorData?.message || error.message || 'Failed to initiate QA & Penetration Scan.';
        alert(`Error: ${errorMsg}\n\nPlease try again.`);
        
        // REVERT UI on error
        showSecurityScanningModal.value = false;
        showQAResultsModal.value = true;
        
        if (selectedAsset.value) {
            selectedAsset.value = { ...selectedAsset.value, status: 'ready' };
        }
    }
    return Promise.resolve(); // Ensure promise resolves even if void
}

/**
 * Handle initial sync request from Uploader
 */
const handleSyncInit = (assets: [VaultAsset, VaultAsset]) => {
    console.log("🚦 Sync Initialization Triggered:", assets);
    
    // 1. Reset progress for fresh scan
    auditProgress.value = { step: 'Initializing Sync...', progress: 0, details: 'Titan Sync Protocol Handshake...' };
    
    // 2. Set active assets for the SyncScanning component
    selectedSyncWebAsset.value = assets[0];
    selectedSyncRepoAsset.value = assets[1];
    
    // Set selectedAsset as the primary driver (required by SyncScanning logic)
    selectedAsset.value = assets[0];

    // 3. Open the SyncScanning modal
    showSyncScanningModal.value = true;
    
    // Close generic audit modal if open
    showAuditModal.value = false;
}

/**
 * Handle re-sync request
 */
const handleSyncRescan = async () => {
    // FALLBACK: If selectedAsset is lost but we have sync assets, restore it
    if (!selectedAsset.value && selectedSyncWebAsset.value) {
        selectedAsset.value = selectedSyncWebAsset.value;
    }

    if (!selectedAsset.value) return;

    // TITAN V7: If listed on marketplace (or either asset in sync is listed), require confirmation
    const isMarketplace = selectedAsset.value.is_for_sale || 
                        selectedSyncWebAsset.value?.is_for_sale || 
                        selectedSyncRepoAsset.value?.is_for_sale;

    if (isMarketplace) {
        showRescanConfirmModal.value = true;
        return;
    }

    executeRescan();
};

const executeRescan = async () => {
    if (!selectedAsset.value) return;
    
    showRescanConfirmModal.value = false;
    showSyncModal.value = false; // Close report modal

    // TITAN V7: Reset progress UI for the new scan session
    auditProgress.value = { step: 'Initializing Titan Sync Protocol...', progress: 2, details: 'Contacting forensic nodes...' };

    // Open the SyncScanning modal immediately
    showSyncScanningModal.value = true;
    
    console.log("Initiating Sync Rescan for", selectedAsset.value.id);
    
    try {
        // Trigger the sync job via the Project Sync Endpoint
        // We prioritise passing specific asset IDs if we are in the Sync view
        const payload: any = { asset_id: selectedAsset.value.id };
        
        if (selectedSyncWebAsset.value && selectedSyncRepoAsset.value) {
            payload.web_asset_id = selectedSyncWebAsset.value.id;
            payload.repo_asset_id = selectedSyncRepoAsset.value.id;
            // TITAN V8: Explicitly pass URLs to fix metadata resolution issues in stubs
            payload.website_url = selectedSyncWebAsset.value.website_url || selectedSyncWebAsset.value.original_url || selectedSyncWebAsset.value.file_name;
            payload.github_repo_url = selectedSyncRepoAsset.value.metadata?.github_repo_url || selectedSyncRepoAsset.value.file_name;

            // TITAN V8.5: Pass Batch ID to FORCE valid re-scan of existing set (Prevents duplicate entries)
            if (selectedSyncComparisonData.value?.batch_id) {
                payload.batch_id = selectedSyncComparisonData.value.batch_id;
            }
        } else if (selectedAsset.value) {
            // Support for non-sync assets if triggered via handleSyncRescan
            payload.website_url = selectedAsset.value.website_url || selectedAsset.value.original_url || selectedAsset.value.file_name;
        }

        await axios.post('/api/projects/sync', payload);
        // Progress will be handled via Echo listeners already set up
    } catch (e) {
        console.error("Sync Trigger Failed", e);
    }
};

const handleViewQAResults = () => {
    // Open QA Results
    showQAResultsModal.value = true;
};

/**
 * Handle completion of Deep Scan from PenetrationAndAQTesting Console
 */
function handleDeepAuditCompletion(): void {
    console.log('✅ Dashboard: Deep Audit Complete. Opening Results...');
    refreshSelectedAsset(); // Fetch final scores
    setTimeout(() => {
        showAuditModal.value = true; // Now show the result modal (ProjectForensicModal)
    }, 500);
}

const switchContext = (orgId: number | null) => {
    router.post(route('organizations.switch'), {
        organization_id: orgId
    }, {
        preserveScroll: true,
        onSuccess: () => {
            console.log('Context Switched Successfully');
            refreshDashboardData();
        }
    });
};

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
 * Handle viewing a historical snapshot from AssetHistoryModal
 */
/**
 * Handle viewing a historical snapshot from AssetHistoryModal
 * TITAN V6.4: Supports Triad View (Website | Sync | Repo)
 */
function handleViewSnapshot(payload: { item: any, list: any[], type?: string }) {
    const { item, type } = payload;
    console.log('📜 Viewing Historical Snapshot:', type || 'legacy', item);
    
    // 1. Close History Modal
    showHistoryModal.value = false;

    // 2. Hydrate & Route based on Type
    
    // --- SYNC VIEW ---
    if (type === 'sync' || item.metadata?.audit_type === 'sync_scan' || item.metadata?.audit_context === 'sync') {
        // For Sync Items, the comparison data might be nested in 'comparison_data_snapshot' 
        // if it came from the new Sync Job, or strictly in metadata for legacy.
        const snapshot = item.metadata?.comparison_data_snapshot || item.metadata;
        
        selectedSyncComparisonData.value = {
            ...snapshot,
            score: item.score,
            scanned_at: item.created_at,
            batch_id: item.batch_id || item.metadata?.batch_id, // TITAN V8.5: Capture batch ID
            is_historical: true,
            // Ensure Essential Keys for WebURLandGitRepoSync exist
            drift_analysis: snapshot.drift_analysis || {},
            hexagon_vectors: snapshot.hexagon_vectors || item.metadata.breakdown || [],
        };
        showSyncModal.value = true;
        return;
    }

    // --- BASE HISTORICAL ASSET CONSTRUCT ---
    const historicalAsset = {
        ...historyAsset.value, // Inherit base asset info
        score: item.score,
        status: item.status,
        metadata: {
            ...item.metadata,
            is_historical_snapshot: true,
            snapshot_date: item.created_at
        }
    };

    // --- REPOSITORY VIEW ---
    if (type === 'repository' || item.metadata?.audit_type === 'repository_scan' || item.metadata?.audit_type === 'repository') {
        selectedRepoAsset.value = historicalAsset as VaultAsset;
        showRepoModal.value = true;
        return;
    }

    // --- WEBSITE/DEFAULT VIEW ---
    // Default to Website/Doc if type is 'website' or unknown
    selectedAsset.value = historicalAsset as VaultAsset;
    showAuditModal.value = true;
}

function closeHistoryModal(): void {
    showHistoryModal.value = false;
    historyAsset.value = null;
}

// Watch for specific asset updates (e.g. status changes during audit)
watch(() => props.initialAssets, (newAssets) => {
    // Update selectedAsset reference so modal gets the new status
    if (selectedAsset.value) {
        const found = (newAssets || []).find(a => a.id === selectedAsset.value?.id);
        if (found) selectedAsset.value = found;
    }

    // Refresh Sync-specific refs so "List on Marketplace" updates after Inertia reload
    if (selectedSyncWebAsset.value) {
        const found = (newAssets || []).find(a => a.id === selectedSyncWebAsset.value?.id);
        if (found) selectedSyncWebAsset.value = found;
    }
    if (selectedSyncRepoAsset.value) {
        const found = (newAssets || []).find(a => a.id === selectedSyncRepoAsset.value?.id);
        if (found) selectedSyncRepoAsset.value = found;
    }
}, { immediate: true, deep: true });

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
 * Handle confirmation of listing an asset on the marketplace
 */
function handleMarketplaceConfirm(payload: { price: number, name: string }): void {
    if (!selectedAsset.value) {
        console.error('No asset selected for marketplace listing');
        return;
    }

    router.post(route('marketplace.publish', selectedAsset.value.id), {
        price: payload.price,
        custom_name: payload.name
    }, {
        onSuccess: () => {
            showMarketplaceModal.value = false;
            showSyncModal.value = false;
        },
        onError: (errors) => {
            console.error('Marketplace listing failed:', errors);
        }
    });
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
 * Handle closing the scanning modal (User manually closes or component signals completion)
 * Usage: Passed to @close event of Scanning components
 */
function handleCloseScanningModal() {
    // 1. Close all scanning modals
    showWebsiteScanningModal.value = false;
    showSecurityScanningModal.value = false;
    showSyncScanningModal.value = false;
    showRepositoryScanningModal.value = false;
    showDocumentScanningModal.value = false;

    // 2. Open the Result Modal (if validation passed)
    if (selectedAsset.value && ['verified', 'action_required', 'flagged'].includes(selectedAsset.value.status)) {
        // TITAN V8.1: If it's a sync scan, we want the WebURLandGitRepoSync modal instead of the basic ProjectForensicModal
        const auditStr = String(selectedAsset.value.metadata?.audit_type || '').toLowerCase();
        const isSync = selectedAsset.value.metadata?.is_sync_scan || auditStr === 'sync_scan' || auditStr === 'sync';

        if (isSync) {
            // TITAN V8.5: Prevent auto-reload. Fetch data in background instead.
            refreshDashboardData(); 
            showSyncModal.value = true;
        } else {
            showAuditModal.value = true;
        }
    }
}

/**
 * Get status badge classes based on asset status
 */
function getStatusClasses(status: VaultAsset['status']): string {
    const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium';
    const s = status as string;
    switch (s) {
        case 'pending':
            return `${baseClasses} bg-amber-500/10 text-amber-400 border border-amber-500/30`;
        case 'uploaded':
            return `${baseClasses} bg-cyan-500/10 text-cyan-300 border border-cyan-500/20`;
        case 'processing':
            return `${baseClasses} bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 animate-pulse`;
        case 'ready':
        case 'verified':
            return `${baseClasses} bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 shadow-[0_0_10px_rgba(52,211,153,0.15)]`;
        case 'verified_private':
            return `${baseClasses} bg-emerald-500/20 text-emerald-300 border border-emerald-500/40`;
        case 'flagged':
        case 'failed':
        case 'failed_system':
            return `${baseClasses} bg-red-500/10 text-red-400 border border-red-500/30`;
        case 'action_required':
            return `${baseClasses} bg-amber-500/20 text-amber-400 border border-amber-500/40`;
        default:
            return `${baseClasses} bg-slate-800 text-slate-400 border border-slate-700`;
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
 * Delete a scan activity - Open Confirmation Modal
 */
async function handleDeleteActivity(activity: any): Promise<void> {
    if (isDeleting.value) return;

    activityToDelete.value = activity;
    showDeleteModal.value = true;
}

/**
 * Confirm Delete Action
 */
async function confirmDelete(): Promise<void> {
    if (!activityToDelete.value) return;
    
    const activity = activityToDelete.value;
    isDeleting.value = String(activity.id);

    try {
        await axios.delete(`/scan-activities/${activity.id}`);

        // Remove from local state
        scanActivities.value = scanActivities.value.filter(a => a.id !== activity.id);
        
        // Close modal
        showDeleteModal.value = false;
        activityToDelete.value = null;
    } catch (error) {
        console.error('Delete failed:', error);
        alert('Failed to delete record. Please try again.');
    } finally {
        isDeleting.value = null;
    }
}

/**
 * Handle row click to open appropriate forensic modal
 */
function handleRowClick(activity: any) {
    console.log('DEBUG: Row Clicked', JSON.parse(JSON.stringify(activity)));
    console.log('DEBUG: Vectors', activity.vectors);
    console.log('DEBUG: Primary Asset Metadata', activity.primary_asset?.metadata);
    console.log('DEBUG: Primary Asset Synced Metadata', activity.primary_asset?.synced_metadata);
    if (isDeleting.value) return; // Don't trigger if deleting

    console.log('Opening details for activity:', activity.type, activity);

    // Helper to create a "Snapshot" asset that reflects the historical data
    // This overlays the activity's saved metadata (vectors) onto the asset structure
    const createSnapshotAsset = (baseAsset: any, activityRecord: any, type: string) => {
        if (!baseAsset) return null;
        
        // Clone to avoid mutating
        const snapshot = JSON.parse(JSON.stringify(baseAsset));
        
        // Extract vectors AND detailed report
        // 'details' needs parsing because it's not cast in the Model
        let details = activityRecord.details || {};
        if (typeof details === 'string') {
             try { details = JSON.parse(details); } catch (e) { console.error("Error parsing details", e); details = {}; }
        }

        let vectors = activityRecord.vectors || {};
        if (typeof vectors === 'string') {
             try { vectors = JSON.parse(vectors); } catch (e) { vectors = {}; }
        }

        // Merge deep data: Asset Metadata < Details < Vectors (for chart accuracy)
        const deepMerge = {
            ...details,
            ...vectors, // Ensure chart numbers are latest
        };
        
        // CRITICAL FIX: Merge specialized Asset columns into metadata
        // The modal expects everything in 'metadata', but DB stores some in 'website_metadata' etc.
        if (baseAsset.website_metadata) {
            Object.assign(snapshot.metadata, baseAsset.website_metadata);
        }
        if (baseAsset.repository_metadata) {
            Object.assign(snapshot.metadata, baseAsset.repository_metadata);
        }
        // Ensure radar_data is available in metadata for the radar chart
        if (baseAsset.radar_data) {
             snapshot.metadata.radar_data = baseAsset.radar_data;
        }

        // CRITICAL FIX: If activity has vectors, force them into metadata.hexagon_vectors and radar_data
        // This ensures the charts render usage the latest scan data
        if (activityRecord.vectors) {
            snapshot.metadata.hexagon_vectors = activityRecord.vectors;
            snapshot.metadata.radar_data = activityRecord.vectors;
        }

        // FLATTEN NESTED DATA (Critical Fix for Modals)

        // FLATTEN NESTED DATA (Critical Fix for Modals)
        // If the data is nested under 'security_audit', 'result', or 'scan_result', move it up.
        if (deepMerge.security_audit) {
             Object.assign(deepMerge, deepMerge.security_audit);
        }
        
        // CRITICAL DATA MAPPING: Merge the Activity Details (AI Result) into Snapshot Metadata
        // This makes sure 'tech_assessment', 'supply_chain_stats', 'ghost_code' are visible to the Modal
        Object.assign(snapshot.metadata, deepMerge);

        // Also check the base asset metadata for nesting
        if (snapshot.metadata && snapshot.metadata.security_audit) {
             Object.assign(snapshot.metadata, snapshot.metadata.security_audit);
        }

        // TITAN V8.3: LIVE DATA OVERRIDE
        // The user reported that VaultAsset has the correct "Massive" tech stack, but ScanActivity (details) has old data.
        // We must re-apply the LIVE metadata for critical fields if available, overriding the snapshot.
        if (baseAsset.metadata?.tech_stack && Array.isArray(baseAsset.metadata.tech_stack) && baseAsset.metadata.tech_stack.length > 0) {
             snapshot.metadata.tech_stack = baseAsset.metadata.tech_stack;
        }
        if (baseAsset.metadata?.tech_assessment) {
             // Merge deeply to avoid losing other assessment data if live is partial (unlikely)
             snapshot.metadata.tech_assessment = { 
                 ...snapshot.metadata.tech_assessment, 
                 ...baseAsset.metadata.tech_assessment 
             };
        }
        
        // Merge historical metadata if available
        if (deepMerge) {
            snapshot.metadata = { 
                ...snapshot.metadata, 
                ...deepMerge,
                // Only mark as historical if we are explicitly looking at an old activity, 
                // OR if we want to emphasize the point-in-time nature.
                // For now, let's keep it but rename the UI label to be less confusing if needed.
                is_historical_snapshot: true, 
                snapshot_date: activityRecord.created_at || activityRecord.scanned_at
            };
            
            // Explicitly set the score from the Activity Record, NOT the stale Asset record
            // Use the correct score validation based on type
            const newScore = type === 'sync' 
                ? activityRecord.sync_confidence_score 
                : activityRecord.individual_score;

            if (newScore !== undefined && newScore !== null) {
                snapshot.score = newScore;
                if (snapshot.metadata) snapshot.metadata.confidence_score = newScore;
            }
        }

        // CRITICAL: Ensure TOPOLOGY is merged if available in website_metadata or details
        if (deepMerge.topology) snapshot.metadata.topology = deepMerge.topology;
        if (baseAsset.website_metadata?.topology) snapshot.metadata.topology = baseAsset.website_metadata.topology;

        // FORCE audit_type to ensure the correct modal opens
        if (!snapshot.metadata) snapshot.metadata = {};
        
        if (type === 'website') {
             snapshot.metadata.audit_type = 'website'; 
        } else if (type === 'repository') {
             snapshot.metadata.audit_type = 'repository';
        } else if (type === 'document') {
             snapshot.metadata.audit_type = 'document';
        }
        
        return snapshot;
    };

    if (activity.type === 'website') {
        if (activity.primary_asset) {
            // Force type 'website' and pass full activity for fresh score
            selectedAsset.value = createSnapshotAsset(activity.primary_asset, activity, 'website');
            
            // Check if this is a live scan
            if (['processing', 'pending'].includes(selectedAsset.value?.status || '')) {
                showWebsiteScanningModal.value = true;
                showAuditModal.value = false; // Force close generic modal
            } else {
                showAuditModal.value = true; 
            }
        }
    } else if (activity.type === 'repository') {
        if (activity.primary_asset) {
             selectedRepoAsset.value = createSnapshotAsset(activity.primary_asset, activity, 'repository');
             selectedAsset.value = selectedRepoAsset.value; // set global asset for the scanning component
             
             if (['processing', 'pending'].includes(selectedRepoAsset.value?.status || '')) {
                 showRepositoryScanningModal.value = true;
                 showRepoModal.value = false;
             } else {
                 showRepoModal.value = true;
             }
        }
    } else if (activity.type === 'sync') {
        const primaryId = activity.primary_asset?.id || activity.primary_asset_id;
        const secondaryId = activity.secondary_asset?.id || activity.secondary_asset_id;
        
        // Lookup in recentAssets allows us to get the full metadata including customized fields from Controller
        const fullPrimary = recentAssets.value.find(a => a.id === primaryId) || activity.primary_asset || { id: primaryId, file_name: 'Unknown Web Asset', metadata: { audit_type: 'sync', is_sync_scan: true }, is_for_sale: false };
        const fullSecondary = recentAssets.value.find(a => a.id === secondaryId) || activity.secondary_asset || { id: secondaryId, file_name: 'Unknown Repo Asset', metadata: { audit_type: 'sync', is_sync_scan: true }, is_for_sale: false };

        // Always set the assets, using what we found or the stubs
        selectedSyncWebAsset.value = fullPrimary;
        selectedSyncRepoAsset.value = fullSecondary;

        // CHECK PROCESSING STATE: If either is running, show Scanner instead of Report
        const isProcessing = ['processing', 'scanning', 'pending'].includes(fullPrimary.status || '') || 
                             ['processing', 'scanning', 'pending'].includes(fullSecondary.status || '');

        if (isProcessing) {
            // Set selectedAsset to Primary so SyncScanning can read URL
            selectedAsset.value = fullPrimary;
            
            // EXPLICITLY CLOSE OTHER MODALS to prevent override
            showAuditModal.value = false;
            showRepoModal.value = false;
            showWebsiteScanningModal.value = false;
            
            showSyncScanningModal.value = true;
            return;
        }
        
        // Data Resolution Strategy:
        // Merge order: 
        // 1. Asset Synced Metadata (Base layer: has sync_score, drift_analysis, old vectors)
        // 2. Activity Details (Middle layer: has intermediate data)
        // 3. Activity Vectors (Top layer: Fresh numeric vectors from latest scan)
        const hasData = (obj: any) => obj && typeof obj === 'object' && Object.keys(obj).length > 0;
        const assetSyncData = fullPrimary?.synced_metadata || fullSecondary?.synced_metadata || {};
        
        // Start with the persisted rich metadata (scores, text, diagnosis)
        let syncDataToUse = { ...assetSyncData };

        // If we have fresh activity data, overlay it
        if (hasData(activity.details)) {
             syncDataToUse = { ...syncDataToUse, ...activity.details };
        }
        
        // If we have specific vectors, overlay them (handling potential nesting)
        if (hasData(activity.vectors)) {
             // If vectors is just numbers { velocity: 50 }, merge it.
             // If it has hexagon_vectors { hexagon_vectors: { velocity: 50 } }, use that.
             if (activity.vectors.hexagon_vectors) {
                 syncDataToUse = { ...syncDataToUse, ...activity.vectors };
             } else {
                 // It's likely the flat vector list. We preserve the metadata but update the vectors.
                 syncDataToUse.hexagon_vectors = { ...(syncDataToUse.hexagon_vectors || {}), ...activity.vectors };
                 // Also merge top-level for robustness
                 syncDataToUse = { ...syncDataToUse, ...activity.vectors };
             }
        }
        
        // Final Safety: Ensure sync_score is present if available on the activity wrapper
         if (activity.sync_confidence_score) {
             syncDataToUse.sync_score = activity.sync_confidence_score;
         }

         selectedSyncComparisonData.value = syncDataToUse;
         showSyncModal.value = true;
    } else {
        // Fallback: Document or Unknown Type
        if (activity.primary_asset) {
             selectedAsset.value = createSnapshotAsset(activity.primary_asset, activity, 'document');
             
             if (['processing', 'pending'].includes(selectedAsset.value?.status || '')) {
                showDocumentScanningModal.value = true;
             } else {
                // Documents use the standard DocumentReportModal (triggered via openAuditModal or directly here if we want explicit control)
                // The template uses DocumentReportModal logic: :show="showAuditModal && selectedAsset?.metadata?.audit_type === 'document' ..."
                showAuditModal.value = true;
             }
        }
    }
}
</script>

<style scoped>
/* Custom Emerald/Dark Scrollbar for the table container */
.custom-scrollbar::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #0f172a; /* Dark background */
    border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #374151; /* Gray-700 (Dark) */
    border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #4b5563; /* Gray-600 */
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #059669; /* Emerald-600 */
}
</style>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-3xl font-black italic tracking-tighter text-white uppercase mt-1">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">


                <!-- Context Switcher Mini -->
                <div v-if="props.organizations.length > 0" class="flex justify-start mb-4">
                    <Dropdown align="left" width="64">
                        <template #trigger>
                            <button class="group flex items-center gap-3 px-4 py-2 rounded-xl bg-white/[0.03] border border-white/10 hover:bg-white/[0.07] hover:border-emerald-500/30 transition-all duration-300">
                                <div class="relative">
                                    <div class="absolute -inset-1 rounded-lg bg-emerald-500/20 opacity-0 group-hover:opacity-100 blur-sm transition-opacity"></div>
                                    <div class="relative h-8 w-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                                        <svg v-if="!props.activeOrganization" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex flex-col items-start mr-2">
                                    <span class="text-[8px] font-black text-emerald-400 uppercase tracking-[0.3em] leading-none mb-1">Operational Context</span>
                                    <span class="text-xs font-black italic tracking-tighter text-white uppercase leading-none">
                                        {{ props.activeOrganization ? props.activeOrganization.name : 'Personal Account' }}
                                    </span>
                                </div>
                                <svg class="h-4 w-4 text-gray-500 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </template>

                        <template #content>
                            <div class="p-2 space-y-1">
                                <DropdownLink as="button" @click="switchContext(null)" class="w-full text-left">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-black uppercase tracking-widest" :class="!props.activeOrganization ? 'text-emerald-400' : 'text-gray-400'">Personal Account</span>
                                        <div v-if="!props.activeOrganization" class="h-1.5 w-1.5 rounded-full bg-emerald-400"></div>
                                    </div>
                                </DropdownLink>
                                <div class="border-t border-white/5 mx-2 my-1"></div>
                                <DropdownLink 
                                    v-for="org in props.organizations" 
                                    :key="org.id" 
                                    as="button" 
                                    @click="switchContext(org.id)"
                                    class="w-full text-left"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-black uppercase tracking-widest" :class="props.activeOrganization?.id === org.id ? 'text-emerald-400' : 'text-gray-400'">{{ org.name }}</span>
                                        <div v-if="props.activeOrganization?.id === org.id" class="h-1.5 w-1.5 rounded-full bg-emerald-400"></div>
                                    </div>
                                </DropdownLink>
                            </div>
                        </template>
                    </Dropdown>
                </div>

                <!-- Welcome Box with Uploader -->
                <div class="overflow-hidden border border-white/5 bg-white/[0.02] backdrop-blur-3xl rounded-[2rem] shadow-2xl relative">
                    <div class="p-8 text-white relative z-10">
                        <h3 class="mb-6 text-xl font-black italic tracking-tighter uppercase text-white/90">
                            Upload your assets to the Vault
                        </h3>

                        <!-- Vault Uploader Component -->
                        <VaultUploader
                            :max-file-size="100 * 1024 * 1024"
                            :allowed-file-types="['image/*', '.pdf', '.docx', '.doc']"
                            :max-number-of-files="10"
                            :credits="props.wallet?.credits ?? 0"
                            @upload-success="handleUploadSuccess"
                            @all-uploads-complete="handleAllUploadsComplete"
                            @tab-change="(tab: any) => activeTab = tab"
                            @open-comparison="handleSyncInit"
                            @scan-started="handleScanStarted"
                        />
                    </div>
                </div>


                <!-- Recently Scanned Projects -->
                <div class="overflow-hidden border border-white/5 bg-white/[0.02] backdrop-blur-3xl rounded-[2rem] shadow-2xl">
                    <div class="p-8">
                        <!-- Header & Search -->
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="text-xl font-black italic tracking-tighter uppercase text-white/90">
                                Recently Scanned Projects
                            </h3>
                            <div class="relative w-64">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input 
                                    v-model="searchQuery"
                                    type="text" 
                                    class="block w-full rounded-xl border-white/10 bg-white/5 pl-10 text-sm text-white placeholder-gray-500 focus:border-emerald-400 focus:ring-emerald-400 transition-all font-medium" 
                                    placeholder="Search assets..."
                                >
                            </div>
                        </div>

                        <!-- Filter Tabs -->
                        <div class="flex flex-wrap gap-2 mb-8">
                            <button @click="activeTab = 'all'" :class="[activeTab === 'all' ? 'bg-emerald-400 text-slate-900 shadow-lg shadow-emerald-400/20' : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10', 'px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all active:scale-95']">
                                All
                            </button>
                            <button @click="activeTab = 'website'" :class="[activeTab === 'website' ? 'bg-emerald-400 text-slate-900 shadow-lg shadow-emerald-400/20' : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10', 'px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all active:scale-95']">
                                Website
                            </button>
                            <button @click="activeTab = 'repository'" :class="[activeTab === 'repository' ? 'bg-emerald-400 text-slate-900 shadow-lg shadow-emerald-400/20' : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10', 'px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all active:scale-95']">
                                Repository
                            </button>
                            <button @click="activeTab = 'document'" :class="[activeTab === 'document' ? 'bg-emerald-400 text-slate-900 shadow-lg shadow-emerald-400/20' : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10', 'px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all active:scale-95']">
                                Document
                            </button>
                            <button @click="activeTab = 'sync'" :class="[activeTab === 'sync' ? 'bg-emerald-400 text-slate-900 shadow-lg shadow-emerald-400/20' : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10', 'px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all active:scale-95']">
                                Sync Reports
                            </button>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-if="filteredActivities.length === 0"
                            class="py-8 text-center text-gray-500 dark:text-gray-400"
                        >
                            <!-- Document Icon -->
                            <svg
                                v-if="activeTab === 'document'"
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
                            <p class="mt-2 text-sm">{{ emptyStateText.title || 'No scan activities found' }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                {{ emptyStateText.subtitle || '' }}
                            </p>
                        </div>

                        <!-- Scan Activities Table -->
                        <div v-else class="overflow-x-auto custom-scrollbar pb-2">
                            <table class="min-w-full border-separate border-spacing-y-3">
                                <thead>
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 pl-8">
                                            PROJECT / ASSET
                                        </th>
                                        <!-- Merged Type Column into Project/Asset -->
                                        
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                            SYNC STATUS
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                            QA STATUS
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                            SCANNED
                                        </th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">Actions</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-transparent">
                                    <tr 
                                        v-for="activity in filteredActivities" 
                                        :key="activity.id"
                                        @click="handleRowClick(activity)"
                                        class="group transition-all duration-300 hover:translate-x-1 cursor-pointer"
                                    >
                                        <!-- URL / Document & Type -->
                                        <td class="whitespace-nowrap px-6 py-5 rounded-l-xl">
                                            <div class="flex items-center">
                                                <div 
                                                    class="h-12 w-12 flex-shrink-0 rounded-xl flex items-center justify-center transition-all duration-300 shadow-lg group-hover:scale-110"
                                                    :class="{
                                                        'bg-gradient-to-br from-emerald-400 to-cyan-400 text-white shadow-emerald-400/20': activity.type === 'website',
                                                        'bg-gradient-to-br from-cyan-400 to-emerald-500 text-white shadow-cyan-400/20': activity.type === 'repository',
                                                        'bg-gradient-to-br from-emerald-500 to-cyan-600 text-white shadow-emerald-500/20': activity.type === 'sync',
                                                        'bg-gradient-to-br from-slate-600 to-slate-700 text-white shadow-slate-500/20': !activity.type || activity.type === 'document'
                                                    }"
                                                >
                                                    <!-- Icon based on type -->
                                                    <svg v-if="activity.type === 'website'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                                    </svg>
                                                    <svg v-else-if="activity.type === 'repository'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                                    </svg>
                                                    <svg v-else-if="activity.type === 'sync'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                    </svg>
                                                    <svg v-else class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-bold text-gray-900 dark:text-white truncate max-w-sm group-hover:text-indigo-400 transition-colors" :title="activity.urls_and_sync || 'Unnamed Asset'">
                                                        {{ activity.urls_and_sync || 'Unnamed Asset' }}
                                                    </div>
                                                    <!-- Type Subtitle with Colors -->
                                                    <div 
                                                        class="text-[10px] font-black uppercase tracking-widest mt-1 opacity-60"
                                                        :class="{
                                                            'text-emerald-400': activity.type === 'website',
                                                            'text-cyan-400': activity.type === 'repository',
                                                            'text-emerald-500': activity.type === 'sync',
                                                            'text-slate-400': !activity.type || activity.type === 'document'
                                                        }"
                                                    >
                                                        {{ activity.type || 'Document' }}
                                                    </div>
                                                    <!-- TITAN V2: Sync Batch Badge -->
                                                    <div v-if="activity.batch_id" class="mt-1">
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 font-mono border border-gray-200 dark:border-gray-600 tracking-tighter">
                                                            {{ activity.batch_id }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Sync Status -->
                                        <td class="whitespace-nowrap px-6 py-5">
                                            <div class="flex items-center">
                                                <span 
                                                    v-if="activity.sync_status"
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                                                    :class="{
                                                        'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800': activity.sync_status === 'synced',
                                                        'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-900/40 dark:text-amber-300 dark:border-amber-800': activity.sync_status === 'action_required',
                                                        'bg-yellow-50 text-yellow-700 border-yellow-100 dark:bg-yellow-900/20 dark:text-yellow-400 dark:border-yellow-800': activity.sync_status === 'pending',
                                                        'bg-rose-50 text-rose-700 border-rose-100 dark:bg-rose-900/20 dark:text-rose-400 dark:border-rose-800': ['failed', 'failed_system'].includes(activity.sync_status),
                                                        'bg-gray-50 text-gray-600 border-gray-100 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700': !['synced', 'pending', 'action_required', 'failed', 'failed_system'].includes(activity.sync_status)
                                                    }"
                                                >
                                                    {{ activity.sync_status === 'failed_system' ? 'System Fail' : activity.sync_status }}
                                                </span>
                                                <!-- TITAN V2: Show Batch ID if Synced but not the Sync Report itself -->
                                                <div v-else-if="activity.batch_id" class="flex flex-col">
                                                    <span class="text-[10px] uppercase font-black tracking-widest text-emerald-400/60 font-mono">
                                                        SYNCED WITH
                                                    </span>
                                                    <span class="text-[9px] text-emerald-400 font-black font-mono tracking-tighter">
                                                        {{ activity.batch_id.split('-').slice(2).join('-') }}
                                                    </span>
                                                </div>
                                                <span v-else class="text-xs text-gray-400">N/A</span>
                                                
                                                <span v-if="activity.sync_confidence_score" class="ml-2 text-xs font-semibold text-gray-400 dark:text-gray-500">
                                                    {{ activity.sync_confidence_score }}% match
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Individual Status - "Pill" Style -->
                                        <td class="whitespace-nowrap px-6 py-5">
                                            <div v-if="activity.type !== 'sync'" class="flex items-center space-x-2">
                                                <span 
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                                                    :style="activity.docu_and_urls_status === 'verified_private' ? 'color: #4ade80 !important; background-color: rgba(74, 222, 128, 0.1) !important; border-color: rgba(74, 222, 128, 0.3) !important;' : ''"
                                                    :class="{
                                                        'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800': ['verified', 'ready', 'optimal'].includes(activity.docu_and_urls_status),
                                                        'bg-rose-50 text-rose-700 border-rose-100 dark:bg-rose-900/20 dark:text-rose-400 dark:border-rose-800': ['flagged', 'payment_required', 'critical', 'failed'].includes(activity.docu_and_urls_status),
                                                        'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800': ['action_required', 'warning'].includes(activity.docu_and_urls_status),
                                                        'bg-blue-50 text-blue-700 border-blue-100 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800': ['uploaded', 'processing'].includes(activity.docu_and_urls_status),
                                                        // Fallback for verified_private if style fails (though style has priority)
                                                        '!text-green-400 !bg-emerald-900/10 !border-emerald-500/30': activity.docu_and_urls_status === 'verified_private'
                                                    }"
                                                >
                                                    {{ activity.docu_and_urls_status === 'action_required' ? 'Action Required' : (activity.docu_and_urls_status?.split('_').map((w: string) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ') || 'Pending') }}
                                                </span>
                                                
                                                <div v-if="activity.individual_score" class="flex items-center">
                                                    <!-- Progress bar removed as per request -->
                                                    <span class="ml-1.5 text-xs font-mono text-gray-500 dark:text-gray-400">{{ activity.individual_score }}%</span>
                                                </div>
                                            </div>
                                            <!-- SYNC RESULTS: Show Web & Repo Status separately -->
                                            <div v-else class="flex flex-col space-y-2">
                                                 <!-- Web Status -->
                                                 <div class="flex items-center space-x-2" v-if="activity.primary_asset">
                                                     <svg class="h-3 w-3 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
                                                     <span class="text-[10px] uppercase font-bold text-gray-500 w-8">WEB</span>
                                                     <span class="text-[10px] px-1.5 py-0.5 rounded border" :class="(activity.primary_asset.status === 'verified' || (activity.sync_status === 'synced' && activity.primary_asset.status === 'failed')) ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-slate-700 text-slate-400 border-slate-600'">
                                                         {{ (activity.sync_status === 'synced' && activity.primary_asset.status === 'failed') ? 'verified' : (activity.primary_asset.status || ' Pending') }}
                                                     </span>
                                                 </div>
                                                 <!-- Repo Status -->
                                                 <div class="flex items-center space-x-2" v-if="activity.secondary_asset">
                                                     <svg class="h-3 w-3 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>
                                                     <span class="text-[10px] uppercase font-bold text-gray-500 w-8">REPO</span>
                                                     <span class="text-[10px] px-1.5 py-0.5 rounded border" :class="activity.secondary_asset.status === 'verified' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-slate-700 text-slate-400 border-slate-600'">
                                                         {{ activity.secondary_asset.status || ' Pending' }}
                                                     </span>
                                                 </div>
                                            </div>
                                        </td>

                                        <!-- Scanned At -->
                                        <td class="whitespace-nowrap px-6 py-5 text-right text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatDate(activity.updated_at || activity.scanned_at || activity.created_at) }}
                                        </td>

                                        <!-- Actions -->
                                        <td class="whitespace-nowrap px-6 py-5 text-right text-sm font-medium rounded-r-xl">
                                            <div class="flex items-center justify-end space-x-4 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity duration-200">
                                                <!-- HISTORY AND DELETE COMMENTED OUT PER USER REQUEST
                                                <button 
                                                    v-if="activity.primary_asset"
                                                    @click.stop="openHistoryModal(activity.primary_asset)"
                                                    class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                    title="View History"
                                                >
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </button>
                                                
                                                <button 
                                                    @click.stop="handleDeleteActivity(activity)"
                                                    class="text-gray-400 hover:text-rose-600 dark:hover:text-rose-400 transition-colors"
                                                    title="Remove from list"
                                                >
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                                -->
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
    <!-- Project Modal: Cyber-terminal style for Websites/GitHub (Only when done) -->
    <!-- Relaxed condition: If showAuditModal is true and asset is present, we try to show it. We trust handleRowClick to set the correct state. -->
    <ProjectForensicModal
        v-if="selectedAsset"
        :show="showAuditModal && ['project', 'design', 'website', 'repository', 'repository_scan'].includes(selectedAsset.metadata?.audit_type || '') && !['processing', 'pending'].includes(selectedAsset.status)"
        :asset="selectedAsset"
        @close="closeAuditModal"
        @deep-audit="handleDeepAudit"
        @open-ai-assistant="handleOpenAiAssistant"
        @view-qa-results="handleViewQAResults"
        @refresh="refreshSelectedAsset"
    />
    


    <!-- CORE SCANNING MONITORS -->
    
    <!-- 1. Sync & Project Scanning (Titan Sync Dashboard) -->
    <!-- Usage: When 'showSyncScanningModal' is true AND likely a Sync Scan -->
    <SyncScanning
        v-if="showSyncScanningModal"
        :show="showSyncScanningModal"
        :web-url="selectedAsset?.website_url || selectedAsset?.original_url || ''"
        :repo-name="selectedAsset?.repository_url || 'Linked Repository'"
        :progress="auditProgress?.progress || 0"
        :step="auditProgress?.step || 'Initializing'"
        :details="auditProgress?.details || auditProgress?.step"
        @view-results="handleCloseScanningModal"
        @close="handleCloseScanningModal"
    />

    <!-- 2. Website / Project Scan (Individual) -->
    <!-- Usage: STRICTLY for website audits that are NOT syncs -->
    <WebsiteScanning 
        v-if="showWebsiteScanningModal"
        :show="showWebsiteScanningModal"
        :url="selectedAsset?.website_url || selectedAsset?.original_url || selectedAsset?.file_name"
        :progress="auditProgress?.progress || 0"
        :step="auditProgress?.step || 'Initializing'"
        :details="auditProgress?.details"
        @view-results="handleCloseScanningModal"
        @close="handleCloseScanningModal"
    />

    <!-- 3. Repository Scanning (Git Inspection) -->
    <!-- Usage: STRICTLY for repository audits -->
    <RepositoryScanning
        v-if="showRepositoryScanningModal"
        :show="showRepositoryScanningModal"
        :repo-name="(selectedAsset?.file_name || selectedAsset?.repository_url || selectedAsset?.website_url) || ''"
        :progress="auditProgress?.progress || 0"
        :step="auditProgress?.step || 'Initializing'"
        :details="auditProgress?.details"
        @view-results="handleCloseScanningModal"
        @close="handleCloseScanningModal"
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
        @view-snapshot="handleViewSnapshot"
    />

    <!-- Penetration & QA Testing Modal (Replaces Deep Scan) -->
    <PenetrationAndAQTesting
        v-if="selectedAsset"
        :show="showDeepScanConfirmation"
        :asset="selectedAsset"
        @close="showDeepScanConfirmation = false"
        @confirm="handleDeepScanConfirm"
        @open-credit-modal="showPurchaseModal = true"
        @completion="handleDeepAuditCompletion"
    />


    <!-- Project Analyst Modal (AI Assistant) -->
    <ProjectAnalystModal
        v-if="selectedAsset"
        :show="showProjectAnalystModal"
        :asset="selectedAsset"
        @close="showProjectAnalystModal = false"
    />

    <!-- QA & Penetration Results Modal -->
    <QAPenetrationResultsModal
        v-if="selectedAsset"
        :show="showQAResultsModal"
        :asset="selectedAsset"
        @close="showQAResultsModal = false"
        @open-ai-chat="showPentestAiModal = true"
        @re-scan="handleDeepAudit"
    />

    <!-- Repository Forensic Modal -->
    <GithubRepositoryForensicModal
        v-if="selectedRepoAsset"
        :show="showRepoModal"
        :asset="selectedRepoAsset"
        @close="showRepoModal = false"
        @refresh="refreshSelectedAsset"
    />

    <!-- Sync Reports Modal -->
    <WebURLandGitRepoSync
        v-if="selectedSyncWebAsset && selectedSyncRepoAsset"
        :show="showSyncModal"
        :web-asset="selectedSyncWebAsset"
        :repo-asset="selectedSyncRepoAsset"
        :comparison-data="selectedSyncComparisonData"
        @close="showSyncModal = false"
        @open-pentest-results="selectedAsset = selectedSyncWebAsset; showQAResultsModal = true"
        @deep-audit="console.log('🔘 Dashboard: Deep Audit Requested'); selectedAsset = selectedSyncWebAsset; handleDeepAudit()"
        @open-marketplace-listing="(status) => { selectedAsset = selectedSyncRepoAsset; isAlreadyOwned = status; showMarketplaceModal = true; }"
        @scan-again="handleSyncRescan"
    />

    <!-- Document Scan -->
    <DocumentScanning
        v-if="['document', 'pdf', 'contract'].includes(selectedAsset?.metadata?.audit_type || 'document')"
        :show="showDocumentScanningModal"
        :file-name="selectedAsset?.file_name"
        :progress="auditProgress?.progress || 0"
        :step="auditProgress?.step || 'Initializing'"
        :details="auditProgress?.details"
        @view-results="handleCloseScanningModal"
        @close="handleCloseScanningModal"
    />

    <!-- Security / Penetration Scan -->
    <SecurityScanning
        v-if="showSecurityScanningModal || (
            (selectedAsset?.website_url || selectedAsset?.original_url || selectedAsset?.metadata?.website_url) && (
                selectedAsset?.metadata?.security_audit || 
                auditProgress?.step?.toLowerCase()?.includes('probe') || 
                auditProgress?.step?.toLowerCase()?.includes('scan') || 
                auditProgress?.step?.toLowerCase()?.includes('active') ||
                auditProgress?.step?.toLowerCase()?.includes('crawl') ||
                auditProgress?.step?.toLowerCase()?.includes('ai') ||
                auditProgress?.step?.toLowerCase()?.includes('deep')
            )
        )"
        :is-scanning="showSecurityScanningModal || !!(selectedAsset?.status === 'processing' && (auditProgress?.step || '').includes('Scan'))"
        :url="selectedAsset?.website_url || selectedAsset?.original_url || selectedAsset?.metadata?.website_url || selectedAsset?.file_name"
        :progress="auditProgress?.progress || 0"
        :step="auditProgress?.step || 'Initializing Security Scan...'"
        :details="auditProgress?.details"
        :current-phase="auditProgress?.step || 'init'"
        :logs="auditLogs"
        @view-results="async () => { await refreshSelectedAsset(); showSecurityScanningModal = false; showQAResultsModal = true; }"
        @close="showSecurityScanningModal = false"
    />

    <!-- Pentest AI Support Modal -->
    <LumeAISupport
        v-if="showPentestAiModal"
        :show="showPentestAiModal"
        mode="pentest"
        :asset="selectedAsset"
        @close="showPentestAiModal = false"
    />

    <!-- Marketplace Listing Modal -->
    <MarketplaceListingModal
        v-if="selectedAsset"
        :show="showMarketplaceModal"
        :asset-name="selectedAsset.file_name || 'Asset'"
        :is-subscriber="!!($page.props.auth.user as any)?.active_subscription"
        :is-already-listed="isAlreadyOwned"
        @close="showMarketplaceModal = false"
        @confirm="handleMarketplaceConfirm"
    />

    <!-- Re-scan Confirmation Modal -->
    <Modal :show="showRescanConfirmModal" @close="showRescanConfirmModal = false">
        <div class="p-6 bg-slate-900 border border-slate-700 rounded-xl">
            <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-3">
                <svg class="w-6 h-6 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Re-verify Marketplace Listing?
            </h2>
            <p class="text-slate-400 mb-6">
                This asset is currently listed on the marketplace. Re-scanning will update its score and status. 
                <br><br>
                <span class="text-yellow-500 font-semibold italic">Note: If the new LUME score falls below 85%, the listing will be flagged as inconsistent.</span>
            </p>
            <div class="flex justify-end gap-3">
                <button
                    @click="showRescanConfirmModal = false"
                    class="px-4 py-2 text-sm font-medium text-slate-400 hover:text-white transition-colors"
                >
                    Cancel
                </button>
                <button
                    @click="executeRescan"
                    class="px-6 py-2 bg-brand-primary hover:bg-emerald-500 text-white text-sm font-bold rounded-lg transition-all shadow-lg shadow-brand-primary/20"
                >
                    Confirm & Scan Again
                </button>
            </div>
        </div>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal :show="showDeleteModal" @close="showDeleteModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Are you sure you want to delete this scan record?
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                This will only remove the history record. The actual file or repository asset will remain in your Vault.
            </p>
            <div class="mt-6 flex justify-end">
                <button
                    @click="showDeleteModal = false"
                    class="mr-3 inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    Cancel
                </button>
                <button
                    @click="confirmDelete"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-rose-600 hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500"
                    :disabled="!!isDeleting"
                >
                    <svg v-if="isDeleting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ isDeleting ? 'Deleting...' : 'Delete Record' }}
                </button>
            </div>
        </div>
    </Modal>

    </AuthenticatedLayout>
</template>
