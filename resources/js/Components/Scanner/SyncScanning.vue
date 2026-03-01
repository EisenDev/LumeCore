<script setup lang="ts">
import { ref, watch, nextTick, computed } from 'vue';

const props = defineProps<{
    show: boolean;
    progress?: number;
    step?: string;
    details?: string;
    webUrl?: string;
    repoName?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'view-results'): void;
}>();

const logs = ref<{time: string, message: string, type: 'status' | 'info' | 'warn'}[]>([]);
const logContainer = ref<HTMLElement | null>(null);

// Mock Data for Visuals
const commitHash = computed(() => 'Fix: API route handler');
const buildId = computed(() => 'dSf6a7b2');

// TITAN V5.9: Multi-Phase Sync Progress Logic (0-40% Repo, 40-80% Web, 80-100% Comparison)
type ScanPhase = 'repo' | 'web' | 'comparison' | 'complete';
const scanPhase = ref<ScanPhase>('repo');

// Detect Phase Switch based on keywords (Forward-only state machine)
watch(() => [props.step, props.details], ([newStep, newDetails]) => {
    const text = ((newStep || '') + (newDetails || '')).toLowerCase();
    
    // RESET: Only if explicitly starting over (rare)
    if (text.includes('initializing titan sync') && scanPhase.value !== 'repo') {
        scanPhase.value = 'repo';
    }

    // PHASE 2: Web Scan
    // Triggered by PerformProjectScan messages
    if (scanPhase.value !== 'comparison' && (
        text.includes('initializing forensic crawler') || 
        text.includes('crawling target') || 
        text.includes('analyzing dom') ||
        text.includes('analyzing strategic alignment') ||
        text.includes('infrastructure analyst') ||
        text.includes('web recon')
    )) {
        scanPhase.value = 'web';
    }

    // PHASE 3: Comparison
    // Triggered by PerformSyncComparison messages
    if ((scanPhase.value === 'repo' || scanPhase.value === 'web') && (
        text.includes('synchronizing') || 
        text.includes('cross-component comparison') ||
        text.includes('semantic dna audit') ||
        text.includes('calculating forensic alignment') ||
        text.includes('syncing') || 
        text.includes('comparison') || 
        text.includes('finalizing report')
    )) {
        scanPhase.value = 'comparison';
    }

    // PHASE 4: Complete
    if (text.includes('titan sync complete')) {
        scanPhase.value = 'complete';
    }
});

const unifiedProgress = computed(() => {
    const raw = props.progress || 0;
    
    // Force complete
    if (scanPhase.value === 'complete') return 100;

    if (scanPhase.value === 'repo') {
        // Map 0-100 -> 0-40
        return Math.min(40, raw * 0.4);
    } 
    
    if (scanPhase.value === 'web') {
        // Map 0-100 -> 40-80
        return 40 + (Math.min(100, raw) * 0.4);
    }

    if (scanPhase.value === 'comparison') {
        const compRaw = raw > 80 ? (raw - 80) * 5 : raw; // Normalize 80-100 to 0-100
        return 80 + (Math.min(100, compRaw) * 0.2);
    }

    return 0;
});

// Triple Progress Bar Logic
const repoProgress = computed(() => Math.min(100, (unifiedProgress.value / 40) * 100));
const webProgress = computed(() => {
    if (unifiedProgress.value <= 40) return 0;
    return Math.min(100, ((unifiedProgress.value - 40) / 40) * 100);
});
const syncProgress = computed(() => {
    if (unifiedProgress.value <= 80) return 0;
    return Math.min(100, ((unifiedProgress.value - 80) / 20) * 100);
});

const isComplete = computed(() => unifiedProgress.value >= 100);

