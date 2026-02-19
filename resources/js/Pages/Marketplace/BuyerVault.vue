<script setup lang="ts">
/**
 * Buyer Vault (BuyerVault.vue)
 * Secure view for purchased assets with unlocked secrets and deployment guides.
 */
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { VaultAsset } from '@/types/vault';
import ReactMarkdown from 'vue3-markdown-it'; 

// Reuse components where possible, or inline simple ones
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
import FullAuditModal from '@/Components/FullAuditModal.vue';

ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend);

interface Props {
    asset: VaultAsset;
    deploymentGuide: string;
    isPurchased: boolean;
    isAuthenticated: boolean;
    authUser: any;
}

const props = defineProps<Props>();

const meta = computed(() => (props.asset.metadata || {}) as any);
const deploymentGuide = computed(() => props.deploymentGuide || '# No Guide Available');

// --- Radar Logic (Duplicated for Fidelity) ---
const radarData = computed(() => {
    const getVec = (source: any) => {
        let raw = source?.hexagon_vectors || source?.radar_data || source?.breakdown || {};
        if (typeof raw === 'string') try { raw = JSON.parse(raw); } catch {}
        const n: any = {};
        Object.keys(raw).forEach(k => n[k.toLowerCase().replace(/ /g, '_')] = Number(raw[k]) || 0);
        return n;
    };

    const webRaw = getVec(props.asset.website_metadata || meta.value);
    
    let repoRaw: any = {};
    if (props.asset.repository_metadata?.hexagon_vectors) {
         repoRaw = getVec(props.asset.repository_metadata);
    } else if (props.asset.radar_data) {
         repoRaw = getVec({ hexagon_vectors: props.asset.radar_data });
    } else {
         repoRaw = getVec(meta.value); 
    }

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

    return {
        labels: ['Velocity/Perf', 'Resilience/Sec', 'Security/Arch', 'Supply Chain/DB', 'Infra/Supply', 'DB/Perf'], 
        // Simplified labels for cleaner code in this view
        datasets: [
            {
                label: 'Live Website',
                data: webValues,
                backgroundColor: 'rgba(16, 185, 129, 0.2)',
                borderColor: '#10b981',
                pointBackgroundColor: '#10b981',
            },
            {
                label: 'Source Code',
                data: repoValues,
                backgroundColor: 'rgba(99, 102, 241, 0.2)', 
                borderColor: '#6366f1',
                pointBackgroundColor: '#6366f1',
            }
        ]
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: { r: { ticks: { display: false, backdropColor: 'transparent' }, suggestedMin: 0, suggestedMax: 100, grid: { color: 'rgba(255,255,255,0.1)' } } },
    plugins: { legend: { display: true, labels: { color: '#faa', font: { family: 'monospace' } } } } // Use slightly diff style for Buyer Vault
};

const showAuditModal = ref(false);

const repoUrl = computed(() => props.asset.github_repo_url || props.asset.repository_url || '#');

</script>

<template>
    <Head :title="`VAULT UNLOCKED: ${meta.custom_name || asset.file_name}`" />

    <div class="min-h-screen bg-[#050911] text-slate-300 font-sans selection:bg-emerald-500/30">
        
        <!-- Private Navbar -->
        <header class="border-b border-emerald-500/10 bg-[#0a0f1a]/80 backdrop-blur-xl sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
                <Link :href="route('marketplace.index')" class="flex items-center gap-2 group text-slate-400 hover:text-white transition-colors">
                     <span class="text-sm font-bold uppercase tracking-wide">Marketplace</span>
                </Link>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                    <span class="text-xs font-mono text-emerald-500">SECURE VAULT ACCESS GRANTED</span>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-6 py-12">
            
            <div class="mb-12 text-center">
                 <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold uppercase tracking-wider mb-4">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                    Asset Ownership Verified
                </div>
                <h1 class="text-4xl font-bold text-white mb-2">{{ meta.custom_name || asset.file_name }}</h1>
                <p class="text-slate-500">Full access to source code, documentation, and deployment keys.</p>
            </div>

            <!-- Secrets Reveal Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
                
                <!-- Card 1: Repository Access -->
                <div class="bg-[#0f172a] rounded-2xl border border-indigo-500/30 p-6 relative group overflow-hidden">
                    <div class="absolute inset-0 bg-indigo-500/5 group-hover:bg-indigo-500/10 transition-colors"></div>
                    <h3 class="font-bold text-white mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                        GitHub Repository
                    </h3>
                    <div class="bg-black/50 rounded-lg p-3 flex justify-between items-center border border-indigo-500/20 mb-4">
                        <code class="text-xs text-indigo-300 font-mono break-all">{{ repoUrl }}</code>
                        <a :href="repoUrl" target="_blank" class="p-2 hover:bg-white/10 rounded text-indigo-400 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                        </a>
                    </div>
                    <p class="text-xs text-slate-500">
                        Warning: This repository is now linked to your identity. Do not fork publicly.
                    </p>
                </div>

                 <!-- Card 2: Download Package -->
                <div class="bg-[#0f172a] rounded-2xl border border-emerald-500/30 p-6 relative group overflow-hidden">
                    <div class="absolute inset-0 bg-emerald-500/5 group-hover:bg-emerald-500/10 transition-colors"></div>
                    <h3 class="font-bold text-white mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                        Asset Package
                    </h3>
                   <div class="flex items-center gap-4">
                        <button class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-lg shadow-emerald-500/20 transition-all flex items-center gap-2">
                             Download .ZIP
                        </button>
                        <div class="text-xs text-slate-400">
                            Updates Available: <span class="text-white font-bold">LATEST</span><br>
                            Size: <span class="text-white font-mono">{{ (props.asset.file_size / 1024 / 1024).toFixed(2) }} MB</span>
                        </div>
                   </div>
                </div>
            </div>

            <!-- Deployment Guide -->
            <div class="bg-[#0f172a] border border-slate-700 rounded-2xl overflow-hidden mb-12">
                <div class="px-6 py-4 border-b border-slate-700 bg-slate-900/50 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        🤖 AI Generated Deployment Protocol
                    </h2>
                     <span class="text-xs font-mono text-indigo-400">GENERATED {{ new Date().toLocaleDateString() }}</span>
                </div>
                <div class="p-8 prose prose-invert prose-sm max-w-none">
                     <!-- Markdown Render -->
                     <article v-html="deploymentGuide.replace(/\n/g, '<br>')"></article> 
                     <!-- Using simple replace for now, ideally use a proper markdown library if available, but raw text is okay too -->
                </div>
            </div>

            <!-- Full Audit (Hidden but accessible) -->
            <div class="text-center">
                 <button @click="showAuditModal = true" class="text-sm text-slate-500 hover:text-white underline">
                    Review Original Forensic Audit
                 </button>
            </div>

            <FullAuditModal :show="showAuditModal" :asset="asset" @close="showAuditModal = false" />
        </main>
    </div>
</template>

<style scoped>
/* Add simple markdown styling if needed */
</style>
