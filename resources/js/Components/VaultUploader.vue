<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue';
import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import AwsS3 from '@uppy/aws-s3';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import LumeAISupport from '@/Components/LumeAISupport.vue';
import type { PresignedUrlResponse, ConfirmUploadResponse, VaultAsset } from '@/types/vault';

// Audit types
type AuditType = 'document' | 'project';

// Props
interface Props {
    maxFileSize?: number;
    allowedFileTypes?: string[];
    maxNumberOfFiles?: number;
    credits?: number;
}

const props = withDefaults(defineProps<Props>(), {
    maxFileSize: 100 * 1024 * 1024,
    allowedFileTypes: () => ['image/*', '.pdf'],
    maxNumberOfFiles: 10,
    credits: 0,
});

// Emits
const emit = defineEmits<{
    (e: 'upload-success', asset: VaultAsset): void;
    (e: 'upload-error', error: Error): void;
    (e: 'all-uploads-complete', assets: VaultAsset[]): void;
    (e: 'project-submitted', project: any): void;
    (e: 'tab-change', tab: 'document' | 'project'): void;
    (e: 'open-comparison', assets: [VaultAsset, VaultAsset]): void;
    (e: 'scan-started', asset: VaultAsset): void;
}>();

// State
const activeTab = ref<AuditType>('document');
const uppyContainer = ref<HTMLElement | null>(null);
const isUploading = ref(false);
const uploadedAssets = ref<VaultAsset[]>([]);
const showSuccessNotification = ref(false);
const isBuyingCredits = ref(false);
const showAIGuide = ref(false);

// Sovereign UI State
const isDragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const pendingFile = ref<File | null>(null); // For manual "Preview" before upload
const isAutoScanning = ref(false); // Flag to chain upload -> scan

// Project scan state
const projectUrl = ref('');
const githubUrl = ref('');
const githubToken = ref('');
const isSubmittingProject = ref(false);
const projectError = ref('');

// Live Discovery State Machine
const scanStep = ref<'input' | 'branch' | 'fill'>('input');
const contextType = ref<string>('website');
const contextMessage = ref<string>('');
const hasContextError = ref(false);

// Branching Logic
const hasCodebase = ref<boolean | null>(null);
const isMarketplaceListing = ref<boolean | null>(null);
const hasLiveUrl = ref<boolean | null>(null);
const liveProductionUrl = ref(''); // For GitHub -> Website bridge
const awaitingLiveUrl = ref(false); // UI state for bridge mode
const isLiveUrlValid = ref(false);
const isValidatingUrl = ref(false);
const syncContextMessage = ref('');

// Proof Uppy
const proofUppyContainer = ref<HTMLElement | null>(null);
let proofUppy: Uppy | null = null;
const proofIds = ref<string[]>([]);
const proofAssetMap = new Map<string, string>(); // file.id -> asset_id

let debounceTimer: ReturnType<typeof setTimeout>;

// Emit tab change to parent when activeTab changes
watch(activeTab, (newTab) => {
    emit('tab-change', newTab);
});

// Smart Input Watcher
watch(projectUrl, (newUrl) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    hasContextError.value = false;
    
    if (!newUrl) {
        contextType.value = 'website';
        contextMessage.value = '';
        return;
    }

    debounceTimer = setTimeout(async () => {
        try {
            const response = await axios.post('/vault/detect-context', { url: newUrl });
            const context = response.data.context; // Access the nested context object
            
            contextType.value = context.type;
            contextMessage.value = context.message;
            
            // Move directly to fill step for individual scanning (sync scanning is now handled on the Targets page)
            scanStep.value = 'fill';
            hasLiveUrl.value = false;
            awaitingLiveUrl.value = false;
            isMarketplaceListing.value = false;
            hasCodebase.value = false;

            // Specific logic based on type
            if (context.type === 'repo') {
                githubUrl.value = newUrl; // Auto-fill if it's a repo
            } else if (context.type === 'design') {
                githubUrl.value = '';
                githubToken.value = '';
            }

        } catch (error: any) {
            hasContextError.value = true;
            contextMessage.value = "Invalid URL";
            // Capture specific server error for AI Guide
            projectError.value = error.response?.data?.message || 'The provided URL could not be validated.';
        }
    }, 500);
});

