<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import { ref, watch, onMounted, nextTick } from 'vue';

const props = defineProps<{
    show: boolean;
    progress?: number;
    step?: string;
    details?: string;
    fileName?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'view-results'): void;
}>();

const logs = ref<{time: string, message: string, type: 'info'|'success'|'processing'}[]>([]);
const terminalBody = ref<HTMLElement | null>(null);

function addLog(message: string, type: 'info'|'success'|'processing' = 'info') {
    logs.value.push({
        time: new Date().toLocaleTimeString(),
        message,
        type
    });
    
    // Limit logs
    if (logs.value.length > 50) logs.value.shift();
    
    nextTick(() => {
        if (terminalBody.value) {
            terminalBody.value.scrollTop = terminalBody.value.scrollHeight;
        }
    });
}

// Watch incoming details (granular updates)
watch(() => props.details, (newDetail) => {
    if (newDetail) {
        addLog(newDetail, 'info');
    }
});

// Watch incoming steps from parent (Dashboard real-time listener)
watch(() => props.step, (newStep) => {
    if (newStep) {
        addLog(newStep, 'processing');
    }
});

watch(() => props.show, (val) => {
    if (val) {
        logs.value = [];
        addLog(`Analyzing Artifact: ${props.fileName || 'Unknown Document'}`, 'info');
        addLog('Parsing metadata and structure...', 'info');
    }
});

</script>

<template>
    <Modal :show="show" :maxWidth="'md'" :closeable="false">
        <div class="relative overflow-hidden bg-[#0A0A0B]/90 backdrop-blur-3xl border border-white/5 rounded-2xl shadow-[0_0:50px_rgba(16,185,129,0.15)]">
            <!-- Header -->
            <div class="px-8 py-5 border-b border-white/5 bg-white/[0.02] flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black italic tracking-tighter text-white uppercase mt-1">Document Analysis</h3>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest truncate max-w-[200px]">{{ fileName || 'Analyzing Artifact' }}</p>
                    </div>
                </div>
                <div class="text-xs font-black italic tracking-tighter text-emerald-400 animate-pulse uppercase">
                    {{ progress || 0 }}%
                </div>
            </div>

            <!-- Visualizer Area -->
            <div class="p-10 flex flex-col items-center justify-center relative h-56 border-b border-white/5 bg-black/20 overflow-hidden">
                 <!-- Grid Background -->
                 <div class="absolute inset-0 bg-[linear-gradient(rgba(16,185,129,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(16,185,129,0.05)_1px,transparent_1px)] bg-[size:24px_24px] [mask-image:radial-gradient(ellipse_at_center,black_40%,transparent:70%)]"></div>

                 <!-- Document Animation -->
                 <div class="relative w-28 h-36 border-2 border-emerald-500/30 rounded bg-emerald-500/5 flex flex-col p-3 overflow-hidden">
                      <div class="w-full h-1 bg-emerald-500/20 mb-2"></div>
                      <div class="w-2/3 h-1 bg-emerald-500/20 mb-2"></div>
                      <div class="w-full h-1 bg-emerald-500/20 mb-2"></div>
                      <div class="w-1/2 h-1 bg-emerald-500/20"></div>
                      
                      <!-- Scanning Beam -->
                      <div class="absolute left-0 right-0 h-16 bg-gradient-to-b from-transparent via-emerald-400/20 to-transparent animate-[scan_2s_ease-in-out_infinite] pointer-events-none"></div>
                      <div class="absolute left-0 right-0 h-px bg-emerald-400 shadow-[0_0_15px_#34d399] animate-[scan_2s_ease-in-out_infinite] pointer-events-none"></div>
                 </div>

                 <!-- Status Text -->
                 <div class="mt-6 font-bold text-[10px] text-emerald-300 z-10 uppercase tracking-widest">
                    Analyzing: <span class="text-white animate-pulse">{{ step || 'Processing Blocks' }}</span>
                 </div>
            </div>

            <!-- Terminal Logs -->
            <div class="bg-black/60 h-48 overflow-hidden relative flex flex-col backdrop-blur-md">
                <div class="absolute inset-0 pointer-events-none bg-[url('https://grainy-gradients.vercel.app/noise.svg')] opacity-5"></div>
                
                <div ref="terminalBody" class="flex-1 overflow-y-auto p-5 space-y-2 custom-scrollbar font-bold text-[9px] uppercase tracking-wider">
                    <div v-for="(log, i) in logs" :key="i" class="flex gap-4 text-slate-500 border-l-2 pl-3 transition-all" :class="{
                        'border-emerald-500/50': log.type === 'info',
                        'border-emerald-500': log.type === 'success',
                        'border-amber-500': log.type === 'warning',
                        'border-rose-500': log.type === 'error'
                    }">
                        <span class="text-slate-700 font-black opacity-50">{{ log.time }}</span>
                        <span :class="{
                            'text-emerald-300': log.type === 'info',
                            'text-emerald-400': log.type === 'success',
                            'text-amber-400': log.type === 'warning',
                            'text-rose-400': log.type === 'error'
                        }">{{ log.message }}</span>
                    </div>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="h-1 bg-slate-800 w-full">
                <div class="h-full bg-gradient-to-r from-indigo-500 to-emerald-500 transition-all duration-300 shadow-[0_0_10px_rgba(99,102,241,0.5)]" :style="{ width: `${progress || 0}%` }"></div>
            </div>

            <!-- Complete Overlay -->
            <transition name="fade">
                <div v-if="show && (progress ?? 0) >= 100" class="absolute inset-0 z-50 flex items-center justify-center bg-[#0f172a]/90 backdrop-blur-sm animate-in">
                    <div class="p-6 flex flex-col items-center text-center">
                        <div class="w-16 h-16 rounded-full bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center mb-4 shadow-[0_0_30px_rgba(16,185,129,0.2)]">
                            <svg class="w-8 h-8 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-black text-white italic uppercase tracking-tighter mb-1">Audit Complete</h3>
                        <p class="text-slate-400 text-[10px] mb-6 font-mono">Forensic Analysis Protocol Finalized.</p>
                        
                        <div class="flex flex-col w-full gap-3 min-w-[240px]">
                            <button 
                                @click="emit('view-results')"
                                class="w-full py-4 bg-gradient-to-r from-emerald-600 to-emerald-400 text-white font-black uppercase tracking-[0.2em] italic rounded-2xl shadow-xl shadow-emerald-600/20 transition-all hover:scale-[1.02] active:scale-[0.98]"
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
@keyframes scan {
    0% { top: -50%; opacity: 0; }
    50% { opacity: 1; }
    100% { top: 150%; opacity: 0; }
}
@keyframes scanLine {
    0% { top: 0%; }
    100% { top: 100%; }
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
