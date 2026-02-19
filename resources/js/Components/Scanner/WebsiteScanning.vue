<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import { ref, watch, nextTick } from 'vue';

const props = defineProps<{
    show: boolean;
    progress?: number;
    step?: string;
    details?: string;
    url?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'view-results'): void;
}>();

const logs = ref<{time: string, message: string, type: 'info'|'success'|'warning'|'error'}[]>([]);
const terminalBody = ref<HTMLElement | null>(null);

function addLog(message: string, type: 'info'|'success'|'warning'|'error' = 'info') {
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
         addLog(newDetail, 'info');
    }
});

// Watch milestones
watch(() => props.step, (newStep, oldStep) => {
    if (newStep && newStep !== oldStep) {
        let type: 'info'|'success'|'warning' = 'info';
        if (newStep.toLowerCase().includes('found')) type = 'success';
        if (newStep.toLowerCase().includes('analyzing')) type = 'info';
        addLog(newStep, type);
    }
});

watch(() => props.show, (val) => {
    if (val) {
        logs.value = [];
        addLog(`Initializing crawler target: ${props.url || 'Target Acquisition'}`, 'info');
        addLog('Establishing secure handshake...', 'info');
    }
});
</script>

<template>
    <Modal :show="show" :maxWidth="'md'" :closeable="false">
        <div class="relative overflow-hidden bg-[#0A0A0B]/90 backdrop-blur-3xl border border-white/5 rounded-2xl shadow-[0_0_50px_rgba(16,185,129,0.15)]">
            <!-- Header -->
            <div class="px-8 py-5 border-b border-white/5 bg-white/[0.02] flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black italic tracking-tighter text-white uppercase mt-1">Website Recon</h3>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest truncate max-w-[200px]">{{ url || 'Target Acquisition' }}</p>
                    </div>
                </div>
                <div class="text-xs font-black italic tracking-tighter text-emerald-400 animate-pulse uppercase">
                    {{ progress || 0 }}%
                </div>
            </div>

            <!-- Visualizer Area -->
            <div class="p-10 flex flex-col items-center justify-center relative h-56 border-b border-white/5 bg-black/20 overflow-hidden">
                 <!-- Grid Background -->
                 <div class="absolute inset-0 bg-[linear-gradient(rgba(16,185,129,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(16,185,129,0.05)_1px,transparent_1px)] bg-[size:24px_24px] [mask-image:radial-gradient(ellipse_at_center,black_40%,transparent_70%)]"></div>

                 <!-- Network Animation -->
                 <div class="relative w-36 h-36 flex items-center justify-center">
                      <!-- Central Node -->
                      <div class="absolute w-5 h-5 bg-white rounded-full shadow-[0_0_20px_white] z-10 animate-pulse"></div>
                      
                      <!-- Orbiting Nodes -->
                      <div class="absolute w-full h-full animate-[spin_5s_linear_infinite]">
                          <div class="absolute top-0 left-1/2 w-2.5 h-2.5 bg-emerald-400 rounded-full shadow-[0_0_10px_#34d399]"></div>
                          <div class="absolute bottom-0 left-1/2 w-2.5 h-2.5 bg-cyan-400 rounded-full shadow-[0_0_10px_#22d3ee]"></div>
                      </div>
                      <div class="absolute w-2/3 h-2/3 animate-[spin_4s_linear_infinite_reverse]">
                          <div class="absolute top-1/2 right-0 w-2 h-2 bg-emerald-500 rounded-full shadow-[0_0_10px_#10b981]"></div>
                          <div class="absolute top-1/2 left-0 w-2 h-2 bg-cyan-500 rounded-full shadow-[0_0_10px_#06b6d4]"></div>
                      </div>
                      
                      <!-- Connecting Lines (Pulse) -->
                      <div class="absolute inset-0 border border-emerald-500/20 rounded-full animate-ping opacity-10"></div>
                      <div class="absolute inset-4 border border-cyan-500/20 rounded-full animate-ping opacity-10 delay-150"></div>
                 </div>

                 <!-- Status Text -->
                 <div class="mt-6 font-bold text-[10px] text-emerald-300 z-10 uppercase tracking-widest">
                    Crawling: <span class="text-white animate-pulse">{{ step || 'Scanning DOM' }}</span>
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
            <div class="h-1 bg-slate-800 w-full relative">
                <div class="absolute inset-0 bg-indigo-500/20 blur-sm"></div>
                <div class="h-full bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-500 transition-all duration-300 relative z-10" :style="{ width: `${progress || 0}%` }"></div>
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
                        <h3 class="text-xl font-black text-white italic uppercase tracking-tighter mb-1">Recon Complete</h3>
                        <p class="text-slate-400 text-[10px] mb-6 font-mono">Forensic Digital Footprint Mapped.</p>
                        
                        <div class="flex flex-col w-full gap-3 min-w-[240px]">
                            <button 
                                @click="emit('view-results')"
                                class="w-full py-4 bg-gradient-to-r from-emerald-600 to-cyan-600 text-white font-black uppercase tracking-[0.2em] italic rounded-2xl shadow-xl shadow-emerald-600/20 transition-all hover:scale-[1.02] active:scale-[0.98]"
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
/* Custom animations if needed */
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
