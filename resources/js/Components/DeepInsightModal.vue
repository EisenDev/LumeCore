<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
  Chart as ChartJS,
  RadialLinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
  Legend
} from 'chart.js';
import { Radar } from 'vue-chartjs';
import { marked } from 'marked';

ChartJS.register(
  RadialLinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
  Legend
);

const props = defineProps<{
  show: boolean;
  asset: any;
}>();

const emit = defineEmits(['close']);

// Chart Configuration with animation
const chartOptions: any = {
  responsive: true,
  maintainAspectRatio: false,
  animation: {
    duration: 1500,
    easing: 'easeOutQuart',
  },
  scales: {
    r: {
      angleLines: { color: 'rgba(255, 255, 255, 0.2)' },
      grid: { color: 'rgba(255, 255, 255, 0.2)' },
      pointLabels: {
        color: '#fff',
        font: { size: 11, family: 'Inter' }
      },
      ticks: { display: false, backdropColor: 'transparent' },
      suggestedMin: 0,
      suggestedMax: 100
    }
  },
  plugins: {
    legend: { display: false }
  }
};

// Parse radar_data correctly (handles both object and JSON string)
const radarData = computed(() => {
  const data = props.asset?.radar_data;
  if (!data) return {};
  if (typeof data === 'string') {
    try {
      return JSON.parse(data);
    } catch {
      return {};
    }
  }
  return data;
});

const chartData = computed(() => {
  const radar = radarData.value;
  return {
    labels: [
      'Code Resilience',
      'Security Perimeter',
      'Deployment Maturity',
      'SEO Authority',
      'Database Architecture'
    ],
    datasets: [
      {
        label: 'Audit Score',
        backgroundColor: 'rgba(99, 102, 241, 0.2)',
        borderColor: '#6366f1',
        pointBackgroundColor: '#6366f1',
        pointBorderColor: '#fff',
        pointHoverBackgroundColor: '#fff',
        pointHoverBorderColor: '#6366f1',
        data: [
          radar.code_resilience || 0,
          radar.security_perimeter || 0,
          radar.deployment_maturity || 0,
          radar.seo_authority || 0,
          radar.database_architecture || 0
        ]
      }
    ]
  };
});

// Parse full_audit_report
const auditReport = computed(() => {
  const report = props.asset?.full_audit_report;
  if (!report) return null;
  if (typeof report === 'string') {
    try {
      return JSON.parse(report);
    } catch {
      // If it's not JSON, it might be raw markdown
      return { markdown: report };
    }
  }
  return report;
});

// Get recommendations
const recommendations = computed(() => {
  return auditReport.value?.recommendations || [];
});

// Get markdown content (if available)
const markdownContent = computed(() => {
  const report = auditReport.value;
  if (!report) return '';
  
  // If there's a dedicated markdown field
  if (report.markdown) return report.markdown;
  
  // Build markdown from structured data
  let md = '';
  if (report.summary) {
    md += `## Executive Summary\n\n${report.summary}\n\n`;
  }
  if (report.insights?.length) {
    md += `## Key Insights\n\n${report.insights.map((i: string, idx: number) => `${idx + 1}. ${i}`).join('\n')}\n\n`;
  }
  if (report.recommendations?.length) {
    md += `## Remediation Roadmap\n\n${report.recommendations.map((r: string, idx: number) => `**Step ${idx + 1}:** ${r}`).join('\n\n')}\n\n`;
  }
  if (report.warning_flags?.length) {
    md += `## ⚠️ Warning Flags\n\n${report.warning_flags.map((w: string) => `- ${w}`).join('\n')}\n`;
  }
  return md;
});

// Render markdown
const renderMarkdown = (content: string) => {
  if (!content) return '';
  return marked(content);
};

// Overall score
const overallScore = computed(() => {
  return props.asset?.metadata?.confidence_score || auditReport.value?.score || 0;
});
</script>

