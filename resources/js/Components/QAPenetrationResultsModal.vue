<script setup lang="ts">
/**
 * QAPenetrationResultsModal Component
 * Dedicated modal for displaying security scan results and forensic vectors.
 */
import { computed, ref } from 'vue';
import { Radar, Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    RadialLinearScale,
    PointElement,
    LineElement,
    BarElement,
    CategoryScale,
    LinearScale,
    Filler,
    Tooltip,
    Legend,
} from 'chart.js';
import type { VaultAsset } from '@/types/vault';

ChartJS.register(RadialLinearScale, PointElement, LineElement, BarElement, CategoryScale, LinearScale, Filler, Tooltip, Legend);

interface Props {
    show: boolean;
    asset: VaultAsset | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'open-ai-chat'): void;
    (e: 're-scan'): void;
    (e: 'refresh'): void;
}>();

// ---------------------------------------------------------------------------
// DATA EXTRACTION
// ---------------------------------------------------------------------------

const auditData = computed(() => {
    if (!props.asset?.metadata) return null;
    const metadata = props.asset.metadata as any;
    return {
        score: metadata.security_audit?.security_score ?? props.asset.score ?? metadata.confidence_score ?? 0,
        security_audit: metadata.security_audit ?? null,
    };
});

const activeFindings = computed(() => auditData.value?.security_audit?.active_test_results ?? null);
const subdomainEnum = computed(() => activeFindings.value?.subdomain_enum ?? []);
const directoryFindings = computed(() => activeFindings.value?.directory_fuzzing ?? []);
const xssFindings = computed(() => activeFindings.value?.xss_tests ?? []);
const csrfFindings = computed(() => activeFindings.value?.csrf_validation ?? []);
const techFingerprint = computed(() => activeFindings.value?.tech_fingerprint ?? {});

const hasActiveFindings = computed(() => {
    return subdomainEnum.value.length > 0 || 
           directoryFindings.value.length > 0 || 
           xssFindings.value.length > 0 || 
           csrfFindings.value.length > 0;
});

const remediationProgress = computed(() => auditData.value?.security_audit?.remediation_progress || {
    injection: 0,
    auth: 0,
    privacy: 0,
    access: 0,
    ui_stability: 0
});

const securityScore = computed(() => auditData.value?.score || 0);
const qaScore = computed(() => auditData.value?.security_audit?.qa_score || 0);

const statusLabel = computed(() => {
    // If score is 0 but no active findings/deductions, it might be initializing
    // However, if we truly fail everything, it's 0.
    // Let's check if we have audit data.
    if (securityScore.value === 0 && !hasActiveFindings.value) return { text: 'ANALYZING...', class: 'text-[#CBB48A] bg-[#CBB48A]/10 animate-pulse' };

    if (securityScore.value < 70) return { text: 'CRITICAL FAIL', class: 'text-rose-500 bg-rose-500/10' };
    if (securityScore.value < 85) return { text: 'ACTION REQUIRED', class: 'text-amber-500 bg-amber-500/10' };
    return { text: 'PASS', class: 'text-[#CBB48A] bg-[#CBB48A]/10' };
});

const getSeverityClass = (severity: string) => {
    switch (severity?.toUpperCase()) {
        case 'CRITICAL': return 'bg-rose-500 text-white ring-1 ring-rose-500/50 shadow-lg shadow-rose-500/20';
        case 'HIGH': return 'bg-orange-600 text-white ring-1 ring-orange-500/50';
        case 'MEDIUM': return 'bg-amber-500 text-white ring-1 ring-amber-500/50';
        case 'LOW': return 'bg-blue-500 text-white ring-1 ring-blue-500/50';
        default: return 'bg-slate-500 text-white';
    }
};

