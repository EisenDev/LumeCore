<script setup lang="ts">
/**
 * DocumentReportModal Component
 * Clean, corporate-style modal for PDF/Image document audits.
 * Shows Professionalism Grade, PII Warnings, and Marketplace Eligibility.
 */
import { computed, ref, watch } from 'vue';
import { Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    ArcElement,
    Tooltip,
    Legend,
} from 'chart.js';
import type { VaultAsset } from '@/types/vault';

ChartJS.register(ArcElement, Tooltip, Legend);

interface Props {
    show: boolean;
    asset: VaultAsset | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'publish', asset: VaultAsset): void;
}>();

// Animation state
const animatedScore = ref(0);
let scoreAnimationFrame: number | null = null;

function animateScoreTo(targetScore: number) {
    if (scoreAnimationFrame) cancelAnimationFrame(scoreAnimationFrame);
    const duration = 1200;
    const startTime = performance.now();
    const startScore = animatedScore.value;
    
    function tick(currentTime: number) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeOut = 1 - Math.pow(1 - progress, 3);
        animatedScore.value = Math.round(startScore + (targetScore - startScore) * easeOut);
        if (progress < 1) {
            scoreAnimationFrame = requestAnimationFrame(tick);
        }
    }
    scoreAnimationFrame = requestAnimationFrame(tick);
}

// Computed: Extract audit data from metadata
const auditData = computed(() => {
    if (!props.asset?.metadata) return null;
    const metadata = props.asset.metadata as any;
    return {
        verdict: metadata.verdict ?? props.asset.status,
        score: props.asset.score ?? metadata.confidence_score ?? 0,
        summary: metadata.summary ?? '',
        document_type: metadata.document_type ?? 'Document',
        breakdown: metadata.breakdown ?? {},
        pii_detected: metadata.pii_detected ?? [],
        pii_test_applied: metadata.pii_test_applied ?? 'None',
        insights: metadata.insights ?? [],
        warning_flags: metadata.warning_flags ?? [],
        is_marketplace_eligible: metadata.is_marketplace_eligible ?? false,
        flag_reason: metadata.flag_reason ?? null,
    };
});

// Computed: Grade based on score
const gradeInfo = computed(() => {
    const score = auditData.value?.score ?? 0;
    if (score >= 85) return { letter: 'A', label: 'Excellent', color: 'text-emerald-400', bgColor: 'bg-emerald-500' };
    if (score >= 80) return { letter: 'B', label: 'Good', color: 'text-blue-400', bgColor: 'bg-blue-500' };
    if (score >= 70) return { letter: 'C', label: 'Fair', color: 'text-amber-400', bgColor: 'bg-amber-500' };
    return { letter: 'F', label: 'Needs Work', color: 'text-red-400', bgColor: 'bg-red-500' };
});

// Computed: Status badge styling
const statusInfo = computed(() => {
    const status = auditData.value?.verdict ?? props.asset?.status;
    switch (status) {
        case 'verified':
            return { label: 'VERIFIED', color: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' };
        case 'verified_private':
            return { label: 'PRIVATE ONLY', color: 'bg-purple-500/20 text-purple-400 border-purple-500/30' };
        case 'flagged':
            return { label: 'FLAGGED', color: 'bg-red-500/20 text-red-400 border-red-500/30' };
        case 'action_required':
            return { label: 'ACTION REQUIRED', color: 'bg-amber-500/20 text-amber-400 border-amber-500/30' };
        default:
            return { label: 'PENDING', color: 'bg-gray-500/20 text-gray-400 border-gray-500/30' };
    }
});

// Chart configuration
const chartData = computed(() => ({
    datasets: [{
        data: [auditData.value?.score ?? 0, 100 - (auditData.value?.score ?? 0)],
        backgroundColor: [gradeInfo.value.bgColor.replace('bg-', '#').replace('-500', ''), 'rgba(30, 41, 59, 0.5)'],
        borderWidth: 0,
        cutout: '80%',
    }]
}));

const chartOptions: any = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { enabled: false } },
};

