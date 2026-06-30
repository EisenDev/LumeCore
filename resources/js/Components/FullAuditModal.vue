<script setup lang="ts">
import { computed } from 'vue';
import type { VaultAsset } from '@/types/vault';

interface Props {
    show: boolean;
    asset: VaultAsset;
}

const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'close'): void }>();

// Helpers
const getVal = (source: any, key: string) => Number(source?.[key] || 0);

const meta = computed(() => (props.asset.metadata || {}) as any);

const webData = computed(() => {
    let raw = props.asset.website_metadata?.hexagon_vectors || meta.value.hexagon_vectors || {};
    if (typeof raw === 'string') try { raw = JSON.parse(raw); } catch {}
    
    // Normalize keys
    const n: any = {};
    Object.keys(raw).forEach(k => n[k.toLowerCase().replace(/ /g, '_')] = Number(raw[k]) || 0);
    return n;
});

const repoData = computed(() => {
    // Check repository_metadata, then top-level radar_data, then metadata.radar_data
    let raw = props.asset.repository_metadata?.hexagon_vectors 
           || props.asset.radar_data 
           || meta.value.radar_data 
           || {};

    if (typeof raw === 'string') try { raw = JSON.parse(raw); } catch {}
    
    // Normalize keys
    const n: any = {};
    Object.keys(raw).forEach(k => n[k.toLowerCase().replace(/ /g, '_')] = Number(raw[k]) || 0);
    return n;
});

const factors = computed(() => [
    {
        category: 'Live Website Integrity',
        color: 'text-[#CBB48A]',
        barColor: 'bg-[#CBB48A]',
        items: [
            { label: 'Velocity', score: webData.value.client_side_velocity || 0, desc: 'Client-side render performance & TTFB.' },
            { label: 'Resilience', score: webData.value.code_efficiency || 0, desc: 'Error handling & uptime reliability.' },
            { label: 'Security', score: webData.value.security_perimeter || 0, desc: 'HTTPS, Headers, & Exposure checks.' },
            { label: 'Supply Chain', score: webData.value.supply_chain_governance || 0, desc: '3rd-party scripts (CDN/External).' },
            { label: 'Infrastructure', score: webData.value.infrastructure_maturity || 0, desc: 'CDN, Caching, & Server Config.' },
            { label: 'Database', score: webData.value.database_architecture || 0, desc: 'Query efficiency & Connection checks.' }
        ]
    },
    {
        category: 'Source Code Fidelity',
        color: 'text-indigo-400',
        barColor: 'bg-indigo-500',
        items: [
            { label: 'Code Quality', score: repoData.value.code_efficiency || 0, desc: 'Spaghetti code, formatting, & linting.' },
            { label: 'Security', score: repoData.value.security_perimeter || 0, desc: 'Secrets scan & vulnerability check.' },
            { label: 'Architecture', score: repoData.value.infrastructure_maturity || 0, desc: 'Project structure & scalability.' },
            { label: 'Database', score: repoData.value.database_architecture || 0, desc: 'Schema norms & indexing strategy.' },
            { label: 'Supply Chain', score: repoData.value.supply_chain_governance || 0, desc: 'NPM/Composer dependency risk.' },
            { label: 'Performance', score: repoData.value.client_side_velocity || 0, desc: 'Algorithm complexity & bloat.' }
        ]
    }
]);

const getGrade = (s: number) => {
    if (s >= 95) return 'S';
    if (s >= 90) return 'A';
    if (s >= 80) return 'B';
    if (s >= 70) return 'C';
    return 'F';
};

const getGradeColor = (s: number) => {
    if (s >= 90) return 'text-[#CBB48A]';
    if (s >= 75) return 'text-amber-400';
    return 'text-rose-400';
};

</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/90 backdrop-blur-sm" @click="$emit('close')"></div>
        <div class="relative w-full max-w-5xl bg-[#0a0f1a] border border-slate-700 rounded-xl shadow-2xl flex flex-col h-[85vh]">
            
            <div class="p-6 border-b border-white/5 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-white mb-1">Full 12-Factor Audit</h2>
                    <p class="text-xs text-slate-500 uppercase tracking-widest font-mono">Detailed breakdown of Asset Health</p>
                </div>
                <button @click="$emit('close')" class="text-slate-500 hover:text-white transition-colors">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-8 grid grid-cols-1 md:grid-cols-2 gap-12 custom-scrollbar">
                <div v-for="(factor, idx) in factors" :key="idx">
                    <h3 class="text-sm font-bold uppercase tracking-widest mb-6 border-b border-white/5 pb-2" :class="factor.color">
                        {{ factor.category }}
                    </h3>
                    
                    <div class="space-y-6">
                        <div v-for="item in factor.items" :key="item.label" class="group">
                            <div class="flex justify-between items-end mb-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-slate-200">{{ item.label }}</span>
                                        <span class="text-xs font-mono font-black" :class="getGradeColor(item.score)">{{ getGrade(item.score) }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">{{ item.desc }}</div>
                                </div>
                                <div class="text-sm font-mono font-bold text-white">{{ item.score }}/100</div>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div class="h-2 w-full bg-slate-800 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-1000" 
                                     :class="[factor.barColor, item.score < 50 ? 'opacity-50' : 'opacity-100']" 
                                     :style="{ width: `${item.score}%` }">
                                </div>
                            </div>

                            <!-- Warning Label -->
                            <div v-if="item.score < 70" class="mt-2 flex items-center gap-1.5 text-rose-400">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                <span class="text-[10px] font-bold uppercase">Attention Required</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 border-t border-white/5 bg-slate-900/50 text-center">
                <p class="text-xs text-slate-500">
                    Audit confirmed by <span class="text-indigo-400 font-bold">LUME Intelligence™</span>. 
                    Scores are immutable and verified via blockchain hash.
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
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
