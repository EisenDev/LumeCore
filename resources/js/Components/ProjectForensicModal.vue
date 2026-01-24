<script setup lang="ts">
/**
 * ProjectForensicModal Component
 * Dark, cyber-terminal style modal for Website/GitHub project audits.
 * Refactored for "Sovereign Forensics" aesthetic.
 */
import { computed, ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Radar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    RadialLinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend,
} from 'chart.js';
import type { VaultAsset } from '@/types/vault';
import ProjectAnalystModal from '@/Components/ProjectAnalystModal.vue';

ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend);

interface Props {
    show: boolean;
    asset: VaultAsset | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'deep-audit'): void;
}>();

// Animation state
const animatedScore = ref(0);
const showContent = ref(false);
const hoveredTech = ref<string | null>(null);
const showAIDetailsModal = ref(false);
const showAIModal = ref(false); // For Ask LUME AI modal
const isScanning = ref(false);
const scanStep = ref('Initializing Sovereign Analyst...');
const scanProgress = ref(0);
let scoreAnimationFrame: number | null = null;

function animateScoreTo(targetScore: number) {
    if (scoreAnimationFrame) cancelAnimationFrame(scoreAnimationFrame);
    const duration = 1500;
    const startTime = performance.now();
    const startScore = animatedScore.value;
    
    function tick(currentTime: number) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeOut = 1 - Math.pow(1 - progress, 3);
        animatedScore.value = Math.round(startScore + (targetScore - startScore) * easeOut);
        if (progress < 1) {
            scoreAnimationFrame = requestAnimationFrame(tick);
        }
    }
    scoreAnimationFrame = requestAnimationFrame(tick);
}

// Computed: Extract audit data from metadata
const auditData = computed(() => {
    if (!props.asset?.metadata) return null;
    const metadata = props.asset.metadata as any;
    return {
        verdict: metadata.verdict ?? props.asset.status,
        score: props.asset.score ?? metadata.confidence_score ?? 0,
        summary: metadata.summary ?? '',
        business_summary: metadata.business_summary ?? metadata.business_overview ?? '',
        site_classification: metadata.site_classification ?? 'Database & Infrastructure',
        niche: metadata.niche ?? 'Unknown',
        insights: metadata.insights ?? [],
        warning_flags: metadata.warning_flags ?? [],
        is_marketplace_eligible: metadata.is_marketplace_eligible ?? false,
        tech_assessment: metadata.tech_assessment ?? {},
        security_assessment: metadata.security_assessment ?? {},
        breakdown: metadata.breakdown ?? props.asset.radar_data ?? {},
        vector_details: metadata.vector_details ?? {},
        tech_footprint_explanations: metadata.tech_footprint_explanations ?? {},
        risk_matrix: metadata.risk_matrix ?? { performance: 'low', security: 'low', scalability: 'low' },
        executive_summary: metadata.executive_summary ?? '',
        tech_narrative: metadata.tech_narrative ?? '',
        about_project: metadata.about_project ?? '',
        languages: metadata.languages ?? [],
        supply_chain_risk: metadata.supply_chain_risk ?? null,
        compliance_check: metadata.compliance_check ?? null,
        carbon_footprint: metadata.carbon_footprint ?? null,
        accessibility: metadata.accessibility ?? null,
        data_sovereignty: metadata.data_sovereignty ?? null
    };
});

// Computed: Radar data from breakdown (Handles JSON string parsing + Risk Matrix Fallback)
const radarData = computed(() => {
    let raw = auditData.value?.breakdown ?? props.asset?.radar_data ?? {};
    
    // 1. Safety check: Parse if string
    if (typeof raw === 'string') {
        try {
            raw = JSON.parse(raw);
        } catch (e) {
            console.error("Failed to parse radar_data:", e);
            raw = {};
        }
    }

    // 2. Primary Extraction (with Legacy Support)
    let data = {
        code_resilience: raw.code_resilience ?? raw.tech_score ?? 0,
        security_perimeter: raw.security_perimeter ?? raw.security_score ?? 0,
        deployment_maturity: raw.deployment_maturity ?? raw.scalability_score ?? 0,
        seo_authority: raw.seo_authority ?? 0,
        database_architecture: raw.database_architecture ?? 0,
    };

    // 3. Fallback: If primary vectors are 0, try Risk Matrix
    // The user sees "Performance" but internally we likely have "risk_matrix.performance"
    const risk = auditData.value?.risk_matrix ?? {};
    const hasRiskData = (risk.performance || risk.security || risk.scalability);
    const hasVectorData = (data.code_resilience || data.security_perimeter || data.deployment_maturity);

    if (!hasVectorData && hasRiskData) {
        // Map Risk Matrix (0-100) to Radar Vectors
        data.code_resilience = risk.performance ?? 0;
        data.security_perimeter = risk.security ?? 0;
        data.deployment_maturity = risk.scalability ?? 0;
        // Synthesize minor vectors to avoid ugly chart
        data.seo_authority = 50; 
        data.database_architecture = 50;
    }

    return data;
});

// Computed: Tech stack from assessment
const techStack = computed(() => auditData.value?.tech_assessment?.stack ?? []);

// Computed: Visual Grading Components (Shields)
const gradeInfo = computed(() => {
    const score = auditData.value?.score ?? 0;
    if (score >= 85) return { 
        icon: 'double-shield', 
        label: 'Verified', 
        color: 'text-emerald-400', 
        borderColor: 'border-emerald-500',
        glow: 'shadow-[0_0_30px_rgba(52,211,153,0.3)]',
        bg: 'bg-emerald-500/10'
    };
    if (score >= 80) return { 
        icon: 'shield', 
        label: 'Good', 
        color: 'text-blue-400', 
        borderColor: 'border-blue-500',
        glow: 'shadow-[0_0_30px_rgba(59,130,246,0.3)]',
        bg: 'bg-blue-500/10'
    };
    if (score >= 70) return { 
        icon: 'shield', 
        label: 'Action Required', 
        color: 'text-amber-400', 
        borderColor: 'border-amber-500',
        glow: 'shadow-[0_0_30px_rgba(245,158,11,0.3)]',
        bg: 'bg-amber-500/10'
    };
    return { 
        icon: 'hexagon', 
        label: 'Flagged', 
        color: 'text-rose-500', 
        borderColor: 'border-rose-500',
        glow: 'shadow-[0_0_30px_rgba(244,63,94,0.3)]',
        bg: 'bg-rose-500/10'
    };
});