function addLog(message: string) {
    if (!message) return;
    
    let type: 'status' | 'info' | 'warn' = 'info';
    if (message.includes('STATUS') || message.includes('Initializing') || message.includes('Complete')) type = 'status';
    if (message.includes('Error') || message.includes('Failed')) type = 'warn';

    // Prevent duplicate logs if the event fires twice
    if (logs.value.length > 0 && logs.value[logs.value.length - 1].message === message) return;

    logs.value.push({
        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
        message,
        type
    });
    
    // Limit log size for performance
    if (logs.value.length > 50) logs.value.shift();

    nextTick(() => {
        if (logContainer.value) {
            logContainer.value.scrollTop = logContainer.value.scrollHeight;
        }
    });
}

watch(() => props.details, (val) => {
    if (val) addLog(val);
}, { immediate: true });

watch(() => props.step, (val) => {
    if (val) addLog(`STATUS UPDATE: ${val}`);
}, { immediate: true });

watch(() => props.show, (val) => {
    if (val && (props.progress || 0) < 5) {
        // Only clear if we don't have recent logs from the same scan
        if (logs.value.length < 2) {
            logs.value = [];
            addLog(`Initiating Synchronization Protocol: ${props.projectName || 'Active Project'}`, 'info');
            addLog('Calibrating cross-domain vectors...', 'info');
        }
    }
});

