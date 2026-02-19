<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import { ref, watch, nextTick } from 'vue';

const props = defineProps<{
    show: boolean;
    progress?: number;
    step?: string;
    details?: string;
    repoName?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'view-results'): void;
}>();

const logs = ref<{time: string, message: string, type: 'info'|'success'|'file'|'warning'}[]>([]);
const terminalBody = ref<HTMLElement | null>(null);

function addLog(message: string, type: 'info'|'success'|'file'|'warning' = 'info') {
    logs.value.push({
        time: new Date().toLocaleTimeString(),
        message,
        type
    });
    
    nextTick(() => {
        if (terminalBody.value) {
            terminalBody.value.scrollTop = terminalBody.value.scrollHeight;
        }
    });
}

// Watch granular details (The stream)
watch(() => props.details, (newDetail) => {
    if (newDetail) {
        addLog(newDetail, 'file');
    }
});

// Watch main step (Milestones)
watch(() => props.step, (newStep, oldStep) => {
    if (newStep && newStep !== oldStep) {
        let type: 'info'|'success'|'file' = 'info';
        if (newStep.toLowerCase().includes('success') || newStep.toLowerCase().includes('complete')) type = 'success';
        addLog(newStep, type);
    }
});

watch(() => props.show, (val) => {
    if (val) {
        logs.value = [];
        addLog(`Accessing Source: ${props.repositoryUrl || 'Remote Repository'}`, 'info');
        addLog('Verifying branch integrity...', 'info');
    }
});
</script>

