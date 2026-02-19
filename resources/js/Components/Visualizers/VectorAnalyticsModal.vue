<script setup lang="ts">
import { computed } from 'vue';
import Modal from '@/Components/Modal.vue';

interface VectorDetail {
    explanation: string;
    improvement_tip: string;
}

interface Props {
    show: boolean;
    radarData: Record<string, number>;
    vectorDetails: Record<string, VectorDetail>;
    compositeScore?: number;
}

const props = defineProps<Props>();
const emit = defineEmits(['close']);

const vectors = [
    { key: 'code_efficiency', label: 'Code Quality', color: 'emerald' },
    { key: 'security_perimeter', label: 'Security', color: 'red' },
    { key: 'infrastructure_maturity', label: 'Architecture', color: 'blue' },
    { key: 'database_architecture', label: 'Database', color: 'amber' },
    { key: 'supply_chain_governance', label: 'Supply Chain', color: 'purple' },
    { key: 'client_side_velocity', label: 'Performance', color: 'cyan' },
];

const getScore = (key: string) => props.radarData[key] || 0;
const getDetails = (key: string) => props.vectorDetails?.[key] || { explanation: 'No analysis available.', improvement_tip: 'No actionable tips.' };

const averageScore = computed(() => {
    if (props.compositeScore !== undefined) return props.compositeScore;
    
    // Fallback: Simple Average (Note: Real calculation is weighted)
    const values = Object.values(props.radarData);
    if (!values.length) return 0;
    return (values.reduce((a, b) => a + b, 0) / values.length).toFixed(1);
});
</script>

<template>
    <Modal :show="show" @close="emit('close')" maxWidth="4xl">
        <div class="bg-[#0a0f1a] border border-emerald-500/20 text-slate-300 p-6 rounded-lg relative overflow-hidden">
            
            <!-- Header -->
            <div class="flex justify-between items-start mb-8 relative z-10">
                <div>
                    <h2 class="text-xl font-bold text-white uppercase tracking-wider mb-1">Use Vector Analytics</h2>
                    <p class="text-xs text-slate-500 font-mono">DEEP DIVE INTO ARCHITECTURAL VECTORS</p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-black text-white">{{ averageScore }}<span class="text-base text-slate-500 font-normal">/100</span></div>
                    <div class="text-[10px] uppercase tracking-widest text-emerald-400">Composite Score</div>
                </div>
            </div>

            <!-- Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 relative z-10">
                <div v-for="vector in vectors" :key="vector.key" 
                     class="bg-white/5 border border-white/10 rounded-xl p-4 hover:bg-white/[0.07] transition-colors group">
                    
                    <!-- Title & Score -->
                    <div class="flex justify-between items-center mb-3">
                        <div class="flex items-center gap-2">
                             <div class="w-2 h-2 rounded-full" :class="`bg-${vector.color}-500 shadow-[0_0_10px_rgba(var(--${vector.color}-500),0.5)]`"></div>
                             <h3 class="font-bold text-sm text-slate-200 uppercase">{{ vector.label }}</h3>
                        </div>
                        <span class="font-mono text-sm font-bold" :class="`text-${vector.color}-400`">{{ getScore(vector.key) }}</span>
                    </div>

                    <!-- Progress Bar -->
                    <div class="h-1.5 bg-slate-800 rounded-full mb-4 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500" 
                             :class="`bg-${vector.color}-500`" 
                             :style="{ width: `${getScore(vector.key)}%` }"></div>
                    </div>

                    <!-- Details -->
                    <div class="space-y-3">
                        <div class="bg-black/20 rounded p-3 border-l-2 border-slate-700">
                             <div class="text-[9px] uppercase text-slate-500 mb-1 font-bold">Analysis</div>
                             <p class="text-xs leading-relaxed text-slate-400">
                                {{ getDetails(vector.key).explanation }}
                             </p>
                        </div>
                        
                         <div class="flex items-start gap-2 text-xs text-amber-500/90 bg-amber-500/5 p-2 rounded">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            <span>{{ getDetails(vector.key).improvement_tip }}</span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Close -->
            <div class="mt-8 flex justify-center relative z-10">
                <button @click="emit('close')" class="px-6 py-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded text-xs uppercase tracking-widest text-slate-400 transition-colors">
                    Close Analytics
                </button>
            </div>

            <!-- Background FX -->
            <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-emerald-500/5 rounded-full blur-[100px] pointer-events-none -translate-y-1/2 translate-x-1/2"></div>
        </div>
    </Modal>
</template>