const stripMarkdown = (text: string) => {
    if (!text) return '';
    return text
        .replace(/\*\*(.*?)\*\*/g, '$1') // Bold
        .replace(/\*(.*?)\*/g, '$1')   // Italic
        .replace(/`([^`]+)`/g, '$1')   // Code
        .replace(/#+\s/g, '')          // Headers
        .replace(/\[([^\]]+)\]\([^\)]+\)/g, '$1') // Links
        .replace(/^[-*]\s/gm, '• ')    // Lists (start of line)
        .replace(/_/g, ' ')            // Underscores to spaces
        .replace(/\s+/g, ' ')          // Collapse multiple spaces
        .trim();
};

// ---------------------------------------------------------------------------
// CHARTS CONFIGURATION
// ---------------------------------------------------------------------------

const scoreBreakdownData = computed(() => {
    const rawBreakdown = auditData.value?.security_audit?.score_breakdown || {};
    const labels = Object.keys(rawBreakdown);
    const data = Object.values(rawBreakdown);

    return {
        labels: (labels.length ? labels : ['No Deductions']) as string[],
        datasets: [{
            label: 'Score Deduction',
            data: (data.length ? data : [0]) as number[],
            backgroundColor: (context: any) => {
                const val = context.raw;
                if (val <= -25) return 'rgba(244, 63, 94, 0.8)'; // Rose-500
                if (val <= -10) return 'rgba(245, 158, 11, 0.8)'; // Amber-500
                return 'rgba(6, 182, 212, 0.8)'; // Cyan-500
            },
            borderRadius: 6,
            barThickness: 20
        }]
    };
});

const barOptions = {
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 10 } } },
        y: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10, weight: 'bold' as const } } }
    },
    plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#0f172a', titleColor: '#f1f5f9', bodyColor: '#94a3b8' }
    }
};

const closeModal = () => emit('close');

// REMEDIATION DETAILS LOGIC
const showRemediationDetails = ref(false);
// PROOF LOGIC
const showProofModal = ref(false);
const selectedProof = ref<any>(null);

const viewProof = (vuln: any) => {
    selectedProof.value = vuln;
    showProofModal.value = true;
};

const remediationCategories = computed(() => {
    // Dynamic Analysis from Backend
    const protocols = auditData.value?.security_audit?.remediation_protocols;
    
    if (protocols) {
        const categories = [
            {
                id: 'injection',
                title: 'Injection Flaws (SQLi/XSS)',
                why: protocols.injection?.risk_analysis || 'Analyzing injection risks...',
                recommendation: protocols.injection?.strategic_fix || 'Implement input sanitization.',
                steps: protocols.injection?.tactical_action_plan || ['Audit Input Fields', 'Sanitize Database Queries']
            },
            {
                id: 'auth',
                title: 'Broken Authentication',
                why: protocols.auth?.risk_analysis || 'Analyzing auth risks...',
                recommendation: protocols.auth?.strategic_fix || 'Enforce strong session management.',
                steps: protocols.auth?.tactical_action_plan || ['Check Session Timeouts', 'Validate Tokens']
            },
            {
                id: 'privacy',
                title: 'Sensitive Data Exposure',
                why: protocols.privacy?.risk_analysis || 'Analyzing privacy risks...',
                recommendation: protocols.privacy?.strategic_fix || 'Encrypt sensitive data.',
                steps: protocols.privacy?.tactical_action_plan || ['Enable HTTPS', 'Audit .env']
            },
            {
                id: 'access',
                title: 'Broken Access Control',
                why: protocols.access?.risk_analysis || 'Analyzing access control...',
                recommendation: protocols.access?.strategic_fix || 'Implement strict RBAC.',
                steps: protocols.access?.tactical_action_plan || ['Audit Admin Routes', 'Verify Ownership']
            },
            {
                id: 'ui_stability',
                title: 'UI/UX Stability',
                why: protocols.ui_stability?.risk_analysis || 'Analyzing stability...',
                recommendation: protocols.ui_stability?.strategic_fix || 'Fix console errors.',
                steps: protocols.ui_stability?.tactical_action_plan || ['Check Console', 'Optimize Loading']
            }
        ];

        // Sort by Progress (Lowest First) and Override Text if 100%
        return categories.map(cat => {
            const progress = remediationProgress.value[cat.id] || 0;
            if (progress === 100) {
                return {
                    ...cat,
                    why: "Risk Mitigated. System analysis confirms robust defense architecture.",
                    recommendation: "Maintain current security posture.",
                    steps: ["No further action required.", "Periodic automated monitoring enabled."]
                };
            }
            return cat;
        }).sort((a, b) => {
            const scoreA = remediationProgress.value[a.id] || 0;
            const scoreB = remediationProgress.value[b.id] || 0;
            return scoreA - scoreB;
        });
    }

    // Fallback Static Data (Initializing)
    return [
        {
            id: 'injection',
            title: 'Injection Flaws (SQLi/XSS)',
            why: 'Input fields allow raw database commands or scripts to execute.',
            recommendation: 'Use prepared statements (PDO) and escape all user input.',
            steps: ['Sanitize all $_POST inputs.', 'Audit RAW SQL queries.']
        },
        {
            id: 'auth',
            title: 'Broken Authentication',
            why: 'Session management weak points detected.',
            recommendation: 'Enforce strong session management and token validation.',
            steps: ['Check Session Timeouts', 'Validate Tokens']
        },
        {
            id: 'privacy',
            title: 'Sensitive Data Exposure',
            why: 'Sensitive data transmission not fully encrypted.',
            recommendation: 'Encrypt sensitive data and enforce HTTPS.',
            steps: ['Enable HTTPS', 'Audit .env']
        },
        {
            id: 'access',
            title: 'Broken Access Control',
            why: 'Unrestricted access to sensitive endpoints.',
            recommendation: 'Implement strict Role-Based Access Control (RBAC).',
            steps: ['Audit Admin Routes', 'Verify Ownership']
        },
        {
            id: 'ui_stability',
            title: 'UI/UX Stability',
            why: 'Console errors or layout shifts detected.',
            recommendation: 'Fix console errors and optimize resource loading.',
            steps: ['Check Console', 'Optimize Loading']
        }
    ];
});

const isFixed = (vuln: any) => {
    // If the vulnerability specifically has a status of 'FIXED' (logic from backend comparison)
    if (vuln.status === 'FIXED') return true;
    
    // Or check if it is marked as history/resolved in metadata (future backend implementation)
    return false;
};
</script>

<template>
    <Teleport to="body">
        <transition enter-active-class="transition-opacity duration-200" leave-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-to-class="opacity-0">
            <div v-if="show && asset" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/90 backdrop-blur-md" @click="closeModal"></div>
                
                <div class="relative w-full max-w-5xl max-h-[90vh] overflow-hidden rounded-3xl border border-[#CBB48A]/20 bg-[#070b14] shadow-[0_0_80px_rgba(203, 180, 138, 0.1)] flex flex-col my-12">
                    
                    <!-- Header -->
                    <div class="flex-shrink-0 border-b border-[#CBB48A]/10 bg-[#0a0f1a] px-8 py-5 flex items-center justify-between relative overflow-hidden">
                        <div class="absolute inset-0 opacity-10 pointer-events-none">
                            <div class="absolute top-0 right-0 w-64 h-64 bg-[#CBB48A]/20 blur-3xl rounded-full -mr-32 -mt-32"></div>
                            <div class="absolute bottom-0 left-0 w-64 h-64 bg-[#F3E7C9]/20 blur-3xl rounded-full -ml-32 -mb-32"></div>
                        </div>

                        <div class="flex items-center gap-4 relative z-10">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-[#DCC8A5] to-[#F3E7C9]/90 flex items-center justify-center border border-white/10 shadow-lg shadow-[#CBB48A]/20">
                                <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-black text-white tracking-tight uppercase">LUME SOVEREIGN CONSOLE // <span class="text-[#CBB48A]">PENETRATION AUDIT</span></h2>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#CBB48A] animate-pulse"></span>
                                    <p class="text-[10px] text-[#CBB48A] font-mono uppercase tracking-[0.15em]">SEC_OPS_AUTHORIZED // SESSION_LIVE</p>
                                </div>
                            </div>
                        </div>
                        <button @click="closeModal" class="p-2 rounded-xl text-gray-500 hover:bg-white/10 hover:text-white transition-all z-10">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    
                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto px-8 py-8 bg-[#070b14] custom-scrollbar">
                        
                        <!-- Row 1: Recap & Progress -->
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 mb-8">
                            <div class="md:col-span-12 lg:col-span-7 space-y-6">
                                <div class="p-6 rounded-2xl border border-[#CBB48A]/20 bg-[#CBB48A]/5 relative overflow-hidden">
                                    <div class="absolute top-0 left-0 w-1 h-full bg-[#CBB48A]"></div>
                                    <h4 class="text-[10px] font-black text-[#CBB48A]/70 uppercase tracking-widest mb-4">Penetration Audit Summary & Scoring</h4>
                     <!-- Score Cards -->
                     <div class="grid grid-cols-2 gap-4">
                                <!-- SECURITY SCORE CARD -->
                                <div class="bg-[#050911] rounded-2xl p-5 border border-rose-500/10 relative group hover:border-rose-500/30 transition-all duration-500">
                                    <div class="absolute inset-0 bg-gradient-to-br from-rose-500/5 to-transparent opacity-50 rounded-2xl"></div>
                                    
                                    <div class="relative z-10 flex flex-col h-full justify-between">
                                        <div class="flex justify-between items-start">
                                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                                Security Score
                                                <!-- Tooltip -->
                                                <div class="relative group/tooltip">
                                                    <svg class="w-3.5 h-3.5 text-slate-600 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-slate-800 border border-slate-700 rounded-lg text-[10px] text-slate-300 leading-tight opacity-0 invisible group-hover/tooltip:opacity-100 group-hover/tooltip:visible transition-all duration-200 z-[100] shadow-xl pointer-events-none">
                                                        Based on OWASP Top 10 vulnerabilities and critical exposure vectors.
                                                    </div>
                                                </div>
                                            </h4>
                                        </div>
                                        <div class="flex items-end gap-3 z-10 relative">
                                            <span class="text-5xl font-black text-white tracking-tight">{{ securityScore }}</span>
                                            <span class="text-[10px] font-bold uppercase tracking-widest px-2 py-1 rounded-md mb-2" :class="statusLabel.class">
                                                {{ statusLabel.text }}
                                            </span>
                                        </div>
                                    </div>
                                    <!-- Background Blur -->
                                    <div class="absolute top-0 right-0 w-32 h-32 bg-[#CBB48A]/10 blur-3xl rounded-full -mr-10 -mt-10 group-hover:bg-[#CBB48A]/20 transition-all duration-500"></div>
                                </div>

                         <!-- QA Score -->
                         <div class="p-6 rounded-2xl bg-black/40 border border-slate-800 relative group">
                             <div class="flex items-center gap-2 mb-2">
                                 <h4 class="text-xs font-black text-slate-500 uppercase tracking-widest">QA Score</h4>
                                 <div class="relative group/tooltip z-20">
                                     <svg class="w-4 h-4 text-slate-600 hover:text-[#CBB48A] cursor-help transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                     <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-slate-900 border border-slate-700 rounded-lg shadow-xl opacity-0 invisible group-hover/tooltip:opacity-100 group-hover/tooltip:visible transition-all text-[10px] text-slate-300 pointer-events-none z-[100]">
                                          <p class="font-bold text-white mb-1">Quality Assurance Index</p>
                                         <p>Measures UI stability, functional integrity, and error rate during automated interaction simulation.</p>
                                     </div>
                                 </div>
                             </div>
                             <div class="flex items-end gap-3 z-10 relative">
                                 <span class="text-5xl font-black text-white tracking-tight">{{ qaScore }}</span>
                                 <span class="text-[10px] text-[#CBB48A] font-bold uppercase tracking-widest px-2 py-1 rounded-md mb-2 bg-[#CBB48A]/10">
                                     Optimal
                                 </span>
                             </div>
                             <div class="absolute top-0 right-0 w-32 h-32 bg-[#CBB48A]/10 blur-3xl rounded-full -mr-10 -mt-10 group-hover:bg-[#CBB48A]/20 transition-all duration-500"></div>
                         </div>
                     </div>
                                    <div class="h-40 p-4 rounded-xl border border-white/5 bg-black/20 mt-6">
                                        <Bar :data="scoreBreakdownData" :options="barOptions" />
                                    </div>
                                </div>
                            </div>

                            <div class="md:col-span-12 lg:col-span-5 h-full">
                                <div class="p-6 rounded-2xl border border-white/5 bg-black/20 h-full">
                                    <div class="flex items-center justify-between mb-6">
                                        <h4 class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Remediation Progress</h4>
                                        <button @click="showRemediationDetails = true" class="text-[10px] text-[#CBB48A] hover:text-[#CBB48A]/70 font-bold uppercase flex items-center gap-1">
                                            View Details
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </button>
                                    </div>
                                    <div class="space-y-6">
                                        <div v-for="(val, key) in remediationProgress" :key="key">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-[10px] font-bold text-slate-400 uppercase">{{ key }}</span>
                                                <span class="text-[10px] font-mono text-[#CBB48A]">{{ val }}%</span>
                                            </div>
                                            <div class="h-1.5 w-full bg-white/5 rounded-full overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-[#DCC8A5] to-[#CBB48A]" :style="{ width: val + '%' }"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Checklist -->
                        <div class="p-6 rounded-2xl border border-white/10 bg-black/40 mb-8" v-if="auditData?.security_audit?.vulnerabilities?.length">
                            <h4 class="text-[10px] font-black text-slate-300 uppercase tracking-widest mb-6">Audit & Remediation Checklist</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-black/40 text-slate-500 uppercase font-black">
                                        <tr>
                                            <th class="px-4 py-4 w-28">Severity</th>
                                            <th class="px-4 py-4">Threat Vector</th>
                                            <th class="px-4 py-4 hidden lg:table-cell">Remediation Steps</th>
                                            <th class="px-4 py-4 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5">
                                        <template v-for="(vuln, idx) in auditData.security_audit.vulnerabilities" :key="idx">
                                            <tr class="group hover:bg-white/[0.02] transition-colors border-b border-gray-800/50">
                                                <td class="py-4 pl-4">
                                                    <span class="px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wide" :class="getSeverityClass(vuln.severity)">
                                                        {{ vuln.severity }}
                                                    </span>
                                                </td>
                                                <td class="py-4">
                                                    <div class="flex flex-col">
                                                        <span class="text-xs font-bold text-slate-200">{{ stripMarkdown(vuln.type) }}</span>
                                                        <span class="text-[10px] text-slate-500 font-mono">{{ stripMarkdown(vuln.location || vuln.description) }}</span>
                                                    </div>
                                                </td>
                                                <td class="py-4 pr-4 hidden lg:table-cell">
                                                    <div class="flex flex-col gap-1">
                                                        <span v-for="(step, sIdx) in (vuln.remediation_steps || [vuln.remediation])" :key="sIdx" class="flex items-start gap-1 text-[10px] text-slate-400">
                                                            <span class="text-[#CBB48A] mt-0.5">›</span> {{ stripMarkdown(step) }}
                                                        </span>
                                                    </div>
                                                </td> 
                                                <td class="py-4 pr-4 text-right flex items-center justify-end gap-2">
                                                    <!-- Proof Button -->
                                                    <button @click="viewProof(vuln)" class="px-3 py-1.5 rounded-lg border border-[#CBB48A]/30 bg-[#CBB48A]/10 text-[#CBB48A]/70 text-[10px] font-bold uppercase tracking-wide hover:bg-[#CBB48A]/20 transition-all flex items-center gap-1">
                                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                        Proof
                                                    </button>
                                                
                                                    <!-- Check historical status if this is a rescan -->
                                                    <button v-if="isFixed(vuln)" class="px-3 py-1.5 rounded-lg border border-[#CBB48A]/20 bg-[#CBB48A]/10 text-[#CBB48A] text-[10px] font-bold uppercase tracking-wide cursor-default flex items-center gap-1">
                                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                        Fixed
                                                    </button>
                                                    <button v-else disabled class="px-3 py-1.5 rounded-lg border border-[#CBB48A]/30 bg-[#CBB48A]/10 text-[#CBB48A]/70 text-[10px] font-bold uppercase tracking-wide opacity-50 cursor-not-allowed">
                                                        Fix It Now
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Row 3: Recon Findings -->
                        <div v-if="hasActiveFindings" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                            <div v-if="subdomainEnum.length" class="p-4 rounded-xl border border-white/5 bg-slate-900/40">
                                <h5 class="text-[10px] font-black text-slate-500 uppercase mb-3">Domain Map</h5>
                                <div v-for="sub in subdomainEnum" :key="sub.subdomain" class="flex justify-between text-[10px] font-mono mb-1">
                                    <span class="text-slate-400">{{ sub.subdomain }}</span>
                                    <span class="text-[#CBB48A]">{{ sub.risk }}</span>
                                </div>
                            </div>
                            <!-- Infrastructure section removed - was showing test data -->
                            <div v-if="xssFindings.length" class="p-4 rounded-xl border border-rose-500/20 bg-rose-500/5 col-span-1 md:col-span-1">
                                <h5 class="text-[10px] font-black text-rose-500 uppercase mb-3">Critical Probes</h5>
                                <div class="text-[9px] text-rose-400 font-mono italic">XSS Injection Point Detected</div>
                            </div>
                        </div>

                        <!-- Sovereign Results -->
                        <div v-if="asset.metadata?.specific_metadata?.ai_analysis || asset.metadata?.sovereign_instructions" class="p-6 rounded-2xl border border-[#CBB48A]/20 bg-[#CBB48A]/5 relative overflow-hidden">
                             
                             <!-- Custom Prompt Context Label -->
                             <div v-if="asset.metadata?.sovereign_instructions" class="mb-4 pb-4 border-b border-[#CBB48A]/10">
                                 <h4 class="text-[9px] font-black text-[#DCC8A5] uppercase tracking-widest mb-1.5 opacity-70">Specific Sovereign Instructions</h4>
                                 <div class="text-[10px] text-[#CBB48A]/80 font-mono italic">
                                     "{{ asset.metadata.sovereign_instructions }}"
                                 </div>
                             </div>

                             <h4 class="text-[10px] font-black text-[#CBB48A] uppercase tracking-widest mb-4">Sovereign Result Intel</h4>
                             <div class="text-xs text-[#CBB48A]/10/80 leading-relaxed italic font-mono">
                                {{ stripMarkdown(asset.metadata?.specific_metadata?.ai_analysis || asset.metadata?.sovereign_results || 'Instructions processed. No specific deviations found.') }}
                             </div>
                             <!-- Added custom prompt output check -->
                             <div v-if="asset.metadata?.specific_metadata?.surface_test_output" class="mt-4 pt-4 border-t border-[#CBB48A]/20">
                                 <h4 class="text-[10px] font-black text-[#CBB48A] uppercase tracking-widest mb-2">Targeted Probe Results</h4>
                                 <div v-for="(res, idx) in asset.metadata.specific_metadata.surface_test_output" :key="idx" class="mb-2">
                                     <div class="flex justify-between text-[10px] font-bold uppercase">
                                         <span class="text-slate-300">{{ res.check }}</span>
                                         <span :class="res.status === 'VULNERABLE' ? 'text-rose-500' : 'text-[#CBB48A]'">{{ res.status }}</span>
                                     </div>
                                     <div class="text-[10px] text-slate-500 font-mono">{{ stripMarkdown(res.details) }}</div>
                                 </div>
                             </div>
                        </div>

                    </div>
                    
                    <!-- Footer -->
                    <div class="flex-shrink-0 border-t border-[#CBB48A]/20 bg-[#0a0f1a] px-8 py-5 flex items-center justify-end gap-3 z-10">
                        <div class="absolute bottom-0 right-0 w-32 h-1 bg-[#CBB48A] blur-lg opacity-50"></div>
                        <button @click="$emit('open-ai-chat')" class="px-4 py-2 rounded-xl border border-[#CBB48A]/30 bg-[#CBB48A]/5 text-[#CBB48A] text-[10px] font-black uppercase hover:bg-[#CBB48A]/10 transition-all">Ask LUME AI</button>
                        <button @click="$emit('re-scan')" class="px-4 py-2 rounded-xl border border-[#F3E7C9]/30 bg-[#F3E7C9]/5 text-[#F3E7C9] text-[10px] font-black uppercase hover:bg-[#F3E7C9]/10 transition-all">Initiate Re-Scan</button>
                        <div class="w-px h-6 bg-white/10 mx-2"></div>
                        <button @click="closeModal" class="px-6 py-2 bg-gradient-to-r from-slate-800 to-slate-900 border border-white/5 text-white text-[10px] font-black uppercase rounded-xl hover:from-slate-700 transition-all">Close</button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>

    <!-- REMEDIATION DETAILS MODAL (APPENDED) -->
    <Teleport to="body">
        <div v-if="showRemediationDetails" class="fixed inset-0 z-[9999] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900 bg-opacity-90 transition-opacity" aria-hidden="true" @click="showRemediationDetails = false"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-[#070b14] rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-[#CBB48A]/30 shadow-[#CBB48A]/20">
                    
                    <!-- HEADER -->
                    <div class="bg-[#0a0f1a] px-6 py-4 border-b border-[#CBB48A]/20 flex justify-between items-center relative overflow-hidden">
                        <div class="absolute inset-0 opacity-20 pointer-events-none">
                                <div class="absolute top-0 right-0 w-64 h-64 bg-[#CBB48A]/20 blur-3xl rounded-full -mr-32 -mt-32"></div>
                        </div>
                        <div class="relative z-10">
                            <h3 class="text-lg leading-6 font-black text-white uppercase tracking-wider" id="modal-title">
                                Remediation <span class="text-[#CBB48A]">Protocols</span>
                            </h3>
                            <p class="mt-1 text-xs text-slate-400 font-mono">
                                Detailed analysis and step-by-step fix implementation guide.
                            </p>
                        </div>
                        <button @click="showRemediationDetails = false" class="text-slate-500 hover:text-white transition-colors relative z-10">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- BODY -->
                    <div class="px-6 py-6 max-h-[70vh] overflow-y-auto custom-scrollbar bg-[#070b14]">
                        <div class="space-y-8">
                            
                            <!-- Loop through categories -->
                            <div v-for="category in remediationCategories" :key="category.id" class="bg-slate-900/50 rounded-xl p-6 border border-white/5 hover:border-[#CBB48A]/30 transition-colors group">
                                <div class="flex items-start gap-4">
                                    <!-- Icon/Check Status -->
                                    <div class="flex-shrink-0 mt-1">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center transition-all duration-500" 
                                            :class="remediationProgress[category.id] === 100 ? 'bg-[#CBB48A]/20 text-[#CBB48A] shadow-[0_0_15px_rgba(203, 180, 138, 0.3)]' : 'bg-rose-500/10 text-rose-500 shadow-[0_0_10px_rgba(239,68,68,0.1)]'">
                                            <svg v-if="remediationProgress[category.id] === 100" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        </div>
                                    </div>

                                    <div class="flex-1">
                                        <div class="flex justify-between items-center mb-2">
                                            <h4 class="text-sm font-black text-white uppercase tracking-wide">{{ category.title }}</h4>
                                            <span class="text-[10px] font-mono" :class="remediationProgress[category.id] === 100 ? 'text-[#CBB48A]' : 'text-rose-400'">
                                                {{ remediationProgress[category.id] }}% SECURE
                                            </span>
                                        </div>
                                        
                                        <!-- Progress Bar -->
                                        <div class="w-full bg-slate-800 rounded-full h-1.5 mb-6 overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-1000 relative" 
                                                :class="remediationProgress[category.id] === 100 ? 'bg-[#CBB48A]' : 'bg-rose-500'"
                                                :style="{ width: `${remediationProgress[category.id] || 5}%` }">
                                                <div class="absolute inset-0 bg-white/20 animate-pulse-slow"></div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-4">
                                            <!-- LEFT: WHY & RECOMMENDATION -->
                                            <div class="space-y-5">
                                                <div class="bg-black/20 p-4 rounded-lg border border-white/5">
                                                    <h5 class="text-[10px] font-black text-[#CBB48A] uppercase tracking-widest mb-2 flex items-center gap-2">
                                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                        Risk Analysis
                                                    </h5>
                                                    <p class="text-xs text-slate-300 leading-relaxed font-light">{{ stripMarkdown(category.why) }}</p>
                                                </div>
                                                <div>
                                                    <h5 class="text-[10px] font-black text-[#CBB48A] uppercase tracking-widest mb-2 flex items-center gap-2">
                                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                        Strategic Fix
                                                    </h5>
                                                    <div class="bg-[#050911] rounded p-3 border border-[#CBB48A]/20 font-mono text-[10px] text-[#CBB48A]/20 overflow-x-auto select-all">
                                                        {{ stripMarkdown(category.recommendation) }}
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- RIGHT: ACTION PLAN -->
                                            <div class="border-l border-white/5 pl-6 md:pl-8">
                                                <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Tactical Action Plan</h5>
                                                <ul class="space-y-3">
                                                    <li v-for="(step, stepIdx) in category.steps" :key="stepIdx" class="flex items-start gap-3 text-xs text-slate-300 group/item">
                                                        <span class="flex-shrink-0 w-5 h-5 rounded bg-slate-800 text-[#CBB48A] flex items-center justify-center font-mono text-[10px] border border-white/5 group-hover/item:border-[#CBB48A]/50 transition-colors">{{ stepIdx + 1 }}</span>
                                                        <span class="mt-0.5">{{ stripMarkdown(step) }}</span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- FOOTER (Simple Dismiss) -->
                    <div class="bg-[#0a0f1a] px-6 py-4 border-t border-[#CBB48A]/20 flex justify-end gap-3">
                        <button @click="showRemediationDetails = false" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-bold uppercase tracking-wide transition-colors border border-white/5">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>



<!-- PROOF MODAL (Appended) -->
        <Teleport to="body">
        <transition enter-active-class="transition-opacity duration-200" leave-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-to-class="opacity-0">
            <div v-if="showProofModal" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/90 backdrop-blur-md" @click="showProofModal = false"></div>
                
                <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl border border-[#CBB48A]/30 bg-[#0a0f1a] shadow-[0_0_50px_rgba(203, 180, 138, 0.2)] flex flex-col">
                    <div class="p-6 border-b border-white/10 flex justify-between items-center bg-black/20">
                        <div>
                            <h3 class="text-sm font-black text-white uppercase tracking-wider">Forensic Evidence</h3>
                            <p class="text-[10px] text-[#CBB48A] font-mono mt-1">{{ selectedProof?.type }}</p>
                        </div>
                        <button @click="showProofModal = false" class="text-slate-500 hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    
                    <div class="p-6 overflow-y-auto max-h-[60vh] bg-[#070b14]">
                        <div v-if="selectedProof?.evidence" class="space-y-4">
                            <div class="p-4 rounded-lg bg-black/50 border border-white/5 font-mono text-xs text-slate-300 whitespace-pre-wrap break-all shadow-inner">
                                {{ selectedProof.evidence }}
                            </div>
                            <div class="flex items-start gap-3 text-[11px] text-slate-500 bg-white/[0.02] p-3 rounded-lg border border-white/5">
                                <svg class="w-4 h-4 text-[#CBB48A] mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span>Verified by LUME SecOps Engine. This snippet was captured directly from the HTTP response or DOM analysis during the scan window.</span>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                            <svg class="w-12 h-12 text-slate-700 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            <p class="text-xs text-slate-500 font-bold uppercase">No specific evidence payload available.</p>
                            <p class="text-[10px] text-slate-600 font-mono mt-1">This vulnerability may have been inferred from metadata or missing headers.</p>
                        </div>
                    </div>
                    
                    <div class="p-4 bg-black/20 border-t border-white/5 flex justify-end">
                        <button @click="showProofModal = false" class="px-4 py-2 bg-[#DCC8A5] hover:bg-[#CBB48A] text-white text-xs font-bold uppercase rounded-lg transition-colors shadow-lg">
                            Close Verification
                        </button>
                    </div>
                </div>
            </div>
        </transition>
        </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.1); }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #CBB48A; }
</style>
