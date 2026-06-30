<script setup lang="ts">
import { defineProps, defineEmits } from 'vue';
import ToxicityTreemap from './ToxicityTreemap.vue';

defineProps<{
    show: boolean;
    toxicityData: any;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[60] overflow-y-auto" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity" @click="emit('close')"></div>

        <!-- Modal Panel -->
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-[#0f172a] border border-slate-700 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:p-6 animate-fade-in-up">
                
                <div class="absolute right-0 top-0 hidden pr-4 pt-4 sm:block">
                    <button type="button" class="rounded-md bg-transparent text-slate-400 hover:text-white focus:outline-none" @click="emit('close')">
                        <span class="sr-only">Close</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="sm:flex sm:items-start">
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                        <h3 class="text-xl font-semibold leading-6 text-white mb-2">
                            Code Toxicity Analysis
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-slate-400 mb-6">
                                This heatmap visualizes the relationship between <strong>File Complexity</strong> (Size) and <strong>Churn Rate</strong> (Frequency of Change).
                                Files that are both large and frequently changed are considered "Toxic" as they are hotspots for bugs and technical debt.
                            </p>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                    <div class="bg-black/30 p-4 rounded border border-rose-500/20">
                                    <div class="text-rose-400 font-bold mb-1">High Toxicity (Red)</div>
                                    <div class="text-xs text-slate-400">Large files edited frequently. High risk of breaking changes. Refactor priority: <strong>Critical</strong>.</div>
                                    </div>
                                    <div class="bg-black/30 p-4 rounded border border-amber-500/20">
                                    <div class="text-amber-400 font-bold mb-1">Medium Toxicity (Amber/Pink)</div>
                                    <div class="text-xs text-slate-400">Moderate churn or size. Watch these files closely during code reviews.</div>
                                    </div>
                                    <div class="bg-black/30 p-4 rounded border border-[#CBB48A]/20">
                                    <div class="text-[#CBB48A] font-bold mb-1">Stable (Green)</div>
                                    <div class="text-xs text-slate-400">Static or small files. Low maintenance cost. Solid foundation.</div>
                                    </div>
                            </div>

                            <div class="h-[400px] w-full bg-black/20 rounded-lg overflow-hidden border border-slate-700/50">
                                <ToxicityTreemap :data="toxicityData" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-fade-in-up {
    animation: fadeInUp 0.3s ease-out;
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
</style>