// Computed: Status badge
const statusInfo = computed(() => {
    const status = auditData.value?.verdict ?? props.asset?.status;
    switch (status) {
        case 'verified': return { label: 'VERIFIED', color: 'border-emerald-500 bg-emerald-500/10 text-emerald-400' };
        case 'verified_private': return { label: 'PRIVATE ONLY', color: 'border-purple-500 bg-purple-500/10 text-purple-400' };
        case 'flagged': return { label: 'FLAGGED', color: 'border-rose-500 bg-rose-500/10 text-rose-400' };
        case 'action_required': return { label: 'ACTION REQUIRED', color: 'border-amber-500 bg-amber-500/10 text-amber-400' };
        default: return { label: 'PROCESSING', color: 'border-gray-500 bg-gray-500/10 text-gray-400' };
    }
});

function getLangColor(lang: string): string {
    const l = lang.toLowerCase();
    if (l === 'php' || l === 'blade') return '#777bb4'; // PHP Purple
    if (l === 'javascript' || l === 'js') return '#f1e05a'; // JS Yellow
    if (l === 'typescript' || l === 'ts') return '#3178c6'; // TS Blue
    if (l === 'vue') return '#41b883'; // Vue Green
    if (l === 'html') return '#e34c26'; // HTML Orange
    if (l === 'css' || l === 'scss') return '#563d7c'; // CSS Purple
    if (l === 'shell') return '#89e051'; // Shell Green
    return '#94a3b8'; // Default Slate
}

// Radar chart configuration
const chartData = computed(() => ({
    labels: ['Resilience', 'Security', 'Deployment', 'SEO', 'Database'],
    datasets: [{
        label: 'Audit Score',
        data: [
            radarData.value.code_resilience ?? 0,
            radarData.value.security_perimeter ?? 0,
            radarData.value.deployment_maturity ?? 0,
            radarData.value.seo_authority ?? 0,
            radarData.value.database_architecture ?? 0,
        ],
        backgroundColor: 'rgba(99, 102, 241, 0.2)',
        borderColor: 'rgba(99, 102, 241, 1)',
        borderWidth: 2,
        pointBackgroundColor: 'rgba(99, 102, 241, 1)',
        pointBorderColor: '#fff',
        pointHoverBackgroundColor: '#fff',
        pointHoverBorderColor: 'rgba(99, 102, 241, 1)',
    }]
}));

// Real-time Scanning Listener
onMounted(() => {
    if (props.asset?.id) {
        if ((props.asset?.status as string) === 'scanning' || (props.asset?.status as string) === 'pending' || (props.asset?.status as string) === 'processing') {
            isScanning.value = true;
            // Add initial log
            scanLogs.value = [
                { time: new Date().toLocaleTimeString(), message: 'INITIALIZING LUME_SOVEREIGN_FORENSICS...', type: 'info' },
                { time: new Date().toLocaleTimeString(), message: `ESTABLISHING UPLINK... (Channel: App.Models.User.${props.asset.user_id})`, type: 'info' }
            ];
        }

        // @ts-ignore - Echo is globally available
        const Echo = (window as any).Echo;
        
        // DEBUG: Check user_id presence
        if (!props.asset.user_id) {
            console.error('ProjectForensicModal: Missing user_id on asset', props.asset);
            scanLogs.value.push({
                 time: new Date().toLocaleTimeString(), 
                 message: 'ERROR: USER INTEL MISSING. CANNOT ESTABLISH UPLINK.', 
                 type: 'error' 
            });
        }

        if (Echo && props.asset.user_id) {
             // Safe user_id access
             const userId = props.asset.user_id;
             const channelName = `App.Models.User.${userId}`;
             console.log('ProjectForensicModal: Listening on channel', channelName);
             
             const channel = Echo.private(channelName);

             channel.error((err: any) => {
                 console.error('ProjectForensicModal: Channel Connection Error:', err);
                 scanLogs.value.push({
                     time: new Date().toLocaleTimeString(),
                     message: 'ERROR: UPLINK FAILED (AUTH/CONNECTION). RETRYING...',
                     type: 'error'
                 });
             });
             
             const handleEvent = (e: { asset_id: string; step: string; progress: number, status?: string }) => {
                 // Debug log
                 console.log('⚡ Event Received (Project Forensic):', e);
                 
                 // Logic to handle "stuck" progress
                 // If we receive an event, we are definitely scanning
                 if (!isScanning.value && e.progress < 100) {
                     isScanning.value = true;
                 }

                 // Only process if this event is for the current asset
                 // Cast asset_id to string to ensure type safety
                 if (String(e.asset_id) === String(props.asset?.id)) {
                     // Update progress
                     scanProgress.value = e.progress;
                     
                     // Add to logs if step changed or new log
                     if (e.step && (!scanLogs.value.length || scanLogs.value[scanLogs.value.length - 1].message !== e.step)) {
                         // Auto-classify message type for UI dots
                         let type = 'info';
                         const msg = e.step.toLowerCase();
                         
                         if (msg.includes('error') || msg.includes('failed') || msg.includes('violation')) {
                             type = 'error';
                         } else if (msg.includes('warning') || msg.includes('minor') || msg.includes('alert')) {
                             type = 'warning';
                         } else if (msg.includes('initializing') || msg.includes('scanning') || msg.includes('analyzing')) {
                             type = 'processing';
                         } else {
                             // Default to success/info (Green dot) for standard progress updates
                             // User asked for "green dot" when done, so standard progress steps = good
                             type = 'success';
                         }

                         scanLogs.value.push({
                             time: '', // Timestamp removed as requested
                             message: e.step,
                             type: type
                         });
                         scrollToBottom();
                     }

                     if (e.progress >= 100) {
                        setTimeout(() => {
                            isScanning.value = false;
                            window.location.reload();
                        }, 2000); 
                     }
                 }
             };

             // Listen to all aliases
             channel
                 .listen('.AuditProgressUpdated', handleEvent)
                 .listen('AuditProgressUpdated', handleEvent)
                 .listen('App\\Events\\AuditProgressUpdated', handleEvent);
                 
             // Log connection
             scanLogs.value.push({
                 time: new Date().toLocaleTimeString(), 
                 message: 'UPLINK ESTABLISHED. LISTENING FOR ARTIFACTS...',
                 type: 'success'
             });
        }
    }
});

onUnmounted(() => {
    if (props.asset?.user_id) {
        // @ts-ignore
        (window as any).Echo?.leave(`App.Models.User.${props.asset.user_id}`);
    }
});

// Interactive Chart Options with Custom Tooltips

