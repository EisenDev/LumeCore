<script setup lang="ts">
/**
 * GithubRepositoryForensicModal Component
 * specialized for Source Code Analysis.
 */
import { computed, ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Radar, Pie } from 'vue-chartjs';
import {
    Chart as ChartJS,
    RadialLinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend,
    ArcElement,
} from 'chart.js';
import type { VaultAsset } from '@/types/vault';
import MetricSparkline from '@/Components/Visualizers/MetricSparkline.vue';
import ToxicityTreemap from '@/Components/Visualizers/ToxicityTreemap.vue';
import ToxicityDetailedModal from '@/Components/Visualizers/ToxicityDetailedModal.vue';
import ForensicInsightsModal from '@/Components/Visualizers/ForensicInsightsModal.vue';
import VectorAnalyticsModal from '@/Components/Visualizers/VectorAnalyticsModal.vue';

ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend, ArcElement);

interface Props {
    show: boolean;
    asset: VaultAsset | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh'): void;
}>();

// Animation state
const animatedScore = ref(0);
const showContent = ref(false);
const isScanning = ref(false);
const scanProgress = ref(0);
const scanLogs = ref<{time: string, message: string, type: string}[]>([]);
const terminalBody = ref<HTMLElement | null>(null);
const showDetailedToxicity = ref(false);
const showInsights = ref(false);
const showVectorAnalytics = ref(false);

let scoreAnimationFrame: number | null = null;
const currentChannel = ref<any>(null);

function animateScoreTo(targetScore: number) {
    if (scoreAnimationFrame) cancelAnimationFrame(scoreAnimationFrame);
    const duration = 1500;
    const startTime = performance.now();
    const startScore = animatedScore.value;
    
    function tick(currentTime: number) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeOut = 1 - Math.pow(1 - progress, 3);
        
        // Support Float: Keep 2 decimal places during animation
        const currentVal = startScore + (targetScore - startScore) * easeOut;
        animatedScore.value = Number(currentVal.toFixed(2));
        
        if (progress < 1) {
            scoreAnimationFrame = requestAnimationFrame(tick);
        }
    }
    scoreAnimationFrame = requestAnimationFrame(tick);
}

const auditData = computed(() => {
    if (!props.asset?.metadata) return null;
    return props.asset.metadata as any;
});

// Computed Metrics
const codeQuality = computed(() => auditData.value?.code_quality ?? {});
const techAssessment = computed(() => auditData.value?.tech_assessment ?? {});
const forensics = computed(() => auditData.value?.forensics ?? {});
const languages = computed(() => auditData.value?.languages ?? []);

// Computed: Radar Data for Code Vectors
const radarData = computed(() => {
    const rawRadar = auditData.value?.radar_data || {};
    const rawHex = auditData.value?.hexagon_vectors || {};
    
    // Merge both sources, preferring non-zero values
    const merged = { ...rawRadar, ...rawHex };
    
    // Normalize keys to snake_case just in case
    const normalized: Record<string, number> = {};
    Object.keys(merged).forEach(k => {
        const key = k.toLowerCase().replace(/ /g, '_');
        normalized[key] = Number(merged[k]) || 0;
    });

    return {
        code_efficiency: normalized.code_efficiency || 0,
        security_perimeter: normalized.security_perimeter || 0,
        infrastructure_maturity: normalized.infrastructure_maturity || 0,
        database_architecture: normalized.database_architecture || 0,
        supply_chain_governance: normalized.supply_chain_governance || 0,
        client_side_velocity: normalized.client_side_velocity || 0
    };
});

const chartData = computed(() => ({
    labels: [
        'Code Quality', 
        'Security', 
        'Architecture', 
        'Database', 
        'Supply Chain', 
        'Performance'
    ],
    datasets: [{
        label: 'Repo Score',
        data: Object.values(radarData.value),
        backgroundColor: 'rgba(203, 180, 138, 0.2)', // Emerald tint
        borderColor: '#CBB48A',
        borderWidth: 2,
        pointBackgroundColor: '#CBB48A',
        pointBorderColor: '#fff'
    }]
}));