// Watch Live URL for validation
watch(liveProductionUrl, (newUrl) => {
    isLiveUrlValid.value = false;
    syncContextMessage.value = '';
    
    if (!newUrl) return;
    
    // Simple regex check first
    if (!/^https?:\/\//.test(newUrl)) return;
    
    isValidatingUrl.value = true;
    
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(async () => {
        try {
            const { data } = await axios.post('/validate-url', { url: newUrl });
            if (data.valid) {
                 isLiveUrlValid.value = true;
                 syncContextMessage.value = "Website + Repo Syncing Checked";
            } else {
                 isLiveUrlValid.value = false;
                 syncContextMessage.value = "URL Unreachable";
            }
        } catch (e) {
            isLiveUrlValid.value = false;
            syncContextMessage.value = "Validation Error";
        } finally {
            isValidatingUrl.value = false;
        }
    }, 800);
});

// Credit costs
const creditCosts = {
    document: 1,
    project: 10,
};

// Computed
const activeSubscription = computed(() => usePage().props.auth.user?.active_subscription);

const hasQuota = computed(() => {
    if (!activeSubscription.value) return false;
    
    // Team Plan has unlimited scans
    if (activeSubscription.value.plan_type === 'agency') return true;
    
    // Developer Plan limits
    if (activeSubscription.value.plan_type === 'developer') {
        if (activeTab.value === 'document') {
             return (activeSubscription.value.daily_individual_scans_used || 0) < 20;
        }
        if (activeTab.value === 'project') {
             // For simplicity in UI, we check if they have at least 1 individual scan left
             // (Individual scans cover both documents and projects for Developer)
             // OR if they are specifically doing a sync scan (not tracked yet in uploader but potentially)
             return (activeSubscription.value.daily_individual_scans_used || 0) < 20;
        }
    }
    
    return false;
});

const hasCredits = computed(() => props.credits > 0);
const hasEnoughCredits = computed(() => hasQuota.value || props.credits >= creditCosts[activeTab.value]);
const requiredCredits = computed(() => hasQuota.value ? 0 : creditCosts[activeTab.value]);

const canSubmitProject = computed(() => {
    if (activeTab.value !== 'project' || !hasEnoughCredits.value) return false;
    if (scanStep.value !== 'fill') return false; // Must complete branching
    
    // Unknown type - blocked
    if (contextType.value === 'unknown') return false;

    // Design Branch: URL + 5-10 screenshots
    if (contextType.value === 'design') {
        if (!projectUrl.value) return false;
        if (hasCodebase.value === true) {
            return !!githubUrl.value && proofIds.value.length >= 5;
        }
        return proofIds.value.length >= 5 && proofIds.value.length <= 10;
    }

    // Website Branch: URL + Repo (if marketplace)
    if (contextType.value === 'website') {
        if (!projectUrl.value) return false;
        if (isMarketplaceListing.value === true) return !!githubUrl.value;
        return true;
    }

    // Repo Branch: Repo URL required, Live URL optional but token needed for private
    if (contextType.value === 'repo') {
        if (!githubUrl.value) return false;
        // If they said yes to live URL, they need to provide it
        if (hasLiveUrl.value === true && !liveProductionUrl.value) return false;
        return true;
    }

    return false;
});

/**
 * Buy test credits (DEV ONLY)
 */
function buyTestCredits(): void {
    isBuyingCredits.value = true;
    router.post('/credits/add-test', { amount: 10 }, {
        onFinish: () => {
            isBuyingCredits.value = false;
        },
    });
}

let uppy: Uppy<Record<string, unknown>, Record<string, unknown>> | null = null;
const fileAssetMap = new Map<string, string>();

function initUppy(): void {
    // Custom UI: No Dashboard Plugin
    // We bind directly to core events and use our own UI
    uppy = new Uppy({
        id: 'vault-uploader',
        autoProceed: false,
        restrictions: {
            maxFileSize: props.maxFileSize,
            allowedFileTypes: props.allowedFileTypes,
            maxNumberOfFiles: props.maxNumberOfFiles,
        },
    });

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (uppy as any).use(AwsS3, {
        id: 'AwsS3',
        shouldUseMultipart: () => false,
        async getUploadParameters(file: { id: string; name: string; type?: string }) {
            const { data } = await axios.post<PresignedUrlResponse>('/vault/presigned-url', {
                file_name: file.name,
                file_type: file.type || 'application/octet-stream',
                audit_type: 'document', // Set audit type for documents
            });

            fileAssetMap.set(file.id, data.asset_id);

            return {
                method: 'PUT',
                url: data.upload_url,
                headers: data.headers,
            };
        },
    });

    uppy.on('upload', () => {
        isUploading.value = true;
    });

    uppy.on('upload-success', async (file) => {
        if (!file) return;

        const assetId = fileAssetMap.get(file.id);
        if (!assetId) {
            console.error('No asset ID found for file:', file.id);
            return;
        }

        try {
            const { data: confirmData } = await axios.post<ConfirmUploadResponse>('/vault/confirm-upload', {
                asset_id: assetId,
                audit_type: 'document',
            });

            const asset = confirmData.asset;

            if (asset) {
                uploadedAssets.value.push(asset);
                emit('upload-success', asset);
                
                // Clear pendingFile as it is now uploaded
                pendingFile.value = null;

                // Chain Scan if requested
                if (isAutoScanning.value) {
                    scanDocument(asset);
                    isAutoScanning.value = false;
                }
            } else {
                console.error("No asset returned from confirmation");
            }
        } catch (error) {
            emit('upload-error', error as Error);
        }
    });

    uppy.on('upload-error', (_file, error) => {
        emit('upload-error', error);
    });

    uppy.on('complete', (result) => {
        isUploading.value = false;

        if (result.successful && result.successful.length > 0) {
            showSuccessNotification.value = true;

            setTimeout(() => {
                showSuccessNotification.value = false;
            }, 5000);

            // router.reload({
            //     only: ['wallet', 'assets'],
            //     onSuccess: () => {
            //         console.log('Data updated!');
            //     },
            // });

            emit('all-uploads-complete', [...uploadedAssets.value]);
        }

        fileAssetMap.clear();
    });
}


// Sovereign Dropzone Handlers
const handleDragOver = (e: DragEvent) => {
    e.preventDefault();
    isDragging.value = true;
};

const handleDragLeave = (e: DragEvent) => {
    e.preventDefault();
    isDragging.value = false;
};

const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    isDragging.value = false;
    
    if (e.dataTransfer?.files) {
        // Clear previous pending
        pendingFile.value = null;
        uppy?.cancelAll();

        const files = Array.from(e.dataTransfer.files);
        if (files.length > 0) {
            // Take the first file for Document Scan mode
            const file = files[0];
            pendingFile.value = file;

            // Add to Uppy for eventual upload
            try {
                uppy?.addFile({
                    name: file.name,
                    type: file.type,
                    data: file,
                });
            } catch (err) {
                console.error("File add failed:", err);
            }
        }
    }
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

const handleFileSelect = (e: Event) => {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
        // Clear previous
        pendingFile.value = null;
        uppy?.cancelAll();

        const file = input.files[0];
        pendingFile.value = file;

        try {
            uppy?.addFile({
                name: file.name,
                type: file.type,
                data: file,
            });
        } catch (err) {
            console.error("File add failed:", err);
        }
    }
    // Reset input
    if (input) input.value = '';
};

const isScanInitiating = ref<string | null>(null);

/**
 * Initiate Scan from Pending State (Triggers Upload -> Scan)
 */
function initiateScan() {
    if (!hasEnoughCredits.value) return;
    isAutoScanning.value = true;
    uppy?.upload();
}

