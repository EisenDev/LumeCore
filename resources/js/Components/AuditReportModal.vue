<script setup lang="ts">
/**
 * AuditReportModal Component (Redesigned)
 * Professional AI audit results with Chart.js gauge and grade system
 */
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Chart as ChartJS,
    ArcElement,
    Tooltip,
    Legend,
} from 'chart.js';
import type { VaultAsset, AuditMetadata } from '@/types/vault';
import { marked } from 'marked';
import RepoSyncModal from '@/Components/RepoSyncModal.vue';
import ProjectAnalystModal from '@/Components/ProjectAnalystModal.vue';
import DeepInsightModal from '@/Components/DeepInsightModal.vue';
import DeepScanConfirmationModal from '@/Components/DeepScanConfirmationModal.vue';
import CreditPurchaseModal from '@/Components/CreditPurchaseModal.vue';

// Register Chart.js components
ChartJS.register(ArcElement, Tooltip, Legend);

// Marketplace state
const listingPrice = ref<number>(0);
const isPublishing = ref(false);
const showGauges = ref(false);
const showDeepDive = ref(false);

// Count-up animation state
const animatedScore = ref(0);
let scoreAnimationFrame: number | null = null;

// Function to animate score count-up
function animateScoreTo(targetScore: number) {
    if (scoreAnimationFrame) cancelAnimationFrame(scoreAnimationFrame);
    const duration = 1500; // 1.5 seconds
    const startTime = performance.now();
    const startScore = animatedScore.value;
    
    function tick(currentTime: number) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        // Ease-out cubic for smooth deceleration
        const easeOut = 1 - Math.pow(1 - progress, 3);
        animatedScore.value = Math.round(startScore + (targetScore - startScore) * easeOut);
        
        if (progress < 1) {
            scoreAnimationFrame = requestAnimationFrame(tick);
        }
    }
    scoreAnimationFrame = requestAnimationFrame(tick);
}

// PII Reason modal state
const showPiiReasonModal = ref(false);
const showRepoSyncModal = ref(false);
const showProjectAnalystModal = ref(false);

// Artificial loading delay state
const showLoading = ref(false);
const analysisProgress = ref(0);
let progressInterval: ReturnType<typeof setInterval> | null = null;

// Polling interval for fallback when WebSocket isn't working
let pollingInterval: ReturnType<typeof setInterval> | null = null;

// Scan Logs State
const scanLogs = ref<{time: string, message: string, type: string}[]>([]);
const terminalBody = ref<HTMLElement | null>(null);

function scrollToBottom() {
    // nextTick(() => {
    setTimeout(() => {
        if (terminalBody.value) {
            terminalBody.value.scrollTop = terminalBody.value.scrollHeight;
        }
    }, 50);
}

// Forensic Terminal Steps - Document Audits (Legacy/Fallback)
const documentForensicSteps = [
    { label: 'Initializing Document Analysis Engine', status: 'pending' as const },
    { label: 'Extractor: Parsing Document Text Content', status: 'pending' as const },
    { label: 'Scanner: Detecting PII & Sensitive Data', status: 'pending' as const },
    { label: 'Verifier: Authenticating Document Integrity', status: 'pending' as const },
    { label: 'Ledger: Securing Forensic Hash to Ledger', status: 'pending' as const },
];

const currentOperationText = ref('Initializing System...');

let stepInterval: ReturnType<typeof setInterval> | null = null;

interface Props {
    show: boolean;
    asset: VaultAsset | null;
    progress?: { step: string; progress: number } | null;
    isPublicView?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    progress: null,
    isPublicView: false,
});

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh'): void;
    (e: 'buy'): void;
}>();

/**
 * Poll for asset status updates as fallback when WebSocket isn't working
 */