const close = () => {
    emit('close');
};
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 backdrop-blur-3xl bg-[#020408]/90 overflow-y-auto">
            <!-- Compact Mission Control Container -->
            <div class="w-full max-w-4xl my-auto bg-[#0A0A0B]/90 rounded-[2.5rem] border border-white/5 shadow-[0_0_100px_rgba(0,0,0,0.8)] flex flex-col font-sans text-slate-300 relative overflow-hidden">
                
                <!-- Background Accents -->
                <div class="absolute top-0 right-0 w-1/2 h-1/2 bg-cyan-500/5 blur-[120px] rounded-full pointer-events-none"></div>
                <div class="absolute bottom-0 left-0 w-1/2 h-1/2 bg-emerald-500/5 blur-[120px] rounded-full pointer-events-none"></div>

                <!-- Header: Compact -->
                <div class="relative shrink-0 px-8 py-5 flex items-center justify-between border-b border-white/5 bg-white/[0.02] z-20">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center shadow-lg shadow-emerald-500/10">
                            <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-black italic tracking-tighter text-white uppercase mt-0.5">Synchronization Protocol</h2>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[8px] font-black text-emerald-400 uppercase tracking-widest">{{ step || 'Initializing' }}</span>
                                <div class="w-1 h-1 rounded-full bg-slate-800"></div>
                                <span class="text-[8px] font-bold text-slate-500 uppercase tracking-widest truncate max-w-[150px]">{{ projectName || 'Active Project' }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-6">
                        <div class="hidden sm:flex flex-col items-end">
                            <span class="text-[8px] font-black text-slate-600 uppercase tracking-widest">Global Progress</span>
                            <span class="text-xl font-black italic tracking-tighter text-white uppercase leading-none">{{ Math.round(unifiedProgress) }}%</span>
                        </div>
                        <button 
                            @click="close"
                            class="p-2 hover:bg-white/5 rounded-xl transition-colors text-slate-500 hover:text-white"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Main Grid: Highly Functional -->
                <div class="p-6 space-y-6 overflow-y-auto max-h-[80vh]">
                    <!-- Triple Progress Matrix -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Bar 1: Source -->
                        <div class="bg-white/[0.02] border border-white/5 rounded-2xl p-4 space-y-3 relative overflow-hidden group">
                            <div class="flex justify-between items-center relative z-10">
                                <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Source Inspection</span>
                                <span class="text-[10px] font-black text-emerald-400 italic">{{ Math.round(repoProgress) }}%</span>
                            </div>
                            <div class="h-1.5 w-full bg-black/40 rounded-full overflow-hidden relative z-10">
                                <div class="h-full bg-emerald-500 transition-all duration-500" :style="{ width: `${repoProgress}%` }"></div>
                            </div>
                            <!-- Background Icon -->
                            <svg class="absolute -right-2 -bottom-2 w-12 h-12 text-white/[0.02] rotate-12" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                        </div>

                        <!-- Bar 2: Website -->
                        <div class="bg-white/[0.02] border border-white/5 rounded-2xl p-4 space-y-3 relative overflow-hidden group">
                            <div class="flex justify-between items-center relative z-10">
                                <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Website Recon</span>
                                <span class="text-[10px] font-black text-cyan-400 italic">{{ Math.round(webProgress) }}%</span>
                            </div>
                            <div class="h-1.5 w-full bg-black/40 rounded-full overflow-hidden relative z-10">
                                <div class="h-full bg-cyan-500 transition-all duration-500" :style="{ width: `${webProgress}%` }"></div>
                            </div>
                            <!-- Background Icon -->
                            <svg class="absolute -right-2 -bottom-2 w-12 h-12 text-white/[0.02] rotate-12" fill="currentColor" viewBox="0 0 24 24"><path d="M24 22.525H0l12-21.05 12 21.05z"/></svg>
                        </div>

                        <!-- Bar 3: Mapping -->
                        <div class="bg-white/[0.02] border border-white/5 rounded-2xl p-4 space-y-3 relative overflow-hidden group">
                            <div class="flex justify-between items-center relative z-10">
                                <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Synapse Mapping</span>
                                <span class="text-[10px] font-black text-indigo-400 italic">{{ Math.round(syncProgress) }}%</span>
                            </div>
                            <div class="h-1.5 w-full bg-black/40 rounded-full overflow-hidden relative z-10">
                                <div class="h-full bg-indigo-500 transition-all duration-500" :style="{ width: `${syncProgress}%` }"></div>
                            </div>
                            <!-- Background Icon -->
                            <svg class="absolute -right-1 -bottom-2 w-12 h-12 text-white/[0.02] rotate-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        </div>
                    </div>

                    <!-- Dual Status Interface -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Left: Visual Stats -->
                        <div class="bg-black/20 border border-white/5 rounded-3xl p-6 flex flex-col items-center justify-center relative min-h-[200px]">
                            <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(52,211,153,0.05)_0%,transparent_70%)]"></div>
                            
                            <!-- Central Hologram -->
                            <div class="relative w-32 h-32 flex items-center justify-center">
                                <div class="absolute inset-0 border-2 border-emerald-500/20 rounded-full animate-[spin_8s_linear_infinite]"></div>
                                <div class="absolute inset-2 border border-cyan-500/20 rounded-full animate-[spin_12s_linear_infinite_reverse]"></div>
                                
                                <div class="w-16 h-16 rounded-2xl bg-[#020408] border border-white/10 flex items-center justify-center shadow-[0_0_30px_rgba(16,185,129,0.1)] relative z-10">
                                    <svg class="w-8 h-8 text-emerald-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A10.003 10.003 0 0012 21a10.003 10.003 0 008.384-4.51l.054.09m-4.289-8.325a5 5 0 00-7.18 0M12 11V3" />
                                    </svg>
                                </div>
                            </div>

                            <div class="mt-4 text-center">
                                <span class="text-[8px] font-black text-slate-600 uppercase tracking-[0.3em]">Titan Uplink</span>
                                <p class="text-[10px] font-bold text-cyan-400/80 mt-1 uppercase">{{ details || 'Ready for Sync' }}</p>
                            </div>
                        </div>

                        <!-- Right: Terminal Log -->
                        <div class="bg-[#020408] border border-white/5 rounded-3xl flex flex-col overflow-hidden">
                            <div class="px-4 py-2 border-b border-white/5 bg-white/[0.02] flex justify-between items-center">
                                <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest">System Log Stream</span>
                                <div class="flex gap-1">
                                    <div class="w-1.5 h-1.5 rounded-full bg-rose-500/50"></div>
                                    <div class="w-1.5 h-1.5 rounded-full bg-amber-500/50"></div>
                                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-500/50"></div>
                                </div>
                            </div>
                            <div ref="logContainer" class="p-4 h-[200px] overflow-y-auto space-y-2 custom-scrollbar font-mono text-[9px] leading-relaxed">
                                <div v-for="(log, i) in logs" :key="i" class="flex gap-3">
                                    <span class="text-slate-600 shrink-0">[{{ log.time.split(' ')[0] }}]</span>
                                    <span :class="{
                                        'text-cyan-400': log.type === 'status',
                                        'text-emerald-400': log.type === 'info',
                                        'text-amber-400': log.type === 'warn'
                                    }">{{ log.message }}</span>
                                </div>
                                <div v-if="logs.length === 0" class="h-full flex items-center justify-center text-slate-800 italic uppercase tracking-[0.2em] animate-pulse">
                                    Waiting for Signal...
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Details -->
                    <div class="bg-white/[0.02] border border-white/5 rounded-2xl px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-8">
                             <div class="flex flex-col">
                                 <span class="text-[8px] font-black text-slate-600 uppercase tracking-widest">Source</span>
                                 <span class="text-[10px] font-bold text-slate-300 uppercase italic truncate max-w-[120px]">{{ repoName || 'main' }}</span>
                             </div>
                             <div class="flex flex-col">
                                 <span class="text-[8px] font-black text-slate-600 uppercase tracking-widest">Endpoints</span>
                                 <span class="text-[10px] font-bold text-cyan-500 uppercase italic truncate max-w-[150px]">{{ webUrl || 'Live Site' }}</span>
                             </div>
                        </div>
                        <div class="flex items-center gap-4">
                             <div class="text-right">
                                 <span class="text-[8px] font-black text-slate-600 uppercase tracking-widest">Build Status</span>
                                 <div class="flex items-center justify-end gap-2">
                                     <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                                     <span class="text-[9px] font-black text-emerald-400 uppercase italic tracking-tighter leading-none mt-0.5">Stable Handshake</span>
                                 </div>
                             </div>
                        </div>
                    </div>
                </div>

                <!-- Verified Overlay -->
                <transition name="fade">
                    <div v-if="show && isComplete" class="absolute inset-0 z-[100] flex items-center justify-center bg-[#020408]/90 backdrop-blur-2xl p-6">
                        <div class="bg-[#0b101b] border border-emerald-500/20 p-8 rounded-[3rem] shadow-[0_0_100px_rgba(16,185,129,0.1)] flex flex-col items-center text-center max-w-sm">
                            <div class="w-20 h-20 rounded-full bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center mb-6 shadow-[0_0_30px_rgba(16,185,129,0.2)]">
                                <svg class="w-10 h-10 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h3 class="text-2xl font-black text-white italic uppercase tracking-tighter mb-2">Sync Verified</h3>
                            <p class="text-slate-400 text-sm mb-8 leading-relaxed font-medium">
                                Cross-component mapping complete. Project integrity validated across source and production.
                            </p>
                            <div class="flex flex-col w-full gap-4">
                                <button 
                                    @click="emit('view-results'); emit('close')"
                                    class="w-full py-5 bg-gradient-to-r from-emerald-600 to-cyan-600 text-white font-black uppercase tracking-[0.2em] italic rounded-3xl shadow-2xl shadow-emerald-500/20 transition-all hover:scale-[1.05] active:scale-[0.95]"
                                >
                                    View Security Report
                                </button>
                                <button 
                                    @click="emit('close')"
                                    class="text-slate-600 hover:text-white text-[10px] font-black uppercase tracking-widest transition-colors py-2"
                                >
                                    Dismiss Analyst
                                </button>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.1);
}

.fade-enter-active, .fade-leave-active {
    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
    transform: scale(0.95);
}

.animate-spin-fast {
    animation: spin 2s linear infinite;
}

.animate-shimmer {
    animation: shimmer 2s infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.animate-in {
    animation: animate-in 0.3s ease-out;
}
@keyframes animate-in {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