/**
 * Cancel Pending File (Change)
 */
function cancelPending() {
    pendingFile.value = null;
    uppy?.cancelAll();
    // Re-open file selector
    nextTick(() => {
        triggerFileInput();
    });
}

/**
 * Manually initiate scan for a document
 */
async function scanDocument(asset: VaultAsset): Promise<void> {
    if (isScanInitiating.value) return;
    
    isScanInitiating.value = asset.id;
    
    // Optimistic Update: Initiate Scan UI immediately
    const index = uploadedAssets.value.findIndex(a => a.id === asset.id);
    if (index !== -1) {
        uploadedAssets.value[index].status = 'processing';
    }
    
    // Notify parent to open scanning modal ("Connecting...")
    emit('scan-started', { ...asset, status: 'processing' });

    try {
        await axios.post('/vault/scan-document', { asset_id: asset.id });
        
        // Success
    } catch (error: any) {
        console.error("Scan initiation failed:", error);
        alert("Failed to initiate scan: " + (error.response?.data?.message || "Unknown error"));
        
        // Revert status on failure
        if (index !== -1) {
            uploadedAssets.value[index].status = 'uploaded';
        }
    } finally {
        isScanInitiating.value = null;
    }
}

/**
 * Remove asset from list (Change file)
 */
function removeAsset(asset: VaultAsset): void {
     uploadedAssets.value = uploadedAssets.value.filter(a => a.id !== asset.id);
     // If empty, reset uppy too if needed
     if (uploadedAssets.value.length === 0) {
         uppy?.cancelAll();
         fileAssetMap.clear();
         
         // Automatically re-open file selector
         nextTick(() => {
             triggerFileInput();
         });
     }
}

/**
 * Submit project for scanning
 */
async function startForensicScan(): Promise<void> {
    if (!canSubmitProject.value) return;
    
    // NOTE: Backend dispatches audit as async Job.
    // Asset returned immediately with status 'processing' for instant modal feedback.
    
    isSubmittingProject.value = true;
    projectError.value = '';

    try {
        // Create project asset with audit_type based on context
        const auditType = contextType.value === 'design' ? 'design' : (contextType.value === 'repo' ? 'repository' : 'project');
        
        // Exact URL mapping to prevent ambiguity (e.g. Repo URL spilling into Website URL)
        let finalWebsiteUrl = null;
        let finalGithubUrl = null;

        if (contextType.value === 'website') {
             finalWebsiteUrl = projectUrl.value;
             // If marketplace listing, githubUrl is manually filled
             if (isMarketplaceListing.value) finalGithubUrl = githubUrl.value;
        } 
        else if (contextType.value === 'repo') {
             finalGithubUrl = projectUrl.value || githubUrl.value; // First input is Repo in this context
             // Live URL is only sent if verified/entered
             if (hasLiveUrl.value && liveProductionUrl.value) {
                 finalWebsiteUrl = liveProductionUrl.value;
             }
        } 
        else if (contextType.value === 'design') {
             // Design URL is technically the "website_url" (Figma etc) or just name?
             // Usually treated as projectUrl.
             finalWebsiteUrl = projectUrl.value;
             if (hasCodebase.value) finalGithubUrl = githubUrl.value;
        }

        const { data } = await axios.post('/projects', {
            name: finalWebsiteUrl || finalGithubUrl || 'Untitled Project',
            website_url: finalWebsiteUrl,
            github_repo_url: finalGithubUrl,
            github_token: githubToken.value || null,
            audit_type: auditType,
            proof_asset_ids: proofIds.value,
        });

            if (data.success) {
            showSuccessNotification.value = true;
            
            // Emit the asset immediately so the modal opens during processing
            // The asset will have status: 'processing' initially
            if (data.asset) {
                // FORCE SYNC IDENTIFICATION: If we have a repo asset, mark this as a sync scan
                // This ensures Dashboard.vue routes it to SyncScanning.vue instead of generic AuditReportModal
                if (data.repo_asset) {
                    if (!data.asset.metadata) data.asset.metadata = {};
                    data.asset.metadata.is_sync_scan = true;
                }

                emit('upload-success', data.asset);
            }

            // If Sync Scan (Repo asset returned), trigger comparison flow immediately
            if (data.repo_asset && data.asset) {
                emit('open-comparison', [data.asset, data.repo_asset]);
            }
            
            emit('project-submitted', data.project);
            
            // Clear form
            projectUrl.value = '';
            githubUrl.value = '';
            githubToken.value = '';
            liveProductionUrl.value = '';
            awaitingLiveUrl.value = false;
            proofIds.value = [];
            proofUppy?.cancelAll();

            setTimeout(() => {
                showSuccessNotification.value = false;
            }, 5000);

            // Reload data
            // router.reload({
            //     only: ['wallet', 'assets'],
            // });
        }
    } catch (error: any) {
        projectError.value = error.response?.data?.message || 'Failed to submit project';
    } finally {
        isSubmittingProject.value = false;
    }
}

// Proof Uppy Init
const initProofUppy = () => {
    if (proofUppy) return;
    
    proofUppy = new Uppy({
        id: 'proof-uploader',
        autoProceed: true,
        restrictions: {
            maxFileSize: 5 * 1024 * 1024, // 5MB
            allowedFileTypes: ['image/*'],
            maxNumberOfFiles: 10,
            minNumberOfFiles: 5,
        },
    });

    proofUppy.use(Dashboard, {
        target: proofUppyContainer.value!,
        inline: true,
        width: '100%',
        height: 250,
        proudlyDisplayPoweredByUppy: false,
        theme: 'dark',
        hideUploadButton: true, // Auto proceed
        note: `Min 5, Max 10 Screenshots. Auto-upload enabled.`,
    });

    // Reuse existing upload logic (presigned url)
    (proofUppy as any).use(AwsS3, {
        id: 'AwsS3Proofs',
        shouldUseMultipart: () => false,
        async getUploadParameters(file: { id: string; name: string; type?: string }) {
            const { data } = await axios.post<PresignedUrlResponse>('/vault/presigned-url', {
                file_name: file.name,
                file_type: file.type || 'image/png',
                audit_type: 'proof', // Special type
            });
            proofAssetMap.set(file.id, data.asset_id);
            return {
                method: 'PUT',
                url: data.upload_url,
                headers: data.headers,
            };
        },
    });

    proofUppy.on('upload-success', async (file) => {
        if (!file) return;
        const assetId = proofAssetMap.get(file.id);
        if (assetId) {
             // Confirm upload logic might need to be called here or assumed ok?
             // Usually we must confirm to update status to uploaded.
             await axios.post('/vault/confirm-upload', {
                asset_id: assetId,
                audit_type: 'proof',
            });
            proofIds.value.push(assetId);
        }
    });
};