function startPolling() {
    if (pollingInterval) clearInterval(pollingInterval);
    
    pollingInterval = setInterval(() => {
        // Only poll if we're showing and asset is still processing
        if (props.show && (props.asset?.status === 'processing' || props.asset?.status === 'pending')) {
            // Emit refresh event to parent to reload asset data
            emit('refresh');
        } else if (pollingInterval) {
            // Stop polling when not needed
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }, 3000); // Poll every 3 seconds
}

/**
 * Cleanup on unmount
 */
onUnmounted(() => {
    if (pollingInterval) clearInterval(pollingInterval);
    if (progressInterval) clearInterval(progressInterval);
    if (stepInterval) clearInterval(stepInterval);
    if (fallbackAnimationInterval) clearInterval(fallbackAnimationInterval);
});

/**
 * Computed: Get audit metadata if available
 */
const auditData = computed<AuditMetadata | null>(() => {
    return props.asset?.metadata ?? null;
});

const isAnalyzing = computed(() => {
    // Force stop immediately if status is verified or flagged (fixes "stuck at 95%" issue)
    const finalStatuses = ['verified', 'flagged', 'verified_private', 'action_required'];
    if (props.asset?.status && finalStatuses.includes(props.asset.status)) {
        return false;
    }
    
    // Show loading if:
    // 1. We're still in processing status
    // 2. OR we have active progress updates
    // 3. OR asset has no audit data yet
    const isProcessing = props.asset?.status === 'processing' || props.asset?.status === 'pending';
    const hasProgress = props.progress !== null;
    const noData = !!(props.asset && !auditData.value && props.asset.status !== 'payment_required');
    return isProcessing || hasProgress || noData;
});

// Fallback animation interval ID
let fallbackAnimationInterval: ReturnType<typeof setInterval> | null = null;

/**
 * Start fallback animation when no WebSocket updates are received
 */
function startFallbackAnimation() {
    // Clear any existing interval
    if (fallbackAnimationInterval) clearInterval(fallbackAnimationInterval);
    
    let currentStep = 0;
    
    fallbackAnimationInterval = setInterval(() => {
        // Animate progress smoothly
        if (analysisProgress.value < 95) {
            analysisProgress.value = Math.min(95, analysisProgress.value + 1);
        }
        
        // Cycle operation text for flair
        if (Math.random() > 0.8) {
             const ops = ['Decrypting...', 'Handshaking...', 'Verifying Signature...', 'Parsing Nodes...'];
             const op = ops[Math.floor(Math.random() * ops.length)];
             currentOperationText.value = op;
             
             // Add fake log
             scanLogs.value.push({ time: new Date().toLocaleTimeString(), message: op, type: 'info' });
             scrollToBottom();
        }

    }, 200);
}

// function updateForensicSteps(activeIndex: number) {
//      // Removed in favor of dynamic logs
// }

const isHighRisk = computed(() => {
    const niche = auditData.value?.metadata?.niche?.toLowerCase() || '';
    return niche.includes('phishing') || niche.includes('gambling') || niche.includes('scam');
});

const isRetrying = ref(false);

const retryAuditWithDeepWait = async () => {
    if (!props.asset?.id) return;
    
    isRetrying.value = true;
    try {
        await axios.post(`/api/vault/assets/${props.asset.id}/retry`, {
            deep_wait: true
        });
        // Close modal to let the user see the processing state on the dashboard
        emit('close');
    } catch (e) {
        console.error("Retry failed", e);
        alert("Failed to initiate re-scan. Please try again or check console.");
    } finally {
        isRetrying.value = false;
    }
};

/**
 * Watcher: Handle modal open to trigger minimum loading time and progress animation
 */
// System Error State
const isSystemError = ref(false);
const systemErrorMessage = ref('');
const showDeepInsight = ref(false);
const isGeneratingDeepAudit = ref(false);

const hasDeepInsight = computed(() => {
    return !!props.asset?.radar_data || !!props.asset?.full_audit_report;
});

// Deep Scan Confirmation Modal State
const showDeepConfirmation = ref(false);
const showCreditPurchaseModal = ref(false);

/**
 * Handle Deep Audit button click.
 * If deep insight exists, show the modal.
 * If not, open confirmation modal.
 */
const handleDeepAuditClick = () => {
    if (hasDeepInsight.value) {
        showDeepInsight.value = true;
        return;
    }
    
    // Open confirmation modal instead of immediately generating
    showDeepConfirmation.value = true;
};

/**
 * Confirm and generate Deep Audit with optional custom prompt.
 * NOTE: The DeepScanConfirmationModal stays open and shows the Deep Forensic Console overlay.
 * It will auto-close when the audit completes via Reverb events.
 */
const confirmDeepAudit = async (customPrompt: string) => {
    isGeneratingDeepAudit.value = true;
    // DO NOT close showDeepConfirmation - the modal stays open showing the console
    // showDeepConfirmation.value = false;
    
    try {
        const response = await axios.post('/api/vault/deep-audit', {
            asset_id: props.asset?.id,
            custom_prompt: customPrompt || null
        });
        
        if (response.data.success) {
            // Show loading state - the DeepScanConfirmationModal handles Reverb updates
            currentOperationText.value = 'Deep Audit Initiated...';
            
            // Reload auth and wallet data to sync credits with backend
            router.reload({ 
                only: ['auth', 'wallet']
            });
        } else {
            // Display error in UI instead of alert
            isSystemError.value = true;
            systemErrorMessage.value = response.data.error || 'Failed to initiate Deep Audit';
            // Close the confirmation modal on error
            showDeepConfirmation.value = false;
        }
    } catch (error: any) {
        console.error('Deep Audit Error:', error);
        const message = error.response?.data?.error || error.message || 'Failed to initiate Deep Audit';
        // Display error in UI instead of alert
        isSystemError.value = true;
        systemErrorMessage.value = message;
        // Close the confirmation modal on error
        showDeepConfirmation.value = false;
    } finally {
        isGeneratingDeepAudit.value = false;
    }
};

/**
 * Watcher: Handle modal open to trigger minimum loading time and progress animation
 */
watch(() => props.show, (newValue) => {
    if (newValue) {
        analysisProgress.value = 0;
        showGauges.value = false;
        // Reset Error State
        isSystemError.value = false;
        systemErrorMessage.value = '';
        
        // Reset and start count-up animation for score
        animatedScore.value = 0;
        
        setTimeout(() => {
            showGauges.value = true;
            // Start count-up animation to target score after gauges appear
            const targetScore = props.asset?.score ?? props.asset?.metadata?.confidence_score ?? 0;
            animateScoreTo(targetScore);
        }, 300);
        
        // Reset steps based on audit type
        scanLogs.value = [
             { time: new Date().toLocaleTimeString(), message: 'INITIALIZING LUME_SOVEREIGN_FORENSICS...', type: 'info' }
        ];
        
        currentOperationText.value = 'Initializing System...';
        
        // Start fallback animation (will be overridden if WebSocket works)
        if (props.asset?.status === 'processing' || props.asset?.status === 'pending') {
            startPolling();
        }
    }
});

/**
 * Watcher: Real-time score update when asset.score changes (e.g. after Reverb update)
 */
watch(() => props.asset?.score, (newScore, oldScore) => {
    if (props.show && newScore && newScore !== oldScore && newScore > 0) {
        // Re-trigger animation when score updates in real-time
        animateScoreTo(newScore);
    }
});

/**
 * Watcher: Fallback - also watch metadata.confidence_score
 */
watch(() => props.asset?.metadata?.confidence_score, (newScore, oldScore) => {
    if (props.show && newScore && newScore !== oldScore && newScore > 0 && !props.asset?.score) {
        // Fallback: animate from metadata if no direct score
        animateScoreTo(newScore);
    }
});

// LISTEN FOR SYSTEM FAILURE and PROGRESS UPDATES
watch(() => props.show, (newValue) => {
    if (newValue && props.asset?.id && props.asset?.user_id) {
             // Add connection log
             scanLogs.value.push({
                 time: new Date().toLocaleTimeString(), 
                 message: `ESTABLISHING UPLINK... (Channel: App.Models.User.${props.asset.user_id})`,
                 type: 'info'
             });

             // Use window.Echo if available
             const Echo = (window as any).Echo;
             if (Echo) {
                 // Subscribe to the user's private channel (matches backend broadcast)
                 const channelName = `App.Models.User.${props.asset.user_id}`;
                 
                 const channel = Echo.private(channelName);
                 
                 const handleEvent = (e: { asset_id: string; step: string; progress: number, status?: string }) => {
                     // Debug log to console
                     console.log('⚡ Event Received:', e);
                     
                     // Only process if this event is for the current asset
                     // Loose equality check for safety (string vs number)
                     if (e.asset_id == props.asset?.id) {
                         // Update the dynamic operation text
                         currentOperationText.value = e.step;
                         
                         // Add to logs
                         scanLogs.value.push({
                            time: new Date().toLocaleTimeString([], { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' }) + '.' + Math.floor(Math.random() * 999),
                            message: e.step,
                            type: 'info'
                         });
                         scrollToBottom();
                         
                         // If progress is 100%, trigger refresh
                         if (e.progress >= 100) {
                             emit('refresh');
                         }
                     }
                 };

                 // Listen to all potential event name aliases to be safe
                 channel
                     .listen('.AuditProgressUpdated', handleEvent)
                     .listen('AuditProgressUpdated', handleEvent)
                     .listen('App\\Events\\AuditProgressUpdated', handleEvent)
                     // Listen for failure events
                     .listen('.AuditFailed', (e: any) => {
                         if (e.asset_id == props.asset?.id) {
                             console.error("Audit Failed Event Received", e);
                             isSystemError.value = true;
                             systemErrorMessage.value = e.refund_message || 'System Error';
                             currentOperationText.value = 'CRITICAL_SYSTEM_FAILURE';
                             scanLogs.value.push({ time: new Date().toLocaleTimeString(), message: 'ERROR: ' + e.message, type: 'error' });
                         }
                     });
                     
                 // Log success
                 scanLogs.value.push({
                     time: new Date().toLocaleTimeString(), 
                     message: 'UPLINK ESTABLISHED. WAITING FOR DATA STREAM...',
                     type: 'success'
                 });
             } else {
                 scanLogs.value.push({ time: new Date().toLocaleTimeString(), message: 'ERROR: SIGNAL LOST (Echo Not Found)', type: 'error' });
             }


    } else {
        if (progressInterval) clearInterval(progressInterval);
        if (stepInterval) clearInterval(stepInterval);
        if (fallbackAnimationInterval) clearInterval(fallbackAnimationInterval);
        if (pollingInterval) clearInterval(pollingInterval);
        analysisProgress.value = 0;
        
        // Unsubscribe from user channel
        if (props.asset?.user_id) {
            const Echo = (window as any).Echo;
            if (Echo) Echo.leave(`App.Models.User.${props.asset.user_id}`);
        }
    }
});

/**
 * Watcher: Handle asset status changes (from polling or parent update)
 */
watch(() => props.asset, (newAsset) => {
    const doneStatuses = ['verified', 'verified_private', 'flagged', 'action_required'];
    if (doneStatuses.includes(newAsset?.status || '')) {
        // Audit complete - stop fallback
        if (fallbackAnimationInterval) {
            clearInterval(fallbackAnimationInterval);
            fallbackAnimationInterval = null;
        }
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
        analysisProgress.value = 100;
        // Mark all done
        // Mark all done
        currentOperationText.value = 'AUDIT_COMPLETE_SUCCESS';
    }
}, { deep: true });

/**
 * Watcher: Handle real-time progress updates from WebSocket
 */
watch(() => props.progress, (newProgress) => {
    if (newProgress) {
        // Stop fallback animation when real WebSocket progress received
        if (fallbackAnimationInterval) {
            clearInterval(fallbackAnimationInterval);
            fallbackAnimationInterval = null;
        }
        
        // Update progress bar
        analysisProgress.value = newProgress.progress;
        currentOperationText.value = newProgress.step.toUpperCase().replace(/\.\.\.$/, '');
        
        // Add to logs if progress updated
        if (!scanLogs.value.length || scanLogs.value[scanLogs.value.length - 1].message !== newProgress.step) {
             scanLogs.value.push({
                time: new Date().toLocaleTimeString([], { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' }) + '.' + Math.floor(Math.random() * 999),
                message: newProgress.step,
                type: 'info'
            });
            scrollToBottom();
        }

    } else {
        // If progress becomes null but status is complete (handled by asset watcher), do nothing
        // If progress null and not complete, could be connection lost, fallback might kick in on polling
    }
}, { immediate: true });

/**
 * Computed: Is payment required
 */
const isPaymentRequired = computed(() => props.asset?.status === 'payment_required');

/**
 * Computed: Is the asset verified or flagged
 */
const isVerified = computed(() => props.asset?.status === 'verified');
const isVerifiedPrivate = computed(() => props.asset?.status === 'verified_private');
const isFlagged = computed(() => props.asset?.status === 'flagged');
const isActionRequired = computed(() => props.asset?.status === 'action_required');

/**
 * Computed: Is OCR required (non-readable PDF)
 */
const isOcrRequired = computed(() => auditData.value?.ocr_required === true);

/**
 * Computed: Confidence score - Bind directly to $asset.score (not nested metadata)
 * Falls back to metadata.confidence_score for backwards compatibility
 */
const confidenceScore = computed(() => {
    // Primary: Use asset.score directly (now saved by VaultAuditService)
    const directScore = props.asset?.score;
    if (directScore !== undefined && directScore !== null && directScore > 0) {
        return Number(directScore);
    }
    // Fallback: metadata.confidence_score for backwards compatibility
    return Number(auditData.value?.confidence_score ?? auditData.value?.score ?? 0);
});

/**
 * Computed: Professionalism Grade (A, B, C, F)
 * Thresholds: 85-100:A (Excellent), 80-84:B (Good), 70-79:C (Fair), <70:F (Needs Improvement)
 */
const grade = computed(() => {
    const score = confidenceScore.value;
    if (score >= 85) return { letter: 'A', label: 'Excellent', color: 'text-brand-secondary', bg: 'bg-brand-secondary/20' };
    if (score >= 80) return { letter: 'B', label: 'Good', color: 'text-blue-400', bg: 'bg-blue-500/20' };
    if (score >= 70) return { letter: 'C', label: 'Fair', color: 'text-yellow-400', bg: 'bg-yellow-500/20' };
    return { letter: 'F', label: 'Needs Improvement', color: 'text-red-400', bg: 'bg-red-500/20' };
});

/**
 * Computed: Chart.js data for gauge
 */
const chartData = computed(() => {
    const score = auditData.value?.confidence_score ?? 0;
    const remaining = 100 - score;
    
    // Dynamic Color Logic for High Fidelity UI
    let gaugeColor = '#10B981'; // Cyber Emerald (Default)
    
    if (isFlagged.value || isHighRisk.value || score < 50) {
        gaugeColor = '#EF4444'; // Alert Red
    } else if (score < 75) {
        gaugeColor = '#F59E0B'; // Warning Amber
    }

    return {
        labels: ['Confidence', 'Gap'],
        datasets: [
            {
                data: [score, remaining],
                backgroundColor: [gaugeColor, 'rgba(255, 255, 255, 0.05)'],
                borderWidth: 0,
                cutout: '85%',
                borderRadius: 20,
            },
        ],
    };
});

/**
 * Computed: Chart.js options
 */
const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { enabled: false },
    },
}));

/**
 * Helper: Get document breakdown safely (type-safe accessor)
 */
const documentBreakdown = computed(() => {
    const breakdown = auditData.value?.breakdown;
    if (!breakdown) return null;
    // Check if it's a document breakdown (has formatting property)
    if ('formatting' in breakdown) {
        return breakdown as import('@/types/vault').AuditBreakdown;
    }
    return null;
});

/**
 * Helper: Get tech badge dot color based on technology name
 * Blue = Next.js/React, Emerald = Tailwind/CSS, Indigo = Database/Supabase, Amber = JavaScript/TypeScript
 */
function getTechBadgeDotColor(techName: string): string {
    const name = techName.toLowerCase();
    
    // Next.js/React = Blue
    if (name.includes('next') || name.includes('react') || name.includes('remix') || name.includes('gatsby')) {
        return 'bg-blue-500';
    }
    // Tailwind/CSS = Emerald
    if (name.includes('tailwind') || name.includes('css') || name.includes('sass') || name.includes('styled')) {
        return 'bg-emerald-500';
    }
    // Database/Supabase/Firebase = Indigo
    if (name.includes('postgres') || name.includes('mysql') || name.includes('mongo') || name.includes('supabase') || name.includes('firebase') || name.includes('prisma') || name.includes('sql') || name.includes('redis')) {
        return 'bg-indigo-500';
    }
    // JavaScript/TypeScript = Amber
    if (name.includes('javascript') || name.includes('typescript') || name.includes('node') || name.includes('express') || name.includes('deno')) {
        return 'bg-amber-500';
    }
    // Vue/Laravel/PHP = Rose
    if (name.includes('vue') || name.includes('nuxt') || name.includes('laravel') || name.includes('php') || name.includes('inertia')) {
        return 'bg-rose-500';
    }
    // Default = Slate
    return 'bg-slate-400';
}

/**
 * Computed: Business overview from audit data
 */
const businessOverview = computed(() => {
    return (auditData.value as any)?.business_overview ?? 
           props.asset?.metadata?.business_overview ?? 
           null;
});

/**
 * Computed: Is this a project/technical audit
 */
const isProjectAudit = computed(() => {
    const auditType = props.asset?.metadata?.audit_type;
    // Also check if radar_data exists (new Deep Audit format)
    const hasRadarData = !!props.asset?.radar_data;
    // If radar_data exists, it's a project audit regardless of audit_type
    if (hasRadarData) return true;
    return auditType === 'project' || auditType === 'design';
});

/**
 * Computed: Is GitHub repo missing for a project audit?
 * Used to show yellow alert for unverified ownership.
 */
const isRepoMissing = computed(() => {
    return isProjectAudit.value && !props.asset?.github_repo_url;
});

/**
 * Helper: Get project breakdown safely (type-safe accessor)
 * Now uses tech_score, security_score, scalability_score
 */
const projectBreakdown = computed(() => {
    // First, try to get breakdown from metadata
    const breakdown = auditData.value?.breakdown;
    
    // Also check radar_data from asset (new format)
    const radarData = props.asset?.radar_data;
    
    // New format: code_resilience, security_perimeter, etc.
    if (radarData && typeof radarData === 'object') {
        const rd = typeof radarData === 'string' ? JSON.parse(radarData) : radarData;
        return {
            tech_score: rd.code_resilience ?? rd.tech_score ?? 0,
            security_score: rd.security_perimeter ?? rd.security_score ?? 0,
            scalability_score: rd.deployment_maturity ?? rd.scalability_score ?? 0,
        };
    }
    
    if (!breakdown) return null;
    
    // Legacy format check
    if ('tech_score' in breakdown || 'tech_quality' in breakdown) {
        return {
            tech_score: (breakdown as any).tech_score ?? (breakdown as any).tech_quality ?? 0,
            security_score: (breakdown as any).security_score ?? (breakdown as any).security ?? 0,
            scalability_score: (breakdown as any).scalability_score ?? (breakdown as any).scalability ?? 0,
        };
    }
    
    // New format in breakdown
    if ('code_resilience' in breakdown) {
        return {
            tech_score: (breakdown as any).code_resilience ?? 0,
            security_score: (breakdown as any).security_perimeter ?? 0,
            scalability_score: (breakdown as any).deployment_maturity ?? 0,
        };
    }
    
    return null;
});

const forensicReport = computed(() => auditData.value?.detailed_forensic_report || auditData.value as any);

// Computed for Remediation Cost to ensure it's a number
const remediationCost = computed(() => {
    return Number(forensicReport.value?.estimated_remediation_cost) || 0;
});



const forensicMarkdown = computed(() => {
    if (!forensicReport.value) return '';
    
    let md = '';

    // 1. CRITICAL WARNINGS
    if (isFlagged.value) {
         const flags = auditData.value?.warning_flags || [];
         const reason = auditData.value?.flag_reason || 'Critical issues detected during execution.';
         
         md += `### 🚨 CRITICAL AUDIT FAILURE\n`;
         md += `**Reason:** ${reason}\n\n`;
         if (flags.length) {
             md += `**Violations Detected:**\n`;
             md += flags.map(f => `- ${f}`).join('\n') + '\n\n';
         }
         md += `---\n\n`; 
    }

    // 2. EXECUTIVE SUMMARY
    if (forensicReport.value.summary) {
        md += `### Executive Summary\n${forensicReport.value.summary}\n\n`;
    }

    // 3. TECHNICAL FOOTPRINT (New Section)
    if (forensicReport.value.tech_assessment) {
        const tech = forensicReport.value.tech_assessment;
        md += `### 🏗️ Technical Footprint\n`;
        md += `**Architecture:** ${tech.architecture || 'Unknown'}\n`;
        md += `**Quality Score:** ${tech.quality_score}/100\n\n`;
        
        if (tech.stack && Array.isArray(tech.stack)) {
            md += `**Tech Stack:**\n`;
            // Render as inline code blocks, handling both string and object formats
            md += tech.stack.map((t: any) => `\`${typeof t === 'string' ? t : t.name}\``).join(' ') + '\n\n';
        }
    }

    // 4. HANDSHAKE VERIFICATION (New Structured Data)
    const proof = forensicReport.value.handshake_proof;
    if (proof) {
        md += `### 🤝 Handshake Verification\n`;
        
        if (typeof proof === 'string') {
             md += `${proof}\n\n`;
        } else {
             // TS knows this is HandshakeProof object now
             const p = proof as import('@/types/vault').HandshakeProof;
             const icon = p.status === 'Verified' ? '✅' : (p.status === 'Failed' ? '❌' : '⚠️');
             md += `**Status:** ${icon} ${p.status}\n`;
             md += `**Method:** ${p.method}\n`;
             md += `**Details:** ${p.details}\n\n`;
        }
    }

    // 5. SECURITY ASSESSMENT (New Structured Data)
    if (forensicReport.value.security_assessment) {
        const sec = forensicReport.value.security_assessment;
        md += `### 🛡️ Security Assessment\n`;
        md += `**Risk Level:** ${sec.risk_level.toUpperCase()}\n`;
        md += `**Score:** ${sec.score}/100\n\n`;
        
        if (sec.vulnerabilities && sec.vulnerabilities.length) {
            md += `**Detected Vulnerabilities:**\n`;
            md += sec.vulnerabilities.map((v: string) => `- ${v}`).join('\n') + '\n\n';
        } else {
            md += `*No critical vulnerabilities detected.*\n\n`;
        }
    }

    // 6. Deep Insights (Legacy & New)
    if (forensicReport.value.deep_insights?.length) {
        md += `### 🔍 Deep Forensic Insights\n`;
        md += forensicReport.value.deep_insights.map((i: string) => `- ${i}`).join('\n') + '\n\n';
    } else if (auditData.value?.insights?.length) {
         md += `### 🔍 AI Observations\n`;
         md += auditData.value.insights.map((i: string) => `- ${i}`).join('\n') + '\n\n';
    }

    return md;
});

/**
 * Computed: Handshake status for projects (URL + Code sync)
 */
const handshakeStatus = computed(() => {
    const hasWebsite = !!props.asset?.metadata?.website_url;
    const hasRepo = !!props.asset?.metadata?.github_repo_url;
    
    if (hasWebsite && hasRepo) {
        return { status: 'synced', label: 'URL + Code Synced', color: 'text-brand-secondary' };
    } else if (hasRepo) {
        return { status: 'partial', label: 'Code Only', color: 'text-yellow-400' };
    } else if (hasWebsite) {
        return { status: 'partial', label: 'URL Only', color: 'text-yellow-400', helpText: "We found the live site, but couldn't verify the code match. Link your GitHub repo to improve your score and prove ownership." };
    }
    return { status: 'none', label: 'No Link', color: 'text-red-400' };
});

/**
 * Computed: Breakdown chart data (if available)
 */
const breakdownChartData = computed(() => {
    const db = documentBreakdown.value;
    return {
        labels: ['Formatting', 'Content Quality', 'Industry Relevance'],
        datasets: [
            {
                label: 'Score',
                data: [
                    db?.formatting ?? 0,
                    db?.content_quality ?? 0,
                    db?.industry_relevance ?? 0,
                ],
                backgroundColor: 'rgba(0, 220, 130, 0.2)',
                borderColor: '#00dc82',
                pointBackgroundColor: '#00dc82',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#00dc82',
            },
        ],
    };
});

/**
 * Computed: Payout amount based on status
 */
const payoutAmount = computed(() => {
    if (isVerified.value) return 10.00;
    if (isFlagged.value) return 0.01;
    return 0;
});

/**
 * Computed: Recommendations list - use new array format or parse flag_reason for backward compatibility
 */
const recommendations = computed(() => {
    // Use new recommendations array if available
    if (auditData.value?.recommendations && auditData.value.recommendations.length > 0) {
        return auditData.value.recommendations;
    }
    // Fallback: parse flag_reason
    if (!auditData.value?.flag_reason) return [];
    return auditData.value.flag_reason
        .split(/[,;.]/)
        .map(s => s.trim())
        .filter(s => s.length > 0);
});

/**
 * Computed: Is marketplace eligible (from AI audit)
 */
const isMarketplaceEligible = computed(() => {
    return auditData.value?.is_marketplace_eligible ?? false;
});

/**
 * Computed: Privacy warning message
 */
const privacyWarning = computed(() => {
    return auditData.value?.privacy_warning ?? null;
});

/**
 * Computed: PII Reason (detailed explanation for why document can't be sold)
 */
const piiReason = computed(() => {
    return auditData.value?.pii_reason ?? auditData.value?.privacy_warning ?? 'This document contains personal identifiable information (PII) that could compromise privacy if sold publicly.';
});

/**
 * Computed: Can list on marketplace (verified + score > 80 + AI says eligible)
 */
const canListOnMarketplace = computed(() => {
    return props.asset?.status === 'verified' && 
           confidenceScore.value > 80 && 
           isMarketplaceEligible.value;
});

/**
 * Computed: Is currently listed
 */
const isListed = computed(() => props.asset?.is_for_sale ?? false);

/**
 * Watch: Set default listing price when asset changes
 */
watch(() => props.asset, (newAsset) => {
    if (newAsset) {
        listingPrice.value = newAsset.price ?? newAsset.suggested_value ?? 10.00;
    }
}, { immediate: true });

/**
 * Computd: Get Echo Host for display
 */
const echoHost = computed(() => {
    return import.meta.env.VITE_REVERB_HOST || 'localhost';
});

/**
 * Publish or unpublish asset to marketplace
 */
const showSuccessToast = ref(false);

/**
 * Publish or unpublish asset to marketplace
 */
function toggleMarketplaceListing(): void {
    if (!props.asset) return;
    
    isPublishing.value = true;
    
    if (isListed.value) {
        // Unpublish using toggle
        router.post(route('marketplace.toggle', { asset: props.asset.id }), {
            price: listingPrice.value,
            is_for_sale: false,
        }, {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['assets'] }),
            onError: (errors) => {
                console.error('Marketplace toggle error:', errors);
                alert(errors.asset || 'Failed to update marketplace listing');
            },
            onFinish: () => isPublishing.value = false,
        });
    } else {
        // Publish using new endpoint
        router.post(route('marketplace.publish', { asset: props.asset.id }), {
            price: listingPrice.value,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['assets'] });
                showSuccessToast.value = true;
                setTimeout(() => showSuccessToast.value = false, 4000);
            },
            onError: (errors) => {
                console.error('Marketplace publish error:', errors);
                alert(errors.asset || 'Failed to publish to marketplace');
            },
            onFinish: () => isPublishing.value = false,
        });
    }
}