<template>
    <Modal :show="show" :maxWidth="'md'" :closeable="false">
        <div class="relative overflow-hidden bg-[#0A0A0B]/90 backdrop-blur-3xl border border-white/5 rounded-2xl shadow-[0_0:50px_rgba(6,182,212,0.15)]">
            <!-- Header -->
            <div class="px-8 py-5 border-b border-white/5 bg-white/[0.02] flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-cyan-500/10 flex items-center justify-center border border-cyan-500/20">
                        <svg class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black italic tracking-tighter text-white uppercase mt-1">Source Inspection</h3>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest truncate max-w-[200px]">{{ repositoryUrl || 'Analyzing Origin' }}</p>
                    </div>
                </div>
                <div class="text-xs font-black italic tracking-tighter text-cyan-400 animate-pulse uppercase">
                    {{ progress || 0 }}%
                </div>
            </div>

            <!-- Visualizer Area -->
            <div class="p-10 flex flex-col items-center justify-center relative h-56 border-b border-white/5 bg-black/20 overflow-hidden">
                <!-- Grid Background -->
                <div class="absolute inset-0 bg-[linear-gradient(rgba(6,182,212,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(6,182,212,0.05)_1px,transparent_1px)] bg-[size:24px_24px] [mask-image:linear-gradient(to_bottom,black,transparent)] opacity-50"></div>

                <!-- Source Stream Animation -->
                <div class="relative w-full max-w-md bg-[#0A0A0B]/80 border border-white/10 rounded-xl p-6 overflow-hidden">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-2.5 h-2.5 rounded-full bg-rose-500"></div>
                        <div class="w-2.5 h-2.5 rounded-full bg-amber-500"></div>
                        <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                        <div class="ml-auto text-[9px] font-black text-slate-600 uppercase tracking-widest italic">Origin Main</div>
                    </div>
                    <div class="space-y-2.5">
                        <div class="w-full h-2 bg-white/5 rounded-full overflow-hidden relative">
                            <div class="absolute inset-0 bg-gradient-to-r from-cyan-500 to-emerald-500 animate-[loading_2s_infinite]"></div>
                        </div>
                        <div class="flex gap-2">
                             <div class="w-1/3 h-1.5 bg-white/10 rounded-full"></div>
                             <div class="w-2/3 h-1.5 bg-white/5 rounded-full"></div>
                        </div>
                        <div class="w-5/6 h-1.5 bg-white/5 rounded-full"></div>
                    </div>
                    
                    <!-- Data Stream overlay -->
                    <div class="absolute inset-0 opacity-10 pointer-events-none flex flex-col font-mono text-[8px] p-2 leading-none text-cyan-400">
                        <div v-for="n in 10" :key="n" class="whitespace-nowrap animate-[stream_2s_linear_infinite]" :style="{ animationDelay: (n * 0.2) + 's' }">
                            010101110101011101011010101110101101010100101010101
                        </div>
                    </div>
                </div>

                <!-- Status Text -->
                <div class="mt-6 font-bold text-[10px] text-cyan-300 z-10 uppercase tracking-widest">
                    Source: <span class="text-white animate-pulse">{{ step || 'Indexing Files' }}</span>
                </div>
            </div>

            <!-- Terminal Logs -->
            <div class="bg-black/60 h-48 overflow-hidden relative flex flex-col backdrop-blur-md">
                <div class="absolute inset-0 pointer-events-none bg-[url('https://grainy-gradients.vercel.app/noise.svg')] opacity-5"></div>
                
                <div ref="terminalBody" class="flex-1 overflow-y-auto p-5 space-y-2 custom-scrollbar font-bold text-[9px] uppercase tracking-wider">
                    <div v-for="(log, i) in logs" :key="i" class="flex gap-4 text-slate-500 border-l-2 pl-3 transition-all" :class="{
                        'border-cyan-500/50': log.type === 'info',
                        'border-emerald-500': log.type === 'success',
                        'border-amber-500': log.type === 'warning',
                        'border-rose-500': log.type === 'error'
                    }">
                        <span class="text-slate-700 font-black opacity-50">{{ log.time }}</span>
                        <span :class="{
                            'text-cyan-300': log.type === 'info',
                            'text-emerald-400': log.type === 'success',
                            'text-amber-400': log.type === 'warning',
                            'text-rose-400': log.type === 'error'
                        }">{{ log.message }}</span>
                    </div>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="h-1 bg-slate-800 w-full relative">
                <div class="h-full bg-gradient-to-r from-cyan-500 to-emerald-500 transition-all duration-300 relative z-10" :style="{ width: `${progress || 0}%` }"></div>
            </div>

            <!-- Complete Overlay -->
            <transition name="fade">
                <div v-if="show && (progress ?? 0) >= 100" class="absolute inset-0 z-50 flex items-center justify-center bg-[#0A0A0B]/90 backdrop-blur-3xl animate-in">
                    <div class="p-6 flex flex-col items-center text-center">
                        <div class="w-16 h-16 rounded-full bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center mb-4 shadow-[0_0_30px_rgba(16,185,129,0.2)]">
                            <svg class="w-8 h-8 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-black text-white italic uppercase tracking-tighter mb-1">Source Ingested</h3>
                        <p class="text-slate-400 text-[10px] mb-6 font-bold uppercase tracking-widest">Source Assets Securely Ingested.</p>
                        
                        <div class="flex flex-col w-full gap-3 min-w-[240px]">
                            <button 
                                @click="emit('view-results')"
                                class="w-full py-4 bg-gradient-to-r from-cyan-600 to-emerald-600 text-white font-black uppercase tracking-[0.2em] italic rounded-2xl shadow-xl shadow-cyan-600/20 transition-all hover:scale-[1.02] active:scale-[0.98]"
                            >
                                View Results
                            </button>
                            <button 
                                @click="emit('close')"
                                class="text-slate-600 hover:text-white text-[10px] font-black uppercase tracking-widest transition-colors py-2"
                            >
                                Dismiss
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </Modal>
</template>

<style scoped>
@keyframes scrollY {
    0% { transform: translateY(0); }
    100% { transform: translateY(-50%); }
}
@keyframes progressStripes {
    0% { background-position: 1rem 0; }
    100% { background-position: 0 0; }
}

.animate-in {
    animation: animate-in 0.3s ease-out;
}
@keyframes animate-in {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
</style>
