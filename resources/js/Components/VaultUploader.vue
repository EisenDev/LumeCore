<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue';
import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import AwsS3 from '@uppy/aws-s3';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import AIGuideModal from '@/Components/AIGuideModal.vue';
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
}>();

// State
const activeTab = ref<AuditType>('document');
const uppyContainer = ref<HTMLElement | null>(null);
const isUploading = ref(false);
const uploadedAssets = ref<VaultAsset[]>([]);
const showSuccessNotification = ref(false);
const isBuyingCredits = ref(false);
const showAIGuide = ref(false);

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

// Proof Uppy
const proofUppyContainer = ref<HTMLElement | null>(null);
let proofUppy: Uppy | null = null;
const proofIds = ref<string[]>([]);
const proofAssetMap = new Map<string, string>(); // file.id -> asset_id

let debounceTimer: ReturnType<typeof setTimeout>;

// Emit tab change to parent when activeTab changes
watch(activeTab, (newTab) => {
    emit('tab-change', newTab);
}, { immediate: true });

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
            
            // Move to branch step for all valid types
            scanStep.value = 'branch';

            // Specific logic based on type
            if (context.type === 'repo') {
                githubUrl.value = newUrl; // Auto-fill if it's a repo
            } else if (context.type === 'design') {
                githubUrl.value = '';
                githubToken.value = '';
            } else {
                 // Website
                 // Keep defaults
            }

        } catch (error: any) {
            hasContextError.value = true;
            contextMessage.value = "Invalid URL";
            // Capture specific server error for AI Guide
            projectError.value = error.response?.data?.message || 'The provided URL could not be validated.';
        }
    }, 500);
});

// Credit costs
const creditCosts = {
    document: 1,
    project: 5,
};

// Computed
const hasCredits = computed(() => props.credits > 0);
const hasEnoughCredits = computed(() => props.credits >= creditCosts[activeTab.value]);
const requiredCredits = computed(() => creditCosts[activeTab.value]);

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

/**
 * Initialize Uppy instance
 */