// Initialize Proof Uppy when needed
watch([() => contextType.value, () => scanStep.value, () => hasCodebase.value], async () => {
    if (contextType.value === 'design' && scanStep.value === 'fill' && hasCodebase.value === false) {
        await nextTick();
        if (proofUppyContainer.value) {
            initProofUppy();
        }
    }
});

// Reinitialize Uppy when tab changes
watch(activeTab, (newTab) => {
    if (newTab === 'document' && uppyContainer.value && !uppy) {
        initUppy();
    }
});

// Lifecycle
onMounted(() => {
    if (activeTab.value === 'document') {
        initUppy();
    }
});

onBeforeUnmount(() => {
    uppy?.destroy();
});

// Expose methods
defineExpose({
    clearUploads: (): void => {
        uppy?.cancelAll();
        uploadedAssets.value = [];
        fileAssetMap.clear();
        projectUrl.value = '';
        githubUrl.value = '';
        githubToken.value = '';
    },
    getUploadedAssets: (): VaultAsset[] => [...uploadedAssets.value],
});
</script>

<template>
    <div class="vault-uploader relative">
        <!-- Header: Title / Subtitle on Left, Tab Switcher on Right -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 border-b border-white/5 pb-6 mb-6">
            <!-- Left Side: Title and Subtitle -->
            <div class="flex items-center gap-4">
                <!-- Icon (only for project tab) -->
                <div v-if="activeTab === 'project'" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#CBB48A]/10 border border-[#CBB48A]/20 text-[#CBB48A]">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold tracking-tight text-white leading-tight">
                        {{ activeTab === 'document' ? 'Secure your files and assets' : 'Project & Codebase Scanner' }}
                    </h3>
                    <p class="text-sm font-medium text-gray-400 mt-1 max-w-xl">
                        {{ activeTab === 'document' ? 'Scan documents, websites, and codebases to detect risks and protect your business.' : 'Full forensic analysis of your website and repository' }}
                    </p>
                </div>
            </div>

            <!-- Right Side: Tab Switcher (Pill Buttons) -->
            <div class="inline-flex rounded-full bg-white/[0.03] border border-white/5 p-1 backdrop-blur-sm shrink-0 self-start md:self-auto">
                <button
                    type="button"
                    @click="activeTab = 'document'"
                    :class="[
                        'relative flex items-center gap-2 rounded-full px-5 py-2 text-xs font-bold tracking-wider transition-all duration-300',
                        activeTab === 'document'
                            ? 'bg-[#CBB48A] text-slate-950 shadow-lg shadow-[#CBB48A]/25'
                            : 'text-gray-400 hover:text-white'
                    ]"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Scan Document
                </button>
                <button
                    type="button"
                    @click="activeTab = 'project'"
                    :class="[
                        'relative flex items-center gap-2 rounded-full px-5 py-2 text-xs font-bold tracking-wider transition-all duration-300',
                        activeTab === 'project'
                            ? 'bg-[#CBB48A] text-slate-950 shadow-lg shadow-[#CBB48A]/25'
                            : 'text-gray-400 hover:text-white'
                    ]"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                    </svg>
                    Scan Website & Codebase
                    <span v-if="activeTab === 'project'" class="ml-1.5 rounded-full bg-slate-950/20 px-1.5 py-0.5 text-[9px] font-bold">10 credits</span>
                </button>
            </div>
        </div>

        <!-- Subscription Quota Status (Short) -->
        <div v-if="activeSubscription" class="mb-4 flex flex-wrap items-center justify-center gap-6 text-sm">
             <div class="flex items-center gap-2">
                 <span class="text-slate-500 font-medium">Daily Quota:</span>
                 <span :class="hasQuota ? 'text-[#CBB48A] font-bold' : 'text-rose-400 font-bold'">
                     {{ activeSubscription.plan_type === 'agency' ? 'Unlimited' : `${20 - (activeSubscription.daily_individual_scans_used || 0)} Scans Left` }}
                 </span>
             </div>
             <div v-if="activeSubscription.plan_type === 'developer'" class="flex items-center gap-2 border-l border-slate-700 pl-6 text-slate-500">
                 <span>Wallet: {{ props.credits }} Credits</span>
             </div>
        </div>

        <!-- Credit Requirement Warning -->
        <div v-if="!hasEnoughCredits && !activeSubscription" class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-500/20">
                    <svg class="h-5 w-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="font-medium text-amber-400">
                        {{ requiredCredits }} credit{{ requiredCredits > 1 ? 's' : '' }} required
                    </p>
                    <p class="text-sm text-amber-400/70">
                        You have {{ credits }} credit{{ credits !== 1 ? 's' : '' }}. 
                        {{ activeTab === 'project' ? 'Project scans cost 5 credits due to advanced processing.' : '' }}
                    </p>
                </div>
                <button
                    type="button"
                    @click="buyTestCredits"
                    :disabled="isBuyingCredits"
                    class="rounded-lg bg-[#CBB48A] px-4 py-2 text-xs font-bold tracking-wider text-slate-900 transition-all hover:bg-[#CBB48A]/80 disabled:opacity-50 shadow-lg shadow-[#CBB48A]/10"
                >
                    {{ isBuyingCredits ? 'Adding...' : 'Get Credits' }}
                </button>
            </div>
        </div>

        <!-- Document Scan Tab -->
        <!-- Sovereign Document Scan UI -->
        <div v-show="activeTab === 'document'">
            <div
                v-if="uploadedAssets.length === 0 && !pendingFile"
                class="relative rounded-[2rem] border transition-all duration-700 overflow-hidden group min-h-[400px] flex flex-col items-center justify-center p-8 sm:p-12 text-center"
                :class="[
                    isDragging 
                        ? 'border-[#CBB48A] bg-[#CBB48A]/5 shadow-[0_0_50px_rgba(203,180,138,0.15)] scale-[1.01]' 
                        : hasEnoughCredits 
                            ? 'border-white/5 bg-white/[0.02] hover:border-[#CBB48A]/30' 
                            : 'border-white/5 bg-white/[0.01] opacity-50 cursor-not-allowed'
                ]"
                @dragover="handleDragOver"
                @dragleave="handleDragLeave"
                @drop="handleDrop"
                @click="hasEnoughCredits ? triggerFileInput() : null"
            >
                <!-- Interactive Backdrop Glow -->
                <div class="absolute inset-0 bg-gradient-to-br from-[#CBB48A]/5 via-transparent to-[#F3E7C9]/5 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>

                <!-- Gold swooping lines -->
                <svg class="absolute left-6 bottom-6 w-48 h-32 text-[#CBB48A]/10 pointer-events-none" viewBox="0 0 200 100" fill="none">
                    <path d="M10,90 Q80,80 120,40 T190,10" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 4" />
                </svg>
                <svg class="absolute right-6 top-6 w-48 h-32 text-[#CBB48A]/10 pointer-events-none" viewBox="0 0 200 100" fill="none">
                    <path d="M10,10 Q80,30 120,60 T190,90" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 4" />
                </svg>

                <!-- Glowing stars -->
                <span class="absolute left-16 top-1/4 text-[#CBB48A]/40 text-lg animate-pulse pointer-events-none select-none">✦</span>
                <span class="absolute left-32 bottom-1/5 text-[#F3E7C9]/30 text-xs animate-pulse pointer-events-none select-none" style="animation-delay: 1s">✦</span>
                <span class="absolute right-32 top-1/5 text-[#F3E7C9]/30 text-xs animate-pulse pointer-events-none select-none" style="animation-delay: 0.5s">✦</span>
                <span class="absolute right-20 bottom-1/4 text-[#CBB48A]/50 text-lg animate-pulse pointer-events-none select-none" style="animation-delay: 1.5s">✦</span>

                <!-- Shield Checkmark on bottom right -->
                <div class="absolute right-10 bottom-10 w-12 h-12 rounded-2xl bg-[#0e1217]/90 border border-white/5 flex items-center justify-center shadow-2xl pointer-events-none select-none">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>

                <div class="relative z-10 flex flex-col items-center gap-6 pointer-events-none">
                    <!-- Sovereign Icon -->
                    <div class="relative w-20 h-20 sm:w-24 sm:h-24 flex items-center justify-center">
                        <!-- Animated Rings -->
                        <div class="absolute inset-0 rounded-full border border-indigo-500/20 animate-[spin_10s_linear_infinite]" :class="{ 'border-indigo-500/50 shadow-[0_0_30px_indigo]': isDragging }"></div>
                        <div class="absolute inset-2 rounded-full border border-[#CBB48A]/20 animate-[spin_7s_linear_infinite_reverse]" :class="{ 'border-[#CBB48A]/50': isDragging }"></div>
                        
                        <!-- Core Icon -->
                        <div class="relative w-12 h-12 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center shadow-xl border border-gray-700/50 group-hover:border-[#CBB48A]/30 transition-colors">
                            <svg v-if="!isUploading" class="w-6 h-6 sm:w-8 sm:h-8 text-gray-400 group-hover:text-[#CBB48A] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <svg v-else class="w-6 h-6 sm:w-8 sm:h-8 text-[#CBB48A] animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Typography -->
                    <div class="space-y-2">
                        <h3 class="text-xl sm:text-2xl font-bold tracking-tight text-white">
                            <span v-if="isDragging" class="text-[#CBB48A]">Release to Analyze</span>
                            <span v-else-if="isUploading">Uploading Assets...</span>
                            <span v-else>Upload Documents</span>
                        </h3>
                        <p class="text-sm sm:text-base text-gray-400 max-w-sm mx-auto leading-relaxed">
                            <span v-if="isDragging">Drop your files to begin the forensic audit.</span>
                            <span v-else>Drag & drop your files here or <span class="text-[#CBB48A] underline decoration-[#CBB48A]/30 underline-offset-4 group-hover:text-[#CBB48A]/80 transition-colors cursor-pointer pointer-events-auto" @click.stop="triggerFileInput">browse vault</span></span>
                        </p>
                    </div>

                    <!-- Tech Specs -->
                    <div class="flex items-center gap-4 text-xs font-mono text-gray-600 uppercase tracking-widest mt-4">
                        <span class="px-2 py-1 rounded bg-gray-800/50 border border-gray-700/50">PDF / IMG / ZIP</span>
                        <span class="w-px h-3 bg-gray-700"></span>
                        <span class="px-2 py-1 rounded bg-gray-800/50 border border-gray-700/50">Max 100MB</span>
                    </div>
                </div>

                <!-- Hidden Input -->
                <input 
                    ref="fileInput"
                    type="file" 
                    class="hidden" 
                    @change="handleFileSelect"
                    accept="image/*,.pdf,.zip,video/*,audio/*"
                />
            </div>

            <!-- Pending File (Pre-Scan) -->
            <div v-if="pendingFile && uploadedAssets.length === 0" class="mt-6 space-y-4">
                 <div class="group relative bg-white/[0.03] rounded-2xl border border-white/5 p-6 transition-all border-l-4 border-l-[#CBB48A]/50">
                    <div class="flex flex-col sm:flex-row items-center gap-6">
                        <!-- Icon -->
                        <div class="w-16 h-16 rounded-2xl bg-gray-800 flex items-center justify-center text-gray-400 shrink-0">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        
                        <div class="flex-1 min-w-0 text-center sm:text-left">
                            <h5 class="text-lg font-medium text-white truncate">{{ pendingFile.name }}</h5>
                            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 mt-2 text-sm text-gray-400">
                                <span>{{ (pendingFile.size / 1024 / 1024).toFixed(2) }} MB</span>
                                <span class="w-1 h-1 rounded-full bg-gray-600"></span>
                                <span class="capitalize">{{ pendingFile.type.split('/')[1] || 'Document' }}</span>
                                <span class="w-1 h-1 rounded-full bg-gray-600"></span>
                                <span class="text-amber-400 animate-pulse">Pending Upload</span>
                            </div>
                        </div>

                        <!-- User Actions -->
                        <div class="flex items-center gap-3 w-full sm:w-auto">
                            <button 
                                @click="cancelPending"
                                class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl border border-white/10 text-gray-300 hover:bg-white/10 hover:text-white transition-all text-sm font-bold"
                            >
                                Change
                            </button>
                            <button 
                                @click="initiateScan"
                                :disabled="!hasEnoughCredits || isUploading"
                                class="flex-1 sm:flex-none px-6 py-2.5 rounded-xl bg-[#CBB48A] text-slate-900 font-bold tracking-wider text-xs shadow-lg shadow-[#CBB48A]/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center justify-center gap-2 active:scale-95"
                            >
                                <span v-if="isUploading">Uploading...</span>
                                <span v-else>Scan Document</span>
                            </button>
                        </div>
                    </div>
                 </div>
            </div>

            <!-- Upload List / Preview (Manual Scan Trigger) -->
            <div v-if="uploadedAssets.length > 0" class="mt-6 space-y-4">
                <div class="flex items-center justify-between px-2">
                    <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500">Initial Analysis Complete</h4>
                    <span class="text-[10px] font-bold tracking-wider text-[#F3E7C9]">{{ uploadedAssets.length }} Files Ready</span>
                </div>
                
                <div class="grid grid-cols-1 gap-4">
                    <div 
                        v-for="asset in uploadedAssets" 
                        :key="asset.id"
                        class="group relative bg-white/[0.03] rounded-2xl border border-white/5 p-6 transition-all hover:border-[#CBB48A]/30 hover:shadow-2xl hover:shadow-[#CBB48A]/5"
                    >
                        <div class="flex flex-col sm:flex-row items-center gap-6">
                             <!-- Icon -->
                            <div class="w-16 h-16 rounded-2xl bg-white/5 flex items-center justify-center text-gray-400 group-hover:bg-[#CBB48A]/10 group-hover:text-[#CBB48A] transition-all shrink-0 border border-white/5">
                                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            
                            <div class="flex-1 min-w-0 text-center sm:text-left">
                                <h5 class="text-lg font-medium text-white truncate">{{ asset.file_name }}</h5>
                                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 mt-2 text-sm text-gray-400">
                                    <span>{{ (asset.file_size / 1024 / 1024).toFixed(2) }} MB</span>
                                    <span class="w-1 h-1 rounded-full bg-gray-600"></span>
                                    <span class="capitalize">{{ asset.mime_type?.split('/')[1] || 'Document' }}</span>
                                    <span class="w-1 h-1 rounded-full bg-gray-600"></span>
                                    <span class="text-[#CBB48A]">Ready to Scan</span>
                                </div>
                            </div>

                            <!-- User Actions -->
                            <div class="flex items-center gap-3 w-full sm:w-auto">
                                <button 
                                    @click="removeAsset(asset)"
                                    class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl border border-white/10 text-gray-300 hover:bg-white/10 hover:text-white transition-all text-sm font-bold"
                                >
                                    Change
                                </button>
                                <button 
                                    @click="scanDocument(asset)"
                                    :disabled="isScanInitiating === asset.id || !hasEnoughCredits"
                                    class="flex-1 sm:flex-none px-6 py-2.5 rounded-xl bg-[#CBB48A] text-slate-900 font-bold tracking-wider text-xs shadow-lg shadow-[#CBB48A]/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center justify-center gap-2 active:scale-95"
                                >
                                    <span v-if="isScanInitiating === asset.id">Initiating...</span>
                                    <span v-else>Scan Document</span>
                                    <svg v-if="!isScanInitiating" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div v-show="activeTab === 'project'" class="space-y-6">
            <div class="space-y-6">
                <!-- Production URL / Smart Input -->
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-300">
                        <span class="flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-[#CBB48A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                </svg>
                                Project or Design URL
                            </span>
                            <!-- Context Feedback -->
                            <button 
                                v-if="contextMessage || hasContextError"
                                @click="showAIGuide = true"
                                type="button"
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[10px] font-semibold border transition-all active:scale-95',
                                    hasContextError 
                                        ? 'bg-red-500/10 text-red-400 border-red-500/20 animate-pulse' 
                                        : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                ]"
                            >
                                <span v-if="!hasContextError" class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                <span v-else class="h-1.5 w-1.5 rounded-full bg-red-400"></span>
                                {{ hasContextError ? 'Invalid URL? Need Help?' : contextMessage }}
                            </button>
                        </span>
                    </label>
                    <div class="flex gap-2">
                        <input
                            v-model="projectUrl"
                            type="url"
                            placeholder="https://yoursite.com, figma.com/file/..., or github.com/..."
                            :disabled="!hasEnoughCredits"
                            class="flex-1 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-gray-500 transition-all focus:border-[#CBB48A] focus:outline-none focus:ring-1 focus:ring-[#CBB48A] disabled:opacity-50"
                        />
                        <button
                            type="button"
                            @click="showAIGuide = true"
                            class="flex items-center gap-2 rounded-xl border border-[#CBB48A]/50 bg-[#CBB48A]/5 px-4 py-3 text-xs font-bold tracking-wider text-[#CBB48A] hover:bg-[#CBB48A]/15 active:scale-95 transition-all shadow-lg shadow-[#CBB48A]/5"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                            Guide AI
                        </button>
                    </div>
                </div>

                <!-- GitHub Repository URL / Branching Questions -->
                <div v-if="scanStep === 'branch' || scanStep === 'fill'" class="rounded-xl border border-white/5 bg-white/[0.02] p-5">
                    <!-- Design Branch Question -->
                    <div v-if="contextType === 'design'">
                        <p class="mb-3 text-sm font-medium text-white">Does this design have a matching codebase?</p>
                        <div class="flex gap-4">
                            <button 
                                @click="hasCodebase = true; scanStep = 'fill'"
                                type="button"
                                :class="['rounded-xl px-5 py-3.5 text-xs font-bold uppercase tracking-wider transition-all duration-300', hasCodebase === true ? 'bg-[#CBB48A] text-slate-955 shadow-lg shadow-[#CBB48A]/20' : 'bg-white/5 border border-white/10 text-gray-400 hover:text-white']"
                            >
                                Yes, link Repo
                            </button>
                            <button 
                                @click="hasCodebase = false; scanStep = 'fill'"
                                type="button"
                                :class="['rounded-xl px-5 py-3.5 text-xs font-bold uppercase tracking-wider transition-all duration-300', hasCodebase === false ? 'bg-[#CBB48A] text-slate-955 shadow-lg shadow-[#CBB48A]/20' : 'bg-white/5 border border-white/10 text-gray-400 hover:text-white']"
                            >
                                No, Design Only
                            </button>
                        </div>
                    </div>

                    <!-- Website Branch Question -->
                    <div v-else-if="contextType === 'website'">
                        <p class="mb-3 text-sm font-medium text-white">Do you want to sell this on the Marketplace?</p>
                        <div class="flex gap-4">
                            <button 
                                @click="isMarketplaceListing = true; scanStep = 'fill'"
                                type="button"
                                :class="['rounded-xl px-5 py-3.5 text-xs font-bold uppercase tracking-wider transition-all duration-300', isMarketplaceListing === true ? 'bg-[#CBB48A] text-slate-955 shadow-lg shadow-[#CBB48A]/20' : 'bg-white/5 border border-white/10 text-gray-400 hover:text-white']"
                              >
                                Yes (Business Scan)
                            </button>
                            <button 
                                @click="isMarketplaceListing = false; scanStep = 'fill'"
                                type="button"
                                :class="['rounded-xl px-5 py-3.5 text-xs font-bold uppercase tracking-wider transition-all duration-300', isMarketplaceListing === false ? 'bg-[#CBB48A] text-slate-955 shadow-lg shadow-[#CBB48A]/20' : 'bg-white/5 border border-white/10 text-gray-400 hover:text-white']"
                              >
                                No, Private Scan
                            </button>
                        </div>
                    </div>

                     <!-- Repo Branch Question -->
                     <div v-else-if="contextType === 'repo'">
                        <p class="mb-3 text-sm font-medium text-white">Is there a live production URL?</p>
                        <div class="flex gap-4">
                            <button 
                                @click="hasLiveUrl = true; awaitingLiveUrl = true; scanStep = 'fill'"
                                type="button"
                                :class="['rounded-xl px-5 py-3.5 text-xs font-bold uppercase tracking-wider transition-all duration-300', hasLiveUrl === true ? 'bg-[#CBB48A] text-slate-955 shadow-lg shadow-[#CBB48A]/20' : 'bg-white/5 border border-white/10 text-gray-400 hover:text-white']"
                            >
                                Yes
                            </button>
                            <button 
                                @click="hasLiveUrl = false; awaitingLiveUrl = false; scanStep = 'fill'"
                                type="button"
                                :class="['rounded-xl px-5 py-3.5 text-xs font-bold uppercase tracking-wider transition-all duration-300', hasLiveUrl === false ? 'bg-[#CBB48A] text-slate-955 shadow-lg shadow-[#CBB48A]/20' : 'bg-white/5 border border-white/10 text-gray-400 hover:text-white']"
                            >
                                No
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Visual Proof Gallery (Design Context Only) -->
                <div v-if="contextType === 'design' && scanStep === 'fill' && hasCodebase === false" class="mt-4">
                    <label class="mb-2 block text-sm font-medium text-gray-300">
                         Visual Verification Gallery (Required)
                    </label>
                    <p class="mb-3 text-xs text-gray-400">
                         Upload 5-10 screenshots. 
                         <span class="text-[#CBB48A]">Must include: Mobile View, Component Library, & Key Screens.</span>
                    </p>
                    <div ref="proofUppyContainer" class="rounded-xl border border-dashed border-gray-700 bg-gray-900/50 p-1"></div>
                    <div v-if="proofIds.length > 0 && proofIds.length < 5" class="mt-2 text-xs text-red-400">
                         Minimum 5 screenshots required ({{ proofIds.length }}/5).
                    </div>
                    <div v-else-if="proofIds.length >= 5" class="mt-2 text-xs text-[#CBB48A]">
                         ✓ {{ proofIds.length }} screenshots uploaded
                    </div>
                </div>

                <!-- Live Production URL (GitHub -> Website Bridge) -->
                <div v-if="contextType === 'repo' && awaitingLiveUrl && scanStep === 'fill'" class="mt-4">
                    <label class="mb-2 block text-sm font-medium text-[#CBB48A]">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                            </svg>
                            Now, paste the Live Production URL
                        </span>
                    </label>
                    <input
                        v-model="liveProductionUrl"
                        type="url"
                        placeholder="https://yoursite.com"
                        :disabled="!hasEnoughCredits"
                        class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-gray-500 transition-colors focus:border-[#CBB48A] focus:outline-none focus:ring-1 focus:ring-[#CBB48A] disabled:opacity-50"
                    />
                     <div class="mt-2 flex items-center gap-2 text-xs">
                        <span v-if="isValidatingUrl" class="text-gray-400 flex items-center gap-1">
                            <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Validating...
                        </span>
                        <span v-else-if="isLiveUrlValid" class="text-emerald-400 font-bold flex items-center gap-1">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Website + Repo Syncing Active
                        </span>
                        <span v-else-if="liveProductionUrl && !isLiveUrlValid" class="text-red-400">
                            URL Unreachable
                        </span>
                         <span class="text-gray-500" v-else>
                            This completes the Repository ↔ Website handshake for full verification.
                        </span>
                    </div>
                </div>

                <!-- GitHub Repository URL -->
                <div v-if="contextType === 'repo' || (contextType === 'website' && isMarketplaceListing) || (contextType === 'design' && hasCodebase)">
                    <label class="mb-2 block text-sm font-medium text-gray-300">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd" />
                            </svg>
                            GitHub Repository URL
                        </span>
                    </label>
                    <input
                        v-model="githubUrl"
                        type="url"
                        placeholder="https://github.com/username/repository"
                        :disabled="!hasEnoughCredits"
                        class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-gray-500 transition-all focus:border-[#CBB48A] focus:outline-none focus:ring-1 focus:ring-[#CBB48A] disabled:opacity-50"
                    />
                </div>

                <!-- GitHub Access Token -->
                <div v-if="contextType === 'repo' || (contextType === 'website' && isMarketplaceListing) || (contextType === 'design' && hasCodebase)">
                    <label class="mb-2 flex items-center justify-between text-sm font-medium text-gray-300">
                         <span class="flex items-center gap-2">
                             <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                             </svg>
                             GitHub Access Token
                         </span>
                         <button 
                            type="button"
                            @click="showAIGuide = true" 
                            class="flex items-center gap-1.5 text-xs text-[#CBB48A] hover:underline"
                         >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Guide
                         </button>
                    </label>
                    <input
                        v-model="githubToken"
                        type="password"
                        placeholder="••••••••••••••••••••"
                        :disabled="!hasEnoughCredits"
                        class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-gray-500 transition-all focus:border-[#CBB48A] focus:outline-none focus:ring-1 focus:ring-[#CBB48A] disabled:opacity-50"
                    />
                    <p class="mt-2 text-xs text-gray-500">URL is used to fetch for open-source structure audit. We never store your master keys.</p>
                </div>

                <!-- Error Message -->
                <div v-if="projectError" class="rounded-lg bg-red-500/10 p-3 text-sm text-red-400">
                    {{ projectError }}
                  </div>

                <!-- What We'll Analyze -->
                <div class="rounded-xl bg-white/[0.02] border border-white/5 p-4 sm:p-6 mt-6">
                    <p class="mb-4 text-xs font-bold tracking-wider text-[#CBB48A]">Analysis Includes:</p>
                    <div class="grid gap-4 text-[10px] font-bold uppercase tracking-widest text-gray-400 grid-cols-1 sm:grid-cols-2">
                        <div class="flex items-center gap-3">
                            <span class="text-[#CBB48A] font-bold text-sm shrink-0">✓</span>
                            Tech Stack Detection
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-[#CBB48A] font-bold text-sm shrink-0">✓</span>
                            DNS & Infrastructure
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-[#CBB48A] font-bold text-sm shrink-0">✓</span>
                            Repository Structure Audit
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-[#CBB48A] font-bold text-sm shrink-0">✓</span>
                            Vulnerability Scan
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-[#CBB48A] font-bold text-sm shrink-0">✓</span>
                            Risk Security Mapping
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-[#CBB48A] font-bold text-sm shrink-0">✓</span>
                            Lume Security Score
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <button
                    type="button"
                    @click="startForensicScan"
                    :disabled="!canSubmitProject || isSubmittingProject"
                    class="w-full rounded-2xl bg-gradient-to-r from-[#CBB48A] to-[#F3E7C9] py-5 font-bold tracking-tight text-slate-900 shadow-xl transition-all hover:shadow-[#CBB48A]/25 hover:brightness-110 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span v-if="isSubmittingProject" class="flex items-center justify-center gap-2">
                        <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        Initializing Forensic Scan...
                    </span>
                    <span v-else class="flex items-center justify-center gap-2">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        {{ hasQuota ? 'Start Forensic Scan' : 'Start Forensic Scan (' + creditCosts.project + ' Credits)' }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Success Notification -->
        <transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showSuccessNotification"
                class="fixed bottom-4 right-4 z-50 rounded-[2rem] border border-white/5 bg-[#0A0A0B]/80 backdrop-blur-3xl p-6 text-white shadow-2xl sm:bottom-8 sm:right-8 before:absolute before:inset-0 before:bg-gradient-to-br before:from-[#CBB48A]/5 before:to-[#F3E7C9]/5 before:rounded-[2rem] before:-z-10"
            >
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#CBB48A]/10 border border-[#CBB48A]/20 text-[#CBB48A] shadow-lg shadow-[#CBB48A]/10">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold tracking-tight">{{ activeTab === 'document' ? 'Upload Complete' : 'Scan Initiated' }}</h4>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">
                            {{ activeTab === 'document' ? 'AI analysis has started' : 'Forensic analysis in progress' }}
                        </p>
                    </div>
                </div>
            </div>
        </transition>
        <LumeAISupport 
            :show="showAIGuide" 
            mode="dashboard"
            :error-context="projectError || (hasContextError ? 'The provided URL seems invalid or unrecognizable.' : '')"
            :initial-message="projectUrl ? 'I am having trouble scanning this URL: ' + projectUrl : ''"
            @close="showAIGuide = false" 
        />
        <!-- Hidden File Input -->
        <input 
            type="file" 
            ref="fileInput" 
            class="hidden" 
            @change="handleFileSelect"
            :accept="allowedFileTypes.join(',')" 
        />
    </div>
</template>

<style scoped lang="postcss">
/* Sovereign Uploader Styles */
</style>
