<script setup lang="ts">
import { defineProps, defineEmits } from 'vue';

defineProps<{
    show: boolean;
    data: {
        insights?: string[];
        recommendations?: string[];
        warning_flags?: string[];
    };
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[70] overflow-y-auto" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/90 backdrop-blur-sm transition-opacity" @click="emit('close')"></div>

        <!-- Modal Panel -->
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-xl bg-[#0f172a] border border-slate-700 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl animate-scale-in">
                
                <!-- Header -->
                <div class="bg-slate-900/50 px-6 py-4 border-b border-slate-700 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Detailed Forensic Insights
                    </h3>
                    <button @click="emit('close')" class="text-slate-400 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto custom-scrollbar">
                    
                    <!-- Recommendations -->
                    <div v-if="data.recommendations?.length">
                        <h4 class="text-sm font-bold text-emerald-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                             <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                             Recommended Actions
                        </h4>
                        <ul class="space-y-3">
                            <li v-for="(rec, i) in data.recommendations" :key="i" class="bg-emerald-500/5 border border-emerald-500/10 p-3 rounded-lg text-slate-300 text-sm flex gap-3">
                                <span class="text-emerald-500 font-mono font-bold">{{ i + 1 }}.</span>
                                {{ rec }}
                            </li>
                        </ul>
                    </div>

                    <!-- Warnings -->
                    <div v-if="data.warning_flags?.length">
                        <h4 class="text-sm font-bold text-rose-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                             <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                             Critical Flags
                        </h4>
                        <ul class="space-y-2">
                            <li v-for="(flag, i) in data.warning_flags" :key="i" class="bg-rose-500/10 border border-rose-500/20 p-2 rounded text-rose-200 text-xs flex gap-2 items-center">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                {{ flag }}
                            </li>
                        </ul>
                    </div>

                    <!-- General Insights -->
                    <div v-if="data.insights?.length">
                        <h4 class="text-sm font-bold text-indigo-400 uppercase tracking-wider mb-3">Key Observations</h4>
                        <div class="grid gap-3">
                            <div v-for="(insight, i) in data.insights" :key="i" class="text-slate-400 text-sm pl-4 border-l-2 border-indigo-500/30">
                                {{ insight }}
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-scale-in {
    animation: scaleIn 0.2s ease-out;
}
@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #0f172a; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
</style>