function initUppy(): void {
    if (!uppyContainer.value) return;
    
    uppy = new Uppy({
        id: 'vault-uploader',
        autoProceed: false,
        restrictions: {
            maxFileSize: props.maxFileSize,
            allowedFileTypes: props.allowedFileTypes,
            maxNumberOfFiles: props.maxNumberOfFiles,
        },
    });

    uppy.use(Dashboard, {
        target: uppyContainer.value!,
        inline: true,
        width: '100%',
        height: 350,
        proudlyDisplayPoweredByUppy: false,
        theme: 'auto',
        note: `Documents & Images • ${Math.round(props.maxFileSize / 1024 / 1024)}MB max • 1 credit per audit`,
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

            const asset: VaultAsset = {
                id: confirmData.asset_id,
                file_name: file.name || 'unknown',
                file_path: '',
                file_size: confirmData.file_size || file.size || 0,
                mime_type: file.type || 'application/octet-stream',
                status: confirmData.status,
                metadata: confirmData.metadata ?? null,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
            };

            uploadedAssets.value.push(asset);
            emit('upload-success', asset);
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

            router.reload({
                only: ['wallet', 'assets'],
                onSuccess: () => {
                    console.log('Data updated!');
                },
            });

            emit('all-uploads-complete', [...uploadedAssets.value]);
        }

        fileAssetMap.clear();
    });
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
        const auditType = contextType.value === 'design' ? 'design' : 'project';
        
        const { data } = await axios.post('/projects', {
            name: projectUrl.value || githubUrl.value || liveProductionUrl.value,
            website_url: projectUrl.value || liveProductionUrl.value || null,
            github_repo_url: githubUrl.value || null,
            github_token: githubToken.value || null,
            audit_type: auditType,
            proof_asset_ids: proofIds.value,
        });

            if (data.success) {
            showSuccessNotification.value = true;
            
            // Emit the asset immediately so the modal opens during processing
            // The asset will have status: 'processing' initially
            if (data.asset) {
                emit('upload-success', data.asset);
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
            router.reload({
                only: ['wallet', 'assets'],
            });
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
        <!-- Tab Switcher (Pill Buttons) -->
        <div class="mb-6 flex justify-center">
            <div class="inline-flex rounded-full bg-gray-800/80 p-1 backdrop-blur-sm ring-1 ring-white/10">
                <button
                    type="button"
                    @click="activeTab = 'document'"
                    :class="[
                        'relative flex items-center gap-2 rounded-full px-6 py-2.5 text-sm font-semibold transition-all duration-300',
                        activeTab === 'document'
                            ? 'bg-gradient-to-r from-brand-primary to-brand-secondary text-white shadow-lg shadow-brand-primary/25'
                            : 'text-gray-400 hover:text-white'
                    ]"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Scan Document
                    <span v-if="activeTab === 'document'" class="ml-1 rounded-full bg-white/20 px-1.5 py-0.5 text-xs">1 credit</span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'project'"
                    :class="[
                        'relative flex items-center gap-2 rounded-full px-6 py-2.5 text-sm font-semibold transition-all duration-300',
                        activeTab === 'project'
                            ? 'bg-gradient-to-r from-purple-500 to-pink-500 text-white shadow-lg shadow-purple-500/25'
                            : 'text-gray-400 hover:text-white'
                    ]"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                    </svg>
                    Scan Website & Codebase
                    <span v-if="activeTab === 'project'" class="ml-1 rounded-full bg-white/20 px-1.5 py-0.5 text-xs">5 credits</span>
                </button>
            </div>
        </div>

        <!-- Credit Requirement Warning -->
        <div v-if="!hasEnoughCredits" class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
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
                    class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-amber-400 disabled:opacity-50"
                >
                    {{ isBuyingCredits ? 'Adding...' : 'Get Credits' }}
                </button>
            </div>
        </div>

        <!-- Document Scan Tab -->
        <div v-show="activeTab === 'document'">
            <div
                ref="uppyContainer"
                class="uppy-dashboard-container rounded-2xl border border-gray-700 overflow-hidden"
                :class="{ 'opacity-30 pointer-events-none': !hasEnoughCredits }"
            />

            <div v-if="isUploading" class="mt-4 flex items-center gap-2 text-sm text-gray-400">
                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                <span>Uploading files...</span>
            </div>

            <div v-if="uploadedAssets.length > 0" class="mt-4 text-sm text-brand-secondary">
                ✓ {{ uploadedAssets.length }} file(s) uploaded successfully
            </div>
        </div>

        <!-- Project Scan Tab -->
        <div v-show="activeTab === 'project'" class="space-y-6">
            <div class="rounded-2xl border border-gray-700 bg-gray-800/50 p-6">
                <div class="mb-6 flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-purple-500/20 to-pink-500/20">
                        <svg class="h-6 w-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-white">Project & Codebase Scanner</h3>
                        <p class="text-sm text-gray-400">Full forensic analysis of your website and repository</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <!-- Production URL -->
                    <!-- Production URL / Smart Input -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-300">
                            <span class="flex items-center justify-between">
                                <span class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                        'flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium transition-all',
                                        hasContextError ? 'bg-red-500/20 text-red-400 animate-pulse' : 'bg-emerald-500/10 text-emerald-400'
                                    ]"
                                >
                                    <span v-if="hasContextError">Invalid URL? Need Help?</span>
                                    <span v-else>{{ contextMessage }}</span>
                                </button>
                            </span>
                        </label>
                        <div class="flex gap-2">
                            <input
                                v-model="projectUrl"
                                type="url"
                                placeholder="https://yoursite.com, figma.com/file/..., or github.com/..."
                                :disabled="!hasEnoughCredits"
                                class="flex-1 rounded-xl border border-gray-600 bg-gray-900 px-4 py-3 text-white placeholder-gray-500 transition-colors focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 disabled:opacity-50"
                            />
                            <button
                                type="button"
                                @click="showAIGuide = true"
                                class="flex items-center gap-2 rounded-xl border border-purple-500/30 bg-purple-500/10 px-4 py-3 font-semibold text-purple-400 hover:bg-purple-500/20 active:scale-95 transition-all"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Guide AI
                            </button>
                        </div>
                    </div>

                    <!-- GitHub Repository URL -->
                    <!-- Branching Questions -->
                    <div v-if="scanStep === 'branch' || scanStep === 'fill'" class="rounded-xl border border-gray-700 bg-gray-800/80 p-4">
                        <!-- Design Branch Question -->
                        <div v-if="contextType === 'design'">
                            <p class="mb-3 text-sm font-medium text-white">Does this design have a matching codebase?</p>
                            <div class="flex gap-4">
                                <button 
                                    @click="hasCodebase = true; scanStep = 'fill'"
                                    :class="['rounded-lg px-4 py-2 text-sm font-medium transition-colors', hasCodebase === true ? 'bg-purple-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600']"
                                >
                                    Yes, link Repo
                                </button>
                                <button 
                                    @click="hasCodebase = false; scanStep = 'fill'"
                                    :class="['rounded-lg px-4 py-2 text-sm font-medium transition-colors', hasCodebase === false ? 'bg-purple-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600']"
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
                                    :class="['rounded-lg px-4 py-2 text-sm font-medium transition-colors', isMarketplaceListing === true ? 'bg-purple-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600']"
                                >
                                    Yes (Requires Code)
                                </button>
                                <button 
                                    @click="isMarketplaceListing = false; scanStep = 'fill'"
                                    :class="['rounded-lg px-4 py-2 text-sm font-medium transition-colors', isMarketplaceListing === false ? 'bg-purple-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600']"
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
                                    :class="['rounded-lg px-4 py-2 text-sm font-medium transition-colors', hasLiveUrl === true ? 'bg-purple-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600']"
                                >
                                    Yes
                                </button>
                                <button 
                                    @click="hasLiveUrl = false; awaitingLiveUrl = false; scanStep = 'fill'"
                                    :class="['rounded-lg px-4 py-2 text-sm font-medium transition-colors', hasLiveUrl === false ? 'bg-purple-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600']"
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
                            <span class="text-brand-primary">Must include: Mobile View, Component Library, & Key Screens.</span>
                        </p>
                        <div ref="proofUppyContainer" class="rounded-xl border border-dashed border-gray-700 bg-gray-900/50 p-1"></div>
                        <div v-if="proofIds.length > 0 && proofIds.length < 5" class="mt-2 text-xs text-red-400">
                            Minimum 5 screenshots required ({{ proofIds.length }}/5).
                        </div>
                        <div v-else-if="proofIds.length >= 5" class="mt-2 text-xs text-emerald-400">
                            ✓ {{ proofIds.length }} screenshots uploaded
                        </div>
                    </div>

                    <!-- Live Production URL (GitHub -> Website Bridge) -->
                    <div v-if="contextType === 'repo' && awaitingLiveUrl && scanStep === 'fill'" class="mt-4">
                        <label class="mb-2 block text-sm font-medium text-emerald-400">
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
                            class="w-full rounded-xl border border-emerald-600 bg-gray-900 px-4 py-3 text-white placeholder-gray-500 transition-colors focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:opacity-50"
                        />
                        <p class="mt-2 text-xs text-gray-500">This completes the Repository ↔ Website handshake for full verification.</p>
                    </div>

                    <!-- GitHub Repository URL (Dynamic: Show if Repo Type, or Website needing market, or Design needing code) -->
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
                            class="w-full rounded-xl border border-gray-600 bg-gray-900 px-4 py-3 text-white placeholder-gray-500 transition-colors focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 disabled:opacity-50"
                        />
                    </div>

                    <!-- GitHub Access Token -->
                    <div v-if="contextType === 'repo' || (contextType === 'website' && isMarketplaceListing) || (contextType === 'design' && hasCodebase)">
                        <label class="mb-2 flex items-center justify-between text-sm font-medium text-gray-300">
                             GitHub Access Token
                             <button 
                                type="button"
                                @click="showAIGuide = true" 
                                class="flex items-center gap-1.5 text-xs text-brand-primary transition-colors hover:text-brand-secondary"
                             >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                </svg>
                                Guide
                             </button>
                        </label>
                        <input
                            v-model="githubToken"
                            type="password"
                            placeholder="ghp_xxxxxxxxxxxx (Optional for Private Repos)"
                            :disabled="!hasEnoughCredits"
                            class="w-full rounded-xl border border-gray-600 bg-gray-900 px-4 py-3 text-white placeholder-gray-500 transition-colors focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 disabled:opacity-50"
                        />
                        <p class="mt-2 text-xs text-gray-500">LUME uses this token for a one-time structural audit. We never store your master keys.</p>
                    </div>

                    <!-- Error Message -->
                    <div v-if="projectError" class="rounded-lg bg-red-500/10 p-3 text-sm text-red-400">
                        {{ projectError }}
                    </div>

                    <!-- What We'll Analyze -->
                    <div class="rounded-xl bg-gray-900/50 p-4">
                        <p class="mb-3 text-sm font-medium text-gray-300">What we'll analyze:</p>
                        <div class="grid gap-2 text-sm text-gray-400 sm:grid-cols-2">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Tech Stack Detection
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                DNS & Infrastructure
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Security Assessment
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Transfer Requirements
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                LUME Scalability Score
                            </div>
                            <div class="flex items-center gap-2" v-if="githubToken">
                                <svg class="h-4 w-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Using Access Token for Private Audit
                            </div>
                            <div class="flex items-center gap-2" v-else-if="contextType === 'design'">
                                <svg class="h-4 w-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Figma Visual Analysis
                            </div>
                            <div class="flex items-center gap-2" v-else>
                                <svg class="h-4 w-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                .env Variable Mapping
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="button"
                        @click="startForensicScan"
                        :disabled="!canSubmitProject || isSubmittingProject"
                        class="w-full rounded-xl bg-gradient-to-r from-purple-500 to-pink-500 py-4 font-semibold text-white shadow-lg transition-all hover:shadow-purple-500/25 hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span v-if="isSubmittingProject" class="flex items-center justify-center gap-2">
                            <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            Initializing Forensic Scan...
                        </span>
                        <span v-else class="flex items-center justify-center gap-2">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Start Forensic Scan (5 Credits)
                        </span>
                    </button>
                </div>
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
                :class="[
                    'fixed bottom-4 right-4 z-50 rounded-2xl p-4 text-white shadow-xl sm:bottom-8 sm:right-8',
                    activeTab === 'document' ? 'bg-brand-secondary' : 'bg-gradient-to-r from-purple-500 to-pink-500'
                ]"
            >
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/20">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-bold">{{ activeTab === 'document' ? 'Upload Complete!' : 'Scan Initiated!' }}</h4>
                        <p class="text-sm opacity-90">
                            {{ activeTab === 'document' ? 'AI analysis has started.' : 'Forensic analysis in progress.' }}
                        </p>
                    </div>
                </div>
            </div>
        </transition>
        <AIGuideModal 
            :show="showAIGuide" 
            :initial-error="projectError || (hasContextError ? 'The provided URL seems invalid or unrecognizable.' : '')"
            :initial-input="projectUrl"
            @close="showAIGuide = false" 
        />
    </div>