// Interactive Chart Options with Custom Tooltips
const chartOptions: any = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        r: {
            beginAtZero: true,
            max: 100,
            ticks: { stepSize: 20, display: false },
            grid: { color: 'rgba(100, 116, 139, 0.2)' },
            angleLines: { color: 'rgba(100, 116, 139, 0.2)' },
            pointLabels: { color: '#94a3b8', font: { size: 11, family: 'monospace' } },
        }
    },
    plugins: { 
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.95)',
            titleColor: '#818cf8',
            bodyColor: '#e2e8f0',
            borderColor: 'rgba(99, 102, 241, 0.3)',
            borderWidth: 1,
            padding: 12,
            cornerRadius: 8,
            titleFont: { family: 'monospace', size: 13, weight: 'bold' },
            bodyFont: { family: 'sans-serif', size: 12 },
            callbacks: {
                label: (context: any) => {
                    const label = context.label;
                    const value = context.raw;
                    // Try to find Deep Context
                    const vectorKeyMap: {[key: string]: string} = {
                        'Resilience': 'code_resilience',
                        'Security': 'security_perimeter',
                        'Deployment': 'deployment_maturity',
                        'SEO': 'seo_authority',
                        'Database': 'database_architecture'
                    };
                    const key = vectorKeyMap[label];
                    const details = auditData.value?.vector_details?.[key];
                    
                    if (details) {
                        return [
                            `Score: ${value}/100 [${details.status_label}]`,
                            '',
                            ...details.explanation.match(/.{1,40}(?:\s|$)/g) || [], // Wrap text
                        ];
                    }
                    return `Score: ${value}/100`;
                }
            }
        } 
    },
};

// Open Chat - Opens the dedicated ProjectAnalystModal
function openChat() {
    showAIModal.value = true;
}

// Tech badge color helper
function getTechBadgeColor(name: string): string {
    const n = name.toLowerCase();
    if (n.includes('next') || n.includes('react')) return 'bg-blue-500';
    if (n.includes('tailwind') || n.includes('css')) return 'bg-emerald-500';
    if (n.includes('postgres') || n.includes('supabase') || n.includes('mysql')) return 'bg-indigo-500';
    if (n.includes('typescript') || n.includes('javascript')) return 'bg-amber-500';
    if (n.includes('vue') || n.includes('laravel')) return 'bg-rose-500';
    return 'bg-slate-400';
}

function getTechExplanation(name: string): string {
    return auditData.value?.tech_footprint_explanations?.[name] 
        ?? `Detected ${name} technology usage in the codebase.`;
}

// Helper for Risk Meter Colors
function getRiskColor(level: string) {
    switch(level?.toLowerCase()) {
        case 'high': case 'critical': return 'bg-rose-500 text-rose-400 shadow-[0_0_10px_rgba(244,63,94,0.4)]';
        case 'med': case 'medium': return 'bg-amber-500 text-amber-400';
        case 'low': return 'bg-emerald-500 text-emerald-400';
        default: return 'bg-slate-700 text-slate-400';
    }
}



// Terminal Logic
const scanLogs = ref<{time: string, message: string, type: string}[]>([]);
const terminalBody = ref<HTMLElement | null>(null);

function scrollToBottom() {
    nextTick(() => {
        if (terminalBody.value) {
            terminalBody.value.scrollTop = terminalBody.value.scrollHeight;
        }
    });
}

// Watch for modal open
watch(() => props.show, (newValue) => {
    if (newValue) {
        // Reset logs if starting new scan
         if ((props.asset?.status as string) === 'scanning' || (props.asset?.status as string) === 'pending' || (props.asset?.status as string) === 'processing') {
            scanLogs.value = [
                { time: new Date().toLocaleTimeString(), message: 'INITIALIZING LUME_SOVEREIGN_FORENSICS...', type: 'info' }
            ];
         }
    }
    
    if (newValue && auditData.value) {
        showContent.value = false;
        animatedScore.value = 0;
        setTimeout(() => {
            showContent.value = true;
            animateScoreTo(auditData.value?.score ?? 0);
        }, 100);
    }
});

const closeModal = () => emit('close');
</script>