// Watch for modal open
watch(() => props.show, (newValue) => {
    if (newValue && auditData.value) {
        animatedScore.value = 0;
        setTimeout(() => {
            animateScoreTo(auditData.value?.score ?? 0);
        }, 200);
    }
});

const closeModal = () => emit('close');
</script>

<template>
    <Teleport to="body">
        <transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200"
            leave-to-class="opacity-0"
        >
            <div v-if="show && asset" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm" @click="closeModal"></div>
                
                <!-- Modal Container - Clean Corporate Style -->
                <div class="relative w-full max-w-2xl max-h-[90vh] overflow-hidden rounded-2xl bg-white shadow-2xl">
                    
                    <!-- Header - Clean White -->
                    <div class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200 px-8 py-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">
                                    Document Analysis Report
                                </p>
                                <h2 class="text-xl font-bold text-slate-800 truncate max-w-md">
                                    {{ asset.file_name }}
                                </h2>
                                <span class="inline-block mt-2 text-xs font-medium text-slate-500 bg-slate-200 px-2 py-1 rounded">
                                    {{ auditData?.document_type ?? 'Document' }}
                                </span>
                            </div>
                            <button @click="closeModal" class="p-2 rounded-lg hover:bg-slate-200 transition-colors">
                                <svg class="w-6 h-6 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Body - Scrollable -->
                    <div class="overflow-y-auto max-h-[calc(90vh-180px)] px-8 py-6 bg-white">
                        
                        <!-- Score & Grade Section -->
                        <div class="flex items-center gap-8 mb-8">
                            <!-- Score Ring -->
                            <div class="relative w-32 h-32 flex-shrink-0">
                                <Doughnut :data="chartData" :options="chartOptions" />
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="text-3xl font-bold text-slate-800">{{ animatedScore }}</span>
                                    <span class="text-xs text-slate-500 uppercase">Score</span>
                                </div>
                            </div>
                            
                            <!-- Grade Info -->
                            <div class="flex-1">
                                <div class="flex items-center gap-4 mb-3">
                                    <span 
                                        class="w-14 h-14 rounded-xl flex items-center justify-center text-2xl font-black text-white shadow-lg"
                                        :class="gradeInfo.bgColor"
                                    >
                                        {{ gradeInfo.letter }}
                                    </span>
                                    <div>
                                        <p class="text-lg font-semibold text-slate-800">{{ gradeInfo.label }}</p>
                                        <span 
                                            class="inline-block px-3 py-1 text-xs font-bold rounded-full border"
                                            :class="statusInfo.color"
                                        >
                                            {{ statusInfo.label }}
                                        </span>
                                    </div>
                                </div>
                                <p class="text-sm text-slate-600 leading-relaxed">
                                    {{ auditData?.summary || 'No summary available.' }}
                                </p>
                            </div>
                        </div>
                        
                        <!-- PII Detection Warning -->
                        <div v-if="auditData?.pii_detected?.length" class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-5">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-200 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-semibold text-amber-800 mb-2">Personal Information Detected</h4>
                                    <div class="flex flex-wrap gap-2 mb-3">
                                        <span 
                                            v-for="pii in auditData.pii_detected" 
                                            :key="pii"
                                            class="px-2 py-1 text-xs font-medium bg-amber-200 text-amber-800 rounded"
                                        >
                                            {{ pii }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-amber-700">
                                        <strong>Test Applied:</strong> {{ auditData.pii_test_applied }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Marketplace Eligibility -->
                        <div 
                            class="mb-6 rounded-xl p-5 border"
                            :class="auditData?.is_marketplace_eligible 
                                ? 'border-emerald-300 bg-emerald-50' 
                                : 'border-red-300 bg-red-50'"
                        >
                            <div class="flex items-center gap-3">
                                <div 
                                    class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center"
                                    :class="auditData?.is_marketplace_eligible ? 'bg-emerald-200' : 'bg-red-200'"
                                >
                                    <svg v-if="auditData?.is_marketplace_eligible" class="w-5 h-5 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <svg v-else class="w-5 h-5 text-red-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 
                                        class="font-semibold"
                                        :class="auditData?.is_marketplace_eligible ? 'text-emerald-800' : 'text-red-800'"
                                    >
                                        {{ auditData?.is_marketplace_eligible ? 'Eligible for Marketplace' : 'Not Marketplace Eligible' }}
                                    </h4>
                                    <p 
                                        class="text-sm"
                                        :class="auditData?.is_marketplace_eligible ? 'text-emerald-700' : 'text-red-700'"
                                    >
                                        {{ auditData?.flag_reason || (auditData?.is_marketplace_eligible ? 'This document can be listed for sale.' : 'Privacy restrictions prevent marketplace listing.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Breakdown Metrics -->
                        <div v-if="auditData?.breakdown" class="mb-6">
                            <h4 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-4">Quality Breakdown</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div v-if="auditData.breakdown.formatting !== undefined" class="bg-slate-50 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm text-slate-600">Formatting</span>
                                        <span class="text-sm font-bold text-slate-800">{{ auditData.breakdown.formatting }}%</span>
                                    </div>
                                    <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-blue-500 rounded-full" :style="{ width: `${auditData.breakdown.formatting}%` }"></div>
                                    </div>
                                </div>
                                <div v-if="auditData.breakdown.content_quality !== undefined" class="bg-slate-50 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm text-slate-600">Content Quality</span>
                                        <span class="text-sm font-bold text-slate-800">{{ auditData.breakdown.content_quality }}%</span>
                                    </div>
                                    <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" :style="{ width: `${auditData.breakdown.content_quality}%` }"></div>
                                    </div>
                                </div>
                                <div v-if="auditData.breakdown.template_value !== undefined" class="bg-slate-50 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm text-slate-600">Template Value</span>
                                        <span class="text-sm font-bold text-slate-800">{{ auditData.breakdown.template_value }}%</span>
                                    </div>
                                    <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-purple-500 rounded-full" :style="{ width: `${auditData.breakdown.template_value}%` }"></div>
                                    </div>
                                </div>
                                <div v-if="auditData.breakdown.industry_relevance !== undefined" class="bg-slate-50 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm text-slate-600">Industry Relevance</span>
                                        <span class="text-sm font-bold text-slate-800">{{ auditData.breakdown.industry_relevance }}%</span>
                                    </div>
                                    <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-amber-500 rounded-full" :style="{ width: `${auditData.breakdown.industry_relevance}%` }"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- AI Insights -->
                        <div v-if="auditData?.insights?.length" class="mb-6">
                            <h4 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-4">Professional Insights</h4>
                            <ul class="space-y-2">
                                <li 
                                    v-for="(insight, idx) in auditData.insights" 
                                    :key="idx"
                                    class="flex items-start gap-3 text-sm text-slate-600"
                                >
                                    <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold">
                                        {{ Number(idx) + 1 }}
                                    </span>
                                    {{ insight }}
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Warning Flags -->
                        <div v-if="auditData?.warning_flags?.length" class="mb-6">
                            <h4 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-4">Warnings</h4>
                            <ul class="space-y-2">
                                <li 
                                    v-for="(warning, idx) in auditData.warning_flags" 
                                    :key="idx"
                                    class="flex items-start gap-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"
                                >
                                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01" />
                                    </svg>
                                    {{ warning }}
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="border-t border-slate-200 bg-slate-50 px-8 py-4 flex items-center justify-between">
                        <p class="text-xs text-slate-500">
                            Analyzed by LUME Document Integrity Expert
                        </p>
                        <button 
                            @click="closeModal"
                            class="px-6 py-2 bg-slate-800 text-white text-sm font-medium rounded-lg hover:bg-slate-700 transition-colors"
                        >
                            Close Report
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
