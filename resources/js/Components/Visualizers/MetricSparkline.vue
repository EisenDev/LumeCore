<script setup lang="ts">
import { computed } from 'vue';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Filler,
  ChartData
} from 'chart.js';
import { Line } from 'vue-chartjs';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Filler
);

const props = defineProps<{
    history: { score: number; date: string }[];
}>();

const chartData = computed<ChartData<'line'>>(() => ({
    labels: props.history.map(h => h.date),
    datasets: [{
        label: 'Trust Score',
        data: props.history.map(h => h.score),
        fill: true,
        borderColor: '#10b981', // Emerald-500
        backgroundColor: (context) => {
            const ctx = context.chart.ctx;
            const gradient = ctx.createLinearGradient(0, 0, 0, 100);
            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.4)');
            gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
            return gradient;
        },
        tension: 0.4,
        pointRadius: 2,
        pointHoverRadius: 4,
        borderWidth: 2
    }]
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            mode: 'index' as const,
            intersect: false,
            backgroundColor: 'rgba(15, 23, 42, 0.9)',
            titleColor: '#94a3b8',
            bodyColor: '#e2e8f0',
            borderColor: 'rgba(99, 102, 241, 0.2)',
            borderWidth: 1,
            displayColors: false,
            callbacks: {
                title: (items: any[]) => {
                    return new Date(items[0].label).toLocaleDateString();
                }
            }
        }
    },
    scales: {
        x: { display: false },
        y: { 
            display: false,
            min: 0,
            max: 100 
        }
    },
    interaction: {
        mode: 'nearest' as const,
        axis: 'x' as const,
        intersect: false
    }
};
</script>

<template>
    <div class="w-full h-full">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