</template>

<style scoped>
.vault-uploader :deep(.uppy-Dashboard-inner) {
    @apply bg-gray-900 border-0;
}

.vault-uploader :deep(.uppy-Dashboard-AddFiles-title) {
    @apply text-gray-300;
}

.vault-uploader :deep(.uppy-Dashboard-browse) {
    @apply text-brand-primary hover:opacity-80;
}

.vault-uploader :deep(.uppy-Dashboard-dropFilesHereHint) {
    @apply text-gray-500;
}

.vault-uploader :deep(.uppy-Dashboard-AddFiles-info) {
    @apply text-gray-500;
}

/* Proof Gallery Grid Layout */
.vault-uploader :deep(.uppy-Dashboard-files) {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)) !important;
    gap: 0.75rem !important;
    padding: 0.75rem !important;
}

.vault-uploader :deep(.uppy-Dashboard-Item) {
    @apply rounded-lg overflow-hidden border border-gray-700;
    margin: 0 !important;
    padding: 0 !important;
}

.vault-uploader :deep(.uppy-Dashboard-Item-preview) {
    height: 100px !important;
    width: 100% !important;
}

.vault-uploader :deep(.uppy-Dashboard-Item-preview img) {
    object-fit: cover !important;
    width: 100% !important;
    height: 100% !important;
}

.vault-uploader :deep(.uppy-Dashboard-Item-fileInfoAndButtons) {
    display: none !important;
}

.vault-uploader :deep(.uppy-Dashboard-Item-action--remove) {
    position: absolute !important;
    top: 4px !important;
    right: 4px !important;
    background: rgba(0, 0, 0, 0.7) !important;
    border-radius: 50% !important;
    width: 24px !important;
    height: 24px !important;
}
</style>