const supplyChain = computed(() => auditData.value?.supply_chain_stats ?? { direct_dependencies: 0, shadow_dependencies: 0, verdict: 'Unknown' });
const ghostCode = computed(() => auditData.value?.ghost_code ?? { percentage: 0, unused_files_estimate: 0, explanation: 'No data' });

const busFactorPieData = computed(() => {
    const authors = forensics.value?.bus_factor?.authors || [];
    // Sort by commits desc
    const sorted = [...authors].sort((a: any, b: any) => b.commits - a.commits);
    
    return {
        labels: sorted.map((a: any) => a.name),
        datasets: [{
            data: sorted.map((a: any) => a.commits),
            backgroundColor: [
                '#CBB48A', // Emerald 500
                '#CBB48A', // Cyan 500
                '#14b8a6', // Teal 500
                '#2dd4bf', // Teal 400
                '#4ade80', // Green 400
                '#64748b', // Slate 500
            ],
            borderWidth: 0
        }]
    };
});

const busFactorOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx: any) => `${ctx.label}: ${ctx.raw} commits`
            }
        }
    }
};

const getBusFactorColor = (index: any) => {
    const i = Number(index);
    const colors = busFactorPieData.value.datasets[0].backgroundColor;
    return Array.isArray(colors) ? (colors[i] as string) : '#cbd5e1';
};

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        r: {
            beginAtZero: true,
            max: 100,
            ticks: { display: false },
            grid: { color: 'rgba(255, 255, 255, 0.1)' },
            pointLabels: { 
                color: '#94a3b8', 
                font: { family: 'monospace', size: 10 } 
            },
            angleLines: { color: 'rgba(255, 255, 255, 0.1)' }
        }
    },
    plugins: { 
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.9)',
            titleColor: '#CBB48A',
            bodyColor: '#cbd5e1',
            borderColor: '#334155',
            borderWidth: 1,
            padding: 10,
            callbacks: {
                label: (context: any) => `Score: ${context.raw}/100`,
                afterBody: (context: any) => {
                    // Match label to vector_details key
                    const labelMap: Record<string, string> = {
                        'Code Quality': 'code_efficiency',
                        'Security': 'security_perimeter',
                        'Architecture': 'infrastructure_maturity',
                        'Database': 'database_architecture',
                        'Supply Chain': 'supply_chain_governance',
                        'Performance': 'client_side_velocity'
                    };
                    const label = context[0].label;
                    const key = labelMap[label];
                    const details = auditData.value?.vector_details?.[key];
                    
                    if (details) {
                        return [
                            '', // Spacer
                            `AI Analysis: ${details.explanation || details.insight || 'No details available.'}`,
                            '',
                            `Tip: ${details.improvement_tip || details.improvement || 'No tips available.'}`
                        ];
                    }
                    return [];
                }
            }
        }
    }
}));


// Real-time Listener removed (Handled by RepositoryScanning.vue)

// Fix for Race Condition: If asset data arrives/updates while modal is open
watch(() => auditData.value, (newVal) => {
    if (props.show && newVal && !showContent.value) {
        // Trigger animation if we were waiting for data
        showContent.value = true;
        animateScoreTo(newVal?.score ?? 0);
    }
});

watch(() => props.show, (val) => {
    if (val) {
        if (auditData.value) {
            showContent.value = false;
            animatedScore.value = 0;
            setTimeout(() => {
                showContent.value = true;
                animateScoreTo(auditData.value?.score ?? 0);
            }, 100);
        } else {
             // If no data yet, wait for watch(auditData) to catch it
             showContent.value = false;
        }
    }
}, { immediate: true });

// onUnmounted logic removed as no listener exists

const closeModal = () => emit('close');
</script>