<template>
  <!-- Slide-over Panel (Right Side) -->
  <transition
    enter-active-class="transform transition ease-in-out duration-300"
    enter-from-class="translate-x-full"
    enter-to-class="translate-x-0"
    leave-active-class="transform transition ease-in-out duration-300"
    leave-from-class="translate-x-0"
    leave-to-class="translate-x-full"
  >
    <div v-if="show" class="fixed inset-y-0 right-0 z-[60] flex max-w-full">
      <!-- Backdrop -->
      <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="$emit('close')"></div>
      
      <!-- Panel -->
      <div class="relative w-screen max-w-2xl">
        <div class="flex h-full flex-col overflow-y-auto bg-gray-900 border-l border-indigo-500/30 shadow-2xl">
          
          <!-- Header -->
          <div class="sticky top-0 z-10 bg-gray-900/95 backdrop-blur-md px-6 py-5 border-b border-gray-700">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-xl font-bold text-white flex items-center gap-3">
                  <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-500/20">
                    <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                  </div>
                  Deep Forensic Audit
                </h2>
                <p class="mt-1 text-sm text-gray-400 font-mono">>> LUME_SOVEREIGN_INTELLIGENCE</p>
              </div>
              <button @click="$emit('close')" class="rounded-lg p-2 text-gray-400 hover:bg-gray-800 hover:text-white transition-colors">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Content -->
          <div class="flex-1 px-6 py-6 space-y-6">
            
            <!-- Overall Score Badge -->
            <div class="flex items-center justify-center">
              <div class="flex items-center gap-4 rounded-xl bg-gradient-to-r from-indigo-500/10 to-purple-500/10 border border-indigo-500/30 px-6 py-4">
                <div class="text-center">
                  <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Overall Score</p>
                  <p class="text-4xl font-bold" :class="overallScore >= 80 ? 'text-green-400' : overallScore >= 60 ? 'text-yellow-400' : 'text-red-400'">
                    {{ overallScore }}
                  </p>
                </div>
                <div class="h-12 w-px bg-gray-700"></div>
                <div class="text-center">
                  <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Verdict</p>
                  <p class="text-lg font-semibold text-white">{{ asset?.status || 'N/A' }}</p>
                </div>
              </div>
            </div>

            <!-- Radar Chart -->
            <div class="rounded-xl bg-gray-800/30 border border-gray-700 p-6">
              <h3 class="text-lg font-semibold text-indigo-400 mb-4 flex items-center gap-2">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                </svg>
                Audit Vectors
              </h3>
              <div class="w-full h-[280px] relative">
                <Radar :data="chartData" :options="chartOptions" />
              </div>
              
              <!-- Vector Scores Grid -->
              <div class="mt-4 grid grid-cols-5 gap-2 text-center text-xs">
                <div>
                  <p class="text-gray-500">Resilience</p>
                  <p class="font-bold text-indigo-400">{{ radarData.code_resilience || 0 }}</p>
                </div>
                <div>
                  <p class="text-gray-500">Security</p>
                  <p class="font-bold text-indigo-400">{{ radarData.security_perimeter || 0 }}</p>
                </div>
                <div>
                  <p class="text-gray-500">Deploy</p>
                  <p class="font-bold text-indigo-400">{{ radarData.deployment_maturity || 0 }}</p>
                </div>
                <div>
                  <p class="text-gray-500">SEO</p>
                  <p class="font-bold text-indigo-400">{{ radarData.seo_authority || 0 }}</p>
                </div>
                <div>
                  <p class="text-gray-500">Database</p>
                  <p class="font-bold text-indigo-400">{{ radarData.database_architecture || 0 }}</p>
                </div>
              </div>
            </div>

            <!-- Remediation Roadmap -->
            <div v-if="recommendations.length > 0" class="rounded-xl bg-gray-800/30 border border-gray-700 p-6">
              <h3 class="text-lg font-semibold text-indigo-400 mb-4 flex items-center gap-2">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                Technical Remediation Roadmap
              </h3>
              
              <div class="flow-root relative">
                <div class="absolute left-4 top-2 bottom-2 w-0.5 bg-gradient-to-b from-indigo-500 to-purple-500"></div>
                <ul role="list" class="-mb-8">
                  <li v-for="(item, index) in recommendations" :key="index">
                    <div class="relative pb-6 pl-10">
                      <span class="absolute left-1.5 top-1.5 h-5 w-5 rounded-full border-2 border-indigo-500 bg-gray-900 flex items-center justify-center">
                        <span class="text-xs font-bold text-indigo-400">{{ Number(index) + 1 }}</span>
                      </span>
                      <div class="pt-0.5">
                        <p class="text-sm font-medium text-white">Priority {{ Number(index) + 1 }}</p>
                        <p class="text-sm text-gray-300 mt-1">{{ item }}</p>
                      </div>
                    </div>
                  </li>
                </ul>
              </div>
            </div>

            <!-- Full Report (Markdown) -->
            <div v-if="markdownContent" class="rounded-xl bg-gray-800/30 border border-gray-700 p-6">
              <h3 class="text-lg font-semibold text-indigo-400 mb-4 flex items-center gap-2">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Full Forensic Report
              </h3>
              <div 
                class="prose prose-invert prose-sm max-w-none prose-headings:text-indigo-400 prose-a:text-blue-400 prose-strong:text-white"
                v-html="renderMarkdown(markdownContent)"
              ></div>
            </div>

          </div>

          <!-- Footer -->
          <div class="sticky bottom-0 bg-gray-900/95 backdrop-blur-md px-6 py-4 border-t border-gray-700 flex justify-end gap-3">
            <button 
              type="button" 
              class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-lg hover:bg-indigo-500 transition-colors"
              @click="$emit('close')"
            >
              Close Report
            </button>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>
