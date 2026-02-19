<script setup lang="ts">
import { ref, watch, onMounted, computed, nextTick } from 'vue';

const props = defineProps<{
    isScanning: boolean;
    progress: number;
    currentPhase: string;
    logs: string[];
}>();

const terminalContent = ref<HTMLElement | null>(null);

// Auto-scroll terminal
watch(() => props.logs, () => {
    nextTick(() => {
        if (terminalContent.value) {
            terminalContent.value.scrollTop = terminalContent.value.scrollHeight;
        }
    });
}, { deep: true });

const phases = [
    { id: 'init', label: 'Initial Handshake' },
    { id: 'discovery', label: 'Asset Discovery' },
    { id: 'vuln', label: 'Vulnerability Analysis' },
    { id: 'final', label: 'Generating Report' }
];

const currentPhaseIndex = computed(() => {
    // Map backend phase string to index
    if (props.progress < 10) return 0;
    if (props.progress < 40) return 1;
    if (props.progress < 90) return 2;
    return 3;
});

</script>

<template>
    <Teleport to="body">
        <transition enter-active-class="transition-opacity duration-300" leave-active-class="transition-opacity duration-300" enter-from-class="opacity-0" leave-to-class="opacity-0">
            <div v-if="isScanning" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/90 backdrop-blur-xl"></div>
                
                <!-- Main Card -->
                <div class="relative w-full max-w-4xl overflow-hidden rounded-3xl border border-emerald-500/20 bg-[#070b14] shadow-[0_0_100px_rgba(16,185,129,0.15)] flex flex-col">
                    
                    <!-- Header -->
                    <div class="flex-shrink-0 border-b border-emerald-500/10 bg-[#0a0f1a] px-8 py-6 flex items-center justify-between relative overflow-hidden">
                        <div class="absolute inset-0 opacity-10 pointer-events-none">
                            <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/20 blur-3xl rounded-full -mr-32 -mt-32"></div>
                             <div class="absolute bottom-0 left-0 w-64 h-64 bg-cyan-500/20 blur-3xl rounded-full -ml-32 -mb-32"></div>
                        </div>

                        <div class="flex items-center gap-5 relative z-10">
                            <div class="relative">
                                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-600 to-cyan-700 flex items-center justify-center border border-white/10 shadow-lg shadow-emerald-500/20 relative z-10">
                                    <svg class="w-7 h-7 text-white animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <div class="absolute inset-0 bg-emerald-500 blur-md opacity-40 animate-pulse"></div>
                            </div>
                            
                            <div>
                                <h2 class="text-2xl font-black text-white tracking-tight uppercase">SOVEREIGN <span class="text-emerald-400">RECONNAISSANCE</span></h2>
                                <div class="flex items-center gap-3 mt-1.5">
                                    <div class="flex items-center gap-1.5 px-2 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                                        <span class="text-[10px] text-emerald-400 font-bold uppercase tracking-wider">Active Scan</span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 font-mono uppercase tracking-widest">Target Locked // Analyzing Vectors</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="p-8 bg-[#070b14] space-y-8">
                        
                        <!-- Progress Section -->
                        <div class="space-y-4">
                            <div class="flex justify-between items-end">
                                <span class="text-xs font-black text-slate-400 uppercase tracking-widest">System Infiltration Progress</span>
                                <span class="text-xl font-black text-emerald-400 font-mono">{{ progress }}%</span>
                            </div>
                            <div class="h-2 w-full bg-slate-900 rounded-full overflow-hidden border border-white/5">
                                <div class="h-full bg-gradient-to-r from-emerald-600 via-emerald-400 to-cyan-400 transition-all duration-300 relative" :style="{ width: progress + '%' }">
                                    <div class="absolute inset-0 bg-white/30 w-full animate-[shimmer_1s_infinite]"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Phase Indicators -->
                        <div class="grid grid-cols-4 gap-4">
                            <div v-for="(phase, idx) in phases" :key="phase.id" 
                                class="relative p-4 rounded-xl border transition-all duration-500"
                                :class="idx <= currentPhaseIndex ? 'bg-emerald-500/5 border-emerald-500/30' : 'bg-slate-900/50 border-white/5 opacity-50'">
                                
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold border transition-colors"
                                        :class="idx < currentPhaseIndex ? 'bg-emerald-500 border-emerald-500 text-white' : (idx === currentPhaseIndex ? 'bg-emerald-500/20 border-emerald-500 text-emerald-400 animate-pulse' : 'bg-slate-800 border-slate-700 text-slate-500')">
                                        <svg v-if="idx < currentPhaseIndex" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        <span v-else>{{ idx + 1 }}</span>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wide transition-colors"
                                        :class="idx <= currentPhaseIndex ? 'text-emerald-300' : 'text-slate-500'">
                                        {{ phase.label }}
                                    </span>
                                </div>
                                
                                <div v-if="idx === currentPhaseIndex" class="h-0.5 w-12 bg-emerald-500/50 rounded-full animate-pulse"></div>
                            </div>
                        </div>

                        <!-- Terminal Output -->
                        <div class="relative group">
                            <div class="absolute -inset-0.5 bg-gradient-to-r from-emerald-500/20 to-cyan-500/20 rounded-2xl blur opacity-20 group-hover:opacity-40 transition duration-1000"></div>
                            <div class="relative bg-black rounded-xl border border-white/10 p-4 font-mono text-xs h-64 flex flex-col shadow-inner">
                                <div class="flex items-center justify-between mb-3 border-b border-white/5 pb-2">
                                    <div class="flex gap-1.5">
                                        <div class="w-2.5 h-2.5 rounded-full bg-rose-500/20 border border-rose-500/50"></div>
                                        <div class="w-2.5 h-2.5 rounded-full bg-amber-500/20 border border-amber-500/50"></div>
                                        <div class="w-2.5 h-2.5 rounded-full bg-emerald-500/20 border border-emerald-500/50"></div>
                                    </div>
                                    <span class="text-[10px] text-slate-600 uppercase">/var/log/lume-sec-ops.log</span>
                                </div>
                                
                                <div ref="terminalContent" class="flex-1 overflow-y-auto custom-scrollbar space-y-1 pr-2">
                                    <div v-for="(log, i) in logs" :key="i" class="flex gap-2 text-slate-300">
                                        <span class="text-emerald-500/50 select-none">›</span>
                                        <span :class="{'text-emerald-400': log.includes('Success'), 'text-amber-400': log.includes('Warning'), 'text-rose-400': log.includes('Error')}">
                                            {{ log }}
                                        </span>
                                    </div>
                                    <div class="flex gap-2 animate-pulse">
                                        <span class="text-emerald-500/50">›</span>
                                        <span class="w-2 h-4 bg-emerald-500/50 block"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <!-- Footer / Actions -->
                    <div class="flex-shrink-0 border-t border-emerald-500/10 bg-[#0a0f1a] px-8 py-5 flex items-center justify-end gap-3 transition-opacity duration-500"
                        :class="progress === 100 ? 'opacity-100' : 'opacity-0 pointer-events-none'">
                        <button @click="$emit('view-results')" class="px-6 py-2 bg-emerald-500 hover:bg-emerald-400 text-[#0a0f1a] text-xs font-black uppercase rounded-xl transition-all shadow-[0_0_20px_rgba(16,185,129,0.3)] flex items-center gap-2">
                            <span>View Sovereign Report</span>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </div>

                </div>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }
</style>
