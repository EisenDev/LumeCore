<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps<{
    show: boolean;
    asset: any;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'confirm', customPrompt: string): void;
    (e: 'open-credit-modal'): void;
}>();

const page = usePage();
const customPrompt = ref('');
const isSubmitting = ref(false);
const showConsole = ref(false);

// Deep Forensic Console Steps
interface ForensicStep {
    id: string;
    label: string;
    status: 'pending' | 'running' | 'complete';
}

const forensicSteps = ref<ForensicStep[]>([
    { id: 'step_init', label: 'Initializing Deep Forensic Scan...', status: 'pending' },
    { id: 'step_crawl', label: 'Performing 10-Page Recursive Crawl', status: 'pending' },
    { id: 'step_security', label: 'Auditing Security Header Compliance', status: 'pending' },
    { id: 'step_dependencies', label: 'Mapping Component Inter-dependencies', status: 'pending' },
    { id: 'step_roadmap', label: 'Generating Technical Remediation Roadmap', status: 'pending' },
    { id: 'step_embeddings', label: 'Generating AI Embeddings for RAG...', status: 'pending' },
]);

const currentProgress = ref(0);
let echoChannel: any = null;

// Use credits directly from page props via wallet - same as Dashboard
const userCredits = computed(() => {
    const walletCredits = (page.props as any).wallet?.credits;
    if (walletCredits !== undefined && walletCredits !== null) {
        return Number(walletCredits);
    }
    const user = (page.props as any).auth?.user;
    return Number(user?.wallet?.credits ?? user?.credits ?? 0);
});
const hasEnoughCredits = computed(() => userCredits.value >= 20);

// Setup Reverb/Echo listener
function setupReverbListener() {
    if (typeof window !== 'undefined' && (window as any).Echo && props.asset?.user_id) {
        const userId = (page.props as any).auth?.user?.id;
        if (!userId) return;

        echoChannel = (window as any).Echo.private(`App.Models.User.${userId}`)
            .listen('.AuditProgressUpdated', (data: any) => {
                if (data.asset_id === props.asset?.id) {
                    currentProgress.value = data.progress;
                    
                    // Update step status based on the step identifier
                    const stepId = data.status;
                    const stepIndex = forensicSteps.value.findIndex(s => s.id === stepId);
                    
                    if (stepIndex !== -1) {
                        // Mark previous steps as complete
                        for (let i = 0; i < stepIndex; i++) {
                            forensicSteps.value[i].status = 'complete';
                        }
                        // Mark current step as running
                        forensicSteps.value[stepIndex].status = 'running';
                    }
                    
                    // If progress is 100, mark all as complete
                    if (data.progress >= 100) {
                        forensicSteps.value.forEach(s => s.status = 'complete');
                        // Auto-close after completion with delay
                        setTimeout(() => {
                            handleClose();
                        }, 1500);
                    }
                }
            });
    }
}

function cleanupReverbListener() {
    if (echoChannel) {
        echoChannel.stopListening('.AuditProgressUpdated');
        echoChannel = null;
    }
}

const handleConfirm = () => {
    if (!hasEnoughCredits.value) {
        handleBuyCredits();
        return;
    }
    isSubmitting.value = true;
    
    // Reset steps
    forensicSteps.value.forEach(s => s.status = 'pending');
    forensicSteps.value[0].status = 'running';
    currentProgress.value = 0;
    
    // Switch to console view
    showConsole.value = true;
    
    // Setup listener before emitting
    setupReverbListener();
    
    // Emit confirm to parent
    emit('confirm', customPrompt.value);
};

const handleClose = () => {
    cleanupReverbListener();
    customPrompt.value = '';
    isSubmitting.value = false;
    showConsole.value = false;
    forensicSteps.value.forEach(s => s.status = 'pending');
    currentProgress.value = 0;
    emit('close');
};

const handleBuyCredits = () => {
    emit('close');
    emit('open-credit-modal');
};

// Watch for modal close to cleanup
watch(() => props.show, (newValue) => {
    if (!newValue) {
        cleanupReverbListener();
    }
});

onUnmounted(() => {
    cleanupReverbListener();
});
</script>