<template>
    <Teleport to="body">
        <transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200"
            leave-to-class="opacity-0"
        >
            <div v-if="show && asset" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Backdrop - Dark Matrix -->
                <div class="absolute inset-0 bg-black/90 backdrop-blur-md" @click="closeModal"></div>
                
                <!-- Modal Container - Cyber Terminal Style -->
                <div class="relative w-full max-w-5xl max-h-[90vh] overflow-hidden rounded-2xl border border-indigo-500/30 bg-[#0a0f1a] shadow-[0_0_50px_rgba(99,102,241,0.15)] flex flex-col">
                    
                    <!-- Header - Terminal Style -->
                    <div class="flex-shrink-0 border-b border-indigo-500/20 bg-gradient-to-r from-indigo-950/50 to-purple-950/30 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <!-- Terminal Icon -->
                                <div class="w-10 h-10 rounded-lg bg-indigo-500/20 flex items-center justify-center border border-indigo-500/30">
                                    <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold text-white truncate max-w-md font-mono tracking-tight">
                                        {{ asset.file_name }}
                                    </h2>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-[10px] font-mono text-indigo-400 bg-indigo-500/10 px-1.5 py-0.5 rounded border border-indigo-500/20">
                                            LUME_SOVEREIGN_FORENSICS
                                        </span>
                                        <span class="text-xs text-slate-400">{{ auditData?.site_classification }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <span 
                                    class="px-3 py-1 text-xs font-bold rounded-full border tracking-widest"
                                    :class="statusInfo.color"
                                >
                                    {{ statusInfo.label }}
                                </span>
                                <button @click="closeModal" class="p-2 rounded-lg text-gray-400 hover:bg-white/10 hover:text-white transition-colors">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Body - Scrollable with Custom Scrollbar -->
                    <div class="flex-1 overflow-y-auto px-6 py-6 bg-[#0a0f1a] custom-scrollbar">
                        
                        <!-- REAL-TIME SMART TERMINAL UI -->
                        <div v-if="isScanning || (!auditData && (asset?.status as string) !== 'failed_system')" class="flex flex-col h-full min-h-[500px] animate-fade-in relative z-10">
                            
                            <!-- Agent Header -->
                            <div class="flex items-center gap-3 mb-4 border-b border-indigo-500/20 pb-3">
                                <div class="relative w-10 h-10 flex items-center justify-center">
                                    <div class="absolute inset-0 bg-indigo-500/20 rounded-full animate-pulse"></div>
                                    <svg class="w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-white font-mono flex items-center gap-2">
                                        LUME_AI_AGENT // ACTIVE
                                        <span class="flex h-2 w-2 relative">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                        </span>
                                    </h3>
                                    <p class="text-[10px] text-slate-400 font-mono tracking-widest uppercase">
                                        Thread ID: {{ asset?.id.substring(0, 8) }} | Mode: Deep Forensic
                                    </p>
                                </div>
                            </div>

                            <!-- Terminal Window -->
                            <div class="flex-1 bg-black/40 rounded-lg border border-white/5 font-mono text-xs p-4 overflow-hidden flex flex-col relative shadow-inner">
                                <!-- Matrix Rain / Grid Background -->
                                <div class="absolute inset-0 opacity-10 pointer-events-none bg-[url('https://grainy-gradients.vercel.app/noise.svg')]"></div>
                                
                                <!-- Scrollable Logs -->
                                <div ref="terminalBody" class="flex-1 overflow-y-auto space-y-2 pr-2 custom-scrollbar scroll-smooth">
                                    <div v-for="(log, index) in scanLogs" :key="index" class="flex gap-3 items-center group animate-in slide-in-from-left-2 duration-300">
                                        <!-- Status Dot -->
                                        <div class="flex-shrink-0 w-4 flex justify-center">
                                            <div class="w-2 h-2 rounded-full" :class="{
                                                'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]': log.type === 'success' || log.type === 'info',
                                                'bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.6)] animate-pulse': log.type === 'error',
                                                'bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.6)]': log.type === 'warning',
                                                'bg-blue-500 animate-ping': log.type === 'processing'
                                            }"></div>
                                        </div>
                                        
                                        <!-- Message -->
                                        <span class="text-slate-300 break-words font-mono text-xs">
                                            <span v-if="log.message.includes('LOG:') || log.message.includes('ANALYZING:')" class="text-indigo-400 font-bold mr-1">></span>
                                            {{ log.message }}
                                        </span>
                                    </div>
                                    
                                    <!-- Typing Indicator (Phantom Element) -->
                                    <div v-if="isScanning" class="flex gap-3 animate-pulse opacity-50">
                                        <span class="text-slate-700">[..:..:..]</span>
                                        <span class="text-slate-500">_</span>
                                    </div>
                                </div>
                                
                                <!-- Status Footer -->
                                <div class="mt-4 pt-3 border-t border-white/5 flex items-center justify-between text-[10px] text-slate-500 uppercase tracking-widest">
                                    <span>Mem: {{ Math.floor(Math.random() * 50) + 120 }}MB</span>
                                    <span>
                                        Processing: {{ scanProgress }}% 
                                        <span class="inline-block w-16 h-1 bg-slate-800 ml-2 rounded-full overflow-hidden align-middle">
                                            <span class="block h-full bg-indigo-500 transition-all duration-300" :style="{ width: scanProgress + '%' }"></span>
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <transition
                            enter-active-class="transition-all duration-500 ease-out"
                            enter-from-class="opacity-0 translate-y-4"
                            enter-to-class="opacity-100 translate-y-0"
                        >
                            <div v-if="showContent && !isScanning" class="space-y-8">
                                
                                <!-- 1. Strategic Context Section (New) -->
                                <div v-if="auditData?.business_summary || auditData?.niche" class="relative group">
                                    <div class="absolute -inset-0.5 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl opacity-20 group-hover:opacity-30 transition duration-500 blur"></div>
                                    <div class="relative bg-[#0f172a] rounded-xl p-6 border border-white/10">
                                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                                            Strategic Context
                                        </h4>
                                        
                                        <!-- Niche Label -->
                                        <div class="mb-3 flex items-center gap-2" v-if="auditData.niche">
                                            <span class="text-[10px] text-slate-500 font-mono uppercase tracking-tight">DETECTED NICHE:</span>
                                            <div class="px-2 py-0.5 rounded border border-indigo-500/30 bg-indigo-500/10 text-xs text-indigo-300 font-mono tracking-wide shadow-[0_0_10px_rgba(99,102,241,0.2)]">
                                                {{ auditData.niche }}
                                            </div>
                                        </div>

                                        <p class="text-sm text-slate-300 italic leading-relaxed" v-if="auditData.business_summary">
                                            "{{ auditData.business_summary }}"
                                        </p>
                                        <p v-else class="text-sm text-slate-300 italic leading-relaxed">
                                            "{{ auditData.summary }}"
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- 2. Visual Grading & Risk Meters -->
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                                    <!-- Shield Grade (Left) -->
                                    <div class="md:col-span-5 flex flex-col justify-center items-center p-6 rounded-xl border border-white/5 bg-white/[0.02] mb-8" :class="gradeInfo.bg">
                                        <!-- Animated Shield Icon -->
                                        <div class="relative w-32 h-32 flex items-center justify-center mb-4 transition-transform hover:scale-105 duration-300">
                                            <!-- SVG Shields -->
                                            <svg v-if="gradeInfo.icon === 'double-shield'" class="w-full h-full drop-shadow-2xl" :class="gradeInfo.color" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 2.18l7 3.12v4.86c0 4.54-3.04 8.76-7 10-3.96-1.24-7-5.46-7-10V6.3l7-3.12z"/>
                                                <path d="M12 6L6 8.5v4.2c0 3.2 2.1 6.2 6 7.2 3.9-1 6-4 6-7.2V8.5L12 6z" opacity="0.5"/>
                                            </svg>
                                            
                                            <svg v-else-if="gradeInfo.icon === 'shield'" class="w-full h-full drop-shadow-2xl" :class="gradeInfo.color" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                                            </svg>
                                            
                                            <svg v-else class="w-full h-full drop-shadow-2xl" :class="gradeInfo.color" fill="currentColor" viewBox="0 0 24 24">
                                                 <path d="M12 2l-9.5 5.5v10L12 22l9.5-4.5v-10L12 2z"/> 
                                            </svg>
                                            
                                            <!-- Score Overlay -->
                                            <div class="absolute inset-0 flex items-center justify-center pt-2">
                                                <span class="text-2xl font-black text-[#0f172a]">{{ animatedScore }}</span>
                                            </div>
                                        </div>
                                        
                                        <h3 class="text-2xl font-bold tracking-tight mb-1" :class="gradeInfo.color">{{ gradeInfo.label }}</h3>
                                        <p class="text-xs text-slate-500 font-mono">{{ auditData?.niche }} Niche</p>
                                    </div>
                                    
                                    <!-- Risk Triple-Meter (Circular Scores) -->
                                    <div class="md:col-span-7 bg-[#0f172a] rounded-xl border border-white/10 p-6 flex flex-col justify-center space-y-5">
                                        <div class="flex justify-around items-center">
                                            <!-- Performance (Code Resilience) -->
                                            <div class="flex flex-col items-center group">
                                                <div class="relative w-24 h-24 mb-3">
                                                    <svg class="w-full h-full rotate-[-90deg]" viewBox="0 0 100 100">
                                                        <circle cx="50" cy="50" r="40" fill="transparent" stroke="currentColor" stroke-width="8" class="text-slate-800" />
                                                        <circle 
                                                            cx="50" cy="50" r="40" 
                                                            fill="transparent" 
                                                            stroke="currentColor" 
                                                            stroke-width="8" 
                                                            stroke-linecap="round"
                                                            class="transition-all duration-1000 ease-out shadow-[0_0_15px_currentColor]"
                                                            :class="(radarData?.code_resilience || 0) >= 80 ? 'text-emerald-500' : (radarData?.code_resilience || 0) >= 50 ? 'text-amber-500' : 'text-rose-500'"
                                                            :stroke-dasharray="251.2"
                                                            :stroke-dashoffset="251.2 * (1 - ((radarData?.code_resilience || 0) / 100))" 
                                                        />
                                                    </svg>
                                                    <div class="absolute inset-0 flex items-center justify-center flex-col">
                                                        <span class="text-2xl font-bold text-white">{{ radarData?.code_resilience || 0 }}</span>
                                                    </div>
                                                </div>
                                                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">PERFORMANCE</span>
                                            </div>

                                            <!-- Security (Security Perimeter) -->
                                            <div class="flex flex-col items-center group">
                                                <div class="relative w-24 h-24 mb-3">
                                                    <svg class="w-full h-full rotate-[-90deg]" viewBox="0 0 100 100">
                                                        <circle cx="50" cy="50" r="40" fill="transparent" stroke="currentColor" stroke-width="8" class="text-slate-800" />
                                                        <circle 
                                                            cx="50" cy="50" r="40" 
                                                            fill="transparent" 
                                                            stroke="currentColor" 
                                                            stroke-width="8" 
                                                            stroke-linecap="round"
                                                            class="transition-all duration-1000 ease-out shadow-[0_0_15px_currentColor]"
                                                            :class="(radarData?.security_perimeter || 0) >= 80 ? 'text-emerald-500' : (radarData?.security_perimeter || 0) >= 50 ? 'text-amber-500' : 'text-rose-500'"
                                                            :stroke-dasharray="251.2"
                                                            :stroke-dashoffset="251.2 * (1 - ((radarData?.security_perimeter || 0) / 100))" 
                                                        />
                                                    </svg>
                                                    <div class="absolute inset-0 flex items-center justify-center flex-col">
                                                        <span class="text-2xl font-bold text-white">{{ radarData?.security_perimeter || 0 }}</span>
                                                    </div>
                                                </div>
                                                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">SECURITY</span>
                                            </div>

                                            <!-- Scalability (Deployment Maturity) -->
                                            <div class="flex flex-col items-center group">
                                                <div class="relative w-24 h-24 mb-3">
                                                    <svg class="w-full h-full rotate-[-90deg]" viewBox="0 0 100 100">
                                                        <circle cx="50" cy="50" r="40" fill="transparent" stroke="currentColor" stroke-width="8" class="text-slate-800" />
                                                        <circle 
                                                            cx="50" cy="50" r="40" 
                                                            fill="transparent" 
                                                            stroke="currentColor" 
                                                            stroke-width="8" 
                                                            stroke-linecap="round"
                                                            class="transition-all duration-1000 ease-out shadow-[0_0_15px_currentColor]"
                                                            :class="(radarData?.deployment_maturity || 0) >= 80 ? 'text-emerald-500' : (radarData?.deployment_maturity || 0) >= 50 ? 'text-amber-500' : 'text-rose-500'"
                                                            :stroke-dasharray="251.2"
                                                            :stroke-dashoffset="251.2 * (1 - ((radarData?.deployment_maturity || 0) / 100))" 
                                                        />
                                                    </svg>
                                                    <div class="absolute inset-0 flex items-center justify-center flex-col">
                                                        <span class="text-2xl font-bold text-white">{{ radarData?.deployment_maturity || 0 }}</span>
                                                    </div>
                                                </div>
                                                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">SCALABILITY</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Sovereign Footprint Bar (New) -->
                                <div class="mt-6 mb-2 p-3 rounded-lg border border-indigo-500/20 bg-indigo-900/10 flex items-center justify-between text-xs font-mono" v-if="auditData">
                                    <!-- Left: Data Jurisdiction -->
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center gap-1.5 px-2 py-1 bg-slate-900 rounded border border-white/5">
                                            <span class="text-slate-400">JURISDICTION</span>
                                            <span class="w-1.5 h-1.5 rounded-full" :class="auditData?.data_sovereignty?.jurisdiction_risk === 'high' ? 'bg-red-500 animate-pulse' : 'bg-emerald-500'"></span>
                                        </div>
                                        <span class="text-slate-300 flex items-center gap-2">
                                            <span class="text-indigo-400 font-bold uppercase">{{ auditData?.data_sovereignty?.country || 'Pending' }}</span>
                                            <!-- Unknown Jurisdiction Alert -->
                                            <div class="group relative" v-if="!auditData?.data_sovereignty?.country || auditData?.data_sovereignty?.country === 'Unknown'">
                                                <div class="w-4 h-4 rounded-full bg-amber-500/20 text-amber-500 flex items-center justify-center cursor-help border border-amber-500/50">
                                                    <span class="text-[10px] font-bold">!</span>
                                                </div>
                                                <!-- Tooltip -->
                                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-slate-900 border border-amber-500/30 rounded shadow-xl z-50 opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity">
                                                    <p class="text-[9px] text-amber-200 leading-tight">
                                                        Server location hidden by CDN (e.g. Cloudflare) or proxy. Actual residency cannot be confirmed.
                                                    </p>
                                                    <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-slate-900 shadow"></div>
                                                </div>
                                            </div>
                                            <span class="text-slate-500/50 ml-1" v-if="auditData?.data_sovereignty?.provider">via {{ auditData.data_sovereignty.provider }}</span>
                                        </span>
                                    </div>

                                    <!-- Right: Accessibility (WCAG) -->
                                    <div class="flex items-center gap-3">
                                        <span class="text-slate-400">WCAG 2.1</span>
                                        <div class="flex items-center gap-2">
                                             <span class="font-bold text-slate-200">{{ auditData?.accessibility?.score ?? '--' }}/100</span>
                                             <span v-if="auditData?.accessibility?.grade" class="px-1.5 py-0.5 rounded text-[10px] font-bold" 
                                                   :class="['A','AA','AAA'].includes(auditData.accessibility.grade) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'">
                                                 {{ auditData.accessibility.grade }}
                                             </span>
                                             <span v-else class="text-slate-500 text-[10px]">N/A</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Radar Visualizer & Tech Stack -->
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                                    
                                    <!-- Radar Chart Area -->
                                    <!-- Radar Chart Area -->
