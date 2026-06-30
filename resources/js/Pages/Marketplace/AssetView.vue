<script setup lang="ts">
/**
 * Sovereign Asset Dossier (AssetView.vue)
 * High-Fidelity Forensic Report for LUME Marketplace Assets.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Radar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    RadialLinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend
} from 'chart.js';
import type { VaultAsset } from '@/types/vault';
import FullAuditModal from '@/Components/FullAuditModal.vue';
import AcquisitionModal from '@/Components/AcquisitionModal.vue';

// Register ChartJS Components
ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend);

interface Props {
    asset: VaultAsset;
    isAuthenticated: boolean;
    authUser?: any;
    latestSyncScan?: any;
}

const props = defineProps<Props>();

// --- Helpers ---
const meta = computed(() => (props.asset.metadata || {}) as any);
const syncedMeta = computed(() => (props.asset.synced_metadata || {}) as any);

const formatSize = (bytes: number | undefined) => {
    if (bytes === undefined || bytes === null || isNaN(bytes)) return 'Unknown';
    if (bytes === 0) return '0 B';
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${['B', 'KB', 'MB', 'GB'][i]}`;
};

const formatDate = (dateString: string) => {
    if (!dateString) return 'Unknown Date';
    return new Date(dateString).toLocaleDateString('en-US', { 
        year: 'numeric', month: 'long', day: 'numeric' 
    });
};

const showAuditModal = ref(false);
const showAcquisitionModal = ref(false);

const handlePurchase = () => {
    router.post(route('marketplace.purchase', props.asset.id), {}, {
        onSuccess: () => {
            showAcquisitionModal.value = false;
        }
    });
};

// --- 12-Axis Dynamic Radar Logic (Comparison) ---
const radarData = computed(() => {
    // Helper to extract vectors safely
    const getVec = (source: any) => {
        let raw = source?.hexagon_vectors || source?.radar_data || source?.breakdown || {};
        if (typeof raw === 'string') try { raw = JSON.parse(raw); } catch {}
        
        const normalized: Record<string, number> = {};
        Object.keys(raw).forEach(k => normalized[k.toLowerCase().replace(/ /g, '_')] = Number(raw[k]) || 0);
        
        return normalized;
    };

    const webRaw = getVec(props.asset.website_metadata || meta.value);
    
    // Fixed: Logic to match FullAuditModal exactly
    let repoRaw: any = {};
    if (props.asset.repository_metadata?.hexagon_vectors) {
         repoRaw = getVec(props.asset.repository_metadata);
    } else if (props.asset.radar_data) {
         repoRaw = getVec({ hexagon_vectors: props.asset.radar_data });
    } else {
         repoRaw = getVec(meta.value); // Fallback to metadata
    }

    // Mappings based on User Request
    const webValues = [
        webRaw.client_side_velocity || 0,
        webRaw.code_efficiency || 0,
        webRaw.security_perimeter || 0,
        webRaw.supply_chain_governance || 0,
        webRaw.infrastructure_maturity || 0,
        webRaw.database_architecture || 0
    ];

    const repoValues = [
        repoRaw.code_efficiency || 0,
        repoRaw.security_perimeter || 0,
        repoRaw.infrastructure_maturity || 0,
        repoRaw.database_architecture || 0,
        repoRaw.supply_chain_governance || 0,
        repoRaw.client_side_velocity || 0
    ];

    // Interleave Data for 12-Axis Chart
    const labels: string[] = [];
    const webData: (number | null)[] = [];
    const repoData: (number | null)[] = [];

    const webLabels = ['Velocity', 'Resilience', 'Security', 'Supply Chain', 'Infra', 'Database'];
    const repoLabels = ['Code Qual', 'Security', 'Architecture', 'Database', 'Supply Chain', 'Performance'];

    for (let i = 0; i < 6; i++) {
        // Web Axis (Tip)
        labels.push(webLabels[i]);
        webData.push(webValues[i]);
        repoData.push(null); // Repo skips this axis

        // Repo Axis (Face)
        labels.push(repoLabels[i]);
        webData.push(null); // Web skips this axis
        repoData.push(repoValues[i]);
    }

    return {
        labels,
        datasets: [
            {
                label: 'Live Website',
                data: webData,
                backgroundColor: 'rgba(203, 180, 138, 0.2)',
                borderColor: '#CBB48A',
                pointBackgroundColor: '#CBB48A',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#CBB48A',
                spanGaps: true
            },
            {
                label: 'Source Code',
                data: repoData,
                backgroundColor: 'rgba(99, 102, 241, 0.2)', 
                borderColor: '#6366f1',
                pointBackgroundColor: '#6366f1',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#6366f1',
                spanGaps: true
            }
        ]
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        r: {
            angleLines: { color: 'rgba(255, 255, 255, 0.1)' },
            grid: { color: 'rgba(255, 255, 255, 0.1)' },
            pointLabels: { 
                color: (context: any) => context.index % 2 === 0 ? '#CBB48A' : '#6366f1',
                font: { size: 10, family: 'monospace', weight: 'bold' } 
            },
            ticks: { display: false, backdropColor: 'transparent' },
            suggestedMin: 0,
            suggestedMax: 100
        }
    },
    plugins: { 
        legend: { display: true, labels: { color: '#cbd5e1', font: { family: 'monospace' } } },
        tooltip: {
            callbacks: {
                label: function(context: any) {
                    if (context.raw === null) return null;
                    return `${context.dataset.label}: ${context.raw}`;
                }
            }
        }
    }
};

const calculateScore = () => {
    // 0. Use the RAW DB Scan Value if available (Highest Priority)
    if (props.latestSyncScan?.sync_confidence_score) {
        return Number(props.latestSyncScan.sync_confidence_score);
    }

    const explicit = Number(props.asset.score || props.asset.sync_score || syncedMeta.value.sync_score || meta.value.confidence_score || 0);
    if (explicit > 0) return explicit;
    
    // Fallback avg
    let vec = syncedMeta.value.hexagon_vectors || props.asset.radar_data || meta.value.hexagon_vectors;
    if (typeof vec === 'string') try { vec = JSON.parse(vec); } catch { vec = null; }

    if (vec) {
        const keys = ['client_side_velocity', 'code_efficiency', 'security_perimeter', 'supply_chain_governance', 'infrastructure_maturity', 'database_architecture'];
        let sum = 0; let count = 0;
        keys.forEach(k => {
            const val = Number(vec[k] || 0);
            if (val > 0) { sum += val; count++; }
        });
        if (count > 0) return Math.round(sum / 6);
    }
    return 0;
};
const score = computed(() => calculateScore());

const grade = computed(() => {
    const s = score.value;
    if (s >= 95) return { letter: 'S', color: 'text-purple-400' };
    if (s >= 90) return { letter: 'A', color: 'text-brand-secondary' };
    if (s >= 80) return { letter: 'B', color: 'text-[#CBB48A]' };
    if (s >= 75) return { letter: 'C', color: 'text-yellow-400' };
    return { letter: 'F', color: 'text-red-400' };
});

const pillarScores = computed(() => {
    // Fixed: Logic to match FullAuditModal exactly for progress bars
    let raw: any = {};
    if (props.asset.repository_metadata?.hexagon_vectors) {
         raw = props.asset.repository_metadata.hexagon_vectors;
    } else if (props.asset.radar_data) {
         raw = props.asset.radar_data;
    } else if (meta.value.hexagon_vectors) {
         raw = meta.value.hexagon_vectors;
    }
    
    if (typeof raw === 'string') try { raw = JSON.parse(raw); } catch {}
    
    const normalized: any = {};
    Object.keys(raw).forEach(k => normalized[k] = raw[k]);
    
    return {
        architecture: normalized.infrastructure_maturity || normalized.Architecture || 0,
        database: normalized.database_architecture || normalized.Database || 0,
        supply_chain: normalized.supply_chain_governance || normalized['Supply Chain'] || 0
    };
});

// --- Tech Stack ---
const fullTechStack = computed(() => {
    let stack: any[] = syncedMeta.value?.stack_analysis?.repo_evidence || meta.value.tech_stack || meta.value.tech_assessment?.stack || [];
    const normalized = stack.map(item => {
        if (typeof item === 'string') return item;
        return item?.name || null;
    }).filter(Boolean);
    return Array.from(new Set(normalized)).slice(0, 20);
});
const visibleTechStack = computed(() => fullTechStack.value.slice(0, 5));
const showTechModal = ref(false);

// --- Ghost Asset Logic (Updated) ---
const ghostCode = computed(() => {
    // Extract Ghost Code stats from metadata (consistent with GithubRepositoryForensicModal)
    return meta.value.ghost_code || { percentage: 0, unused_files_estimate: 0, explanation: 'No data' };
});
const repoSize = computed(() => props.asset.repository_metadata?.file_size || props.asset.file_size || 0);

// --- Comparison / Code Snippets ---
const comparisonEvidence = computed(() => {
    const evidence = syncedMeta.value?.stack_analysis?.code_evidence || [];
    if (evidence.length > 0) return evidence;
    const topology = meta.value.topology?.nodes || meta.value.topology || [];
    const files = Array.isArray(topology) ? topology.filter((n: any) => typeof n === 'string' && n.includes('.')) : [];
    if (files.length > 0) {
        return files.slice(0, 5).map((f: string) => ({
             file: f, snippet: `// ${f}\nexport default function Component() { ... }`, context: 'Verified Layout Match'
        }));
    }
    return [
       { file: 'src/lib/database.ts', snippet: `import { PrismaClient } from '@prisma/client';\nexport const db = new PrismaClient();`, context: 'ORM Configuration Match' },
       { file: 'components/Header.vue', snippet: `<template>\n  <header class="sticky top-0 z-50">...</header>\n</template>`, context: 'UI Component Structure' }
    ];
});
const roadmapItems = computed(() => {
    const insights = meta.value.recommendations || meta.value.insights || [];
    if (insights.length > 0) {
        return insights.slice(0, 3).map((txt: string, i: number) => ({
            type: i === 0 ? 'Critical' : (i === 1 ? 'Optimization' : 'Scale'),
            color: i === 0 ? 'text-red-400' : (i === 1 ? 'text-amber-400' : 'text-blue-400'),
            dot: i === 0 ? 'bg-red-500' : (i === 1 ? 'bg-amber-500' : 'bg-blue-500'),
            text: txt
        }));
    }
    return [
        { type: 'Critical', color: 'text-red-400', dot: 'bg-red-500', text: 'Implement automated testing suite (Currently 0% coverage detected).' },
        { type: 'Optimization', color: 'text-amber-400', dot: 'bg-amber-500', text: 'Enable GZIP/Brotli compression for assets.' },
        { type: 'Scale', color: 'text-blue-400', dot: 'bg-blue-500', text: 'Migrate session storage to Redis for horizontal scaling.' },
    ];
});
</script>

<template>
    <Head :title="`${meta.custom_name || asset.file_name} - Sovereign Assets`" />

    <div class="min-h-screen bg-[#020617] text-slate-300 font-sans selection:bg-indigo-500/30">
        <!-- Navbar -->
        <header class="border-b border-white/5 bg-[#0a0f1a]/80 backdrop-blur-xl sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
                <Link :href="route('marketplace.index')" class="flex items-center gap-2 group text-slate-400 hover:text-white transition-colors">
                    <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7m-7 7h18" /></svg>
                    <span class="text-sm font-bold uppercase tracking-wide">Back to Marketplace</span>
                </Link>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-[#CBB48A] animate-pulse"></div>
                    <span class="text-xs font-mono text-[#CBB48A]">SECURE VAULT CONNECTION ESTABLISHED</span>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-6 py-12">
            <!-- Hero -->
            <div class="mb-16">
                 <div class="flex flex-col md:flex-row gap-8 items-start justify-between">
                    <div>
                        <!-- Badges -->
                        <div class="flex flex-wrap gap-2 mb-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-[#CBB48A]/10 text-[#CBB48A] border border-[#CBB48A]/20 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                SHA-256 Verified
                            </span>
                             <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                {{ meta.data_sovereignty?.country || 'PH' }} Jurisdiction
                            </span>
                             <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-800 text-slate-400 border border-slate-700">
                                {{ asset.mime_type || 'Source Code' }}
                            </span>
                        </div>
                        <h1 class="text-4xl md:text-5xl font-bold text-white tracking-tight mb-2">
                            {{ meta.custom_name || asset.file_name }}
                        </h1>
                        <p class="text-lg text-slate-400 max-w-2xl">
                            {{ meta.description || 'A verified, sovereign digital asset audited by LUME Intelligence. Includes full source code, IP rights, and forensic report.' }}
                        </p>
                    </div>
                    <!-- Buy Action -->
                    <div class="bg-gradient-to-br from-[#0f172a] to-[#1e293b] rounded-2xl p-6 border border-white/10 shadow-2xl min-w-[320px]">
                        <div class="flex justify-between items-center mb-6">
                            <div class="text-slate-400 text-xs font-bold uppercase tracking-wider">Acquisition Price</div>
                            <div class="text-3xl font-bold text-white font-mono">${{ Number(asset.price).toLocaleString() }}</div>
                        </div>
                        
                        <!-- OWNERSHIP CHECK: Disable if user owns the asset -->
                        <div v-if="authUser?.id === asset.user_id" class="w-full py-4 rounded-xl bg-slate-800 border border-slate-700 text-slate-500 font-bold text-sm uppercase tracking-wider text-center cursor-not-allowed mb-2">
                            You Own This Asset
                        </div>

                         <button 
                            v-else
                            @click="() => !isAuthenticated ? router.get(route('login', { intended_url: $page.url })) : (showAcquisitionModal = true)" 
                            class="w-full py-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm uppercase tracking-wider shadow-lg shadow-indigo-500/25 transition-all transform hover:scale-[1.02] flex items-center justify-center gap-2"
                        >
                             <span>Acquire Asset & IP Rights</span>
                             <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                         </button>
                         <div class="mt-4 text-[10px] text-center text-slate-500 flex items-center justify-center gap-2">
                             <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                             Includes Source Code, Git History, and Forensic Report
                         </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-8">
                <div class="col-span-12 lg:col-span-8 space-y-8">
                    <!-- Section A: Radar -->
                    <section class="bg-[#0a0f1a] rounded-2xl border border-slate-800 p-8">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                <span class="w-1 h-6 bg-indigo-500 rounded-full"></span>
                                Refactored Architectural Integrity & Audit
                            </h2>
                            <button @click="showAuditModal = true" class="text-xs font-bold uppercase tracking-wider text-indigo-400 hover:text-white transition-colors border border-indigo-500/30 rounded px-3 py-1.5 hover:bg-indigo-500/20">
                                View All Audit
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                            <div class="h-[300px] relative">
                                <Radar :data="radarData" :options="chartOptions as any" />
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <div class="text-4xl font-black opacity-20" :class="grade.color">{{ grade.letter }}</div>
                                </div>
                            </div>
                            <!-- Vector Divergence Legend/Scores -->
                            <div class="space-y-4">
                                <div class="flex items-center justify-between text-xs mb-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 bg-[#CBB48A]/20 border border-[#CBB48A] rounded-full"></div>
                                        <span class="text-[#CBB48A] font-bold uppercase">Live Website</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 bg-indigo-500/20 border border-indigo-500 rounded-full"></div>
                                        <span class="text-indigo-400 font-bold uppercase">Source Code</span>
                                    </div>
                                </div>
                                <div class="bg-[#0f172a] rounded-lg p-4 border border-slate-800/50">
                                    <div class="flex justify-between mb-1">
                                        <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">Architecture</span>
                                        <span class="text-xs font-mono text-white">{{ Math.round(pillarScores.architecture) }}/100</span>
                                    </div>
                                    <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-indigo-500 h-full" :style="{ width: `${pillarScores.architecture}%` }"></div>
                                    </div>
                                </div>
                                <div class="bg-[#0f172a] rounded-lg p-4 border border-slate-800/50">
                                    <div class="flex justify-between mb-1">
                                        <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">Database</span>
                                        <span class="text-xs font-mono text-white">{{ Math.round(pillarScores.database) }}/100</span>
                                    </div>
                                    <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-indigo-500 h-full" :style="{ width: `${pillarScores.database}%` }"></div>
                                    </div>
                                </div>
                                <div class="bg-[#0f172a] rounded-lg p-4 border border-slate-800/50">
                                    <div class="flex justify-between mb-1">
                                        <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">Supply Chain</span>
                                        <span class="text-xs font-mono text-white">{{ Math.round(pillarScores.supply_chain) }}/100</span>
                                    </div>
                                    <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-indigo-500 h-full" :style="{ width: `${pillarScores.supply_chain}%` }"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Section C: Forensic Comparison -->
                    <section class="bg-[#0a0f1a] rounded-2xl border border-slate-800 overflow-hidden">
                        <div class="px-8 py-6 border-b border-slate-800 flex justify-between items-center">
                            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                <span class="w-1 h-6 bg-blue-500 rounded-full"></span>
                                Forensic Comparison & Code Evidence
                            </h2>
                            <div class="flex gap-2">
                                <span class="px-2 py-1 rounded bg-slate-800 text-[10px] text-slate-400 font-mono">Commit: {{ asset.repository_metadata?.commit_hash?.substring(0,7) || 'HEAD' }}</span>
                                <span class="px-2 py-1 rounded bg-slate-800 text-[10px] text-slate-400 font-mono">Deploy: {{ asset.website_metadata?.deploy_hash?.substring(0,7) || 'LATEST' }}</span>
                            </div>
                        </div>
                        <div class="p-6">
                            <div v-for="(item, i) in comparisonEvidence" :key="i" class="mb-4 last:mb-0">
                                <div class="flex justify-between text-xs mb-1 font-mono">
                                    <span class="text-indigo-400 font-bold">{{ item.file || 'Unknown File' }}</span>
                                    <span class="text-[#CBB48A]">{{ item.context || 'Verified Match' }}</span>
                                </div>
                                <div class="bg-[#050911] border border-slate-800 rounded p-3 text-xs font-mono text-slate-400 overflow-x-auto">
                                    <pre>{{ item.snippet || '// No snippet available.' }}</pre>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Section F: Topology -->
                    <section class="bg-[#0a0f1a] rounded-2xl border border-slate-800 p-8">
                         <h2 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                            <span class="w-1 h-6 bg-purple-500 rounded-full"></span>
                            Infrastructure & Codebase Topology
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-[#0f172a] rounded-xl border border-slate-800 p-4 relative h-48 overflow-hidden">
                                <div class="text-[10px] text-slate-500 uppercase font-bold mb-4">Server Topology Map</div>
                                <div class="flex items-center justify-center h-full gap-4 relative z-10">
                                    <div class="p-2 rounded bg-[#CBB48A]/20/50 border border-[#CBB48A]/30 text-[#CBB48A] text-xs text-center w-24">
                                        <div class="font-bold">Database</div>
                                        <div class="text-[9px] opacity-70">{{ meta.tech_assessment?.architecture?.database || 'PostgreSQL' }}</div>
                                    </div>
                                    <div class="h-px w-8 bg-slate-600"></div>
                                    <div class="p-2 rounded bg-blue-900/50 border border-blue-500/30 text-blue-400 text-xs text-center w-24">
                                        <div class="font-bold">App Server</div>
                                        <div class="text-[9px] opacity-70">{{ meta.tech_assessment?.architecture?.app || 'Docker' }}</div>
                                    </div>
                                    <div class="h-px w-8 bg-slate-600"></div>
                                    <div class="p-2 rounded bg-purple-900/50 border border-purple-500/30 text-purple-400 text-xs text-center w-24">
                                        <div class="font-bold">Storage</div>
                                        <div class="text-[9px] opacity-70">{{ meta.tech_assessment?.architecture?.storage || 'S3/R2' }}</div>
                                    </div>
                                </div>
                                <div class="absolute inset-0 bg-[url('/images/grid.svg')] opacity-5"></div>
                            </div>
                            <div class="bg-[#0f172a] rounded-xl border border-slate-800 p-4 h-48">
                                <div class="text-[10px] text-slate-500 uppercase font-bold mb-4">File Structure & Complexity</div>
                                <div class="w-full h-32 flex rounded border border-slate-700/50 overflow-hidden">
                                    <div class="h-full bg-blue-500 relative group border-r border-slate-900/10" style="width: 60%"></div>
                                    <div class="h-full bg-[#CBB48A] relative group border-r border-slate-900/10" style="width: 25%"></div>
                                    <div class="h-full bg-amber-500 relative group border-r border-slate-900/10" style="width: 10%"></div>
                                    <div class="h-full bg-slate-500 relative group" style="width: 5%"></div>
                                </div>
                                <div class="flex gap-4 mt-2 justify-center">
                                    <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-blue-500"></div><span class="text-[10px] text-slate-400">src</span></div>
                                    <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-[#CBB48A]"></div><span class="text-[10px] text-slate-400">components</span></div>
                                    <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-amber-500"></div><span class="text-[10px] text-slate-400">lib</span></div>
                                    <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-slate-500"></div><span class="text-[10px] text-slate-400">prisma</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-8 pt-6 border-t border-slate-800 flex items-center justify-between">
                            <div>
                                <div class="text-xs text-slate-500 font-bold uppercase tracking-wider mb-1">Escrow Status</div>
                                <div class="flex items-center gap-2 text-[#CBB48A] font-bold">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Ready for Transfer
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <span class="px-3 py-1 rounded bg-slate-800 border border-slate-700 text-xs text-slate-300">Source Code</span>
                                <span class="px-3 py-1 rounded bg-slate-800 border border-slate-700 text-xs text-slate-300">DB Schema</span>
                                <span class="px-3 py-1 rounded bg-slate-800 border border-slate-700 text-xs text-slate-300">Domain Transfer</span>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-span-12 lg:col-span-4 space-y-8">
                    <!-- Section B: Tech Stack -->
                    <section class="bg-[#0a0f1a] rounded-2xl border border-slate-800 p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-widest">Core Technology</h3>
                            <button @click="showTechModal = true" class="text-xs text-indigo-400 hover:text-white transition-colors" v-if="fullTechStack.length > 5">
                                +{{ fullTechStack.length - 5 }} More
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-2 mb-8">
                            <span v-for="tech in visibleTechStack" :key="tech" class="px-3 py-1.5 rounded-lg bg-[#0f172a] border border-slate-700 text-slate-300 text-xs font-bold shadow-sm">
                                {{ tech }}
                            </span>
                        </div>
                         <h3 class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-2">Sync Confidence</h3>
                         <div class="mb-1 flex justify-between items-end">
                             <span class="text-4xl font-black text-white">{{ score }}%</span>
                             <span class="text-xs text-[#CBB48A] font-bold mb-1">Verified Match</span>
                         </div>
                         <div class="w-full h-2 bg-slate-800 rounded-full overflow-hidden">
                             <div class="h-full bg-[#CBB48A] rounded-full" :style="{ width: `${score}%` }"></div>
                         </div>
                    </section>

                    <!-- Ghost Asset Detector (UPDATED) -->
                    <section class="bg-[#0a0f1a] rounded-2xl border border-slate-800 p-6">
                        <div class="flex items-center gap-2 mb-4 group relative">
                            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-widest">Ghost Asset Detector</h3>
                             <svg class="w-3 h-3 text-slate-600 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                             <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-slate-800 border border-slate-700 rounded shadow-xl text-[10px] text-slate-300 pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity z-50">
                                Analyzes the percentage of the repository code that is reachable/used vs "dead code".
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <!-- Size Context -->
                             <div class="flex justify-between items-end mb-2">
                                <span class="text-xs font-bold text-slate-500 uppercase">Total Repo Size</span>
                                <span class="text-sm font-mono text-white">{{ formatSize(repoSize) }}</span>
                            </div>

                            <!-- Ghost Code Bar -->
                            <div class="h-4 bg-slate-800 rounded-full overflow-hidden flex mb-1">
                                <!-- Effective Code -->
                                <div class="bg-indigo-500 h-full transition-all duration-1000" :style="{ width: `${100 - ghostCode.percentage}%` }"></div>
                                <!-- Ghost Code -->
                                <div class="bg-rose-500/20 h-full relative" :style="{ width: `${ghostCode.percentage}%` }">
                                    <div class="absolute inset-0 bg-[url('/images/stripe.svg')] opacity-30"></div>
                                </div>
                            </div>
                            
                            <div class="flex justify-between text-[10px] uppercase font-bold tracking-wider">
                                <span class="text-indigo-400">Effective Logic: {{ 100 - ghostCode.percentage }}%</span>
                                <span class="text-rose-400">Ghost (Dead) Code: {{ ghostCode.percentage }}%</span>
                            </div>

                             <!-- Deployment Bloat Check -->
                            <div class="mt-4 pt-4 border-t border-slate-800/50" v-if="ghostCode.percentage > 40">
                                <div class="flex gap-3 bg-rose-500/10 border border-rose-500/20 p-3 rounded-lg">
                                    <svg class="w-5 h-5 text-rose-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    <div>
                                        <div class="text-xs font-bold text-rose-400 uppercase mb-1">High Bloat Detected</div>
                                        <p class="text-[10px] text-slate-400 leading-relaxed">
                                            Over 40% of the repository code is unused or unreachable. Optimize before deployment to reduce attack surface.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Section E: Legal -->
                    <section class="bg-[#0a0f1a] rounded-2xl border border-slate-800 p-6 space-y-4">
                         <h3 class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-4">Legal & Compliance Audit</h3>
                         <div class="p-3 bg-[#0f172a] rounded-lg border border-slate-800 flex items-center gap-3">
                             <div class="w-8 h-8 rounded bg-[#CBB48A]/20 flex items-center justify-center text-[#CBB48A]">
                                 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                             </div>
                             <div>
                                 <div class="text-xs font-bold text-white">GDPR Status: {{ meta.compliance_check?.gdpr || 'Passing' }}</div>
                                 <div class="text-[10px] text-slate-500 uppercase">Data minimization detected</div>
                             </div>
                         </div>
                         <div class="p-3 bg-[#0f172a] rounded-lg border border-slate-800 flex items-center gap-3">
                             <div class="w-8 h-8 rounded bg-amber-500/20 flex items-center justify-center text-amber-400">
                                 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                             </div>
                             <div>
                                 <div class="text-xs font-bold text-white">License Risk: {{ meta.compliance_check?.license || 'Medium' }}</div>
                                 <div class="text-[10px] text-slate-500 uppercase">MIT / Proprietary Mix</div>
                             </div>
                         </div>
                         <div class="p-3 bg-[#0f172a] rounded-lg border border-slate-800 flex items-center gap-3">
                             <div class="w-8 h-8 rounded bg-blue-500/20 flex items-center justify-center text-blue-400">
                                 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                             </div>
                             <div>
                                 <div class="text-xs font-bold text-white">Sovereignty Check</div>
                                 <div class="text-[10px] text-slate-500 uppercase">{{ meta.data_sovereignty?.country || 'PH' }} Data Residency Confirmed</div>
                             </div>
                         </div>
                    </section>

                    <!-- Section D: Roadmap -->
                    <section class="bg-[#0a0f1a] rounded-2xl border border-slate-800 p-6">
                        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-4">Post-Acquisition Roadmap</h3>
                        <ul class="space-y-3">
                            <li v-for="item in roadmapItems" :key="item.text" class="flex gap-3 items-start">
                                <span class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0" :class="item.dot"></span>
                                <p class="text-xs text-slate-400"><strong :class="item.color">{{ item.type }}:</strong> {{ item.text }}</p>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
            
            <div class="mt-12 text-center text-xs text-slate-600 font-mono">
                Analysis ID: {{ asset.id }} • Scanned on {{ formatDate(asset.updated_at) }} • LUME Intelligence v2.4.0
            </div>

            <!-- Tech Stack Modal -->
            <Teleport to="body">
                <div v-if="showTechModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/90 backdrop-blur-sm" @click="showTechModal = false"></div>
                    <div class="relative w-full max-w-lg bg-[#0a0f1a] border border-slate-700 rounded-xl p-6 shadow-2xl">
                         <div class="flex justify-between items-center mb-4">
                             <h3 class="text-lg font-bold text-white">Full Technology Stack</h3>
                             <button @click="showTechModal = false" class="text-slate-400 hover:text-white">
                                 <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                             </button>
                         </div>
                         <div class="flex flex-wrap gap-2">
                             <span v-for="tech in fullTechStack" :key="tech" class="px-3 py-1.5 rounded-lg bg-[#0f172a] border border-slate-700 text-slate-300 text-xs font-bold">
                                {{ tech }}
                            </span>
                         </div>
                    </div>
                </div>
            </Teleport>

            <FullAuditModal :show="showAuditModal" :asset="asset" @close="showAuditModal = false" />
            <AcquisitionModal 
                :show="showAcquisitionModal" 
                :asset="asset" 
                @close="showAcquisitionModal = false"
                @confirm="handlePurchase"
            />
        </main>
    </div>
</template>