<template>
    <Teleport to="body">
        <transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div 
                    class="absolute inset-0 bg-black/70 backdrop-blur-sm"
                    @click="!showConsole && handleClose()"
                ></div>

                <!-- Modal -->
                <div class="relative w-full max-w-md transform overflow-hidden rounded-2xl border border-indigo-500/30 bg-gray-900/95 shadow-[0_0_30px_rgba(99,102,241,0.2)] backdrop-blur-xl">
                    <!-- Header -->
                    <div class="border-b border-indigo-500/20 bg-indigo-500/5 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500/20">
                                <svg v-if="!showConsole" class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                <svg v-else class="h-5 w-5 text-indigo-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white">
                                    {{ showConsole ? 'Deep Forensic Console' : 'Deep Forensic Audit' }}
                                </h3>
                                <p class="text-sm text-indigo-300/70 font-mono">
                                    {{ showConsole ? '>>_ PROCESSING_DEEP_SCAN' : '>>_ SOVEREIGN_DEEP_SCAN' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- FORM VIEW -->
                    <template v-if="!showConsole">
                        <!-- Content -->
                        <div class="p-6 space-y-5">
                            <!-- Credit Info Card -->
                            <div class="rounded-xl border border-gray-700/50 bg-gray-800/50 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-400">Cost</span>
                                    <span class="text-lg font-bold text-indigo-400">20 Credits</span>
                                </div>
                                <div class="flex items-center justify-between border-t border-gray-700/50 pt-3">
                                    <span class="text-sm text-gray-400">Your Balance</span>
                                    <span 
                                        class="text-lg font-bold"
                                        :class="hasEnoughCredits ? 'text-emerald-400' : 'text-red-400'"
                                    >
                                        {{ userCredits.toFixed(2) }} Credits
                                    </span>
                                </div>
                                <div v-if="!hasEnoughCredits" class="flex items-center gap-2 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-400">
                                    <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    Insufficient credits for Deep Audit
                                </div>
                            </div>

                            <!-- Power Prompt Input -->
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 text-sm font-medium text-white">
                                    <svg class="h-4 w-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    Specific Instructions
                                    <span class="text-xs text-gray-500">(Optional)</span>
                                </label>
                                <textarea
                                    v-model="customPrompt"
                                    rows="3"
                                    placeholder="e.g., 'Check my Stripe integration security' or 'Audit my mobile navigation layout'"
                                    class="w-full rounded-xl border border-gray-700 bg-gray-800/50 px-4 py-3 text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                ></textarea>
                                <p class="text-xs text-gray-500">
                                    Tell the AI to focus on a specific area. This makes your Deep Audit feel like a custom consultation.
                                </p>
                            </div>

                            <!-- What You'll Get -->
                            <div class="rounded-xl border border-purple-500/20 bg-purple-500/5 p-4">
                                <h4 class="text-sm font-semibold text-purple-300 mb-2">What You'll Get:</h4>
                                <ul class="space-y-1.5 text-xs text-gray-400">
                                    <li class="flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 rounded-full bg-purple-400"></span>
                                        10-page deep crawl analysis
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 rounded-full bg-purple-400"></span>
                                        Security header audit
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 rounded-full bg-purple-400"></span>
                                        5-vector radar breakdown
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 rounded-full bg-purple-400"></span>
                                        AI-powered RAG insights
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex gap-3 border-t border-gray-800 bg-gray-900/50 px-6 py-4">
                            <button
                                @click="handleClose"
                                class="flex-1 rounded-xl border border-gray-700 bg-gray-800 px-4 py-3 text-sm font-medium text-gray-300 hover:bg-gray-700 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                @click="handleConfirm"
                                :disabled="isSubmitting"
                                class="flex-1 rounded-xl px-4 py-3 text-sm font-bold transition-all disabled:opacity-50"
                                :class="hasEnoughCredits 
                                    ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white hover:shadow-[0_0_20px_rgba(99,102,241,0.4)]' 
                                    : 'bg-gradient-to-r from-amber-500 to-orange-600 text-white hover:shadow-[0_0_20px_rgba(245,158,11,0.4)]'"
                            >
                                <span v-if="isSubmitting" class="flex items-center justify-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Processing...
                                </span>
                                <span v-else>
                                    {{ hasEnoughCredits ? 'Confirm & Deduct 20 Credits' : 'Insufficient Credits - Buy More' }}
                                </span>
                            </button>
                        </div>
                    </template>

                    <!-- CONSOLE VIEW -->
                    <template v-else>
                        <div class="p-6 space-y-4">
                            <!-- Progress Bar -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-indigo-300 font-mono">DEEP_SCAN_PROGRESS</span>
                                    <span class="text-emerald-400 font-bold">{{ currentProgress }}%</span>
                                </div>
                                <div class="h-2 rounded-full bg-gray-800 overflow-hidden">
                                    <div 
                                        class="h-full bg-gradient-to-r from-indigo-500 to-purple-500 transition-all duration-500 ease-out"
                                        :style="{ width: `${currentProgress}%` }"
                                    ></div>
                                </div>
                            </div>

                            <!-- Terminal Console -->
                            <div class="rounded-xl border border-gray-700/50 bg-black/50 p-4 font-mono text-sm space-y-2">
                                <div 
                                    v-for="step in forensicSteps"
                                    :key="step.id"
                                    class="flex items-center gap-3 transition-all duration-300"
                                    :class="{
                                        'text-gray-500': step.status === 'pending',
                                        'text-indigo-400': step.status === 'running',
                                        'text-emerald-400': step.status === 'complete'
                                    }"
                                >
                                    <!-- Status Icon -->
                                    <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center">
                                        <!-- Pending: Empty bracket -->
                                        <span v-if="step.status === 'pending'" class="text-gray-600">[ ]</span>
                                        <!-- Running: Spinner -->
                                        <svg v-else-if="step.status === 'running'" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <!-- Complete: Checkmark -->
                                        <span v-else class="text-emerald-400 font-bold">[✓]</span>
                                    </span>
                                    <!-- Label -->
                                    <span :class="{ 'animate-pulse': step.status === 'running' }">
                                        {{ step.label }}
                                    </span>
                                </div>
                            </div>

                            <!-- Status Message -->
                            <div class="text-center text-xs text-gray-500">
                                <p v-if="currentProgress < 100">
                                    This may take 2-3 minutes. Do not close this window.
                                </p>
                                <p v-else class="text-emerald-400 font-semibold">
                                    Deep Forensic Audit Complete! Closing...
                                </p>
                            </div>
                        </div>

                        <!-- Footer with Cancel Option -->
                        <div class="flex gap-3 border-t border-gray-800 bg-gray-900/50 px-6 py-4">
                            <button
                                @click="handleClose"
                                :disabled="currentProgress > 0 && currentProgress < 100"
                                class="w-full rounded-xl border border-gray-700 bg-gray-800 px-4 py-3 text-sm font-medium text-gray-300 hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ currentProgress >= 100 ? 'Close Console' : 'Processing...' }}
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
