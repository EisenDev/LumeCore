<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VaultUploader from '@/Components/VaultUploader.vue';
import WalletCard from '@/Components/WalletCard.vue';

import DocumentReportModal from '@/Components/DocumentReportModal.vue';
import ProjectAnalystModal from '@/Components/ProjectAnalystModal.vue';
import CreditPurchaseModal from '@/Components/CreditPurchaseModal.vue';

import AssetHistoryModal from '@/Components/AssetHistoryModal.vue';
import PenetrationAndAQTesting from '@/Components/PenetrationAndAQTesting.vue';
import QAPenetrationResultsModal from '@/Components/QAPenetrationResultsModal.vue';
import GithubRepositoryForensicModal from '@/Components/GithubRepositoryForensicModal.vue';
import WebURLandGitRepoSync from '@/Components/WebURLandGitRepoSync.vue';
import MarketplaceListingModal from '@/Components/MarketplaceListingModal.vue';
import UniversalScanning from '@/Components/Scanner/UniversalScanning.vue';
import LumeAISupport from '@/Components/LumeAISupport.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
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

// Dynamic & Mocked Stats matching mockup image
// Dynamic & Mocked Stats matching mockup image
const stats = computed(() => {
    const assetsWithScores = recentAssets.value.filter(a => a.score !== null && a.score !== undefined && !isNaN(Number(a.score)));
    const avgScore = assetsWithScores.length > 0
        ? Math.round(assetsWithScores.reduce((sum, a) => sum + Number(a.score || 0), 0) / assetsWithScores.length)
        : 0;

    const criticalCount = recentAssets.value.filter(a => a.score !== null && a.score !== undefined && Number(a.score) >= 70).length;
    const highCount = recentAssets.value.filter(a => a.score !== null && a.score !== undefined && Number(a.score) >= 45 && Number(a.score) < 70).length;
    const totalAssets = recentAssets.value.length;
    const totalScans = scanActivities.value.length;

    return {
        totalAssets,
        totalScans,
        highRisk: highCount,
        critical: criticalCount,
        averageRiskScore: isNaN(avgScore) ? 0 : avgScore,
    };
});

// Dynamic & Mocked Activities matching mockup image
const recentActivitiesMock = computed(() => {
    return scanActivities.value.slice(0, 5).map((act, index) => {
        const type = act.type || 'website';
        const name = act.urls_and_sync || act.primary_asset?.file_name || 'Asset Scan';
        
        let timeAgo = '2m ago';
        const diffMs = new Date().getTime() - new Date(act.scanned_at || act.created_at).getTime();
        const diffMins = Math.floor(diffMs / 60000);
        if (diffMins < 60) timeAgo = `${Math.max(1, diffMins)}m ago`;
        else timeAgo = `${Math.floor(diffMins / 60)}h ago`;

        const score = type === 'sync' ? act.sync_confidence_score : act.individual_score;
        let severity = 'LOW';
        if (score >= 70) severity = 'CRITICAL';
        else if (score >= 45) severity = 'HIGH';
        else if (score >= 25) severity = 'MEDIUM';
        
        return {
            title: act.type === 'sync' ? 'Sync scan completed' : `Scan completed`,
            detail: name,
            time: timeAgo,
            badge: severity,
            badgeColor: severity === 'CRITICAL' || severity === 'HIGH' ? 'text-red-500 bg-red-500/10 border-red-500/20' : severity === 'MEDIUM' ? 'text-amber-500 bg-amber-500/10 border-amber-500/20' : 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20',
            icon: type === 'repository' ? 'code' : type === 'sync' ? 'sync' : 'globe',
            rawRecord: act
        };
    });
});

// Dynamic & Mocked Scans matching mockup image
const recentScansList = computed(() => {
    return scanActivities.value.slice(0, 5).map((act, index) => {
        const type = act.type || 'website';
        const name = act.urls_and_sync || act.primary_asset?.file_name || 'Unnamed Target';
        const dateStr = act.scanned_at || act.created_at;
        const formattedDate = new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ' ' + new Date(dateStr).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        const score = type === 'sync' ? act.sync_confidence_score : act.individual_score;
        let severity = 'LOW';
        let badgeColor = 'text-emerald-400 bg-emerald-400/5 border-emerald-400/10';
        if (score >= 70) {
            severity = 'CRITICAL';
            badgeColor = 'text-red-500 bg-red-500/5 border-red-500/10';
        } else if (score >= 45) {
            severity = 'HIGH';
            badgeColor = 'text-red-400 bg-red-400/5 border-red-400/10';
        } else if (score >= 25) {
            severity = 'MEDIUM';
            badgeColor = 'text-amber-500 bg-amber-500/5 border-amber-500/10';
        }
        
        return {
            name,
            date: formattedDate,
            badge: severity,
            badgeColor,
            score: score !== null ? score : 0,
            icon: type === 'repository' ? 'github' : type === 'sync' ? 'sync' : 'globe',
            rawRecord: act
        };
    });
});

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

    // CRITICAL: Merge in Pending/Uploaded/Processing assets from recentAssets
    // The user wants to see their assets (including websites/repos/syncs) even if they haven't generated a "ScanActivity" record yet.
    const mappedAssets = recentAssets.value
        .filter(asset => asset.file_name && asset.file_name.trim() !== '')
        .map(asset => {
            const auditStr = String(asset.metadata?.audit_type || '').toLowerCase();
            let type = 'document';
            if (['project', 'website', 'website_scan', 'design'].includes(auditStr)) {
                type = 'website';
            } else if (['repository', 'repository_scan', 'github'].includes(auditStr)) {
                type = 'repository';
            } else if (asset.metadata?.is_sync_scan || auditStr === 'sync_scan' || auditStr === 'sync') {
                type = 'sync';
            }

            // Determine urls_and_sync display name
            let urlsAndSync = asset.file_name;
            if (type === 'sync') {
                const sibling = recentAssets.value.find(a => a.batch_id === asset.batch_id && a.id !== asset.id);
                if (sibling) {
                    urlsAndSync = `Sync: ${asset.file_name} & ${sibling.file_name}`;
                } else {
                    urlsAndSync = `Sync: ${asset.file_name}`;
                }
            }

            return {
                id: 'asset_' + asset.id,
                type: type,
                primary_asset: asset,
                primary_asset_id: asset.id,
                scanned_at: asset.created_at,
                docu_and_urls_status: asset.status,
                urls_and_sync: urlsAndSync,
                display_name: asset.file_name,
                is_virtual: true
            };
        });

    // Filter mapped assets based on activeTab
    let matchedMapped = mappedAssets;
    if (activeTab.value !== 'all') {
        matchedMapped = mappedAssets.filter(activity => {
            const type = activity.type;
            if (activeTab.value === 'website') return type === 'website';
            if (activeTab.value === 'repository') return type === 'repository';
            if (activeTab.value === 'document') return type === 'document';
            if (activeTab.value === 'sync') return type === 'sync';
            return true;
        });
    }

    // Filter out if already present in real activities (by primary asset id or batch id)
    const uniqueMapped = matchedMapped.filter(d => 
        !filtered.some(existing => 
            existing.primary_asset?.id === d.primary_asset.id || 
            existing.primary_asset_id === d.primary_asset.id ||
            (d.primary_asset.batch_id && existing.batch_id === d.primary_asset.batch_id)
        )
    );

    // Merge and sort
    filtered = [...uniqueMapped, ...filtered];
    filtered.sort((a, b) => new Date(b.scanned_at).getTime() - new Date(a.scanned_at).getTime());

    return filtered;
});

