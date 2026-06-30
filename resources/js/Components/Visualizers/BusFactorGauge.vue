<script setup lang="ts">
/**
 * BusFactorGauge.vue
 * Displays Bus Factor Risk using a Doughnut chart + Author List.
 */
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend
} from 'chart.js';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps<{
    data: {
        total_commits: number;
        authors: { name: string; percent: number; commits: number }[];
        bus_factor_score: number; // 1 (Critical) to 5 (Healthy)
    };
}>();

const riskLabel = computed(() => {
    const score = props.data.bus_factor_score;
    if (score <= 1) return { text: 'CRITICAL', color: 'text-rose-500' };
    if (score <= 3) return { text: 'MODERATE', color: 'text-amber-500' };
    return { text: 'HEALTHY', color: 'text-[#CBB48A]' };
});

const chartData = computed(() => {
    const authors = props.data.authors || [];
    return {
        labels: authors.map(a => a.name),
        datasets: [{
            data: authors.map(a => a.percent),
            backgroundColor: [
                '#CBB48A', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444', 
                '#6366f1', '#ec4899', '#14b8a6', '#f97316', '#64748b'
            ],
            borderWidth: 0
        }]
    };
});

const chartOptions = {
    responsive: true,
    cutout: '75%',
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (item: any) => `${item.label}: ${item.raw}%`
            }
        }
    }
};
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- Gauge Area -->
        <div class="relative h-40 w-full flex items-center justify-center mb-4">
            <Doughnut :data="chartData" :options="chartOptions" />
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                <div class="text-xs text-slate-500 uppercase tracking-widest">BUS FACTOR</div>
                <div class="text-2xl font-black font-mono" :class="riskLabel.color">
                    {{ riskLabel.text }}
                </div>
            </div>
        </div>

        <!-- Author List -->
        <div class="flex-1 overflow-y-auto custom-scrollbar px-2 max-h-[140px]">
            <div v-for="(author, index) in data.authors" :key="index" class="flex justify-between items-center py-2 border-b border-white/5 last:border-0">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: chartData.datasets[0].backgroundColor[index] }"></div>
                    <span class="text-xs text-slate-300 truncate max-w-[120px]" :title="author.name">{{ author.name }}</span>
                </div>
                <span class="text-xs font-mono text-slate-500">{{ author.percent }}%</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 2px; }
</style>