<div class="lg:col-span-1 flex flex-col gap-6 self-start">
                                        <!-- Radar Chart Area -->
                                        <div class="p-5 rounded-xl border border-indigo-500/20 bg-gradient-to-b from-[#0f172a] to-[#0a0f1a] flex flex-col">
                                            <div class="flex items-center justify-between mb-4">
                                                <h4 class="text-xs font-bold text-indigo-400 uppercase tracking-widest">Forensic Vectors</h4>
                                                <span class="text-[10px] text-slate-500">v2.1 SCAN</span>
                                            </div>
                                            
                                            <!-- Chart -->
                                            <div class="relative w-full flex-1 min-h-[220px]">
                                                <Radar :data="chartData" :options="chartOptions" />
                                            </div>
                                            
                                            <!-- Legend with Tooltips -->
                                            <div class="mt-4 grid grid-cols-5 gap-1 border-t border-white/5 pt-3">
                                                <div v-for="(score, key, idx) in radarData" :key="key" 
                                                     class="group relative flex flex-col items-center cursor-help hover:bg-white/5 rounded p-1 transition-colors"
                                                >
                                                    <span class="text-[8px] text-slate-500 uppercase tracking-tight mb-0.5 truncate w-full text-center">
                                                        {{ String(key).replace('_', ' ').replace('architecture', '').replace('perimeter', '').replace('maturity', '').replace('authority', '').trim() }}
                                                    </span>
                                                    <span class="text-xs font-bold text-indigo-400">{{ score }}</span>
                                                    
                                                    <!-- Intelligent Tooltip (Popover) -->
                                                    <div 
                                                        class="absolute bottom-full mb-2 w-48 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50 translate-y-2 group-hover:translate-y-0"
                                                        :class="[
                                                            idx === 0 || idx === 1 ? 'left-0 translate-x-0' : 
                                                            idx === 3 || idx === 4 ? 'right-0 translate-x-0' : 
                                                            'left-1/2 -translate-x-1/2'
                                                        ]"
                                                    >
                                                        <div class="bg-slate-900 border border-indigo-500/30 rounded-lg p-3 shadow-[0_0_20px_rgba(0,0,0,0.5)] text-left backdrop-blur-xl">
                                                            <p class="text-[10px] font-bold text-indigo-400 uppercase mb-1 border-b border-indigo-500/20 pb-1">
                                                                {{ String(key).replace('_', ' ') }} Analysis
                                                            </p>
                                                            <p class="text-[10px] text-slate-300 leading-tight mb-2 font-light">
                                                                {{ auditData?.vector_details?.[String(key)]?.insight || 'Deep forensic analysis of ' + String(key).replace('_', ' ') }}
                                                            </p>
                                                            <div class="text-[9px] text-emerald-400 bg-emerald-500/10 p-1.5 rounded border border-emerald-500/20" v-if="auditData?.vector_details?.[String(key)]?.improvement">
                                                                <span class="font-bold">FIX:</span> {{ auditData?.vector_details?.[String(key)]?.improvement }}
                                                            </div>
                                                        </div>
                                                        <div class="w-2 h-2 bg-slate-900 border-r border-b border-indigo-500/30 transform rotate-45 absolute -bottom-1"
                                                             :class="[
                                                                idx === 0 || idx === 1 ? 'left-6' : 
                                                                idx === 3 || idx === 4 ? 'right-6' : 
                                                                'left-1/2 -translate-x-1/2'
                                                             ]"
                                                        ></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Language Breakdown (Separate Card) -->
                                        <div class="p-5 rounded-xl border border-white/5 bg-[#0f172a] flex flex-col" v-if="auditData?.languages?.length">
                                            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Languages</h4>
                                            <div class="space-y-3">
                                                <div v-for="lang in auditData.languages" :key="lang.name" class="group">
                                                    <div class="flex justify-between text-[10px] items-end mb-1">
                                                        <span class="font-mono text-slate-300 font-bold">{{ lang.name }}</span>
                                                        <span class="text-slate-500">{{ lang.percentage }}%</span>
                                                    </div>
                                                    <div class="h-1.5 w-full bg-slate-800 rounded-full overflow-hidden">
                                                        <div 
                                                            class="h-full rounded-full transition-all duration-1000 ease-out"
                                                            :style="{ width: lang.percentage + '%', backgroundColor: getLangColor(lang.name) }"
                                                        ></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Tech Stack & Insights -->
                                    <div class="lg:col-span-2 space-y-6">
                                        
                                        <!-- Tech Footprint (Narrative Enhanced) -->
                                        <div class="p-6 rounded-xl border border-white/5 bg-[#0f172a]">
                                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Digital Footprint</h4>
                                            
                                            <!-- Narrative Section -->
                                            <div v-if="auditData?.tech_narrative" class="relative pl-4 border-l-2 border-indigo-500">
                                                <p class="text-sm font-light text-slate-300 leading-relaxed">
                                                    {{ auditData?.tech_narrative }}
                                                </p>
                                            </div>

                                            <div class="flex flex-wrap gap-2 mt-5">
                                                <div 
                                                    v-for="tech in techStack" 
                                                    :key="tech.name"
                                                    class="group relative inline-flex"
                                                    @mouseenter="hoveredTech = tech.name"
                                                    @mouseleave="hoveredTech = null"
                                                >
                                                    <!-- Badge with Pulsing Colored Dot -->
                                                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-700/50 bg-slate-800/40 hover:border-indigo-500/50 hover:bg-slate-800/60 transition-colors cursor-help">
                                                        <div class="w-2 h-2 rounded-full animate-pulse" :class="getTechBadgeColor(tech.name)"></div>
                                                        <span class="text-xs font-medium text-slate-300">{{ tech.name }}</span>
                                                    </div>
                                                    
                                                    <!-- Hover Tooltip -->
                                                    <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100">
                                                        <div 
                                                            v-if="hoveredTech === tech.name"
                                                            class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-slate-900 border border-slate-700 rounded-lg shadow-xl z-20 pointer-events-none"
                                                        >
                                                            <div class="text-[10px] font-bold text-indigo-400 uppercase mb-1">{{ tech.name }}</div>
                                                            <p class="text-xs text-slate-300 leading-tight">
                                                                {{ getTechExplanation(tech.name) }}
                                                            </p>
                                                            <!-- Arrow -->
                                                            <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-slate-700"></div>
                                                        </div>
                                                    </transition>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Insights List -->
                                        <div class="p-6 rounded-xl border border-white/5 bg-[#0f172a]">
                                            <!-- AI Observation Header with Details Button -->
                                            <div class="flex items-center justify-between mb-4" v-if="auditData?.insights?.length">
                                               <div class="flex items-center gap-2">
                                                   <div class="w-1.5 h-1.5 rounded-full bg-brand-primary animate-pulse"></div>
                                                   <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest">AI Observation</h4>
                                               </div>
                                               <button 
                                                   @click="showAIDetailsModal = true"
                                                   class="text-[10px] font-semibold text-indigo-400 hover:text-indigo-300 transition-colors flex items-center gap-1 px-2 py-1 rounded border border-indigo-500/30 hover:bg-indigo-500/10"
                                               >
                                                   <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                   View Details
                                               </button>
                                            </div>

                                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Key Evidence</h4>
                                            <ul class="space-y-3">
                                                <li v-for="(insight, idx) in (auditData?.insights || []).slice(0, 3)" :key="idx" class="flex gap-3 items-start">
                                                    <span class="flex-shrink-0 mt-1 w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                                    <span class="text-sm text-slate-400 leading-relaxed">{{ insight }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                        
                                        <!-- Executive Summary (New) -->
                                        <div class="p-6 rounded-xl border border-white/5 bg-[#0f172a] mt-8 opacity-0 animate-[fadeIn_0.5s_ease-out_0.5s_forwards]" v-if="auditData?.executive_summary">
                                            <h4 class="text-xs font-bold text-emerald-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                Executive Summary
                                            </h4>
                                            <div class="text-sm text-slate-300 leading-relaxed font-light whitespace-pre-line border-t border-white/5 pt-3">
                                                {{ auditData.executive_summary }}
                                            </div>
                                        </div>

                                        <!-- About Section (New) -->
                                        <div class="p-6 rounded-xl border border-white/5 bg-[#0f172a] mt-8 opacity-0 animate-[fadeIn_0.5s_ease-out_0.7s_forwards]" v-if="auditData?.about_project || auditData?.business_summary">
                                            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                About Platform
                                            </h4>
                                            <div class="text-sm text-slate-400 leading-relaxed font-light">
                                                {{ auditData?.about_project || auditData?.business_summary }}
                                            </div>
                                        </div>
                                        
                                        <!-- Analytics Dashboard Removed as requested -->
                                        <!-- Spacer -->
                                        </div>