<template>
    <Teleport to="body">
        <transition enter-active-class="duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="duration-200 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="show && asset" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/90 backdrop-blur-sm" @click="closeModal"></div>
                
                <div class="relative w-full max-w-7xl h-[90vh] bg-[#0a0f1a] border border-[#CBB48A]/20 rounded-xl shadow-2xl flex flex-col overflow-hidden">
                    
                    <!-- Code Forensic Header -->
                    <div class="flex items-center justify-between px-6 py-4 bg-[#05080f] border-b border-[#CBB48A]/20">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-[#CBB48A]/10 rounded-lg border border-[#CBB48A]/20">
                                <svg class="w-6 h-6 text-[#CBB48A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-white font-mono font-bold">{{ asset.file_name }}</h2>
                                <p class="text-[10px] text-[#CBB48A] font-mono tracking-widest uppercase">LUME_SOURCE_CODE_ANALYST</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-2xl font-mono font-bold" :class="animatedScore >= 80 ? 'text-[#CBB48A]' : (animatedScore >= 60 ? 'text-[#F3E7C9]' : 'text-rose-400')">
                                {{ animatedScore }}/100
                            </span>
                            <button @click="closeModal" class="text-slate-500 hover:text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="flex-1 overflow-y-auto custom-scrollbar bg-[#0a0f1a] p-6">
                        
                        <!-- Results View -->
                        <div v-if="showContent && auditData" class="grid grid-cols-1 lg:grid-cols-4 gap-6 animate-fade-in">
                            
                            <!-- Middle: Architecture & Insights (NOW FIRST for Strategic Context) -->
                            <div class="lg:col-span-2 space-y-6 lg:order-2">
                                
                                <!-- Strategic Context (New) -->
                                <div v-if="auditData.niche || auditData.executive_summary" class="bg-[#0f172a] border border-[#F3E7C9]/20 rounded-xl p-6 relative overflow-hidden group">
                                     <div class="absolute inset-0 bg-[#F3E7C9]/5 group-hover:bg-[#F3E7C9]/10 transition-colors"></div>
                                     
                                     <h3 class="text-xs font-bold text-[#F3E7C9] uppercase tracking-widest mb-3 flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#F3E7C9] animate-pulse"></span>
                                        Strategic Context
                                     </h3>

                                     <!-- Niche / Type -->
                                     <div class="mb-3" v-if="auditData.niche">
                                        <div class="inline-flex items-center gap-2 px-2 py-1 rounded bg-[#F3E7C9]/10 border border-[#F3E7C9]/20">
                                            <span class="text-xs text-[#F3E7C9]/70 font-mono">{{ auditData.niche }} Repository</span>
                                        </div>
                                     </div>

                                     <p class="text-slate-300 leading-relaxed italic text-sm">"{{ auditData.executive_summary }}"</p>
                                     <div class="mt-4 flex gap-2">
                                         <span class="px-2 py-1 rounded bg-slate-800 text-slate-400 text-xs border border-slate-700">
                                             Architecture: {{ techAssessment.architecture || 'Monolith' }}
                                         </span>
                                     </div>
                                </div>

                                <!-- Toxicity Treemap -->
                                <div v-if="forensics.toxicity" class="bg-white/5 border border-white/10 rounded-xl p-4">
                                     <div class="flex justify-between items-center mb-4">
                                        <div class="flex items-center gap-2">
                                            <h3 class="text-xs font-bold text-rose-400 uppercase">Code Toxicity (Heatmap)</h3>
                                            <span class="text-[10px] text-slate-500">Churn vs Complexity</span>
                                        </div>
                                        <button @click="showDetailedToxicity = true" class="text-[10px] text-[#CBB48A] hover:text-[#CBB48A]/70 underline font-mono">
                                            View Detailed Heatmap
                                        </button>
                                     </div>
                                     <ToxicityTreemap :data="forensics.toxicity" />
                                </div>

                                <!-- Major Tech Stack (4 Columns) -->
                                <div>
                                    <h3 class="text-xs font-bold text-slate-500 uppercase mb-3 flex items-center gap-2">
                                        Digital Footprint (Tech Stack)
                                    </h3>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        <div v-for="tech in techAssessment.stack?.filter((t: any) => t.category !== 'Language' && t.category !== undefined)" :key="tech.name" 
                                             class="bg-white/5 border border-white/10 p-2 rounded hover:bg-white/10 transition-colors flex flex-col justify-between h-full">
                                            <div class="flex items-center gap-2 mb-1">
                                                <!-- TITAN V8.4: Support Hex Colors -->
                                                <div class="w-2 h-2 rounded-full shadow-[0_0_8px_rgba(255,255,255,0.3)]" 
                                                     :style="tech.dot_color?.startsWith('#') ? { backgroundColor: tech.dot_color } : {}"
                                                     :class="!tech.dot_color?.startsWith('#') ? `bg-${tech.dot_color || 'slate'}-500` : ''">
                                                </div>
                                                <span class="font-bold text-slate-200 text-xs truncate" :title="tech.name">{{ tech.name }}</span>
                                            </div>
                                            <div>
                                                <div class="text-[9px] text-slate-500 uppercase tracking-tight">{{ tech.category }}</div>
                                                <div class="text-[9px] text-[#CBB48A]/70">{{ tech.version }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Left: Score, Tech Stack & Radar (Now order 1) -->
                            <div class="space-y-6 lg:col-span-1 lg:order-1">
                                <!-- ... (Radar Chart) ... -->
                                <div class="bg-white/5 border border-white/10 rounded-xl p-4 relative overflow-hidden group">
                                    <div class="absolute inset-0 bg-[#CBB48A]/5 group-hover:bg-[#CBB48A]/10 transition-colors pointer-events-none"></div>
                                    <div class="flex justify-between items-center mb-2 relative z-10">
                                         <h3 class="text-[10px] font-bold text-slate-500 uppercase">Hexagon Vectors</h3>
                                         <button @click="showVectorAnalytics = true" class="text-[10px] text-[#CBB48A] hover:text-[#CBB48A]/70 underline font-mono">
                                            View Analytics
                                         </button>
                                    </div>
                                    <div class="relative z-10 h-64">
                                        <Radar :data="chartData" :options="chartOptions" />
                                    </div>
                                </div>

                                <!-- ... (Code Quality) ... -->
                                <div class="bg-white/5 border border-white/10 rounded-xl p-5" title="Code Quality Rating based on spaghetti code, formatting, and best practices.">
                                    <h3 class="text-sm font-bold text-slate-400 uppercase mb-4 flex items-center gap-2">
                                        Code Quality
                                        <svg class="w-3 h-3 text-slate-500 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </h3>
                                    <div class="flex items-center justify-between">
                                        <div class="text-5xl font-mono font-black text-[#CBB48A] text-shadow-glow">{{ codeQuality.rating || 'N/A' }}</div>
                                        <div class="text-right">
                                            <div class="text-xs text-slate-500">SPAGHETTI LEVEL</div>
                                            <div class="font-mono text-amber-400">{{ codeQuality.spaghetti_level || 'Unknown' }}</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- ... (Languages) ... -->
                                <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                                    <h3 class="text-xs font-bold text-slate-500 uppercase mb-3">Languages</h3>
                                    <div class="space-y-3" v-if="languages.length">
                                        <div v-for="lang in languages" :key="lang.name">
                                            <div class="flex justify-between text-xs text-slate-300 mb-1">
                                                <span>{{ lang.name }}</span>
                                                <span class="text-slate-500">{{ lang.percentage }}%</span>
                                            </div>
                                            <div class="h-1.5 bg-slate-800 rounded-full overflow-hidden">
                                                <div class="h-full bg-[#F3E7C9] shadow-[0_0_10px_rgba(6,182,212,0.4)]" :style="{ width: `${lang.percentage}%` }"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-slate-600 italic">No language data detected.</div>
                                </div>
                            </div>

                            <!-- Right: Bus Factor, Ghost Code, Supply Chain, Pulse (Order 3) -->
                            <div class="space-y-6 lg:col-span-1 lg:order-3">
                                
                                <!-- Feature 1: Bus Factor Risk (Pie Chart) -->
                                <div v-if="forensics.bus_factor" class="bg-white/5 border border-white/10 rounded-xl p-5 relative overflow-hidden">
                                     <div class="flex justify-between items-start mb-6">
                                        <div class="flex items-center gap-2 group relative">
                                            <h3 class="text-xs font-bold text-slate-400 uppercase">Bus Factor Risk</h3>
                                            <svg class="w-3 h-3 text-slate-600 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            
                                            <!-- Tooltip -->
                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-slate-800 border border-slate-700 rounded shadow-xl text-[10px] text-slate-300 pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity z-50">
                                                Risk resulting from information and capabilities not being shared among team members.
                                            </div>
                                        </div>
                                        <div class="flex flex-col items-end">
                                            <span class="text-[10px] font-mono px-2 py-0.5 rounded" 
                                                :class="forensics.bus_factor.bus_factor_score <= 3 ? 'bg-rose-500/20 text-rose-400' : 'bg-[#CBB48A]/20 text-[#CBB48A]'">
                                                {{ forensics.bus_factor.bus_factor_score <= 3 ? 'CRITICAL' : 'HEALTHY' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="h-32 flex items-center justify-center relative">
                                        <Pie :data="busFactorPieData" :options="busFactorOptions" />
                                        <!-- Donut Hole Text -->
                                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                            <div class="text-xs text-slate-500 font-mono">
                                                {{ forensics.bus_factor.authors.length }} Devs
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Legend -->
                                    <div class="mt-4 space-y-1">
                                        <div v-for="(author, idx) in forensics.bus_factor.authors.slice(0, 3)" :key="author.name" class="flex justify-between text-[10px] text-slate-400">
                                            <div class="flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: getBusFactorColor(idx) }"></div>
                                                <span class="truncate max-w-[100px]">{{ author.name }}</span>
                                            </div>
                                            <span>{{ author.percent }}%</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Feature 5: Ghost Code (Bar) -->
                                <div class="bg-white/5 border border-white/10 rounded-xl p-5" title="Code created but never used.">
                                    <div class="flex justify-between items-center mb-2">
                                        <div class="flex items-center gap-2 group relative">
                                            <h3 class="text-xs font-bold text-slate-400 uppercase">Ghost Code</h3>
                                            <svg class="w-3 h-3 text-slate-600 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-slate-800 border border-slate-700 rounded shadow-xl text-[10px] text-slate-300 pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity z-50">
                                                Code that is reachable but never executed, or commented out code that should be removed.
                                            </div>
                                        </div>
                                        <span class="text-xs font-mono text-slate-300">{{ ghostCode.percentage }}% Dead</span>
                                    </div>
                                    <div class="h-4 bg-slate-800 rounded-full overflow-hidden flex mb-2 border border-slate-700/50">
                                        <!-- Healthy Code -->
                                        <div class="bg-[#CBB48A] h-full shadow-[inset_0_0_10px_rgba(0,0,0,0.2)]" :style="{ width: `${100 - ghostCode.percentage}%` }"></div>
                                        <!-- Ghost Code -->
                                        <div class="bg-slate-600 h-full relative group" :style="{ width: `${ghostCode.percentage}%` }">
                                            <div class="absolute inset-0 bg-repeat bg-[length:4px_4px] opacity-20"
                                                style="background-image: repeating-linear-gradient(45deg, transparent, transparent 2px, #000 2px, #000 4px);">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-[9px] text-slate-500 uppercase tracking-wider">
                                        <span>Effective Logic</span>
                                        <span>Boilerplate / Unused</span>
                                    </div>
                                </div>

                                <!-- Feature 4: Supply Chain (Badge/Grid) -->
                                <div class="bg-white/5 border border-white/10 rounded-xl p-5">
                                    <div class="flex justify-between items-center mb-2">
                                        <div class="flex items-center gap-2 group relative">
                                            <h3 class="text-xs font-bold text-slate-400 uppercase">Supply Chain</h3>
                                            <svg class="w-3 h-3 text-slate-600 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-slate-800 border border-slate-700 rounded shadow-xl text-[10px] text-slate-300 pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity z-50">
                                                Analysis of third-party dependencies, including direct and transitive (shadow) packages.
                                            </div>
                                        </div>
                                        <span class="text-[10px] uppercase border px-1.5 py-0.5 rounded"
                                            :class="supplyChain.verdict === 'Bloated' ? 'border-rose-500 text-rose-400' : 'border-[#CBB48A] text-[#CBB48A]'">
                                            {{ supplyChain.verdict }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-center">
                                        <div class="bg-black/30 rounded p-2 border border-slate-700">
                                            <div class="text-lg font-mono font-bold text-white">{{ supplyChain.direct_dependencies }}</div>
                                            <div class="text-[9px] text-slate-500 uppercase">Direct</div>
                                        </div>
                                        <div class="bg-black/30 rounded p-2 border border-slate-700 relative overflow-hidden">
                                            <div class="absolute inset-0 bg-rose-500/5 animate-pulse" v-if="supplyChain.shadow_dependencies > 100"></div>
                                            <div class="text-lg font-mono font-bold text-slate-300">{{ supplyChain.shadow_dependencies }}</div>
                                            <div class="text-[9px] text-slate-500 uppercase">Shadow</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Feature 3: Pulse Sparkline -->
                                <div v-if="forensics.pulse" class="bg-white/5 border border-white/10 rounded-xl p-5">
                                    <div class="flex items-center gap-2 mb-2 group relative">
                                        <h3 class="text-[10px] font-bold text-amber-500 uppercase">Repo Pulse (1 yr)</h3>
                                        <svg class="w-3 h-3 text-slate-600 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-slate-800 border border-slate-700 rounded shadow-xl text-[10px] text-slate-300 pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity z-50">
                                            Activity frequency over the last year, showing the heartbeat of development velocity.
                                        </div>
                                    </div>
                                    <div class="h-16">
                                        <MetricSparkline :history="forensics.pulse.map((p: any) => ({ 
                                            date: p.week, 
                                            score: p.commits 
                                        }))" />
                                    </div>
                                </div>

                                 <!-- Insights Button (Moved here for consistency) -->
                                <div class="text-center pt-2">
                                    <button @click="showInsights = true" class="w-full py-2 bg-[#CBB48A]/10 hover:bg-[#CBB48A]/20 border border-[#CBB48A]/50 text-[#CBB48A] text-xs font-bold uppercase tracking-wider rounded transition-colors">
                                        View Deep Insights & Findings
                                    </button>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>
            </div>
            
        </transition>

            <ToxicityDetailedModal 
                :show="showDetailedToxicity" 
                :toxicityData="forensics.toxicity" 
                @close="showDetailedToxicity = false" 
            />

            <ForensicInsightsModal 
                :show="showInsights" 
                :data="{ 
                    insights: auditData?.insights, 
                    recommendations: auditData?.recommendations, 
                    warning_flags: auditData?.warning_flags 
                }" 
                @close="showInsights = false" 
            />

            <VectorAnalyticsModal
                :show="showVectorAnalytics"
                :radarData="radarData"
                :vectorDetails="auditData?.vector_details || {}"
                :compositeScore="auditData?.score"
                @close="showVectorAnalytics = false"
            />
    </Teleport>
</template>

<style scoped>
.text-shadow-glow {
    text-shadow: 0 0 20px rgba(203, 180, 138, 0.5);
}
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #0a0f1a;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #1e293b;
    border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #334155;
}
</style>