/**
 * Render Markdown Helper
 */
function renderMarkdown(text: string): string {
    return marked(text || '') as string;
}

/**
 * Close the modal
 */
function closeModal(): void {
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <transition
            enter-active-class="ease-out duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="props.show"
                class="fixed inset-0 z-50 overflow-y-auto"
                @click.self="closeModal"
            >
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-xl" @click="closeModal" />

                <!-- Modal -->
                <div class="flex min-h-full items-center justify-center p-4">
                    <transition
                        enter-active-class="ease-out duration-300"
                        enter-from-class="opacity-0 scale-95 translate-y-4"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="ease-in duration-200"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="props.show"
                            class="relative w-full max-w-2xl transform overflow-hidden rounded-2xl border border-slate-700/50 bg-[#0B0F19]/90 text-white shadow-2xl ring-1 ring-white/5 backdrop-blur-md"
                        >

                        <!-- Top Decor Line -->
                        <div 
                            class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r"
                            :class="[
                                isHighRisk ? 'from-red-600 via-red-500 to-red-600 animate-pulse' :
                                isVerified ? 'from-emerald-500 via-teal-400 to-emerald-500 shadow-[0_0_15px_rgba(16,185,129,0.5)]' : 
                                'from-indigo-500 via-purple-500 to-indigo-500'
                            ]"
                        ></div>

                        <!-- Modal Content Wrapper -->
                        <div class="flex h-full flex-col">
                            
                            <!-- Header Section -->
                            <div 
                                class="border-b px-6 py-5 transition-colors duration-500"
                                :class="isHighRisk ? 'bg-red-950/30 border-red-900/50' : 'bg-white/5 border-white/5'"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-3">
                                            <h3 class="text-xl font-bold text-white tracking-tight">
                                                {{ isPublicView ? 'Public Audit Report' : (props.asset?.file_name || 'Asset Audit') }}
                                            </h3>
                                            
                                            <!-- High Risk Badge -->
                                            <span v-if="isHighRisk" class="animate-pulse rounded border border-red-500/50 bg-red-500/10 px-2 py-0.5 text-xs font-bold text-red-500 shadow-[0_0_10px_rgba(239,68,68,0.4)]">
                                                HIGH RISK DETECTED
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-400 font-mono">
                                            // LUME_SOVEREIGN_FORENSICS
                                        </p>
                                    </div>
                                    
                                    <!-- Close Button -->
                                    <button 
                                        @click="closeModal"
                                        class="rounded-lg p-2 text-gray-400 hover:bg-white/10 hover:text-white transition-colors"
                                    >
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                           <!-- Body with Risk Alert (Only if High Risk) -->
                           <div v-if="isHighRisk" class="bg-red-950/40 border-b border-red-900/30 px-6 py-3 backdrop-blur-sm">
                                <div class="flex items-center gap-3">
                                     <svg class="w-5 h-5 text-red-500 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                     <p class="text-sm font-medium text-red-200">
                                        This asset is classified as <strong class="text-white">{{ auditData?.metadata?.niche }}</strong>. 
                                        LUME advises extreme caution.
                                     </p>
                                </div>
                           </div>

                            <!-- Scrollable Body -->
                            <div class="flex-1 overflow-y-auto bg-[#0B0F19] px-6"> <!-- Force dark background with horizontal padding -->
                                <!-- State (Scanning / Forensic Terminal) -->
                                <div v-if="isAnalyzing" class="flex flex-col items-center justify-center p-10 h-full">
                                    
                                    <!-- LUME Eye Scanning Animation (New) -->
                                    <div class="relative mb-8">
                                        <!-- SYSTEM ERROR STATE -->
                                        <div v-if="isSystemError" class="relative flex items-center justify-center">
                                             <div class="h-24 w-24 rounded-full bg-red-500/10 flex items-center justify-center animate-pulse">
                                                 <svg class="w-12 h-12 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                 </svg>
                                             </div>
                                        </div>

                                        <!-- ACTIVE SCANNING STATE -->
                                        <div v-else class="relative">
                                            <!-- Outer Ping -->
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <div class="h-24 w-24 animate-[ping_2s_cubic-bezier(0,0,0.2,1)_infinite] rounded-full bg-gradient-to-r from-indigo-500/20 to-purple-600/20"></div>
                                            </div>
                                            <!-- Middle Glow -->
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <div class="h-16 w-16 animate-pulse rounded-full bg-indigo-500/10 blur-xl"></div>
                                            </div>
                                            <!-- Inner Eye -->
                                            <div class="relative flex h-20 w-20 items-center justify-center rounded-full border border-indigo-500/30 bg-black/50 shadow-[0_0_30px_rgba(99,102,241,0.3)] backdrop-blur-sm">
                                                <div class="h-10 w-10 animate-spin rounded-full border-2 border-transparent border-t-indigo-400 border-r-purple-500"></div>
                                                <div class="absolute h-4 w-4 rounded-full bg-gradient-to-tr from-indigo-400 to-purple-400 shadow-[0_0_10px_rgba(167,139,250,0.8)]"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Terminal Window -->
                                    <div class="w-full max-w-lg overflow-hidden rounded-lg bg-black/90 border border-gray-800 shadow-2xl font-mono text-xs leading-relaxed backdrop-blur-md flex flex-col h-[300px]">
                                        <!-- Terminal Header -->
                                        <div class="flex items-center justify-between border-b border-gray-800 bg-gray-900/50 px-4 py-2 flex-shrink-0">
                                            <div class="flex gap-1.5">
                                                <div class="h-2.5 w-2.5 rounded-full bg-red-500/50"></div>
                                                <div class="h-2.5 w-2.5 rounded-full bg-yellow-500/50"></div>
                                                <div class="h-2.5 w-2.5 rounded-full bg-green-500/50"></div>
                                            </div>
                                            <div class="text-[10px] text-gray-500 tracking-wider">LUME_SOVEREIGN_AUDIT.exe</div>
                                        </div>

                                        <!-- Terminal Content -->
                                        <div class="p-4 flex-1 relative overflow-hidden flex flex-col">
                                            <!-- Scanline Overlay -->
                                            <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(18,16,16,0)_50%,rgba(0,0,0,0.25)_50%),linear-gradient(90deg,rgba(255,0,0,0.06),rgba(0,255,0,0.02),rgba(0,0,255,0.06))] bg-[length:100%_2px,3px_100%] bg-repeat z-10 opacity-20"></div>

                                            <div ref="terminalBody" class="flex-1 overflow-y-auto space-y-2 pr-2 custom-scrollbar scroll-smooth relative z-20">
                                                <div v-for="(log, index) in scanLogs" :key="index" class="flex gap-3 group animate-in slide-in-from-left-2 duration-300">
                                                    <span class="text-slate-600 flex-shrink-0 select-none">[{{ log.time }}]</span>
                                                    <span class="text-slate-300 break-words font-light">
                                                        <span v-if="log.message.includes('LOG:') || log.message.includes('ANALYZING:')" class="text-brand-primary font-bold mr-1">></span>
                                                        <span :class="{
                                                            'text-indigo-300 font-semibold': log.message.includes('Initializing') || log.message.includes('Complete'),
                                                            'text-emerald-400': log.message.includes('successfully') || log.message.includes('complete'),
                                                            'text-amber-400': log.message.includes('Resolving') || log.message.includes('Detecting'),
                                                            'text-cyan-300': log.message.includes('AI') || log.message.includes('Thinking'),
                                                            'text-rose-400': log.message.includes('failed') || log.message.includes('Error')
                                                        }">
                                                            {{ log.message }}
                                                        </span>
                                                    </span>
                                                </div>
                                                
                                                <!-- Typing Indicator (Phantom Element) -->
                                                <div class="flex gap-3 animate-pulse opacity-50">
                                                    <span class="text-slate-700">[..:..:..]</span>
                                                    <span class="text-slate-500">_</span>
                                                </div>
                                            </div>

                                            <!-- Live Output Log (Small footer) -->
                                            <div class="mt-3 pt-3 border-t border-dashed border-gray-800 text-[10px] text-gray-500 font-mono opacity-80 flex-shrink-0">
                                                <p>>_ SYSTEM_STATUS: ONLINE</p>
                                                <p>>_ UPLINK: SECURE (wss://{{ echoHost }})</p>
                                                <p class="text-brand-primary/80 animate-pulse">>_ CURRENT_OP: {{ currentOperationText }}</p>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <!-- Payment Required State -->
                                <div v-else-if="isPaymentRequired" class="flex flex-col items-center justify-center py-12 text-center">
                                    <div class="mb-4 rounded-full bg-red-500/10 p-4 ring-1 ring-red-500/20 shadow-[0_0_20px_rgba(239,68,68,0.2)]">
                                        <svg class="h-12 w-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <h3 class="mb-2 text-xl font-bold text-white">Insufficient Credits</h3>
                                    <p class="mb-6 max-w-sm text-gray-400">
                                        This audit requires more credits than you currently have available. Please top up your balance to continue.
                                    </p>
                                    <div class="flex gap-4">
                                        <button
                                            @click="closeModal"
                                            class="rounded-xl bg-gray-700 px-6 py-2.5 font-semibold text-white shadow-lg transition-all hover:bg-gray-600"
                                        >
                                            Close
                                        </button>
                                        <button
                                            @click="emit('buy')"
                                            class="rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-6 py-2.5 font-semibold text-white shadow-lg transition-all hover:shadow-brand-primary/25 hover:brightness-110"
                                        >
                                            Buy Credits
                                        </button>
                                    </div>
                                </div>

                                <!-- Results -->
                                <div v-else-if="auditData" class="space-y-6">
                                    <!-- Top Section: Score Gauge + Grade -->
                                    <div class="flex items-center gap-6">
                                        <!-- Gauge Chart -->
                                        <div class="relative h-36 w-36 flex-shrink-0">
                                            <Doughnut :data="chartData" :options="chartOptions" />
                                            <div class="absolute inset-0 flex flex-col items-center justify-center pt-6">
                                                <span 
                                                    class="text-4xl font-black tracking-tight transition-all duration-300"
                                                    :class="isFlagged ? 'text-red-500 drop-shadow-[0_0_15px_rgba(239,68,68,0.6)]' : 'text-emerald-400 drop-shadow-[0_0_15px_rgba(52,211,153,0.6)]'"
                                                >{{ animatedScore }}</span>
                                                <span class="text-[10px] font-bold tracking-widest text-gray-500 uppercase">Trust Score</span>
                                            </div>
                                        </div>

                                        <!-- Grade & Status -->
                                        <div class="flex-1 space-y-3">
                                            <div class="flex items-center gap-4">
                                                <div 
                                                    :class="['flex h-16 w-16 items-center justify-center rounded-xl text-3xl font-black shadow-lg ring-1 ring-white/5', grade.bg, grade.color]"
                                                    class="backdrop-blur-sm"
                                                >
                                                    {{ grade.letter }}
                                                </div>
                                                <div>
                                                    <p class="text-xs uppercase tracking-wider text-gray-500 font-bold">Professionalism Grade</p>
                                                    <p class="text-xl font-bold text-white">
                                                        {{ grade.letter === 'A' ? 'Excellent' : grade.letter === 'B' ? 'Good' : grade.letter === 'C' ? 'Fair' : 'Needs Improvement' }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2">

                                                <span
                                                    :class="[
                                                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-bold shadow-sm ring-1 ring-inset',
                                                        isVerified ? 'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20' : 
                                                        isVerifiedPrivate ? 'bg-sky-500/10 text-sky-400 ring-sky-500/20' :
                                                        isActionRequired ? 'bg-amber-500/10 text-amber-400 ring-amber-500/20' :
                                                        'bg-red-500/10 text-red-500 ring-red-500/30'
                                                    ]"
                                                >
                                                    <span :class="[
                                                        'h-1.5 w-1.5 rounded-full shadow-[0_0_5px_currentColor]', 
                                                        isVerified ? 'bg-emerald-400' : 
                                                        isVerifiedPrivate ? 'bg-sky-400' :
                                                        isActionRequired ? 'bg-amber-400' :
                                                        'bg-red-500'
                                                    ]" />
                                                    {{ isVerified ? 'VERIFIED' : isVerifiedPrivate ? 'VERIFIED (PRIVATE)' : isActionRequired ? 'ACTION REQUIRED' : 'FLAGGED' }}
                                                </span>
                                                <span class="rounded-full bg-gray-800 px-3 py-1 text-sm text-gray-300 ring-1 ring-white/10">
                                                    {{ auditData.document_type }}
                                                </span>

                                                <!-- Niche Badge (LUME Niche Guard) -->
                                                <span v-if="auditData.metadata?.niche" 
                                                      :class="[
                                                          'inline-flex items-center rounded-full px-3 py-1 text-sm font-bold shadow-sm transition-all duration-300',
                                                          isHighRisk 
                                                              ? 'bg-red-500 text-white animate-pulse ring-2 ring-red-400 ring-offset-2 ring-offset-black shadow-[0_0_15px_rgba(239,68,68,0.6)]' 
                                                              : 'bg-slate-800 text-gray-200 ring-1 ring-white/10 hover:bg-slate-700'
                                                      ]">
                                                    Category: {{ auditData.metadata.niche }}
                                                </span>

                                                <!-- Database Badge -->
                                                <span v-if="forensicReport?.tech_assessment?.database_detected && forensicReport.tech_assessment.database_detected !== 'Unknown'" 
                                                      class="inline-flex items-center rounded-full bg-blue-900/40 px-3 py-1 text-sm font-medium text-blue-200 shadow-sm ring-1 ring-blue-500/30 backdrop-blur-sm">
                                                    {{ forensicReport.tech_assessment.database_detected }}
                                                </span>
                                            </div>

                                            <!-- RE-SCAN ACTION (Visual Persistence) -->
                                            <!-- Valid only if flagged/action_required AND project audit -->
                                            <div v-if="(isFlagged || isActionRequired) && isProjectAudit" class="mt-2">
                                                <button 
                                                    @click="retryAuditWithDeepWait" 
                                                    :disabled="isRetrying"
                                                    class="flex items-center gap-2 text-xs text-brand-primary hover:text-brand-secondary transition-colors disabled:opacity-50"
                                                >
                                                    <svg v-if="isRetrying" class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                    <svg v-else class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                                    {{ isRetrying ? 'Initiating Deep Scan...' : 'Re-Scan with Deep Wait (Fix 404s)' }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Style A: Technical (for project/design audits) -->
                                    <div v-if="isProjectAudit && projectBreakdown" class="space-y-4">
                                        <!-- Yellow Alert: Unverified Ownership (No Repo Linked) -->
                                        <div 
                                            v-if="isRepoMissing" 
                                            class="flex items-start gap-3 rounded-lg border border-yellow-500/40 bg-yellow-500/10 p-4 backdrop-blur-sm"
                                        >
                                            <svg class="h-5 w-5 flex-shrink-0 text-yellow-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            <div class="flex-1">
                                                <p class="text-sm font-semibold text-yellow-300">⚠️ Ownership Unverified</p>
                                                <p class="text-xs text-yellow-200/70 mt-1">
                                                    Link your GitHub repository to unlock Marketplace Listing and full Technical Audit.
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Handshake Status -->
                                        <div class="flex items-center justify-between rounded-lg border border-white/5 bg-gray-800/30 p-3 backdrop-blur-sm">
                                            <span class="text-sm text-gray-400 font-mono">HANDSHAKE_PROTOCOL</span>
                                            <span class="flex items-center gap-2 text-sm font-bold tracking-wide uppercase" :class="handshakeStatus.color">
                                                <svg v-if="handshakeStatus.status === 'synced'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                                {{ handshakeStatus.label }}
                                            </span>
                                        </div>

                                        <!-- Technical Gauges (Animated Rings) -->
                                        <div class="grid grid-cols-3 gap-8">
                                            <!-- Tech Score -->
                                            <div class="relative flex flex-col items-center justify-center rounded-lg bg-gray-800/20 border border-white/5 p-4 backdrop-blur-sm group hover:bg-gray-800/40 transition-colors">
                                                <div class="relative h-20 w-20">
                                                    <svg class="h-full w-full -rotate-90 transform" viewBox="0 0 100 100">
                                                        <circle class="text-gray-800" stroke-width="6" stroke="currentColor" fill="transparent" r="44" cx="50" cy="50" />
                                                        <circle 
                                                            class="transition-all duration-1000 ease-out drop-shadow-[0_0_5px_currentColor]" 
                                                            :class="projectBreakdown.tech_score >= 70 ? 'text-blue-500' : 'text-yellow-500'"
                                                            stroke-width="6" 
                                                            :stroke-dasharray="276" 
                                                            :stroke-dashoffset="showGauges ? 276 - (276 * projectBreakdown.tech_score) / 100 : 276" 
                                                            stroke-linecap="round" stroke="currentColor" fill="transparent" r="44" cx="50" cy="50" 
                                                        />
                                                    </svg>
                                                    <div class="absolute inset-0 flex items-center justify-center">
                                                        <span class="text-lg font-bold text-white">{{ projectBreakdown.tech_score }}</span>
                                                    </div>
                                                </div>
                                                <p class="mt-2 text-[10px] font-bold uppercase tracking-widest text-gray-500 group-hover:text-gray-300 transition-colors">Performance</p>
                                            </div>

                                            <!-- Security Score -->
                                            <div class="relative flex flex-col items-center justify-center rounded-lg bg-gray-800/20 border border-white/5 p-4 backdrop-blur-sm group hover:bg-gray-800/40 transition-colors">
                                                <div class="relative h-20 w-20">
                                                    <svg class="h-full w-full -rotate-90 transform" viewBox="0 0 100 100">
                                                        <circle class="text-gray-800" stroke-width="6" stroke="currentColor" fill="transparent" r="44" cx="50" cy="50" />
                                                        <circle 
                                                            class="transition-all duration-1000 ease-out drop-shadow-[0_0_5px_currentColor]" 
                                                            :class="isFlagged ? 'text-red-500' : (projectBreakdown.security_score >= 80 ? 'text-emerald-500' : 'text-amber-500')"
                                                            stroke-width="6" 
                                                            :stroke-dasharray="276" 
                                                            :stroke-dashoffset="showGauges ? 276 - (276 * projectBreakdown.security_score) / 100 : 276" 
                                                            stroke-linecap="round" stroke="currentColor" fill="transparent" r="44" cx="50" cy="50" 
                                                        />
                                                    </svg>
                                                    <div class="absolute inset-0 flex items-center justify-center">
                                                        <span class="text-lg font-bold text-white">{{ projectBreakdown.security_score }}</span>
                                                    </div>
                                                </div>
                                                <p class="mt-2 text-[10px] font-bold uppercase tracking-widest text-gray-500 group-hover:text-gray-300 transition-colors">Security</p>
                                            </div>

                                            <!-- Scalability Score -->
                                            <div class="relative flex flex-col items-center justify-center rounded-lg bg-gray-800/20 border border-white/5 p-4 backdrop-blur-sm group hover:bg-gray-800/40 transition-colors">
                                                <div class="relative h-20 w-20">
                                                    <svg class="h-full w-full -rotate-90 transform" viewBox="0 0 100 100">
                                                        <circle class="text-gray-800" stroke-width="6" stroke="currentColor" fill="transparent" r="44" cx="50" cy="50" />
                                                        <circle 
                                                            class="transition-all duration-1000 ease-out drop-shadow-[0_0_5px_currentColor]" 
                                                            :class="projectBreakdown.scalability_score >= 70 ? 'text-purple-500' : 'text-yellow-500'"
                                                            stroke-width="6" 
                                                            :stroke-dasharray="276" 
                                                            :stroke-dashoffset="showGauges ? 276 - (276 * projectBreakdown.scalability_score) / 100 : 276" 
                                                            stroke-linecap="round" stroke="currentColor" fill="transparent" r="44" cx="50" cy="50" 
                                                        />
                                                    </svg>
                                                    <div class="absolute inset-0 flex items-center justify-center">
                                                        <span class="text-lg font-bold text-white">{{ projectBreakdown.scalability_score }}</span>
                                                    </div>
                                                </div>
                                                <p class="mt-2 text-[10px] font-bold uppercase tracking-widest text-gray-500 group-hover:text-gray-300 transition-colors">Scalability</p>
                                            </div>
                                        </div>

                                        <!-- Tech Footprint (Glass Badges) -->
                                        <div v-if="forensicReport?.tech_assessment?.stack" class="mt-8 mb-6">
                                            <h4 class="mb-3 text-[10px] font-bold uppercase tracking-widest text-gray-500">Tech Footprint</h4>
                                            <div class="flex flex-wrap gap-2">
                                                <div v-for="tech in forensicReport.tech_assessment.stack" :key="tech.name" 
                                                     class="group relative flex items-center gap-2 rounded-lg border border-slate-600/30 bg-slate-800/40 px-3 py-1.5 backdrop-blur-md transition-all duration-300 hover:bg-slate-700/50 hover:border-slate-500/50">
                                                     
                                                     <!-- Colored Dot -->
                                                     <div class="h-2 w-2 rounded-full flex-shrink-0" :class="getTechBadgeDotColor(tech.name)"></div>
                                                     <span class="text-sm font-medium text-gray-200">{{ tech.name }}</span>
                                                     <span v-if="tech.version" class="text-xs text-gray-500 font-mono group-hover:text-brand-primary transition-colors">{{ tech.version }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Deep Dive Toggle (Digital Terminal Style) -->
                                        <div v-if="forensicReport" class="mt-6 border-t border-white/10 pt-4">
                                            <!-- Deep Forensic Audit Button -->
                                            <button 
                                                @click="handleDeepAuditClick"
                                                :disabled="isGeneratingDeepAudit"
                                                class="group mb-4 flex w-full items-center justify-between rounded-xl border border-indigo-500/30 bg-indigo-500/10 px-5 py-4 transition-all hover:bg-indigo-500/20 hover:shadow-[0_0_15px_rgba(99,102,241,0.2)] disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                <div class="flex items-center gap-3">
                                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-500/20 text-indigo-400 group-hover:scale-110 transition-transform">
                                                        <svg v-if="isGeneratingDeepAudit" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                                        </svg>
                                                    </div>
                                                    <div class="text-left">
                                                        <p class="font-bold text-indigo-100 group-hover:text-white transition-colors">
                                                            {{ isGeneratingDeepAudit ? 'Generating Deep Audit...' : (hasDeepInsight ? 'View Deep Forensic Audit' : 'Generate Deep Forensic Audit') }}
                                                        </p>
                                                        <p class="text-xs text-indigo-300/70 group-hover:text-indigo-300 transition-colors font-mono">
                                                            {{ isGeneratingDeepAudit ? '>>_ PROCESSING...' : (hasDeepInsight ? '>>_ ACCESS_VECTOR_ANALYSIS' : '>>_ COST: 20 CREDITS') }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <svg class="h-5 w-5 text-indigo-400 transition-transform duration-300 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                                </svg>
                                            </button>

                                            <button @click="showDeepDive = !showDeepDive" 
                                                class="group flex w-full items-center justify-between rounded-xl border px-5 py-4 transition-all hover:bg-white/5 hover:border-brand-primary/30"
                                                :class="[
                                                    isFlagged 
                                                        ? 'bg-red-500/5 border-red-500/20 text-red-100 hover:shadow-[0_0_15px_rgba(239,68,68,0.1)]' 
                                                        : 'bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 border-white/5'
                                                ]"
                                            >
                                                <div class="flex items-center gap-3">
                                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-primary/10 text-brand-primary group-hover:bg-brand-primary/20 group-hover:scale-110 transition-transform">
                                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                                        </svg>
                                                    </div>
                                                    <div class="text-left">
                                                        <p class="font-bold text-gray-200 group-hover:text-white transition-colors">Forensic Log Access</p>
                                                        <p class="text-xs text-gray-500 group-hover:text-brand-primary/70 transition-colors font-mono">>>_ READ_FULL_AUDIT_LOGS</p>
                                                    </div>
                                                </div>
                                                <svg :class="{'rotate-180': showDeepDive}" class="h-5 w-5 text-gray-500 transition-transform duration-300 group-hover:text-brand-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                            
                                            <transition
                                                enter-active-class="transition duration-200 ease-out"
                                                enter-from-class="transform scale-95 opacity-0 -translate-y-2"
                                                enter-to-class="transform scale-100 opacity-100 translate-y-0"
                                                leave-active-class="transition duration-150 ease-in"
                                                leave-from-class="transform scale-100 opacity-100 translate-y-0"
                                                leave-to-class="transform scale-95 opacity-0 -translate-y-2"
                                            >
                                                <div v-if="showDeepDive" class="mt-4 overflow-hidden rounded-xl border border-white/10 bg-black/60 p-6 shadow-inner backdrop-blur-xl">
                                                    <div class="prose prose-invert max-w-none prose-sm prose-p:font-mono prose-headings:font-sans prose-headings:text-brand-primary prose-a:text-blue-400 prose-strong:text-white font-mono text-xs leading-relaxed opacity-90" v-html="renderMarkdown(forensicMarkdown)"></div>
                                                </div>
                                            </transition>
                                        </div>
                                    </div>

                                    <!-- Style B: Professional (for document audits) -->
                                    <div v-else-if="documentBreakdown" class="grid grid-cols-3 gap-3">
                                        <div class="rounded-lg bg-gray-800/50 p-3 text-center">
                                            <p class="text-xs text-gray-400">Formatting</p>
                                            <p class="text-xl font-bold" :class="documentBreakdown.formatting >= 70 ? 'text-brand-secondary' : 'text-yellow-400'">
                                                {{ documentBreakdown.formatting }}
                                            </p>
                                            <div class="mt-1 h-1 rounded-full bg-gray-700">
                                                <div class="h-full rounded-full bg-brand-primary" :style="{ width: `${documentBreakdown.formatting}%` }" />
                                            </div>
                                        </div>
                                        <div class="rounded-lg bg-gray-800/50 p-3 text-center">
                                            <p class="text-xs text-gray-400">Content</p>
                                            <p class="text-xl font-bold" :class="documentBreakdown.content_quality >= 70 ? 'text-brand-secondary' : 'text-yellow-400'">
                                                {{ documentBreakdown.content_quality }}
                                            </p>
                                            <div class="mt-1 h-1 rounded-full bg-gray-700">
                                                <div class="h-full rounded-full bg-brand-primary" :style="{ width: `${documentBreakdown.content_quality}%` }" />
                                            </div>
                                        </div>
                                        <div class="rounded-lg bg-gray-800/50 p-3 text-center">
                                            <p class="text-xs text-gray-400">Industry</p>
                                            <p class="text-xl font-bold" :class="documentBreakdown.industry_relevance >= 70 ? 'text-brand-secondary' : 'text-yellow-400'">
                                                {{ documentBreakdown.industry_relevance }}
                                            </p>
                                            <div class="mt-1 h-1 rounded-full bg-gray-700">
                                                <div class="h-full rounded-full bg-brand-primary" :style="{ width: `${documentBreakdown.industry_relevance}%` }" />
                                            </div>
                                        </div>
                                    </div>


                                    <!-- Strategic Business Context Section -->
                                    <div v-if="businessOverview && isProjectAudit" class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-6 mb-6">
                                        <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-amber-400">
                                            <span class="text-lg">💼</span>
                                            Strategic Business Context
                                        </h4>
                                        <div class="prose prose-invert prose-sm max-w-none">
                                            <p class="text-gray-300 leading-relaxed whitespace-pre-line">{{ businessOverview }}</p>
                                        </div>
                                    </div>

                                    <!-- LUME AI Insights Section -->
                                    <div class="rounded-xl border border-white/10 bg-white/5 p-6">
                                        <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wider" :class="isFlagged ? 'text-red-400' : 'text-brand-primary'">
                                            <svg v-if="isFlagged" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                            LUME AI Insights
                                        </h4>

                                        <!-- OCR Required Warning -->
                                        <div v-if="isOcrRequired" class="mb-4 rounded-lg border border-orange-500/30 bg-orange-500/10 p-4">
                                            <div class="flex items-start gap-3">
                                                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                                <div>
                                                    <h5 class="font-semibold text-orange-400">Non-Readable PDF Detected</h5>
                                                    <p class="mt-1 text-sm text-orange-300/80">
                                                        LUME cannot read this PDF. Please ensure it is a <strong>text-based document</strong>, not a scanned image. OCR processing is not currently supported.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Summary -->
                                        <div 
                                            class="mb-4 text-sm leading-relaxed text-gray-300 prose prose-invert prose-sm max-w-none"
                                            v-html="renderMarkdown(auditData.summary)"
                                        ></div>

                                        <!-- Recommendations (if flagged) -->
                                        <div v-if="isFlagged && recommendations.length > 0" class="rounded-lg border border-red-500/30 bg-red-500/10 p-4">
                                            <h5 class="mb-2 flex items-center gap-2 text-sm font-semibold text-red-400">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                                Areas for Improvement
                                            </h5>
                                            <ul class="space-y-1.5 text-sm text-red-300/80">
                                                <li v-for="(rec, idx) in recommendations" :key="idx" class="flex items-start gap-2">
                                                    <span class="mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-red-400" />
                                                    {{ rec }}
                                                </li>
                                            </ul>
                                        </div>

                                        <!-- Single flag reason (fallback) -->
                                        <div v-else-if="isFlagged && auditData.flag_reason" class="rounded-lg border border-red-500/30 bg-red-500/10 p-4">
                                            <p class="text-sm text-red-300/80">
                                                <strong class="text-red-400">Improvement needed:</strong> {{ auditData.flag_reason }}
                                            </p>
                                        </div>

                                        <!-- Verified success -->
                                        <div v-else-if="isVerified" class="rounded-lg border border-brand-secondary/30 bg-brand-secondary/10 p-4">
                                            <p class="flex items-center gap-2 text-sm text-brand-secondary">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                This document meets professional standards and is eligible for marketplace listing.
                                            </p>
                                        </div>

                                        <!-- Verified Private (PII detected but high quality) -->
                                        <div v-else-if="isVerifiedPrivate" class="rounded-lg border border-sky-500/30 bg-sky-500/10 p-4">
                                            <div class="flex items-start gap-3">
                                                <!-- Privacy Shield Icon -->
                                                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-sky-500/20">
                                                    <svg class="h-6 w-6 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-sky-300">Excellent Score! Privacy Protected</p>
                                                    <p class="mt-1 text-sm text-sky-200/80">
                                                        LUME has verified the quality of this document. To protect your identity, this asset remains private and cannot be listed on the public marketplace.
                                                    </p>
                                                    <button 
                                                        type="button"
                                                        @click="showPiiReasonModal = true"
                                                        class="mt-2 inline-flex items-center gap-1 text-xs text-sky-400 hover:text-sky-300"
                                                    >
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        Why can't I sell this?
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Financial Impact (Receipt Style) -->
                                    <div class="rounded-xl border border-dashed border-gray-600 bg-gray-800/50 p-4">
                                        <div class="flex items-center justify-between text-sm">
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-500/20">
                                                    <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-white">Ledger Transaction</p>
                                                    <p class="text-xs text-gray-400">Audit Service Fee</p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-2xl font-bold" :class="isProjectAudit ? 'text-brand-secondary' : 'text-blue-400'">
                                                    {{ isProjectAudit ? '-10.00' : '-1.00' }} Credits
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    LUME Sovereign Audit
                                                </p>
                                                <!-- Remediation Cost (Project Risk Offset) -->
                                                <div v-if="forensicReport?.estimated_remediation_cost" class="mt-2 border-t border-gray-600/50 pt-2">
                                                     <p class="text-lg font-bold text-red-400">
                                                        + ${{ Number(forensicReport.estimated_remediation_cost).toLocaleString() }}
                                                     </p>
                                                     <p class="text-xs text-red-400/70">
                                                        Est. Technical Debt
                                                     </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Estimated Market Value Badge -->
                                        <div v-if="props.asset?.suggested_value && props.asset.suggested_value > 0" class="mt-4 flex items-center justify-between rounded-lg bg-brand-secondary/10 p-3 ring-1 ring-brand-secondary/30">
                                            <div class="flex items-center gap-2">
                                                <svg class="h-5 w-5 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span class="text-sm font-medium text-brand-secondary">Estimated Market Value</span>
                                            </div>
                                            <span class="text-lg font-bold text-brand-secondary">
                                                ${{ props.asset.suggested_value.toFixed(2) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- List on Marketplace Section (Only for Verified Assets with Score > 80) -->
                                    <div v-if="canListOnMarketplace && !isPublicView" class="rounded-xl border border-brand-primary/30 bg-gradient-to-br from-brand-primary/10 to-brand-secondary/10 p-5">
                                        <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-brand-primary">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            {{ isListed ? 'Marketplace Listing' : 'List on Marketplace' }}
                                        </h4>

                                        <!-- Currently Listed Badge -->
                                        <div v-if="isListed" class="mb-4 flex items-center gap-2 rounded-lg bg-brand-secondary/20 p-3">
                                            <span class="h-2 w-2 animate-pulse rounded-full bg-brand-secondary" />
                                            <span class="text-sm font-medium text-brand-secondary">Currently listed at ${{ props.asset?.price?.toFixed(2) }}</span>
                                        </div>

                                        <!-- Price Input -->
                                        <div class="mb-4">
                                            <label class="mb-2 block text-sm text-gray-400">Set Your Price</label>
                                            <div class="relative">
                                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                                <input
                                                    v-model.number="listingPrice"
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    max="999999.99"
                                                    class="w-full rounded-lg border border-gray-600 bg-gray-800 py-3 pl-8 pr-4 text-white placeholder-gray-500 focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"
                                                    placeholder="10.00"
                                                />
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500">AI suggested: ${{ props.asset?.suggested_value?.toFixed(2) ?? '10.00' }}</p>
                                        </div>

                                        <!-- Publish Button -->
                                        <button
                                            @click="toggleMarketplaceListing"
                                            :disabled="isPublishing || listingPrice < 0.01"
                                            :class="[
                                                'w-full rounded-xl px-4 py-3 font-semibold shadow-lg transition-all',
                                                isListed
                                                    ? 'bg-red-500/20 text-red-400 ring-1 ring-red-500/50 hover:bg-red-500/30'
                                                    : 'bg-gradient-to-r from-brand-primary to-brand-secondary text-white hover:brightness-110',
                                                (isPublishing || listingPrice < 0.01) ? 'cursor-not-allowed opacity-50' : ''
                                            ]"
                                        >
                                            <span v-if="isPublishing" class="flex items-center justify-center gap-2">
                                                <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                                </svg>
                                                Processing...
                                            </span>
                                            <span v-else>
                                                {{ isListed ? 'Remove from Marketplace' : 'Publish to Marketplace' }}
                                            </span>
                                        </button>
                                    </div>

                                    <!-- Privacy Warning (Verified Private or Verified but NOT marketplace eligible) -->
                                    <div v-else-if="isVerifiedPrivate || (isVerified && !isMarketplaceEligible)" class="rounded-xl border border-sky-500/30 bg-sky-500/10 p-5">
                                        <div class="flex items-start gap-3">
                                            <!-- Privacy Shield Icon -->
                                            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-sky-500/20">
                                                <svg class="h-6 w-6 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h4 class="font-semibold text-sky-300">Privacy Protected Asset</h4>
                                                <p class="mt-1 text-sm text-sky-200/80">
                                                    {{ privacyWarning || 'This document contains personal identifiable information (PII).' }}
                                                </p>
                                                <p class="mt-2 text-xs text-gray-400">
                                                    To protect your identity, this asset cannot be listed on the public marketplace.
                                                    You can still store it securely in your CloudVault.
                                                </p>
                                                <a 
                                                    href="/privacy-policy"
                                                    class="mt-3 inline-flex items-center gap-1 text-xs text-sky-400 hover:text-sky-300"
                                                    @click.prevent="$inertia.visit('/privacy-policy')"
                                                >
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    Learn about LUME's Data Privacy Policy
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- No Metadata (fallback) - shouldn't happen but just in case -->
                                <div v-else class="py-8 text-center text-gray-500">
                                    <p>No audit data available for this asset.</p>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="border-t border-white/10 bg-black/20 px-6 py-4">
                                <template v-if="isPublicView">
                                    <div class="flex gap-4">
                                        <button
                                            type="button"
                                            class="flex-1 rounded-xl border border-white/10 px-4 py-3 font-semibold text-gray-300 transition-colors hover:bg-white/10 hover:text-white"
                                            @click="closeModal"
                                        >
                                            Close
                                        </button>
                                        <button
                                            type="button"
                                            class="flex-1 rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-4 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-brand-primary/25 hover:brightness-110"
                                            @click="$emit('buy')"
                                        >
                                            Purchase for ${{ props.asset?.price?.toFixed(2) }}
                                        </button>
                                    </div>
                                </template>
                                <!-- Project audit footer with Visit Site button -->
                                <template v-if="!isPublicView && isProjectAudit">
                                    <div class="flex gap-4">
                                        <!-- Button 1: Ask LUME AI (New) -->
                                        <button
                                            type="button"
                                            class="flex-1 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 px-4 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-purple-500/25 hover:brightness-110 disabled:opacity-50 disabled:cursor-not-allowed"
                                            :disabled="isAnalyzing"
                                            @click="() => {
                                                showProjectAnalystModal = true;
                                            }"
                                        >
                                            <div class="flex items-center justify-center gap-2">
                                                <svg v-if="!isAnalyzing" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                                </svg>
                                                <svg v-else class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                {{ isAnalyzing ? 'Analyzing Assets...' : 'Ask LUME AI about this project' }}
                                            </div>
                                        </button>

                                        <!-- Button 2: Scan Repository (If URL Only) -->
                                        <button
                                            v-if="!props.asset?.metadata?.github_repo_url"
                                            type="button"
                                            class="flex-1 rounded-xl border border-dashed border-gray-500 bg-gray-800/50 px-4 py-3 font-semibold text-gray-300 transition-all hover:bg-gray-800 hover:text-white hover:border-brand-primary disabled:opacity-50 disabled:cursor-not-allowed"
                                            :disabled="isAnalyzing"
                                            @click="showRepoSyncModal = true"
                                        >
                                            <div class="flex items-center justify-center gap-2">
                                                <svg v-if="!isAnalyzing" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                                </svg>
                                                <svg v-else class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                     <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                     <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                {{ isAnalyzing ? 'Analyzing...' : 'Scan Repository to Sell' }}
                                            </div>
                                        </button>
                                        
                                        <!-- View Repository (If exists) -->
                                        <a
                                            v-else
                                            :href="props.asset?.metadata?.github_repo_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-gray-700 px-4 py-3 font-semibold text-white shadow-lg transition-all hover:bg-gray-600"
                                        >
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                            </svg>
                                            View Repository
                                        </a>
                                    </div>
                                </template>
                                <!-- Document audit footer -->
                                <button
                                    v-if="!isPublicView && !isProjectAudit"
                                    type="button"
                                    class="w-full rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-4 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-brand-primary/25 hover:brightness-110"
                                    @click="closeModal"
                                >
                                    Close Report
                                </button>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </div>
        </transition>
    </Teleport>

    <!-- PII Reason Modal -->
    <Teleport to="body">
        <transition name="fade">
            <div
                v-if="showPiiReasonModal"
                class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto p-4"
            >
                <!-- Backdrop -->
                <div
                    class="fixed inset-0 bg-black/70 backdrop-blur-sm"
                    @click="showPiiReasonModal = false"
                />

                <!-- Modal Content -->
                <div class="relative z-10 w-full max-w-md rounded-2xl bg-gray-900 p-6 shadow-2xl ring-1 ring-white/10">
                    <!-- Header -->
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-sky-500/20">
                            <svg class="h-6 w-6 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">Privacy Protection Active</h3>
                            <p class="text-sm text-gray-400">Why this document can't be sold</p>
                        </div>
                    </div>

                    <!-- AI-Generated Reason -->
                    <div class="mb-4 rounded-lg border border-sky-500/30 bg-sky-500/10 p-4">
                        <p class="text-sm leading-relaxed text-sky-200">{{ piiReason }}</p>
                    </div>

                    <!-- Explanation -->
                    <div class="mb-6 space-y-3 text-sm text-gray-400">
                        <p>
                            LUME detected <strong class="text-white">Personal Identifiable Information (PII)</strong> in this document. 
                            Selling documents with PII could expose you and buyers to:
                        </p>
                        <ul class="ml-4 list-inside list-disc space-y-1">
                            <li>Identity theft risks</li>
                            <li>Privacy violations</li>
                            <li>Legal liability</li>
                        </ul>
                        <p class="pt-2">
                            Your document still received an <strong class="text-brand-secondary">excellent quality score</strong> — 
                            it just can't be distributed publicly.
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3">
                        <button
                            type="button"
                            class="flex-1 rounded-xl border border-gray-600 px-4 py-2.5 text-sm font-medium text-gray-300 hover:bg-gray-800"
                            @click="showPiiReasonModal = false"
                        >
                            Got it
                        </button>
                        <a
                            href="/privacy-policy"
                            class="flex-1 rounded-xl bg-sky-500/20 px-4 py-2.5 text-center text-sm font-medium text-sky-400 hover:bg-sky-500/30"
                            @click.prevent="showPiiReasonModal = false; $inertia.visit('/privacy-policy')"
                        >
                            Learn More
                        </a>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
    <!-- Success Toast -->
    <Teleport to="body">
        <transition name="fade">
            <div
                v-if="showSuccessToast"
                class="fixed bottom-6 right-6 z-[70] flex items-center gap-3 rounded-xl border border-brand-primary/20 bg-gray-900/90 p-4 shadow-2xl backdrop-blur-md"
            >
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-500/20">
                    <svg class="h-6 w-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <h4 class="font-bold text-white">Success!</h4>
                    <p class="text-sm text-gray-300">Asset is now LIVE on the public marketplace!</p>
                </div>
                <button @click="showSuccessToast = false" class="ml-2 text-gray-500 hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </transition>
    </Teleport>
    <!-- Repo Sync Modal -->
    <RepoSyncModal 
        :show="showRepoSyncModal"
        :asset="props.asset"
        @close="showRepoSyncModal = false"
        @submitted="() => {
             showRepoSyncModal = false;
        }"
    />

    <ProjectAnalystModal
        :show="showProjectAnalystModal"
        :asset="props.asset ?? undefined"
        :forensicData="auditData ?? undefined"
        @close="showProjectAnalystModal = false"
    />

    <!-- Deep Insight Modal -->
    <DeepInsightModal 
        :show="showDeepInsight" 
        :asset="asset"
        @close="showDeepInsight = false" 
    />

    <!-- Deep Scan Confirmation Modal -->
    <DeepScanConfirmationModal
        :show="showDeepConfirmation"
        :asset="asset"
        @close="showDeepConfirmation = false"
        @confirm="confirmDeepAudit"
        @open-credit-modal="showCreditPurchaseModal = true"
    />

    <!-- Credit Purchase Modal -->
    <CreditPurchaseModal
        :show="showCreditPurchaseModal"
        @close="showCreditPurchaseModal = false"
    />
</template>