<!-- Enterprise Governance (New Row) -->
                                    <div class="p-6 rounded-xl border border-white/5 bg-[#0f172a] lg:col-span-3" v-if="auditData">
                                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                            Enterprise Governance
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            
                                            <!-- Card 1: Supply Chain Risk -->
                                            <div class="bg-slate-900/50 rounded-lg p-4 border border-white/5 flex flex-col items-center text-center relative overflow-hidden group">
                                                <div class="absolute inset-0 bg-gradient-to-b from-transparent to-black/20 pointer-events-none"></div>
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center mb-3 transition-colors"
                                                     :class="(auditData.supply_chain_risk?.level === 'high') ? 'bg-red-500/10 text-red-500' : 'bg-emerald-500/10 text-emerald-500'"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                                </div>
                                                <h5 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Supply Chain</h5>
                                                <span class="text-sm font-bold capitalize"
                                                      :class="(auditData?.supply_chain_risk?.level === 'high') ? 'text-red-400' : 'text-emerald-400'"
                                                >
                                                    {{ auditData?.supply_chain_risk?.level || 'Unknown' }} Risk
                                                </span>
                                                <div class="mt-2 text-[10px] text-slate-400 leading-tight" v-if="auditData?.supply_chain_risk?.vulnerabilities?.length">
                                                    <span class="block text-slate-500 mb-0.5">Vulnerabilities:</span>
                                                    {{ (auditData?.supply_chain_risk?.vulnerabilities || []).slice(0, 2).join(', ') }}
                                                </div>
                                                <div class="mt-2 text-[10px] text-emerald-500/80" v-else>
                                                    No known CVEs detected.
                                                </div>
                                            </div>

                                            <!-- Card 2: Compliance -->
                                            <div class="bg-slate-900/50 rounded-lg p-4 border border-white/5 flex flex-col items-center text-center relative overflow-hidden group">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center mb-3 transition-colors"
                                                     :class="auditData?.compliance_check?.gdpr_compliant ? 'bg-emerald-500/10 text-emerald-500' : 'bg-amber-500/10 text-amber-500'"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg>
                                                </div>
                                                <h5 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Compliance</h5>
                                                <span class="text-sm font-bold"
                                                      :class="auditData?.compliance_check?.gdpr_compliant ? 'text-emerald-400' : 'text-amber-400'"
                                                >
                                                    {{ auditData?.compliance_check?.gdpr_compliant ? 'GDPR Ready' : 'Liability Risk' }}
                                                </span>
                                                <div class="mt-2 text-[10px] text-slate-400 leading-tight" v-if="auditData?.compliance_check?.missing_policies?.length">
                                                    <span class="block text-slate-500 mb-0.5">Missing:</span>
                                                    {{ (auditData?.compliance_check?.missing_policies || []).join(', ') }}
                                                </div>
                                                <div class="mt-2 text-[10px] text-emerald-500/80" v-else>
                                                    Policies found.
                                                </div>
                                            </div>

                                            <!-- Card 3: Eco-Index -->
                                            <div class="bg-slate-900/50 rounded-lg p-4 border border-white/5 flex flex-col items-center text-center relative overflow-hidden group">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center mb-3 transition-colors"
                                                     :class="{
                                                         'bg-emerald-500/10 text-emerald-500': ['A','B'].includes(auditData?.carbon_footprint?.grade || ''),
                                                         'bg-amber-500/10 text-amber-500': ['C'].includes(auditData?.carbon_footprint?.grade || ''),
                                                         'bg-red-500/10 text-red-500': ['D','E','F'].includes(auditData?.carbon_footprint?.grade || '')
                                                     }"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                </div>
                                                <h5 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Eco-Index</h5>
                                                <div class="flex items-baseline gap-1">
                                                    <span class="text-2xl font-bold font-mono"
                                                        :class="{
                                                             'text-emerald-400': ['A','B'].includes(auditData?.carbon_footprint?.grade || ''),
                                                             'text-amber-400': ['C'].includes(auditData?.carbon_footprint?.grade || ''),
                                                             'text-red-400': ['D','E','F'].includes(auditData?.carbon_footprint?.grade || '')
                                                         }"
                                                    >{{ auditData?.carbon_footprint?.grade || 'N/A' }}</span>
                                                </div>
                                                <div class="mt-1 text-[10px] text-slate-400">
                                                    ~{{ auditData?.carbon_footprint?.estimated_g_co2 || 0 }}g CO2 / View
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <!-- About Platform (Existing) -->
                                    <div class="p-6 rounded-xl border border-white/5 bg-[#0f172a] mt-8 lg:col-span-3" v-if="auditData?.about_project || auditData?.business_summary">
                                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            About Platform
                                        </h4>
                                        <div class="text-sm text-slate-400 leading-relaxed font-light">
                                            {{ auditData?.about_project || auditData?.business_summary }}
                                        </div>
                                    </div>
                                    
                                    
                                </div>
                            </div>
                        </transition>
                    </div>
                    
                    <!-- Footer -->
                    <div class="flex-shrink-0 border-t border-indigo-500/20 bg-[#0f172a] px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2 text-[10px] text-slate-600 font-mono">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            SYSTEM_ONLINE
                        </div>
                        <div class="flex gap-3">
                            <button 
                                @click="$emit('deep-audit')"
                                class="flex items-center gap-2 px-4 py-2 bg-indigo-600/10 hover:bg-indigo-600/20 text-indigo-400 border border-indigo-500/30 text-xs font-bold uppercase tracking-wider rounded-lg transition-all hover:shadow-[0_0_15px_rgba(99,102,241,0.2)]"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                Run Deep Scan
                            </button>
                            <button 
                                @click="openChat"
                                class="flex items-center gap-2 px-5 py-2 border border-indigo-500/30 bg-indigo-500/10 text-indigo-400 text-sm font-semibold rounded-lg hover:bg-indigo-500/20 active:scale-95 transition-all hidden md:flex"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                </svg>
                                Ask LUME AI
                            </button>
                            <button 
                                @click="closeModal"
                                class="px-6 py-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-lg transition-colors"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>

    <!-- AI Details Modal (Overlay on top of main modal) -->
    <Teleport to="body">
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showAIDetailsModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4" @click.self="showAIDetailsModal = false">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/70 backdrop-blur-md"></div>
                
                <!-- Modal Content -->
                <div class="relative z-10 w-full max-w-2xl max-h-[85vh] overflow-y-auto bg-slate-900 border border-indigo-500/30 rounded-2xl shadow-2xl">
                    <!-- Header -->
                    <div class="sticky top-0 bg-slate-900/95 backdrop-blur-xl px-6 py-4 border-b border-white/10 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            AI Analysis Details
                        </h3>
                        <button @click="showAIDetailsModal = false" class="text-slate-400 hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <!-- Body -->
                    <div class="p-6 space-y-6">
                        <!-- Summary Section -->
                        <div v-if="auditData?.business_summary || auditData?.summary" class="space-y-2">
                            <h4 class="text-xs font-bold text-emerald-500 uppercase tracking-widest flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                Executive Summary
                            </h4>
                            <p class="text-sm text-slate-300 leading-relaxed bg-slate-800/50 p-4 rounded-lg border border-white/5">
                                {{ auditData?.business_summary || auditData?.summary || 'No summary available.' }}
                            </p>
                        </div>
                        
                        <!-- Key Insights Section -->
                        <div v-if="auditData?.insights?.length" class="space-y-2">
                            <h4 class="text-xs font-bold text-indigo-400 uppercase tracking-widest flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                                Key Observations
                            </h4>
                            <ul class="space-y-2 bg-slate-800/50 p-4 rounded-lg border border-white/5">
                                <li v-for="(insight, idx) in auditData.insights" :key="idx" class="flex gap-3 items-start text-sm text-slate-300">
                                    <span class="flex-shrink-0 mt-1.5 w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                    <span>{{ insight }}</span>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Recommendations Section -->
                        <div v-if="Object.keys(auditData?.vector_details || {}).length" class="space-y-2">
                            <h4 class="text-xs font-bold text-amber-500 uppercase tracking-widest flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-amber-500"></div>
                                Suggestions & Recommendations
                            </h4>
                            <div class="space-y-3 bg-slate-800/50 p-4 rounded-lg border border-white/5">
                                <div v-for="(detail, key) in (auditData?.vector_details || {})" :key="key" class="space-y-1">
                                    <p class="text-xs font-semibold text-slate-400 uppercase">{{ String(key).replace('_', ' ') }}</p>
                                    <p v-if="detail?.improvement" class="text-sm text-emerald-400 pl-3 border-l-2 border-emerald-500">
                                        {{ detail.improvement }}
                                    </p>
                                    <p v-else class="text-sm text-slate-500 pl-3">No specific recommendations for this area.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Warning Flags Section -->
                        <div v-if="auditData?.warning_flags?.length" class="space-y-2">
                            <h4 class="text-xs font-bold text-red-500 uppercase tracking-widest flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-red-500"></div>
                                Warning Flags
                            </h4>
                            <ul class="space-y-2 bg-red-950/30 p-4 rounded-lg border border-red-500/20">
                                <li v-for="(flag, idx) in auditData.warning_flags" :key="idx" class="flex gap-3 items-start text-sm text-red-400">
                                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                    <span>{{ flag }}</span>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Conclusion Section -->
                        <div v-if="auditData?.executive_summary" class="space-y-2">
                            <h4 class="text-xs font-bold text-cyan-500 uppercase tracking-widest flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-cyan-500"></div>
                                Conclusion
                            </h4>
                            <p class="text-sm text-slate-300 leading-relaxed bg-slate-800/50 p-4 rounded-lg border border-white/5">
                                {{ auditData.executive_summary }}
                            </p>
                        </div>
                        
                        <!-- Marketplace Eligibility -->
                        <div class="pt-4 border-t border-white/10">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-400">Marketplace Eligible</span>
                                <span :class="auditData?.is_marketplace_eligible ? 'text-emerald-400' : 'text-red-400'" class="font-semibold text-sm">
                                    {{ auditData?.is_marketplace_eligible ? 'Yes' : 'No' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
    
    <!-- LUME AI Chat Modal -->
    <!-- LUME AI Chat Modal -->
    <ProjectAnalystModal 
        v-if="asset"
        :show="showAIModal" 
        :asset="asset"
        @close="showAIModal = false" 
    />
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #020617; /* slate-950 */
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #334155; /* slate-700 */
    border-radius: 9999px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #6366f1; /* indigo-500 */
}
</style>