// Tab-reactive text
const listTitle = computed(() => 
    activeTab.value === 'document' ? 'Recently Uploaded Assets' : 'Recent scans and verifications'
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

        // Auto-open active scan monitor on load/refresh if one is running
        const runningAsset = recentAssets.value.find(a => ['processing', 'scanning', 'pending'].includes(a.status || ''));
        if (runningAsset) {
            console.log('🔄 Auto-opening scanning monitor on mount for running asset:', runningAsset.id);
            openAuditModal(runningAsset);
        }
    }
});

/**
 * TITAN V8.5: Background Data Refresh (Replaces Router Reload)
 */
const refreshDashboardData = async () => {
    try {
        console.log("Refreshing Dashboard Data (Background)...");
        const response = await axios.get('/overview/refresh');
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
        // Real-time Security Scans take ~13 mins (780 seconds), others take ~5 mins (300 seconds)
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
        auditProgress.value = { step: 'Queueing Website scan...', progress: 0 };
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
    // Fixes the "Wrong Modal" issue for Real-time Security Scans
    const auditStr = String(asset.metadata?.audit_type || '').toLowerCase();
    const isSync = asset.metadata?.is_sync_scan || auditStr === 'sync' || auditStr === 'sync_scan';
    const isDocument = ['document', 'pdf', 'contract'].includes(auditStr);
    const isRepository = ['repository', 'repository_scan', 'github'].includes(auditStr);
    const isWebsite = ['project', 'website', 'website_scan', 'design'].includes(auditStr);
    const isProcessing = ['processing', 'scanning', 'pending'].includes(asset.status || '');

    if (isSync) {
        selectedSyncWebAsset.value = asset;
        selectedSyncRepoAsset.value = recentAssets.value.find(a => a.batch_id === asset.batch_id && a.id !== asset.id) || null;
    }
    
    if (isWebsite && !isProcessing) {
        const hash = asset.hash || btoa(String(asset.id)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        router.visit(route('website.results', { hash }));
        return;
    }

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

    // Default to results
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

    // Bypass marketplace check as marketplace is disabled
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

function getStatusText(activity: any): string {
    const status = activity.type === 'sync' ? activity.sync_status : activity.docu_and_urls_status;
    if (['verified', 'ready', 'optimal', 'synced'].includes(status)) {
        return 'Completed';
    }
    if (['flagged', 'payment_required', 'critical', 'failed', 'failed_system'].includes(status)) {
        return 'Failed';
    }
    if (['action_required', 'warning'].includes(status)) {
        return 'Warning';
    }
    if (['uploaded', 'processing'].includes(status)) {
        return 'Processing';
    }
    return 'Pending';
}

function getStatusBadgeClasses(activity: any): string {
    const text = getStatusText(activity);
    if (text === 'Completed') {
        return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
    }
    if (text === 'Failed') {
        return 'bg-red-500/10 text-red-400 border-red-500/20';
    }
    if (text === 'Warning') {
        return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
    }
    if (text === 'Processing') {
        return 'bg-[#F3E7C9]/10 text-[#F3E7C9] border-[#F3E7C9]/20 animate-pulse';
    }
    return 'bg-amber-500/5 text-amber-500/80 border-amber-500/10';
}

function getRiskText(activity: any): string {
    const status = activity.type === 'sync' ? activity.sync_status : activity.docu_and_urls_status;
    if (['verified', 'ready', 'optimal', 'synced'].includes(status)) {
        return 'No issues found';
    }
    if (['flagged', 'payment_required', 'critical', 'failed', 'failed_system'].includes(status)) {
        return '3 High risks';
    }
    if (['action_required', 'warning'].includes(status)) {
        return '2 Medium risks';
    }
    if (['uploaded', 'processing'].includes(status)) {
        return 'Analyzing...';
    }
    return 'No issues found';
}

function getRiskTextClasses(activity: any): string {
    const risk = getRiskText(activity);
    if (risk === 'No issues found') {
        return 'text-emerald-400';
    }
    if (risk === '3 High risks') {
        return 'text-red-400';
    }
    if (risk === '2 Medium risks') {
        return 'text-amber-400';
    }
    return 'text-gray-500';
}

function formatDateDate(dateString: string): string {
    const d = new Date(dateString);
    if (isNaN(d.getTime())) return 'Pending';
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatDateTime(dateString: string): string {
    const d = new Date(dateString);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
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
            selectedSyncWebAsset.value = selectedAsset.value;
            selectedSyncRepoAsset.value = recentAssets.value.find(a => a.batch_id === selectedAsset.value.batch_id && a.id !== selectedAsset.value.id) || null;
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
            return `${baseClasses} bg-[#F3E7C9]/10 text-[#F3E7C9]/70 border border-[#F3E7C9]/20`;
        case 'processing':
            return `${baseClasses} bg-[#F3E7C9]/10 text-[#F3E7C9] border border-[#F3E7C9]/30 animate-pulse`;
        case 'ready':
        case 'verified':
            return `${baseClasses} bg-[#CBB48A]/10 text-[#CBB48A] border border-[#CBB48A]/30 shadow-[0_0_10px_rgba(203,180,138,0.15)]`;
        case 'verified_private':
            return `${baseClasses} bg-[#CBB48A]/20 text-[#CBB48A]/90 border border-[#CBB48A]/40`;
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
            const hash = activity.primary_asset.hash;
            if (hash) {
                router.visit(route('website.results', { hash }));
                return;
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
    background: #CBB48A; /* Emerald-600 */
}
</style>

<template>
    <Head title="Overview" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col">
                <h2 class="text-xl font-bold tracking-tight text-white/90">Overview</h2>
                <span class="text-xs text-gray-500 mt-1 font-medium">Welcome back, Analyst. Here's what's happening across your digital assets.</span>
            </div>
        </template>

        <div class="flex-1 overflow-y-auto px-8 py-6 space-y-8 bg-[#09090B] custom-scrollbar">
            <!-- Stat Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <!-- Card 1: Total Assets -->
                <div class="border border-white/5 bg-white/[0.01] hover:bg-white/[0.02] p-5 rounded-2xl flex items-center justify-between transition-all duration-300 relative group">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-blue-500 animate-pulse"></span>
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-widest">Total Assets</span>
                        </div>
                        <div class="text-3xl font-black text-white tracking-tight">{{ stats.totalAssets }}</div>
                        <div class="text-[10px] font-bold text-emerald-400 flex items-center gap-1">
                            <span>↑ 18%</span>
                            <span class="text-gray-600 font-medium">vs last 30 days</span>
                        </div>
                    </div>
                    <!-- Sparkline SVG -->
                    <div class="h-10 w-20 shrink-0 opacity-60 group-hover:opacity-100 transition-opacity">
                        <svg class="h-full w-full" viewBox="0 0 100 40">
                            <path d="M 0 35 Q 25 15 50 25 T 100 10" fill="none" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round"></path>
                        </svg>
                    </div>
                </div>

                <!-- Card 2: Total Scans -->
                <div class="border border-white/5 bg-white/[0.01] hover:bg-white/[0.02] p-5 rounded-2xl flex items-center justify-between transition-all duration-300 relative group">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-purple-500 animate-pulse"></span>
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-widest">Total Scans</span>
                        </div>
                        <div class="text-3xl font-black text-white tracking-tight">{{ stats.totalScans }}</div>
                        <div class="text-[10px] font-bold text-emerald-400 flex items-center gap-1">
                            <span>↑ 12%</span>
                            <span class="text-gray-600 font-medium">vs last 30 days</span>
                        </div>
                    </div>
                    <!-- Sparkline SVG -->
                    <div class="h-10 w-20 shrink-0 opacity-60 group-hover:opacity-100 transition-opacity">
                        <svg class="h-full w-full" viewBox="0 0 100 40">
                            <path d="M 0 35 Q 35 15 65 25 T 100 8" fill="none" stroke="#a855f7" stroke-width="2.5" stroke-linecap="round"></path>
                        </svg>
                    </div>
                </div>

                <!-- Card 3: High Risk -->
                <div class="border border-white/5 bg-white/[0.01] hover:bg-white/[0.02] p-5 rounded-2xl flex items-center justify-between transition-all duration-300 relative group">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-red-400 animate-pulse"></span>
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-widest">High Risk</span>
                        </div>
                        <div class="text-3xl font-black text-white tracking-tight">{{ stats.highRisk }}</div>
                        <div class="text-[10px] font-bold text-emerald-400 flex items-center gap-1">
                            <span>↑ 8%</span>
                            <span class="text-gray-600 font-medium">vs last 30 days</span>
                        </div>
                    </div>
                    <!-- Sparkline SVG -->
                    <div class="h-10 w-20 shrink-0 opacity-60 group-hover:opacity-100 transition-opacity">
                        <svg class="h-full w-full" viewBox="0 0 100 40">
                            <path d="M 0 38 Q 25 35 50 15 T 100 5" fill="none" stroke="#f87171" stroke-width="2.5" stroke-linecap="round"></path>
                        </svg>
                    </div>
                </div>

                <!-- Card 4: Critical Findings -->
                <div class="border border-white/5 bg-white/[0.01] hover:bg-white/[0.02] p-5 rounded-2xl flex items-center justify-between transition-all duration-300 relative group">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-red-600 animate-pulse"></span>
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-widest">Critical</span>
                        </div>
                        <div class="text-3xl font-black text-white tracking-tight">{{ stats.critical }}</div>
                        <div class="text-[10px] font-bold text-emerald-400 flex items-center gap-1">
                            <span>↑ 20%</span>
                            <span class="text-gray-600 font-medium">vs last 30 days</span>
                        </div>
                    </div>
                    <!-- Sparkline SVG -->
                    <div class="h-10 w-20 shrink-0 opacity-60 group-hover:opacity-100 transition-opacity">
                        <svg class="h-full w-full" viewBox="0 0 100 40">
                            <path d="M 0 10 Q 25 25 50 15 T 100 32" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round"></path>
                        </svg>
                    </div>
                </div>

                <!-- Card 5: Avg Risk Score -->
                <div class="border border-white/5 bg-white/[0.01] hover:bg-white/[0.02] p-5 rounded-2xl flex items-center justify-between transition-all duration-300 relative group">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#CBB48A] animate-pulse"></span>
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-widest">Avg Risk Score</span>
                        </div>
                        <div class="text-3xl font-black text-white tracking-tight">{{ stats.averageRiskScore }}</div>
                        <div class="text-[10px] font-bold text-emerald-400 flex items-center gap-1">
                            <span>↑ 6 points</span>
                            <span class="text-gray-600 font-medium">vs last 30 days</span>
                        </div>
                    </div>
                    <!-- Sparkline SVG -->
                    <div class="h-10 w-20 shrink-0 opacity-60 group-hover:opacity-100 transition-opacity">
                        <svg class="h-full w-full" viewBox="0 0 100 40">
                            <path d="M 5,35 L 25,30 L 45,33 L 65,20 L 85,10 L 95,3" fill="none" stroke="#CBB48A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Row 2: Risk Overview (60%) & Recent Activity (40%) -->
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-6">
                <!-- Left: Risk Overview (3 cols) -->
                <div class="bg-[#09090b]/40 border border-white/5 rounded-3xl p-6 lg:col-span-3 flex flex-col justify-between shadow-xl">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-sm font-black text-white tracking-wider uppercase font-mono">Risk Overview</h3>
                        <button class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-500 hover:text-white uppercase tracking-wider transition-colors">
                            <span>Last 30 Days</span>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-8 lg:gap-12 flex-1">
                        <!-- Left: Circular Gauge -->
                        <div class="relative w-36 h-36 flex items-center justify-center shrink-0">
                            <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="38" stroke="rgba(255,255,255,0.03)" stroke-width="8" fill="none" />
                                <!-- Informational (9%) -> length 21.5, offset 0 -->
                                <circle cx="50" cy="50" r="38" stroke="#3b82f6" stroke-width="8" stroke-dasharray="21.5 238.7" stroke-dashoffset="0" fill="none" stroke-linecap="round" />
                                <!-- Low (34%) -> length 81.1, offset -21.5 -->
                                <circle cx="50" cy="50" r="38" stroke="#10b981" stroke-width="8" stroke-dasharray="81.1 238.7" stroke-dashoffset="-21.5" fill="none" stroke-linecap="round" />
                                <!-- Medium (27%) -> length 64.4, offset -102.6 -->
                                <circle cx="50" cy="50" r="38" stroke="#f59e0b" stroke-width="8" stroke-dasharray="64.4 238.7" stroke-dashoffset="-102.6" fill="none" stroke-linecap="round" />
                                <!-- High (22%) -> length 52.5, offset -167.0 -->
                                <circle cx="50" cy="50" r="38" stroke="#ec4899" stroke-width="8" stroke-dasharray="52.5 238.7" stroke-dashoffset="-167.0" fill="none" stroke-linecap="round" />
                                <!-- Critical (8%) -> length 19.2, offset -219.5 -->
                                <circle cx="50" cy="50" r="38" stroke="#ef4444" stroke-width="8" stroke-dasharray="19.2 238.7" stroke-dashoffset="-219.5" fill="none" stroke-linecap="round" />
                            </svg>
                            <div class="absolute flex flex-col items-center justify-center">
                                <span class="text-3xl font-black text-white font-mono leading-none">{{ stats.averageRiskScore }}</span>
                                <span class="text-[9px] text-slate-500 font-black uppercase tracking-wider mt-1">/100</span>
                            </div>
                        </div>

                        <!-- Middle: Legend List -->
                        <div class="flex flex-col gap-2 w-full max-w-[150px] shrink-0 font-sans text-xs">
                            <div class="flex items-center justify-between text-slate-400">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-[#ef4444]"></span>
                                    <span>Critical</span>
                                </div>
                                <span class="font-mono text-white font-bold">6 (8%)</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-400">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-[#ec4899]"></span>
                                    <span>High</span>
                                </div>
                                <span class="font-mono text-white font-bold">28 (22%)</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-400">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-[#f59e0b]"></span>
                                    <span>Medium</span>
                                </div>
                                <span class="font-mono text-white font-bold">34 (27%)</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-400 relative">
                                <!-- Selected arrow in mockup -->
                                <div class="absolute -left-4 text-[#CBB48A] text-xs font-black">-></div>
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-[#10b981]"></span>
                                    <span class="text-white font-bold">Low</span>
                                </div>
                                <span class="font-mono text-white font-bold">42 (34%)</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-400">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-[#3b82f6]"></span>
                                    <span>Informational</span>
                                </div>
                                <span class="font-mono text-white font-bold">18 (9%)</span>
                            </div>
                        </div>

                        <!-- Right: Line Trend Graph -->
                        <div class="flex-1 h-36 relative bg-white/[0.01] border border-white/5 rounded-xl p-4 flex flex-col justify-between w-full min-w-0">
                            <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest leading-none mb-1">Risk Score Trend</span>
                            <div class="relative flex-1 min-w-0">
                                <svg class="w-full h-full" viewBox="0 0 300 80" preserveAspectRatio="none">
                                    <line x1="0" y1="0" x2="300" y2="0" stroke="rgba(255,255,255,0.03)" stroke-width="1" />
                                    <line x1="0" y1="20" x2="300" y2="20" stroke="rgba(255,255,255,0.03)" stroke-width="1" />
                                    <line x1="0" y1="40" x2="300" y2="40" stroke="rgba(255,255,255,0.03)" stroke-width="1" />
                                    <line x1="0" y1="60" x2="300" y2="60" stroke="rgba(255,255,255,0.03)" stroke-width="1" />
                                    <line x1="0" y1="80" x2="300" y2="80" stroke="rgba(255,255,255,0.03)" stroke-dasharray="2 2" stroke-width="1" />
                                    
                                    <path d="M 20,50 Q 75,55 110,60 T 200,45 T 280,25" fill="none" stroke="#CBB48A" stroke-width="2" stroke-linecap="round" />
                                    <circle cx="20" cy="50" r="2.5" fill="#CBB48A" />
                                    <circle cx="110" cy="60" r="2.5" fill="#CBB48A" />
                                    <circle cx="200" cy="45" r="2.5" fill="#CBB48A" />
                                    <circle cx="280" cy="25" r="3" fill="#fff" stroke="#CBB48A" stroke-width="1" />
                                </svg>
                            </div>
                            <div class="flex justify-between text-[7px] font-bold text-slate-500 uppercase tracking-wider mt-1 px-1">
                                <span>May 24</span>
                                <span>Jun 7</span>
                                <span>Jun 22</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Recent Activity (2 cols) -->
                <div class="bg-[#09090b]/40 border border-white/5 rounded-3xl p-6 lg:col-span-2 flex flex-col justify-between shadow-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-black text-white tracking-wider uppercase font-mono">Recent Activity</h3>
                        <Link :href="route('scans.index')" class="text-[10px] font-black text-[#CBB48A] hover:underline uppercase tracking-wider transition-colors">View All</Link>
                    </div>

                    <div class="space-y-3.5 flex-1 overflow-y-auto pr-1">
                        <div v-if="recentActivitiesMock.length === 0" class="flex flex-col items-center justify-center h-full py-8 text-center text-slate-500">
                            <svg class="w-8 h-8 text-slate-700 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span class="text-xs font-mono">No recent activity.</span>
                            <span class="text-[10px] text-slate-600 mt-1 max-w-[200px] leading-relaxed">Connect a target or run a security scan to populate this feed.</span>
                        </div>
                        <div 
                            v-else
                            v-for="act in recentActivitiesMock" 
                            :key="act.detail"
                            @click="act.rawRecord && handleRowClick(act.rawRecord)"
                            class="flex items-center justify-between gap-3 p-2.5 rounded-xl border border-transparent hover:border-white/5 hover:bg-white/[0.01] transition-all cursor-pointer group"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <!-- Dynamic Icon -->
                                <div class="h-8 w-8 rounded-lg flex items-center justify-center shrink-0 border border-white/5 bg-white/[0.02] text-slate-400 group-hover:text-[#CBB48A] transition-colors">
                                    <svg v-if="act.icon === 'globe'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                    </svg>
                                    <svg v-else-if="act.icon === 'code'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                    </svg>
                                    <svg v-else-if="act.icon === 'sync'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    <svg v-else-if="act.icon === 'cpu'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                                    </svg>
                                    <svg v-else-if="act.icon === 'calendar'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="text-xs font-bold text-white group-hover:text-[#CBB48A] transition-colors truncate">{{ act.title }}</span>
                                    <span class="text-[10px] text-slate-500 font-mono mt-0.5 truncate">{{ act.detail }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-[9px] text-slate-500 font-medium">{{ act.time }}</span>
                                <span v-if="act.badge" class="px-2 py-0.5 rounded text-[8px] font-black tracking-wider" :class="act.badgeColor">
                                    {{ act.badge }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 3: Top Findings (33%), Assets by Type (33%), Recent Scans (33%) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Top Findings Card -->
                <div class="bg-[#09090b]/40 border border-white/5 rounded-3xl p-6 shadow-xl flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-black text-white tracking-wider uppercase font-mono">Top Findings</h3>
                        <a href="#" class="text-[10px] font-black text-slate-500 hover:text-white uppercase tracking-wider transition-colors">View All Findings</a>
                    </div>

                    <div class="space-y-4 flex-1">
                        <!-- Item 1 -->
                        <div class="flex items-start justify-between gap-3 p-1 rounded-xl">
                            <div class="flex items-start gap-2.5 min-w-0">
                                <span class="px-1.5 py-0.5 text-[8px] font-black bg-red-950/40 text-red-500 border border-red-500/20 rounded mt-0.5 shrink-0">CRITICAL</span>
                                <div class="flex flex-col min-w-0 leading-tight">
                                    <span class="text-xs font-bold text-white truncate">Outdated framework detected</span>
                                    <span class="text-[9px] text-slate-500 mt-1 truncate font-sans">Laravel 11.x with known vulnerabilities</span>
                                </div>
                            </div>
                            <span class="font-mono text-xs font-bold text-slate-400">6</span>
                        </div>

                        <!-- Item 2 -->
                        <div class="flex items-start justify-between gap-3 p-1 rounded-xl">
                            <div class="flex items-start gap-2.5 min-w-0">
                                <span class="px-1.5 py-0.5 text-[8px] font-black bg-red-950/40 text-red-500 border border-red-500/20 rounded mt-0.5 shrink-0">CRITICAL</span>
                                <div class="flex flex-col min-w-0 leading-tight">
                                    <span class="text-xs font-bold text-white truncate">Exposed administrative endpoints</span>
                                    <span class="text-[9px] text-slate-500 mt-1 truncate font-sans">Admin/auth endpoints accessible without auth</span>
                                </div>
                            </div>
                            <span class="font-mono text-xs font-bold text-slate-400">6</span>
                        </div>

                        <!-- Item 3 -->
                        <div class="flex items-start justify-between gap-3 p-1 rounded-xl">
                            <div class="flex items-start gap-2.5 min-w-0">
                                <span class="px-1.5 py-0.5 text-[8px] font-black bg-amber-950/40 text-amber-500 border border-amber-500/20 rounded mt-0.5 shrink-0 font-mono">HIGH</span>
                                <div class="flex flex-col min-w-0 leading-tight">
                                    <span class="text-xs font-bold text-white truncate">Missing security headers</span>
                                    <span class="text-[9px] text-slate-500 mt-1 truncate font-sans">Weak security configuration detected</span>
                                </div>
                            </div>
                            <span class="font-mono text-xs font-bold text-slate-400">11</span>
                        </div>

                        <!-- Item 4 -->
                        <div class="flex items-start justify-between gap-3 p-1 rounded-xl">
                            <div class="flex items-start gap-2.5 min-w-0">
                                <span class="px-1.5 py-0.5 text-[8px] font-black bg-amber-950/40 text-amber-500 border border-amber-500/20 rounded mt-0.5 shrink-0 font-mono">HIGH</span>
                                <div class="flex flex-col min-w-0 leading-tight">
                                    <span class="text-xs font-bold text-white truncate">Configured HTTP Security Headers</span>
                                    <span class="text-[9px] text-slate-500 mt-1 truncate font-sans">Recommended security headers not fully verified</span>
                                </div>
                            </div>
                            <span class="font-mono text-xs font-bold text-slate-400">7</span>
                        </div>

                        <!-- Item 5 -->
                        <div class="flex items-start justify-between gap-3 p-1 rounded-xl">
                            <div class="flex items-start gap-2.5 min-w-0">
                                <span class="px-1.5 py-0.5 text-[8px] font-black bg-yellow-950/40 text-yellow-500 border border-yellow-500/20 rounded mt-0.5 shrink-0 font-mono">MEDIUM</span>
                                <div class="flex flex-col min-w-0 leading-tight">
                                    <span class="text-xs font-bold text-white truncate">Minimal third-party scripts</span>
                                    <span class="text-[9px] text-slate-500 mt-1 truncate font-sans">Third-party exposure increases attack surface</span>
                                </div>
                            </div>
                            <span class="font-mono text-xs font-bold text-slate-400">9</span>
                        </div>
                    </div>
                </div>

                <!-- Assets by Type Card -->
                <div class="bg-[#09090b]/40 border border-white/5 rounded-3xl p-6 shadow-xl flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-black text-white tracking-wider uppercase font-mono">Assets by Type</h3>
                        <a href="#" class="text-[10px] font-black text-slate-500 hover:text-white uppercase tracking-wider transition-colors">View All Assets</a>
                    </div>

                    <div class="flex items-center gap-6 flex-1">
                        <!-- Donut SVG -->
                        <div class="relative w-28 h-28 flex items-center justify-center shrink-0">
                            <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="30" stroke="rgba(255,255,255,0.03)" stroke-width="12" fill="none" />
                                <!-- Websites (27%): length 50.9, offset 0 -->
                                <circle cx="50" cy="50" r="30" stroke="#3b82f6" stroke-width="12" stroke-dasharray="50.9 188.4" stroke-dashoffset="0" fill="none" />
                                <!-- Repositories (14%): length 26.4, offset -50.9 -->
                                <circle cx="50" cy="50" r="30" stroke="#a855f7" stroke-width="12" stroke-dasharray="26.4 188.4" stroke-dashoffset="-50.9" fill="none" />
                                <!-- APIs (6%): length 11.3, offset -77.3 -->
                                <circle cx="50" cy="50" r="30" stroke="#06b6d4" stroke-width="12" stroke-dasharray="11.3 188.4" stroke-dashoffset="-77.3" fill="none" />
                                <!-- Infrastructure (4%): length 7.5, offset -88.6 -->
                                <circle cx="50" cy="50" r="30" stroke="#f97316" stroke-width="12" stroke-dasharray="7.5 188.4" stroke-dashoffset="-88.6" fill="none" />
                                <!-- Domains (22%): length 41.4, offset -96.1 -->
                                <circle cx="50" cy="50" r="30" stroke="#10b981" stroke-width="12" stroke-dasharray="41.4 188.4" stroke-dashoffset="-96.1" fill="none" />
                                <!-- Other (27%): length 50.9, offset -137.5 -->
                                <circle cx="50" cy="50" r="30" stroke="#6b7280" stroke-width="12" stroke-dasharray="50.9 188.4" stroke-dashoffset="-137.5" fill="none" />
                            </svg>
                            <div class="absolute flex flex-col items-center justify-center leading-none">
                                <span class="text-lg font-black text-white font-mono">248</span>
                                <span class="text-[7px] text-slate-500 font-black uppercase tracking-wider mt-0.5">Total</span>
                            </div>
                        </div>

                        <!-- Legend List -->
                        <div class="flex-1 flex flex-col gap-1.5 text-[10px] font-sans">
                            <div class="flex items-center justify-between text-slate-400">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-[#3b82f6]"></span>
                                    <span>Websites</span>
                                </div>
                                <span class="font-mono text-white font-bold">68 (27%)</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-400">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-[#a855f7]"></span>
                                    <span>Repositories</span>
                                </div>
                                <span class="font-mono text-white font-bold">34 (14%)</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-400">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-[#06b6d4]"></span>
                                    <span>APIs</span>
                                </div>
                                <span class="font-mono text-white font-bold">16 (6%)</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-400">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-[#f97316]"></span>
                                    <span>Infrastructure</span>
                                </div>
                                <span class="font-mono text-white font-bold">10 (4%)</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-400">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-[#10b981]"></span>
                                    <span>Domains</span>
                                </div>
                                <span class="font-mono text-white font-bold">54 (22%)</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-400">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-[#6b7280]"></span>
                                    <span>Other</span>
                                </div>
                                <span class="font-mono text-white font-bold">66 (27%)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Scans Card -->
                <div class="bg-[#09090b]/40 border border-white/5 rounded-3xl p-6 shadow-xl flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-black text-white tracking-wider uppercase font-mono">Recent Scans</h3>
                        <Link :href="route('scans.index')" class="text-[10px] font-black text-slate-500 hover:text-white uppercase tracking-wider transition-colors">View All Scans</Link>
                    </div>

                    <div class="space-y-3.5 flex-1 overflow-y-auto pr-1">
                        <div v-if="recentScansList.length === 0" class="flex flex-col items-center justify-center h-full py-8 text-center text-slate-500">
                            <svg class="w-8 h-8 text-slate-700 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9" />
                            </svg>
                            <span class="text-xs font-mono">No recent scans.</span>
                            <span class="text-[10px] text-slate-600 mt-1 max-w-[200px] leading-relaxed">Trigger a new scan from the Overview or Scans panel.</span>
                        </div>
                        <div 
                            v-else
                            v-for="scan in recentScansList" 
                            :key="scan.name"
                            @click="scan.rawRecord && handleRowClick(scan.rawRecord)"
                            class="flex items-center justify-between gap-3 p-1.5 rounded-xl hover:bg-white/[0.01] transition-all cursor-pointer group"
                        >
                            <div class="flex items-center gap-2.5 min-w-0">
                                <!-- Type Icon -->
                                <div class="text-slate-500 group-hover:text-[#CBB48A] transition-colors shrink-0">
                                    <svg v-if="scan.icon === 'github'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                    </svg>
                                    <svg v-else-if="scan.icon === 'gitlab'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                    </svg>
                                    <svg v-else-if="scan.icon === 'aws'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                    </svg>
                                </div>
                                <div class="flex flex-col min-w-0 leading-tight">
                                    <span class="text-xs font-bold text-white group-hover:text-[#CBB48A] transition-colors truncate">{{ scan.name }}</span>
                                    <span class="text-[9px] text-slate-500 mt-1 truncate">{{ scan.date }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 shrink-0">
                                <span class="px-1.5 py-0.5 rounded text-[8px] font-black tracking-wider" :class="scan.badgeColor">
                                    {{ scan.badge }}
                                </span>
                                <!-- Gold Hexagon -->
                                <div class="relative w-6 h-6 flex items-center justify-center shrink-0">
                                    <svg class="absolute inset-0 w-full h-full text-[#CBB48A]/70 group-hover:text-[#CBB48A] transition-colors" viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="8">
                                        <polygon points="50,5 95,25 95,75 50,95 5,75 5,25" />
                                    </svg>
                                    <span class="text-[9px] font-mono font-black text-[#CBB48A] mt-0.5">{{ scan.score }}</span>
                                </div>
                                <svg class="w-3.5 h-3.5 text-slate-600 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 4: Scheduled Scans (33%), Integrations (33%), Notifications (33%) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Scheduled Scans -->
                <div class="bg-[#09090b]/40 border border-white/5 rounded-3xl p-6 shadow-xl flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-black text-white tracking-wider uppercase font-mono">Scheduled Scans</h3>
                        <a href="#" class="text-[10px] font-black text-slate-500 hover:text-white uppercase tracking-wider transition-colors">View All</a>
                    </div>

                    <div class="flex items-center justify-between gap-4 p-3 bg-white/[0.01] border border-white/5 rounded-2xl">
                        <div class="flex items-center gap-3 min-w-0">
                            <!-- Icon -->
                            <div class="h-9 w-9 rounded-xl bg-white/[0.03] border border-white/5 flex items-center justify-center text-slate-400 shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="flex flex-col min-w-0 leading-tight">
                                <span class="text-xs font-bold text-white truncate">AWS Infrastructure Scan</span>
                                <span class="text-[9px] text-slate-500 mt-1 truncate font-sans">Every 24 hours</span>
                                <span class="text-[8px] text-[#CBB48A] mt-1.5 truncate">Next run: Jun 23, 2026 11:00 AM</span>
                            </div>
                        </div>

                        <!-- Active status badge -->
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg border border-emerald-500/20 bg-emerald-500/5 text-[9px] font-bold text-emerald-400 uppercase tracking-wider shrink-0 font-sans">
                            <span class="w-1 h-1 rounded-full bg-emerald-400"></span>
                            Active
                        </span>
                    </div>
                </div>

                <!-- Integrations -->
                <div class="bg-[#09090b]/40 border border-white/5 rounded-3xl p-6 shadow-xl flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-black text-white tracking-wider uppercase font-mono">Integrations</h3>
                        <a href="#" class="text-[10px] font-black text-slate-500 hover:text-white uppercase tracking-wider transition-colors">Manage</a>
                    </div>

                    <div class="flex items-center gap-3">
                        <!-- AWS -->
                        <div class="h-10 w-10 rounded-xl bg-white/[0.02] border border-white/5 flex items-center justify-center hover:bg-white/[0.06] transition-colors cursor-pointer" title="AWS">
                            <img src="/images/aws-icon.png" onerror="this.src='/images/none-transparent-logo.png'" alt="AWS" class="h-6 w-6 object-contain" />
                        </div>
                        <!-- GitHub -->
                        <div class="h-10 w-10 rounded-xl bg-white/[0.02] border border-white/5 flex items-center justify-center hover:bg-white/[0.06] transition-colors cursor-pointer" title="GitHub">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.87 8.17 6.84 9.5.5.08.66-.23.66-.5v-1.69c-2.77.6-3.36-1.34-3.36-1.34-.46-1.16-1.11-1.47-1.11-1.47-.9-.62.07-.6.07-.6 1 .07 1.53 1.03 1.53 1.03.9 1.52 2.34 1.07 2.91.83.09-.65.35-1.09.63-1.34-2.22-.25-4.55-1.11-4.55-4.92 0-1.11.38-2 1.03-2.71-.1-.25-.45-1.29.1-2.64 0 0 .84-.27 2.75 1.02.79-.22 1.65-.33 2.5-.33.85 0 1.71.11 2.5.33 1.91-1.29 2.75-1.02 2.75-1.02.55 1.35.2 2.39.1 2.64.65.71 1.03 1.6 1.03 2.71 0 3.82-2.34 4.66-4.57 4.91.36.31.69.92.69 1.85V21c0 .27.16.59.67.5C19.14 20.16 22 16.42 22 12A10 10 0 0012 2z" />
                            </svg>
                        </div>
                        <!-- GitLab -->
                        <div class="h-10 w-10 rounded-xl bg-white/[0.02] border border-white/5 flex items-center justify-center hover:bg-white/[0.06] transition-colors cursor-pointer" title="GitLab">
                            <img src="/images/gitlab-icon.png" onerror="this.src='/images/none-transparent-logo.png'" alt="GitLab" class="h-6 w-6 object-contain" />
                        </div>
                        <!-- Slack -->
                        <div class="h-10 w-10 rounded-xl bg-white/[0.02] border border-white/5 flex items-center justify-center hover:bg-white/[0.06] transition-colors cursor-pointer" title="Slack">
                            <img src="/images/slack-icon.png" onerror="this.src='/images/none-transparent-logo.png'" alt="Slack" class="h-6 w-6 object-contain" />
                        </div>
                        <!-- Teams -->
                        <div class="h-10 w-10 rounded-xl bg-white/[0.02] border border-white/5 flex items-center justify-center hover:bg-white/[0.06] transition-colors cursor-pointer" title="Microsoft Teams">
                            <img src="/images/teams-icon.png" onerror="this.src='/images/none-transparent-logo.png'" alt="Teams" class="h-6 w-6 object-contain" />
                        </div>
                        <!-- More -->
                        <div class="h-10 w-10 rounded-xl bg-white/[0.02] border border-white/5 flex items-center justify-center hover:bg-white/[0.06] text-slate-400 hover:text-white transition-colors cursor-pointer text-xs font-bold font-mono">
                            +3
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="bg-[#09090b]/40 border border-white/5 rounded-3xl p-6 shadow-xl flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-black text-white tracking-wider uppercase font-mono">Notifications</h3>
                        <a href="#" class="text-[10px] font-black text-slate-500 hover:text-white uppercase tracking-wider transition-colors">View All</a>
                    </div>

                    <div class="space-y-2.5 flex-1 font-sans text-xs">
                        <div class="flex items-center justify-between text-slate-400">
                            <div class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                <span>6 critical findings need attention</span>
                            </div>
                            <span class="text-[9px] text-slate-500 font-medium">2m ago</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-400">
                            <div class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                <span>3 scans completed successfully</span>
                            </div>
                            <span class="text-[9px] text-slate-500 font-medium">10m ago</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-400">
                            <div class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                <span>1 scheduled scan failed</span>
                            </div>
                            <span class="text-[9px] text-slate-500 font-medium">1h ago</span>
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
    

    


    <!-- CORE SCANNING MONITORS -->
    
    <!-- 1. Sync & Project Scanning (Titan Sync Dashboard) -->
    <!-- Usage: When 'showSyncScanningModal' is true AND likely a Sync Scan -->
    <UniversalScanning
        v-if="showSyncScanningModal"
        :show="showSyncScanningModal"
        type="sync"
        :target-name="`${selectedAsset?.website_url || selectedAsset?.original_url || ''} & ${selectedAsset?.repository_url || 'Linked Repository'}`"
        :progress="auditProgress?.progress || 0"
        :step="auditProgress?.step || 'Initializing'"
        :details="auditProgress?.details || auditProgress?.step"
        @view-results="handleCloseScanningModal"
        @close="handleCloseScanningModal"
    />

    <!-- 2. Website / Project Scan (Individual) -->
    <!-- Usage: STRICTLY for website audits that are NOT syncs -->
    <UniversalScanning 
        v-if="showWebsiteScanningModal"
        :show="showWebsiteScanningModal"
        type="website"
        :target-name="selectedAsset?.website_url || selectedAsset?.original_url || selectedAsset?.file_name"
        :progress="auditProgress?.progress || 0"
        :step="auditProgress?.step || 'Initializing'"
        :details="auditProgress?.details"
        @view-results="handleCloseScanningModal"
        @close="handleCloseScanningModal"
    />

    <!-- 3. Repository Scanning (Git Inspection) -->
    <!-- Usage: STRICTLY for repository audits -->
    <UniversalScanning
        v-if="showRepositoryScanningModal"
        :show="showRepositoryScanningModal"
        type="repository"
        :target-name="(selectedAsset?.file_name || selectedAsset?.repository_url || selectedAsset?.website_url) || ''"
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
    <UniversalScanning
        v-if="['document', 'pdf', 'contract'].includes(selectedAsset?.metadata?.audit_type || 'document')"
        :show="showDocumentScanningModal"
        type="document"
        :target-name="selectedAsset?.file_name"
        :progress="auditProgress?.progress || 0"
        :step="auditProgress?.step || 'Initializing'"
        :details="auditProgress?.details"
        @view-results="handleCloseScanningModal"
        @close="handleCloseScanningModal"
    />

    <!-- Security / Penetration Scan -->
    <UniversalScanning
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
        :show="showSecurityScanningModal || !!(selectedAsset?.status === 'processing' && (auditProgress?.step || '').includes('Scan'))"
        type="security"
        :target-name="selectedAsset?.website_url || selectedAsset?.original_url || selectedAsset?.metadata?.website_url || selectedAsset?.file_name"
        :progress="auditProgress?.progress || 0"
        :step="auditProgress?.step || 'Initializing Security Scan...'"
        :details="auditProgress?.details"
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

    <!-- Marketplace Listing Modal commented out as marketplace is disabled
    <MarketplaceListingModal
        v-if="selectedAsset"
        :show="showMarketplaceModal"
        :asset-name="selectedAsset.file_name || 'Asset'"
        :is-subscriber="!!($page.props.auth.user as any)?.active_subscription"
        :is-already-listed="isAlreadyOwned"
        @close="showMarketplaceModal = false"
        @confirm="handleMarketplaceConfirm"
    />
    -->

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
                    class="px-6 py-2 bg-[#CBB48A] hover:bg-[#CBB48A]/80 text-black text-sm font-bold rounded-lg transition-all shadow-lg shadow-[#CBB48A]/20"
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
