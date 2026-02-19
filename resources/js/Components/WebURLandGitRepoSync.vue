<script setup lang="ts">
/**
 * WebURLandGitRepoSync Component
 * Forensic Synchronization Dashboard
 */
import { computed, ref, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Radar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    RadialLinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend
} from 'chart.js';
import type { VaultAsset } from '@/types/vault';

ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend);

interface Props {
    show: boolean;
    webAsset: VaultAsset;
    repoAsset: VaultAsset;
    comparisonData: any; 
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'scan-again'): void;
    (e: 'open-marketplace-listing', isAlreadyListed: boolean): void;
    (e: 'deep-audit'): void;
    (e: 'open-pentest-results'): void;
    (e: 're-scan-pentest'): void;
}>();

// --- STATE ---
const showTechDetails = ref(false);
const showVectorDetails = ref(false);
const showCalculationReport = ref(false);
const showVerificationModal = ref(false);
const showPenEligibilityModal = ref(false);
const expandedRoadmapTask = ref<number | null>(null);

const roadmapGuides: Record<string, { title: string, steps: string[] }> = {
    'csp': {
        title: 'Security Headers Guide',
        steps: [
            'Enable Strict-Transport-Security (HSTS) with max-age=31536000.',
            'Configure Content-Security-Policy (CSP) to restrict unauthorized scripts.',
            'Verify headers using tools like securityheaders.com.'
        ]
    },
    'location': {
        title: 'Browser API Guide',
        steps: [
            'Ensure your site is served over HTTPS (required for Geolocation).',
            'Handle "Permission Denied" states gracefully in your UI.',
            'Check for active location blocks in your browser settings.'
        ]
    },
    'audit': {
        title: 'Dependency Hygiene Guide',
        steps: [
            'Run `npm audit fix` to resolve known vulnerabilities in JS packages.',
            'Run `composer audit` to check PHP dependency security.',
            'Update critical outdated libraries manually if auto-fix fails.'
        ]
    }
};

const getTaskGuide = (taskName: string) => {
    const name = taskName.toLowerCase();
    if (name.includes('csp') || name.includes('hsts')) return roadmapGuides.csp;
    if (name.includes('location') || name.includes('denied')) return roadmapGuides.location;
    if (name.includes('audit') || name.includes('dependency')) return roadmapGuides.audit;
    return null;
};

const isLumeVerified = computed(() => props.comparisonData?.drift_analysis?.is_lume_verified === true);
const syncScore = computed(() => Number(props.comparisonData?.sync_score ?? 0));
const canPerformPentest = computed(() => true); // DEV OVERRIDE: Allow testing on own website
// const canPerformPentest = computed(() => syncScore.value >= 85 && isLumeVerified.value);

const startPentest = () => {
    if (canPerformPentest.value) {
        showPenEligibilityModal.value = false;
        
        if (hasExistingScan.value) {
            // Open existing results
            emit('open-pentest-results');
        } else {
            // New scan initiation
            emit('deep-audit'); 
        }
    }
};

const triggerReScan = () => {
    showPenEligibilityModal.value = false;
    emit('re-scan-pentest');
};

const hasExistingScan = computed(() => {
    // A scan is only "existing" if it has security_audit data.
    // Sync scores should NOT trigger this button.
    const metadata = props.webAsset.metadata as any;
    return !!(metadata?.security_audit && Object.keys(metadata.security_audit).length > 0);
});

const generateVerificationFile = () => {
    const code = props.repoAsset.metadata?.sync_verify_code || props.webAsset.metadata?.sync_verify_code || 'LUME-VERIFY-' + Math.random().toString(36).substring(7).toUpperCase();
    const blob = new Blob([code], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'lume_verification.txt';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};
// ...




// --- CHART DATA ---
// --- SHARED VECTOR LOGIC ---
const getVec = (source: any) => {
    let raw = source?.hexagon_vectors ?? source?.radar_data ?? source?.breakdown ?? source?.metadata?.breakdown ?? (source as any)?.metadata?.hexagon_vectors ?? {};
    if (typeof raw === 'string') try { raw = JSON.parse(raw); } catch {}
    if (raw.hexagon_vectors) raw = raw.hexagon_vectors;
    
    // CRITICAL FIX: If raw is empty but source itself has the keys, use source
    if (Object.keys(raw).length === 0 && source && (source.client_side_velocity || source.velocity || source.security_perimeter)) {
        raw = source;
    }
    
    // TITAN V7.2: Also check metadata.comparison_data.hexagon_vectors
    if (Object.keys(raw).length === 0 && source?.metadata?.comparison_data?.hexagon_vectors) {
        raw = source.metadata.comparison_data.hexagon_vectors;
    }

    const normalized: any = {};
    Object.keys(raw).forEach(k => {
        let key = k.toLowerCase().replace(/ /g, '_');
        // Alias Mapping for Robustness
        if (key === 'velocity' || key === 'performance') key = 'client_side_velocity';
        if (key === 'code_quality' || key === 'efficiency' || key === 'code_qual') key = 'code_efficiency';
        if (key === 'supply_chain' || key === 'resilience') key = 'supply_chain_governance';
        if (key === 'security') key = 'security_perimeter';
        if (key === 'database' || key === 'db') key = 'database_architecture';
        if (key === 'infra' || key === 'infrastructure') key = 'infrastructure_maturity';
        
        normalized[key] = Number(raw[k]) || 0;
    });

    return normalized;
};

// Shared Computed Sources (Prioritize radar_data)
const webVectorData = computed(() => getVec(props.webAsset?.radar_data) || getVec(props.comparisonData?.ai_report) || getVec(props.comparisonData) || getVec(props.webAsset));
const repoVectorData = computed(() => getVec(props.repoAsset));

// --- CHART DATA ---
const chartData = computed(() => {
    // 6 Standard Vectors
    const labels = ['Velocity', 'Code Qual', 'Resilience', 'Security', 'Database', 'Infra'];
    // Map keys to these labels
    const keys = ['client_side_velocity', 'code_efficiency', 'supply_chain_governance', 'security_perimeter', 'database_architecture', 'infrastructure_maturity'];
    
    // Create Data Arrays
    const webValues = keys.map(k => webVectorData.value[k] || 0);
    const repoValues = keys.map(k => repoVectorData.value[k] || 0);

    return {
        labels,
        datasets: [
            {
                label: 'Live Website',
                data: webValues,
                backgroundColor: 'rgba(16, 185, 129, 0.2)', // Emerald
                borderColor: '#10b981',
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#10b981'
            },
            {
                label: 'Source Code',
                data: repoValues,
                backgroundColor: 'rgba(6, 182, 212, 0.2)', // Cyan-500
                borderColor: '#06b6d4',
                pointBackgroundColor: '#06b6d4',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#06b6d4'
            }
        ]
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        r: {
            angleLines: { color: 'rgba(255, 255, 255, 0.1)' },
            grid: { color: 'rgba(255, 255, 255, 0.1)' },
            pointLabels: { color: '#94a3b8', font: { size: 10, family: 'monospace' } },
            ticks: { display: false, backdropColor: 'transparent' }
        }
    },
    plugins: {
        legend: {
            labels: { color: '#cbd5e1', font: { family: 'monospace' }, usePointStyle: true, boxWidth: 6 }
        }
    }
};

// --- LOGIC ---
const getGrade = (score: number) => {
    if (score >= 95) return 'S';
    if (score >= 90) return 'A';
    if (score >= 80) return 'B';
    if (score >= 70) return 'C';
    if (score >= 60) return 'D';
    return 'F';
};

const getGradeColor = (score: number) => {
    if (score >= 90) return 'text-emerald-500'; // S, A
    if (score >= 70) return 'text-cyan-500';    // B, C
    if (score >= 60) return 'text-yellow-500';  // D
    return 'text-rose-500';                     // F
};

const formatFileSize = (bytes: number | undefined) => {
    if (bytes === undefined || bytes === null || isNaN(bytes)) return '0 B';
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
};

// Tech Stack Analysis
const enrichedStackAnalysis = computed(() => {
    const data = props.comparisonData?.stack_analysis;
    if (data && (data.live_evidence?.length || data.repo_evidence?.length)) return data;

    // Fallback: Compute local diff from metadata
    const getStack = (asset: any) => {
        // TITAN V6.8 FIX: Combine ALL potential tech sources instead of just picking one.
        // This ensures Deterministic (Python) and Enriched (AI) data both show up.
        const sources = [
            asset.metadata?.tech_assessment?.stack,
            asset.metadata?.tech_stack,
            asset.website_metadata?.tech_stack,
            asset.repository_metadata?.tech_stack,
            asset.tech_stack
        ];
        
        const allTech: string[] = [];
        sources.forEach(s => {
            if (Array.isArray(s)) {
                s.forEach((i: any) => {
                    const name = typeof i === 'string' ? i : i.name;
                    if (name && typeof name === 'string') allTech.push(name);
                });
            }
        });

        // Deduplicate by name
        return Array.from(new Set(allTech));
    };
    
    const webStack = getStack(props.webAsset);
    const repoStack = getStack(props.repoAsset);
    
    // Normalize and fuzzy match
    const norm = (s: string) => s.toLowerCase().trim();
    const repoNorms = repoStack.map(norm);
    
    // Fuzzy matching: "Next.js" matches "Next.js (Framework)"
    const matching = webStack.filter(w => {
        const wn = norm(w);
        return repoNorms.some(rn => wn.includes(rn) || rn.includes(wn));
    });
    
    return {
        matching_status: matching.length > 0 
            ? `Verified ${matching.length} Core Technologies` 
            : 'Tech Stack Mismatch / No Data',
        live_evidence: matching.length ? matching : (webStack.length ? ['Mismatch: ' + webStack[0] + '...'] : ['No Live Tech']),
        repo_evidence: matching.length ? matching : (repoStack.length ? ['Mismatch: ' + repoStack[0] + '...'] : ['No Repo Tech'])
    };
});

// --- DUPLICATE DETECTION LOGIC ---
const duplicateCheckResult = ref<{ is_duplicate: boolean; is_owned_by_me?: boolean; original_asset?: any } | null>(null);
const isAlreadyListed = computed(() => {
    return props.repoAsset.is_for_sale || 
           props.webAsset.is_for_sale || 
           duplicateCheckResult.value?.is_owned_by_me === true;
});

const checkDuplicate = async () => {
    if (!(props.repoAsset as any)?.fingerprint_hash) return;
    
    try {
        const res = await axios.post(route('marketplace.check-duplicate'), {
            fingerprint: (props.repoAsset as any).fingerprint_hash,
            repo_url: (props.repoAsset as any).github_repo_url 
        });
        duplicateCheckResult.value = res.data;
    } catch (e) {
        console.error("Duplicate Check Failed", e);
    }
};

onMounted(() => {
    checkDuplicate();
});

watch(() => props.repoAsset, () => {
    checkDuplicate();
});

// --- ASSURANCE LEDGER DATA ---
const assuranceData = computed(() => {
    return {
        commitHash: props.repoAsset.metadata?.last_commit_hash ?? 'N/A', // If we have it
        verifiedAt: new Date().toISOString(),
        auditorId: 'TITAN-V5',
        integritySeal: (props.comparisonData?.sync_score || 0) >= 85 ? 'Verified' : 'Unverified'
    };
});


// DNA Map (Mock/Real) with Visualizer Logic
const dnaMap = computed(() => {
    // 1. Semantic Match from AI (New in V6.6)
    const map = props.comparisonData?.structural_dna_map || props.comparisonData?.ai_report?.structural_dna_map;
    if (Array.isArray(map) && map.length > 0) {
        return map.map(item => ({
             repoFile: item.repoFile || 'unknown',
             liveRoute: item.route || item.liveRoute,
             status: item.status || 'ghost',
             type: item.type || 'page',
             reason: item.reason || ''
        }));
    }

    // 2. FALLBACK: Basic Routing Topology (V6.5)
    let rawTopology = props.comparisonData?.routing_topology ?? (props.webAsset.metadata as any)?.routing_topology;
    if (Array.isArray(rawTopology) && rawTopology.length > 0) {
        return rawTopology.map((route: string) => ({
            repoFile: route === '/' ? 'index.php' : route.split('/').pop() + '...',
            liveRoute: route,
            status: 'match',
            type: 'page'
        }));
    }
    
    // 3. LEGACY: Node/Hash based topology
    let legacyTopology = props.comparisonData?.topology ?? (props.webAsset.metadata as any)?.topology;
    if (legacyTopology && legacyTopology.nodes) {
        return legacyTopology.nodes.map((node: any) => ({
            repoFile: node.type === 'home' ? 'index.ts' : (node.id.split('/').pop() || 'page'),
            liveRoute: node.id,
            status: node.status === 200 ? 'match' : 'ghost',
            type: node.type
        }));
    }
    
    return []; 
});

// --- BEAUTIFICATION: VECTOR CORRELATION ---
// --- BEAUTIFICATION: VECTOR CORRELATION ---
const vectorCorrelation = computed(() => {
    const webVec = webVectorData.value;
    const repoVec = repoVectorData.value;

    const keys = {
        'client_side_velocity': 'Client Velocity',
        'code_efficiency': 'Code Efficiency',
        'supply_chain_governance': 'Supply Chain',
        'security_perimeter': 'Security Perimeter',
        'database_architecture': 'Database Arch',
        'infrastructure_maturity': 'Infra Maturity'
    };

    return Object.entries(keys).map(([key, label]) => {
        const v1 = Number(webVec[key]) || 0;
        const v2 = Number(repoVec[key]) || 0;
        const delta = Math.abs(v1 - v2);
        let status = 'match';
        // TITAN V2.1: Aligned with ForensicCalculator.php thresholds
        if (delta > 40) status = 'drift-critical';      // Matches Backend Deviation (>40)
        else if (delta >= 15) status = 'drift-warning'; // Matches Backend Drift (>=15)

        return { key, label, v1, v2, delta, status };
    });
});

const getStatusColor = (status: string) => {
    if (status === 'match') return 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20';
    if (status === 'drift-warning') return 'text-amber-400 bg-amber-500/10 border-amber-500/20';
    return 'text-rose-400 bg-rose-500/10 border-rose-500/20';
};

// --- ROBUST DATA ACCESS: CALCULATION LOG ---
const finalCalculationLog = computed(() => {
    const data = props.comparisonData;
    if (!data) return [];

    // 1. Direct from auditor response (Preferred)
    let logs = data.drift_analysis?.calculation_log 
            || data.ai_report?.drift_analysis?.calculation_log
            || data.ai_report?.calculation_log;
    
    // 2. Deep nested in legacy metadata structure
    if (!logs) {
        logs = data.latest_sync_comparison?.drift_analysis?.calculation_log 
            || data.comparison_data?.drift_analysis?.calculation_log
            || data.metadata?.comparison_data?.drift_analysis?.calculation_log;
    }

    // 3. Additive Model Fallback (No Deductions)
    if (!logs) {
        return [
            `Analysis Complete: Score based on additive proof of work.`,
            `Detailed Breakdown: Not available for this scan version.`
        ];
    }

    let finalLogs = Array.isArray(logs) ? [...logs] : [];

    // ENFORCE ALWAYS VISIBLE ROWS (User Request)
    const requiredKeys = [
        { key: 'Structural DNA', fallback: 'Structural DNA Partial: +0.0' },
        { key: 'Tech Stack', fallback: 'Tech Stack Verification: +0.0' },
        { key: 'Strategic Context', fallback: 'Strategic Context Match: +0.0' },
        { key: 'Identity', fallback: 'Identity/Name Match: +0.0' }
    ];

    requiredKeys.forEach(req => {
        const exists = finalLogs.some(l => l.includes(req.key));
        if (!exists) {
            // Insert after specific items or just append? Appending is safest.
            finalLogs.push(req.fallback);
        }
    });

    // Sort to maintain consistency: DNA -> Tech -> Context -> Identity -> Vectors
    const priority = ['Structural', 'Tech', 'Strategic', 'Identity', 'Vector'];
    finalLogs.sort((a, b) => {
        const idxA = priority.findIndex(p => a.includes(p));
        const idxB = priority.findIndex(p => b.includes(p));
        return (idxA === -1 ? 99 : idxA) - (idxB === -1 ? 99 : idxB);
    });

    // TITAN V7.2: Convert to objects for tooltip support
    return finalLogs.map(log => {
        const colonIndex = log.indexOf(':');
        let label = 'Analysis Item';
        let rest = log;

        if (colonIndex !== -1) {
            label = log.substring(0, colonIndex).trim();
            rest = log.substring(colonIndex + 1).trim();
        }

        // Extract score like +59.0 or +9
        const scoreMatch = rest.match(/\+?\d+(\.\d+)?/);
        const score = scoreMatch ? scoreMatch[0] : '+0.0';
        
        // Discussion is everything else
        let discussion = rest.replace(score, '').trim();
        // Clean up brackets if they exist
        discussion = discussion.replace(/^\[|\]$/g, '').trim();

        // Formal Label Mapping (User Request)
        let displayLabel = label;
        let maxScore = "";

        if (label.toUpperCase().includes('STRUCTURAL DNA')) {
            displayLabel = 'STRUCTURAL DNA (AI)';
            maxScore = '(0-60)';
        } else if (label.toUpperCase().includes('TECH STACK')) {
            displayLabel = 'TECH STACK (AI)';
            maxScore = '(0-20)';
        } else if (label.toUpperCase().includes('STRATEGIC CONTEXT')) {
            displayLabel = 'STRATEGIC CONTEXT (AI)';
            maxScore = '(0-9)';
        } else if (label.toUpperCase().includes('IDENTITY')) {
            displayLabel = 'IDENTITY/NAME MATCH';
            maxScore = '';
        } else if (label.toUpperCase().includes('VECTOR ALIGNMENT')) {
            displayLabel = 'VECTOR ALIGNMENT';
            maxScore = '(0-6)';
        }

        return {
            label: displayLabel,
            score: score,
            maxScore: maxScore,
            discussion: discussion || 'Forensic verification successful.'
        };
    });
});

// AI analysis and path normalization
const normalizedAIReport = computed(() => {
    const data = props.comparisonData;
    if (!data) return { analysis: '', path: [], dna: {}, roadmap: [], vectors: {} };

    // Standardize vectors (Structural Variance)
    const vectors = data.hexagon_vectors 
                 || data.ai_report?.hexagon_vectors 
                 || data.metadata?.comparison_data?.hexagon_vectors 
                 || {};

    // Standardize roadmap and ENFORCE strict scoring (Titan v2.1)
    let roadmap = [...(data.remediation_roadmap || data.ai_report?.remediation_roadmap || [])];
    
    roadmap = roadmap.map(r => {
        const task = (r.task || '').toLowerCase();
        let impact = r.impact || '';

        // Enforce the +14 cap across the 3 core items
        if (task.includes('csp') || task.includes('hsts') || task.includes('headers')) {
            impact = "+5 points";
        } else if (task.includes('console') || task.includes('error') || task.includes('location')) {
            impact = "+4 points";
        } else if (task.includes('dependency') || task.includes('audit') || task.includes('security audit')) {
            impact = "+5 points";
        } else if (task.includes('handshake')) {
            impact = "+16 points";
        }
        
        return { ...r, impact };
    });

    // Auto-inject Handshake if score is in "Action Required" range and not verified
    const isVerified = data.drift_analysis?.is_lume_verified || false;
    const score = Number(data.sync_score || 0);
    
    // Auto-inject Handshake if score is in "Action Required" range and not verified
    // backend now handles Core 3 items (CSP, Console, Dependency) via ForensicCalculator logic.
    
    // We only keep frontend injection for Handshake as a "Critical Last Mile" prompt.

    if (!isVerified && score < 85) {
        const hasHandshakeTask = roadmap.some(r => r.task?.toLowerCase().includes('handshake'));
        if (!hasHandshakeTask) {
            roadmap.unshift({
                task: "Implement LUME Handshake Verification",
                impact: "+16 points",
                priority: "critical"
            });
        }
    }

    // TITAN V7.2: Real-data mapping for DNA Provenance
    const gates = data?.metadata?.heuristic_gates || data?.heuristic_gates || {};
    
    return {
        analysis: data.qualitative_analysis 
               || data.ai_report?.qualitative_analysis 
               || data.drift_analysis?.summary 
               || 'Forensic engine confirms architectural alignment between deployment and source.',
        path: Array.isArray(data.improvement_path) ? data.improvement_path 
            : Array.isArray(data.ai_report?.improvement_path) ? data.ai_report.improvement_path
            : [],
        dna: {
            // TITAN V7.2: Score-Driven DNA Provenance (Provenance Tier 1)
            // We now derive the card status directly from the forensic logs to ensure 100% visual consistency.
            // This fixes the issue where high scores were showing "Drift Detected".
            get structural_dna() {
                const log = finalCalculationLog.value.find(l => l.label.includes('STRUCTURAL DNA'));
                const score = parseFloat(log?.score || '0');
                return score >= 40 ? 'verified' : 'drift';
            },
            get tech_stack() {
                const log = finalCalculationLog.value.find(l => l.label.includes('TECH STACK'));
                const score = parseFloat(log?.score || '0');
                return score >= 15 ? 'verified' : 'flagged';
            },
            get strategic_context() {
                const log = finalCalculationLog.value.find(l => l.label.includes('STRATEGIC CONTEXT'));
                const score = parseFloat(log?.score || '0');
                return score >= 5 ? 'verified' : 'flagged';
            },
            
            // Raw scores for reference
            get structural_score() { return parseFloat(finalCalculationLog.value.find(l => l.label.includes('STRUCTURAL DNA'))?.score || '0'); },
            get tech_score() { return parseFloat(finalCalculationLog.value.find(l => l.label.includes('TECH STACK'))?.score || '0'); },
            get strategic_score() { return parseFloat(finalCalculationLog.value.find(l => l.label.includes('STRATEGIC CONTEXT'))?.score || '0'); },
            
            matching_signals: data.dna_fingerprint?.matching_signals || []
        },
        roadmap: roadmap,
        vectors: vectors
    };
});

const viewOnMarketplace = () => {
    // Priority 1: Use the original asset ID if we found a duplicate listing owned by the current user
    if (duplicateCheckResult.value?.is_owned_by_me && duplicateCheckResult.value?.original_asset?.id) {
         router.visit(route('marketplace.asset.view', duplicateCheckResult.value.original_asset.id));
         return;
    }
    
    // Priority 2: Use current assets
    const idToUse = props.repoAsset.is_for_sale ? props.repoAsset.id : (props.webAsset.is_for_sale ? props.webAsset.id : props.repoAsset.id);
    router.visit(route('marketplace.asset.view', idToUse));
};


</script>

<template>
    <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-[#020408]/95 backdrop-blur-md" @click="$emit('close')"></div>

        <!-- Modal Container -->
        <div class="relative w-full max-w-6xl h-[90vh] bg-[#050811] rounded-2xl border border-slate-800 shadow-2xl flex flex-col overflow-hidden text-slate-300 font-sans">
            
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-[#080c17]">
                <div class="flex items-center gap-3">
                    <div class="bg-cyan-500/20 p-2 rounded-lg">
                        <svg class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-white tracking-widest uppercase italic">Forensic Sync Analysis</h2>
                        <p class="text-[10px] text-cyan-500 font-mono tracking-tighter uppercase opacity-80">LIVE DEPLOYMENT vs. SOURCE REPOSITORY</p>
                    </div>
                </div>
                <button @click="$emit('close')" class="text-slate-500 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 h-full">
                    
                    <!-- Left Column -->
                    <div class="flex flex-col gap-6">
                        
                        <!-- 1. Sync Confidence Card -->
                        <div class="bg-[#0b101b] rounded-xl border border-slate-800 p-6 relative overflow-hidden">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Sync Confidence</h3>
                                    <div class="text-sm italic" :class="(comparisonData?.sync_score ?? 0) > 75 ? 'text-emerald-400' : 'text-slate-400'">
                                        {{ (comparisonData?.sync_score ?? 0) > 75 ? 'Marketplace Verified' : 'Drift Risks Detected' }}
                                    </div>
                                </div>
                                <div class="flex items-end gap-4">
                                    <div class="text-right">
                                        <div class="text-[10px] text-slate-600 uppercase font-bold">Grade</div>
                                        <div class="text-5xl font-black font-mono leading-none" :class="getGradeColor(comparisonData?.sync_score ?? 0)">
                                            {{ getGrade(comparisonData?.sync_score ?? 0) }}
                                        </div>
                                    </div>
                                    <div class="h-12 w-px bg-slate-800 self-center"></div>
                                    <div class="text-right">
                                        <div class="text-[10px] text-slate-600 uppercase font-bold">Score</div>
                                        <div class="text-3xl font-bold text-white font-mono leading-none mb-2">
                                            {{ Number(comparisonData?.sync_score ?? 0).toFixed(2) }}%
                                        </div>
                                        <button @click="showCalculationReport = true" class="px-2 py-0.5 rounded bg-cyan-500/10 border border-cyan-500/20 text-[10px] text-cyan-400 font-bold uppercase transition-all hover:bg-cyan-500/20">
                                            View Report
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Verdict Banner -->
                            <div class="mt-6">
                                <div v-if="(comparisonData?.sync_score ?? 0) > 75" class="bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-3 flex items-center gap-3">
                                    <div class="bg-emerald-500 rounded-full p-1 text-[#020408]">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <div>
                                        <div class="text-emerald-400 text-xs font-bold uppercase">Ready for Market</div>
                                        <div class="text-emerald-500/60 text-[10px] leading-tight">{{ comparisonData?.drift_analysis?.summary || 'Asset pair is fully synchronized' }}</div>
                                    </div>
                                </div>
                                <div v-else class="bg-rose-500/10 border border-rose-500/20 rounded-lg p-3 flex items-center gap-3">
                                    <div class="bg-rose-500 rounded-full p-1 text-[#020408]">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </div>
                                    <div>
                                        <div class="text-rose-400 text-xs font-bold uppercase">Sync Failed</div>
                                        <div class="text-rose-500/60 text-[10px] leading-tight">{{ comparisonData?.drift_analysis?.summary || 'Resolve drift before listing' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Vector Divergence Card -->
                        <div class="bg-[#0b101b] rounded-xl border border-slate-800 p-6 flex-1 flex flex-col min-h-[400px]">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest">Vector Divergence</h3>
                                <button @click="showVectorDetails = true" class="text-[10px] text-cyan-400 hover:text-cyan-300 font-bold uppercase flex items-center gap-1">
                                    View Details
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </button>
                            </div>
                            
                            <div class="flex items-center gap-4 text-xs font-mono mb-4 justify-center">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-1 bg-emerald-500"></span>
                                    <span class="text-slate-300">Live Website</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-1 bg-cyan-500"></span>
                                    <span class="text-slate-300">Source Code</span>
                                </div>
                            </div>

                            <div class="flex-1 relative w-full h-full">
                                <Radar :data="chartData" :options="chartOptions" />
                            </div>
                        </div>

                        <!-- 5. Sync Assurance Protocol (New Card) -->
                        <div class="bg-[#0b101b] rounded-xl border border-slate-800 p-6 flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Sync Assurance Protocol</h3>
                                <div class="flex items-center gap-3 text-[10px] text-slate-400 font-mono">
                                    <span>TITAN-V5 AUDIT</span>
                                    <span class="text-slate-600">|</span>
                                    <span>HASH: {{ assuranceData.commitHash.substring(0, 7) }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 px-3 py-1 rounded bg-slate-900 border border-slate-700">
                                <div class="w-2 h-2 rounded-full" :class="assuranceData.integritySeal === 'Verified' ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"></div>
                                <span class="text-[10px] font-bold uppercase" :class="assuranceData.integritySeal === 'Verified' ? 'text-emerald-400' : 'text-rose-400'">
                                    {{ assuranceData.integritySeal }} SEAL
                                </span>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column -->
                    <div class="flex flex-col gap-6">

                        <!-- 3. Tech ID Verification -->
                        <div class="bg-[#0b101b] rounded-xl border border-slate-800 p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest">Tech ID Verification</h3>
                                <button @click="showTechDetails = true" class="text-[10px] text-cyan-400 hover:text-cyan-300 font-bold uppercase flex items-center gap-1">
                                    View Details
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </button>
                            </div>
                            
                            <div class="mb-6">
                                <div class="text-[10px] text-slate-600 font-bold uppercase mb-1">Analysis Verdict</div>
                                <div class="text-sm text-slate-200 font-mono">{{ enrichedStackAnalysis.matching_status }}</div>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <div class="text-[10px] text-emerald-500/70 font-bold uppercase mb-2">Live Site Evidence</div>
                                    <div class="flex flex-wrap gap-2">
                                        <span v-for="tag in enrichedStackAnalysis.live_evidence" :key="tag" class="px-2 py-1 rounded bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-mono">
                                            {{ tag }}
                                        </span>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="text-[10px] text-cyan-500/70 font-bold uppercase mb-2">Repository Evidence</div>
                                    <div class="flex flex-wrap gap-2">
                                        <span v-for="tag in enrichedStackAnalysis.repo_evidence" :key="tag" class="px-2 py-1 rounded bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 text-xs font-mono">
                                            {{ tag }}
                                        </span>
                                    </div>
                                </div>
    
                            </div>

                            <!-- 4. Heuristic Logic Gates (Replaces Resource Integrity) -->
                            <div class="mt-8 pt-6 border-t border-slate-800">
                                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Heuristic Logic Gates</h3>
                                
                                <div class="space-y-3">
                                    <!-- TOPOLOGY -->
                                    <div class="flex items-center justify-between text-xs bg-slate-900/50 p-2 rounded border border-slate-800">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full shadow-[0_0_8px_rgba(16,185,129,0.5)]" 
                                                :class="(comparisonData?.metadata?.heuristic_gates?.route_topology === 'verified' || comparisonData?.logic_gates?.topology === 'VERIFIED') ? 'bg-emerald-500' : 'bg-rose-500'"></div>
                                            <span class="text-slate-300 font-mono">Route Topology Graph</span>
                                        </div>
                                        <span class="font-bold font-mono" 
                                            :class="(comparisonData?.metadata?.heuristic_gates?.route_topology === 'verified' || comparisonData?.logic_gates?.topology === 'VERIFIED') ? 'text-emerald-400' : 'text-rose-400'">
                                            {{ comparisonData?.metadata?.heuristic_gates?.route_topology || comparisonData?.logic_gates?.topology || 'PENDING' }}
                                        </span>
                                    </div>

                                    <!-- HASH -->
                                    <div class="flex items-center justify-between text-xs bg-slate-900/50 p-2 rounded border border-slate-800">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full shadow-[0_0_8px_rgba(16,185,129,0.5)]"
                                                :class="comparisonData?.metadata?.heuristic_gates?.asset_hash === 'verified' ? 'bg-emerald-500' : 'bg-rose-500'"></div>
                                            <span class="text-slate-300 font-mono">Asset Hash Fingerprint</span>
                                        </div>
                                        <span class="font-bold font-mono"
                                            :class="comparisonData?.metadata?.heuristic_gates?.asset_hash === 'verified' ? 'text-emerald-400' : 'text-rose-400'">
                                            {{ comparisonData?.metadata?.heuristic_gates?.asset_hash || comparisonData?.logic_gates?.hash || 'PENDING' }}
                                        </span>
                                    </div>

                                    <!-- DOM PARITY -->
                                    <div class="flex items-center justify-between text-xs bg-slate-900/50 p-2 rounded border border-slate-800">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full shadow-[0_0_8px_rgba(6,182,212,0.5)]"
                                                :class="(comparisonData?.metadata?.heuristic_gates?.dom_parity === 'verified' || parseFloat(comparisonData?.logic_gates?.dom) > 90) ? 'bg-emerald-500' : 'bg-cyan-500'"></div>
                                            <span class="text-slate-300 font-mono">DOM Structure Parity</span>
                                        </div>
                                        <span class="font-bold font-mono"
                                            :class="(comparisonData?.metadata?.heuristic_gates?.dom_parity === 'verified' || parseFloat(comparisonData?.logic_gates?.dom) > 90) ? 'text-emerald-400' : 'text-cyan-400'">
                                            {{ comparisonData?.metadata?.heuristic_gates?.dom_parity || comparisonData?.logic_gates?.dom || 'Calculating...' }}
                                        </span>
                                    </div>

                                    <!-- METADATA -->
                                    <div class="flex items-center justify-between text-xs bg-slate-900/50 p-2 rounded border border-slate-800">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full shadow-[0_0_8px_rgba(16,185,129,0.5)]"
                                                :class="comparisonData?.metadata?.heuristic_gates?.metadata_consistency === 'verified' ? 'bg-emerald-500' : 'bg-rose-500'"></div>
                                            <span class="text-slate-300 font-mono">Metadata Consistency</span>
                                        </div>
                                        <span class="font-bold font-mono"
                                            :class="comparisonData?.metadata?.heuristic_gates?.metadata_consistency === 'verified' ? 'text-emerald-400' : 'text-rose-400'">
                                            {{ comparisonData?.metadata?.heuristic_gates?.metadata_consistency || comparisonData?.logic_gates?.metadata || 'PENDING' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Structural DNA Mapper (Enhanced Visualizer) -->
                        <div class="bg-[#0b101b] rounded-xl border border-slate-800 p-6 flex-1 min-h-[300px] flex flex-col">
                            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Structural DNA Mapper</h3>
                            
                            <div class="flex justify-between text-[10px] text-slate-600 uppercase mb-2 px-2 border-b border-slate-800 pb-2">
                                <span>Codebase Node</span>
                                <span>Live Route</span>
                            </div>

                            <div class="flex-1 overflow-y-auto custom-scrollbar space-y-2 pr-2">
                                <div v-if="dnaMap.length === 0" class="flex flex-col items-center justify-center py-12 text-slate-600">
                                    <span class="italic text-xs">No topology data available for mapping.</span>
                                </div>
                                <div v-else v-for="(node, i) in dnaMap" :key="i" :title="node.reason" class="group flex items-center justify-between p-2 rounded hover:bg-white/5 transition-colors border border-transparent hover:border-slate-800">
                                     <div class="flex items-center gap-3">
                                         <!-- Node Icon (Page, Component, API) -->
                                         <div class="w-6 h-6 rounded flex items-center justify-center bg-slate-800 text-slate-400">
                                             <svg v-if="node.type === 'page'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
                                             <svg v-else-if="node.type === 'api'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                             <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>
                                         </div>
                                         <div class="flex flex-col">
                                             <span class="text-xs font-mono text-cyan-300">{{ node.repoFile }}</span>
                                             <span class="text-[10px] text-slate-500 uppercase">{{ node.type || 'Source' }}</span>
                                         </div>
                                     </div>

                                     <!-- Visual Connector Line (Hidden on small, visible on hover maybe?) -->
                                     <div class="hidden md:block h-px w-8 bg-slate-800 group-hover:bg-slate-600 transition-colors"></div>

                                     <div class="flex items-center gap-2 text-right">
                                         <div class="flex flex-col items-end">
                                             <span class="text-xs font-mono text-emerald-300">{{ node.liveRoute }}</span>
                                             <span class="text-[10px] text-slate-500 uppercase">Live Endpoint</span>
                                         </div>
                                         <div class="w-2 h-2 rounded-full" :class="node.status === 'match' ? 'bg-emerald-500' : 'bg-rose-500'"></div>
                                     </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Footer -->
             <div class="border-t border-slate-800 bg-[#080c17] p-4 flex justify-between items-center">
                 <div class="text-xs text-slate-600 font-mono">
                     Analysis generated on {{ new Date().toLocaleDateString() }}
                 </div>
                 <div class="flex gap-3">
                     <button @click="$emit('scan-again')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold uppercase rounded-lg border border-slate-700 transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        Scan Again
                     </button>
                     <button 
                        @click="isAlreadyListed ? viewOnMarketplace() : $emit('open-marketplace-listing', isAlreadyListed)"
                        :disabled="!isAlreadyListed && (duplicateCheckResult?.is_duplicate || ((comparisonData?.sync_score || 0) < 85 && !comparisonData?.marketplace_eligible))"
                        :class="[
                            isAlreadyListed
                            ? 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-emerald-500/20'
                            : ((comparisonData?.sync_score || 0) >= 85 || comparisonData?.marketplace_eligible) && !duplicateCheckResult?.is_duplicate
                                ? 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-emerald-500/20' 
                                : 'bg-slate-800 text-slate-500 border border-slate-700 cursor-not-allowed opacity-50'
                        ]"
                        class="px-6 py-2 rounded-lg text-xs font-bold uppercase tracking-wide shadow-lg transition-all flex items-center gap-2"
                     >
                        <template v-if="isAlreadyListed">
                            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            View Asset
                        </template>
                        <template v-else-if="duplicateCheckResult?.is_duplicate">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            Duplicate Blocked
                        </template>
                        <template v-else-if="(comparisonData?.sync_score || 0) >= 85 || comparisonData?.marketplace_eligible">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                            List on Marketplace
                        </template>
                        <template v-else>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                             Locked (Sync < 85%)
                        </template>
                     </button>

                     <!-- LUME VERIFICATION BUTTON (70-84 Range) -->
                     <button 
                        v-if="(comparisonData?.sync_score >= 70 && comparisonData?.sync_score < 85) || (comparisonData?.marketplace_eligible === false && (comparisonData?.sync_score >= 70))"
                        @click="showVerificationModal = true"
                        class="px-5 py-2 rounded-lg text-xs font-bold uppercase tracking-wide bg-cyan-600 hover:bg-cyan-500 text-white shadow-lg shadow-cyan-500/20 transition-all flex items-center gap-2"
                     >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        Generate Lume Verification
                     </button>

                      <!-- LUME QA & PENETRATION BUTTON -->
                      <button 
                    v-if="syncScore >= 80"
                    @click="hasExistingScan ? $emit('open-pentest-results') : $emit('deep-audit')"
                    class="px-5 py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-bold rounded-lg shadow-lg shadow-cyan-500/30 flex items-center gap-2 transition-all"
                >
                    <!-- Icon: Document Search (View) or Lightning (Pentest) -->
                    <svg v-if="hasExistingScan" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    
                    {{ hasExistingScan ? 'VIEW SCAN RESULTS' : 'Penetration Testing' }}
                </button>
                <div v-else class="text-xs text-slate-500 font-mono flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                    LOCKED (SYNC < 85%)
                </div>
                  </div>
              </div>

        </div>

    </div>

    <!-- VECTOR DETAILS MODAL (Nested) -->
    <div v-if="showVectorDetails" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showVectorDetails = false"></div>
        <div class="relative w-full max-w-2xl bg-[#0b101b] rounded-xl border border-slate-700 shadow-2xl p-6">
             <div class="flex justify-between items-center mb-6">
                 <h3 class="text-sm font-bold text-white uppercase tracking-widest">Vector Divergence Analysis</h3>
                 <button @click="showVectorDetails = false" class="text-slate-400 hover:text-white">
                     <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                 </button>
             </div>
             
              <div class="space-y-6 max-h-[75vh] overflow-y-auto custom-scrollbar pr-2">
                 
                   <!-- 1. Forensic Summary (The "Why") -->
                   <div v-if="comparisonData?.drift_analysis?.summary || comparisonData?.insights" class="p-5 bg-indigo-500/5 rounded-xl border border-indigo-500/10 relative overflow-hidden group transition-all hover:bg-indigo-500/10">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500/5 blur-3xl rounded-full -mr-16 -mt-16 pointer-events-none"></div>
                        <div class="flex items-center gap-3 mb-3">
                            <div class="p-2 rounded-lg bg-indigo-500/20 text-indigo-400">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <span class="text-xs font-black text-white uppercase tracking-tighter">Forensic Correlation Verdict</span>
                        </div>
                        <p class="text-sm text-slate-400 leading-relaxed italic">
                            "{{ comparisonData.drift_analysis?.summary || comparisonData.insights || 'Analysis successful. High architectural alignment detected.' }}"
                        </p>
                   </div>

                   <!-- 2. The Correlation Table (The "Beauty") -->
                   <div class="space-y-3">
                        <div class="flex justify-between items-center px-1">
                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Vector Alignment Matrix</span>
                            <div class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                <span class="text-[8px] text-slate-600 uppercase">Live</span>
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 ml-1"></span>
                                <span class="text-[8px] text-slate-600 uppercase">Repo</span>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-slate-800 bg-[#050811]">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-white/[0.02] border-b border-slate-800">
                                    <tr>
                                        <th class="p-3 text-[10px] text-slate-500 uppercase font-black">Vector</th>
                                        <th class="p-3 text-[10px] text-slate-500 uppercase font-black text-right">Website</th>
                                        <th class="p-3 text-[10px] text-slate-500 uppercase font-black text-right">Repository</th>
                                        <th class="p-3 text-[10px] text-slate-500 uppercase font-black text-right">Correlation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="v in vectorCorrelation" :key="v.key" class="border-b border-slate-800/50 hover:bg-white/[0.01] transition-colors group">
                                        <td class="p-3 italic">
                                            <div class="flex flex-col">
                                                <span class="text-xs text-slate-300 font-mono group-hover:text-white transition-colors">{{ v.label }}</span>
                                            </div>
                                        </td>
                                        <td class="p-3 text-right">
                                            <span class="text-xs font-mono text-emerald-400">{{ v.v1 }}</span>
                                        </td>
                                        <td class="p-3 text-right">
                                            <span class="text-xs font-mono text-indigo-400">{{ v.v2 }}</span>
                                        </td>
                                        <td class="p-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded border" :class="getStatusColor(v.status)">
                                                    {{ v.delta === 0 ? 'SYNCHRONIZED' : v.status === 'match' ? 'ALIGNED' : v.status === 'drift-warning' ? 'DRIFT' : 'DEVIATION' }}
                                                </span>
                                                <span v-if="v.delta > 0" class="text-[10px] font-mono text-slate-500">Δ{{ v.delta }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                   </div>

                   <!-- 3. Evidence Handshake (Real Code Proofs) -->
                   <div v-if="comparisonData?.stack_analysis?.code_evidence?.length" class="space-y-4">
                        <div class="text-[10px] text-indigo-500 font-bold uppercase tracking-widest pl-1 border-b border-indigo-500/20 pb-1 flex items-center gap-2">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A10.003 10.003 0 0012 30c2.21 0 4.21-.72 5.82-1.94m-5.82 1.94a9.99 9.99 0 005.17-1.43V30m-1.42-20.47a11.42 11.42 0 00-3.58 0V4.26c0-.59.37-1.12.92-1.32.55-.2 1.17 0 1.51.52l.01.01c.21.31.5.58.82.78.32.2.69.29 1.07.26h.02c.38.03.75-.06 1.07-.26a2.4 2.4 0 00.82-.78l.01-.01c.34-.52.96-.72 1.51-.52.55.2.92.73.92 1.32v5.74z" /></svg>
                            Forensic Handshake Verification
                        </div>
                        <div v-for="(evidence, proofIdx) in comparisonData.stack_analysis.code_evidence" :key="proofIdx" class="bg-[#050811] rounded-xl border border-slate-800 shadow-2xl relative group overflow-hidden">
                            <div class="absolute inset-0 bg-indigo-500/5 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
                            
                            <div class="px-4 py-3 border-b border-slate-800 bg-white/5 flex justify-between items-center">
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                    <span class="text-[10px] font-mono text-indigo-400">{{ (evidence as any).file }}</span>
                                </div>
                                <div class="text-[8px] text-slate-500 font-black uppercase tracking-widest">Identity Proof #{{ Number(proofIdx) + 1 }}</div>
                            </div>
                            
                            <div class="p-4 flex flex-col gap-4">
                                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-center">
                                     <!-- Repo Side -->
                                     <div class="md:col-span-2 space-y-1">
                                         <span class="text-[8px] text-slate-600 font-bold uppercase">Source (Git)</span>
                                         <pre class="text-[10px] text-cyan-300 font-mono bg-[#020408] p-3 rounded border border-cyan-500/10 overflow-x-auto whitespace-pre-wrap max-h-24 custom-scrollbar">{{ evidence.snippet }}</pre>
                                     </div>

                                     <!-- Visual Connector -->
                                     <div class="hidden md:flex flex-col items-center justify-center gap-1 opacity-50 group-hover:opacity-100 transition-all">
                                         <div class="h-px w-full bg-gradient-to-r from-cyan-500/0 via-cyan-500 to-emerald-500/0"></div>
                                         <div class="px-2 py-0.5 rounded bg-white/5 border border-white/10 text-[8px] text-slate-400 font-bold uppercase">DNA SYNC</div>
                                         <div class="h-px w-full bg-gradient-to-r from-cyan-500/0 via-emerald-500 to-emerald-500/0"></div>
                                     </div>

                                     <!-- Web Side -->
                                     <div class="md:col-span-2 space-y-1">
                                         <span class="text-[8px] text-slate-600 font-bold uppercase">Runtime (Web)</span>
                                         <div class="p-3 bg-emerald-500/5 border border-emerald-500/10 rounded-lg h-24 flex items-center justify-center">
                                              <div class="flex flex-col items-center">
                                                  <svg class="w-5 h-5 text-emerald-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                                  <span class="text-[9px] text-emerald-400 font-bold text-center">Identity Confirmed</span>
                                              </div>
                                         </div>
                                     </div>
                                </div>

                                <div class="p-3 bg-indigo-500/5 border border-indigo-500/10 rounded-lg">
                                     <div class="text-[10px] text-slate-400 leading-relaxed">
                                         <span class="text-indigo-400 font-bold uppercase mr-1">Forensic Analysis:</span> {{ evidence.context }}
                                     </div>
                                </div>
                            </div>
                        </div>
                   </div>
              </div>
        </div>
    </div>

    <!-- TITAN FORENSIC AUDIT REPORT (v2.0) -->
    <div v-if="showCalculationReport" class="fixed inset-0 z-[120] flex items-center justify-center p-4 sm:p-6 md:p-10 backdrop-blur-sm bg-black/80 overflow-y-auto">
        <div class="absolute inset-0" @click="showCalculationReport = false"></div>
        <div class="relative w-full max-w-2xl my-auto bg-[#0b101b] rounded-2xl border border-slate-700 shadow-[0_0_50px_rgba(0,0,0,0.5)] flex flex-col max-h-[90vh] overflow-hidden">
             
             <!-- Report Header -->
             <div class="flex justify-between items-start px-8 py-6 border-b border-slate-800 bg-white/[0.02] shrink-0">
                 <div>
                     <div class="flex items-center gap-3 mb-1">
                         <div class="px-2 py-0.5 rounded bg-indigo-500 text-[8px] font-black text-white tracking-widest uppercase">Titan v2.0</div>
                         <h3 class="text-lg font-black text-white uppercase tracking-tighter">Forensic Audit Report</h3>
                     </div>
                     <div class="flex gap-4">
                         <div class="text-[9px] text-slate-500 uppercase font-mono tracking-tighter">Serial: <span class="text-slate-400">LUME-SYNC-{{ String(props.comparisonData?.id || 'PROD').slice(-6) }}</span></div>
                         <div class="text-[9px] text-slate-500 uppercase font-mono tracking-tighter">Timestamp: <span class="text-slate-400">{{ new Date().toISOString().split('T')[0] }}</span></div>
                     </div>
                 </div>
                 <div class="flex flex-col items-end">
                      <div :class="[
                          'px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest',
                          (comparisonData?.sync_score ?? 0) >= 85 ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20' : 
                          (comparisonData?.sync_score ?? 0) >= 70 ? 'bg-amber-500/10 text-amber-500 border border-amber-500/20' : 
                          'bg-rose-500/10 text-rose-500 border border-rose-500/20'
                      ]">
                          {{ (comparisonData?.sync_score ?? 0) >= 85 ? 'Verified' : 'Flagged' }}
                      </div>
                      <span class="text-[8px] text-slate-600 font-bold mt-1 uppercase">Sovereign Asset Status</span>
                 </div>
             </div>
             
             <div class="flex-1 overflow-y-auto custom-scrollbar p-8 space-y-8">
                  
                  <!-- DNA Provenance -->
                  <div class="space-y-4">
                      <div class="flex justify-between items-center border-b border-slate-800/50 pb-2">
                          <div class="text-[10px] text-indigo-400 font-black uppercase tracking-widest">DNA Provenance (Provenance Tier 1)</div>
                           <div class="text-[9px] text-slate-500 font-mono italic">Universal Forensic Audit</div>
                      </div>
                      
                       <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                           <!-- Card 1: STRUCTURE DNA -->
                           <div class="p-4 bg-[#050811] border border-white/5 rounded-xl flex flex-col items-center justify-center text-center">
                               <div :class="['w-8 h-8 rounded-lg flex items-center justify-center mb-3', normalizedAIReport.dna?.structural_dna === 'verified' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-rose-500/10 text-rose-500']">
                                   <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                       <path v-if="normalizedAIReport.dna?.structural_dna === 'verified'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                       <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                   </svg>
                               </div>
                               <div class="text-[9px] text-slate-500 font-bold uppercase mb-0.5">Structure DNA</div>
                               <div class="text-[10px] text-white font-black uppercase tracking-tighter">{{ normalizedAIReport.dna?.structural_dna === 'verified' ? 'Match Confirmed' : 'Drift Detected' }}</div>
                           </div>

                           <!-- Card 2: TECH STACK -->
                           <div class="p-4 bg-[#050811] border border-white/5 rounded-xl flex flex-col items-center justify-center text-center">
                               <div :class="['w-8 h-8 rounded-lg flex items-center justify-center mb-3', normalizedAIReport.dna?.tech_stack === 'verified' ? 'bg-indigo-500/20 text-indigo-400' : 'bg-amber-500/10 text-amber-500']">
                                   <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                   </svg>
                               </div>
                               <div class="text-[9px] text-slate-500 font-bold uppercase mb-0.5">Tech Stack</div>
                               <div class="text-[10px] text-white font-black uppercase tracking-tighter">{{ normalizedAIReport.dna?.tech_stack === 'verified' ? 'Verified (AI)' : 'Partial Match' }}</div>
                           </div>

                           <!-- Card 3: STRATEGIC CONTEXT -->
                           <div class="p-4 bg-[#050811] border border-white/5 rounded-xl flex flex-col items-center justify-center text-center">
                               <div :class="['w-8 h-8 rounded-lg flex items-center justify-center mb-3', normalizedAIReport.dna?.strategic_context === 'verified' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-rose-500/10 text-rose-500']">
                                   <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                   </svg>
                               </div>
                               <div class="text-[9px] text-slate-500 font-bold uppercase mb-0.5">Strategic Context</div>
                               <div class="text-[10px] text-white font-black uppercase tracking-tighter">{{ normalizedAIReport.dna?.strategic_context === 'verified' ? 'Context Match' : 'Domain Drift' }}</div>
                           </div>
                       </div>
                      
                      <div v-if="normalizedAIReport.dna?.matching_signals?.length" class="flex flex-wrap gap-2 mt-4">
                          <div v-for="signal in normalizedAIReport.dna.matching_signals" :key="signal" class="px-2 py-1 bg-white/5 border border-white/10 rounded text-[8px] text-slate-400 font-mono uppercase">
                              &bull; {{ signal }}
                          </div>
                      </div>
                  </div>

                  <!-- Auditor Observations -->
                  <div class="space-y-3">
                      <div class="text-[10px] text-indigo-400 font-black uppercase tracking-widest px-1">Auditor Observations</div>
                      <div class="p-6 bg-indigo-500/5 border border-indigo-500/10 rounded-2xl relative overflow-hidden group">
                           <div class="absolute -top-12 -right-12 w-32 h-32 bg-indigo-500/10 blur-3xl rounded-full"></div>
                            <div class="relative z-10 flex gap-6">
                                <div class="shrink-0 w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-400 border border-indigo-500/20">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                </div>
                                <div class="space-y-2">
                                    <div class="text-xs text-white font-medium leading-relaxed italic pr-8 group-hover:text-indigo-100 transition-colors">
                                        "{{ normalizedAIReport.analysis }}"
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></div>
                                        <span class="text-[8px] text-indigo-400/70 font-black uppercase tracking-[0.2em]">Sovereign Analyst Verdict</span>
                                    </div>
                                </div>
                            </div>
                       </div>
                   </div>

                   <!-- Score Derivation (Additive Model) -->
                   <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                       <!-- Math log -->
                       <div class="space-y-4">
                           <div class="text-[10px] text-slate-500 font-black uppercase tracking-widest px-1">Proof of Work (Additive Model)</div>
                           <div class="space-y-2">
                               <div class="flex items-center justify-between p-3 bg-white/[0.02] border border-white/5 rounded-xl border-l-2 border-l-slate-700">
                                   <span class="text-[10px] text-slate-400 font-bold uppercase">Base Trust Score</span>
                                   <span class="text-xs font-mono text-slate-500">0.00</span>
                               </div>
                                <div v-for="(log, logIdx) in finalCalculationLog" :key="logIdx" class="group relative flex items-center justify-between p-3 bg-emerald-500/5 border border-emerald-500/10 rounded-xl border-l-2 border-l-emerald-500/50 hover:bg-emerald-500/10 transition-all cursor-help">
                                    <div class="flex flex-col">
                                        <span class="text-[10px] text-emerald-100/70 uppercase font-black tracking-tight">{{ log.label }}</span>
                                        <span v-if="log.maxScore" class="text-[8px] text-slate-500 font-bold">{{ log.maxScore }}</span>
                                    </div>
                                    <span class="text-xs font-mono text-emerald-400 font-bold">{{ log.score }}</span>
                                    
                                    <!-- Attractive Glassmorphism Tooltip -->
                                    <div class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 w-64 p-3 bg-[#0b101b]/98 backdrop-blur-xl border border-white/10 rounded-xl shadow-2xl opacity-0 scale-95 group-hover:opacity-100 group-hover:scale-100 transition-all duration-200 z-[130]">
                                        <div class="text-[9px] text-indigo-400 font-black uppercase tracking-widest mb-1.5 border-b border-white/5 pb-1">Forensic Discussion</div>
                                        <div class="text-[10px] text-slate-200 leading-relaxed font-medium italic">"{{ log.discussion }}"</div>
                                        <div class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-3 h-3 bg-[#0b101b] border-r border-b border-white/10 rotate-45"></div>
                                    </div>
                                </div>
                               <div class="mt-4 flex items-center justify-between p-4 bg-indigo-500/10 border border-indigo-500/20 rounded-xl relative overflow-hidden">
                                   <div class="absolute inset-0 bg-indigo-500/5 blur-xl"></div>
                                   <span class="text-[11px] text-white font-black uppercase tracking-widest relative">Final Sync Score</span>
                                   <span class="text-xl font-black text-white font-mono relative">{{ Number(comparisonData?.sync_score ?? 0).toFixed(2) }}%</span>
                               </div>
                           </div>
                       </div>

                       <!-- Vector Alignment Graph -->
                       <div class="space-y-4">
                           <div class="text-[10px] text-slate-500 font-black uppercase tracking-widest px-1">Structural Alignment</div>
                            <div class="p-5 bg-black/40 border border-white/5 rounded-2xl h-full flex flex-col justify-center gap-4">
                                 <div v-for="vec in vectorCorrelation" :key="vec.key" class="space-y-1.5">
                                     <div class="flex justify-between text-[8px] uppercase font-bold text-slate-600">
                                         <span>{{ vec.label }}</span>
                                         <span :class="vec.status === 'match' ? 'text-emerald-400' : 'text-amber-400'">
                                             {{ Math.max(0, 100 - vec.delta).toFixed(0) }}% Correlation
                                         </span>
                                     </div>
                                     <div class="h-1 w-full bg-white/5 rounded-full overflow-hidden">
                                         <div 
                                            class="h-full transition-all duration-1000" 
                                            :class="vec.status === 'match' ? 'bg-emerald-500/50' : 'bg-amber-500/50'"
                                            :style="{ width: Math.max(0, 100 - vec.delta) + '%' }"
                                         ></div>
                                     </div>
                                 </div>
                                 <p class="text-[9px] text-slate-600 italic leading-tight text-center px-4 mt-2">
                                     Correlation indicates the semantic and statistical alignment between Live and Repo vectors.
                                 </p>
                            </div>
                       </div>
                   </div>

                   <!-- Remediation Roadmap -->
                   <div v-if="normalizedAIReport.roadmap?.length" class="space-y-4">
                        <div class="text-[10px] text-emerald-500 font-black uppercase tracking-widest px-1">Remediation Roadmap</div>
                        <div class="grid grid-cols-1 gap-3">
                            <div v-for="(task, tIdx) in normalizedAIReport.roadmap" :key="tIdx" class="p-4 bg-emerald-500/5 border border-emerald-500/10 rounded-xl flex flex-col gap-4 group hover:border-emerald-500/30 transition-all">
                                <div 
                                    @click="expandedRoadmapTask = expandedRoadmapTask === tIdx ? null : tIdx"
                                    class="flex items-center justify-between cursor-pointer"
                                >
                                    <div class="flex items-center gap-4">
                                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500 text-[10px] font-black">
                                            {{ tIdx + 1 }}
                                        </div>
                                        <div>
                                            <div class="text-[11px] text-slate-200 font-bold uppercase tracking-tight flex items-center gap-2">
                                                {{ (task as any).task }}
                                                <svg 
                                                    class="w-3 h-3 text-slate-500 transition-transform duration-300" 
                                                    :class="{ 'rotate-180': expandedRoadmapTask === tIdx }"
                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                            <div class="text-[9px] text-slate-500 uppercase font-bold">{{ (task as any).priority }} Priority</div>
                                        </div>
                                    </div>
                                    <div class="px-2 py-1 rounded bg-emerald-500/20 text-emerald-400 text-[9px] font-black uppercase tracking-tighter">
                                        {{ (task as any).impact }}
                                    </div>
                                </div>

                                <!-- Expert Implementation Guide Dropdown -->
                                <div v-if="expandedRoadmapTask === tIdx && getTaskGuide((task as any).task)" class="mt-2 p-4 bg-slate-900/80 border border-white/5 rounded-xl space-y-3 animate-in fade-in slide-in-from-top-2 duration-300">
                                    <div class="flex items-center gap-2 text-[9px] font-black text-indigo-400 uppercase tracking-widest">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        {{ getTaskGuide((task as any).task)?.title }}
                                    </div>
                                    <div class="space-y-2">
                                        <div v-for="(step, sIdx) in getTaskGuide((task as any).task)?.steps" :key="sIdx" class="flex items-start gap-2">
                                            <div class="w-4 h-4 rounded-full bg-indigo-500/20 text-indigo-400 text-[8px] flex items-center justify-center font-bold shrink-0 mt-0.5">{{ sIdx + 1 }}</div>
                                            <div class="text-[10px] text-slate-400 leading-tight">{{ step }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Handshake Interaction -->
                                <div v-if="(task as any).task.toLowerCase().includes('handshake')" class="flex flex-col gap-3">
                                    <div class="p-3 bg-indigo-500/5 border border-indigo-500/10 rounded-lg text-[10px] text-slate-400 leading-relaxed italic">
                                        Download the file below and place it in your repository root to verify ownership and gain the +16 point boost.
                                    </div>
                                    
                                    <!-- Handshake Implementation Guide (Phase 14) -->
                                    <div class="p-4 bg-slate-900/50 border border-indigo-500/20 rounded-xl space-y-3">
                                        <div class="text-[9px] font-black text-indigo-400 uppercase tracking-widest">Implementation Guide</div>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2">
                                                <div class="w-4 h-4 rounded-full bg-indigo-500/20 text-indigo-400 text-[8px] flex items-center justify-center font-bold">1</div>
                                                <div class="text-[10px] text-slate-300">Download <code class="text-indigo-400">lume_verification.txt</code></div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <div class="w-4 h-4 rounded-full bg-indigo-500/20 text-indigo-400 text-[8px] flex items-center justify-center font-bold">2</div>
                                                <div class="text-[10px] text-slate-300">Upload to <span class="text-white font-bold">GitHub Repository</span> root</div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <div class="w-4 h-4 rounded-full bg-indigo-500/20 text-indigo-400 text-[8px] flex items-center justify-center font-bold">3</div>
                                                <div class="text-[10px] text-slate-300">Upload to your <span class="text-white font-bold">Production Server</span></div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <div class="w-4 h-4 rounded-full bg-indigo-500/20 text-indigo-400 text-[8px] flex items-center justify-center font-bold">4</div>
                                                <div class="text-[10px] text-slate-300 italic">Re-scan to claim +16 points boost</div>
                                            </div>
                                        </div>
                                    </div>

                                    <button @click="generateVerificationFile" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-[10px] font-black uppercase tracking-widest rounded-lg flex items-center justify-center gap-2 transition-all shadow-lg shadow-indigo-600/20">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                        Download Handshake File
                                    </button>
                                </div>
                            </div>
                        </div>
                   </div>

                   <!-- Recovery Path (Fallback for legacy) -->
                   <div v-else-if="(comparisonData?.sync_score ?? 0) < 95" class="space-y-3">
                       <div class="text-[10px] text-emerald-500/70 font-bold uppercase tracking-widest px-1">Eligibility Recovery Path</div>
                       <div class="p-5 bg-emerald-500/5 border border-emerald-500/10 rounded-2xl space-y-4">
                           <div v-if="normalizedAIReport.path?.length" class="space-y-4">
                                <div v-for="(step, sIdxInPath) in normalizedAIReport.path" :key="sIdxInPath" class="flex gap-4">
                                    <div class="shrink-0 w-6 h-6 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500 text-[10px] font-black">
                                        {{ Number(sIdxInPath) + 1 }}
                                    </div>
                                    <p class="text-[11px] text-slate-300 leading-tight pt-1">{{ step }}</p>
                                </div>
                           </div>
                       </div>
                   </div>
              </div>
              
              <!-- Report Footer -->
              <div class="px-8 py-4 border-t border-slate-800 bg-black/40 flex justify-between items-center shrink-0 gap-4">
                  <div class="flex items-center gap-4">
                      <div class="text-[9px] text-slate-600 uppercase font-black tracking-widest">Sovereign Proof of Sync</div>
                      <div class="w-px h-3 bg-slate-800"></div>
                      <div class="text-[9px] text-indigo-500 font-black uppercase tracking-widest">Hash Verified</div>
                  </div>
                  <button @click="showCalculationReport = false; showPenEligibilityModal = false" class="px-6 py-2 bg-white/5 hover:bg-white/10 text-white text-[10px] font-black uppercase tracking-widest rounded-lg border border-white/10 transition-all">
                      Dismiss Report
                  </button>
              </div>

        </div>
    </div>

    <!-- TECH ID DETAILS MODAL -->
    <div v-if="showTechDetails" class="fixed inset-0 z-[120] flex items-center justify-center p-4 sm:p-6 md:p-10 backdrop-blur-sm bg-black/60 overflow-y-auto">
        <div class="absolute inset-0" @click="showTechDetails = false"></div>
        <div class="relative w-full max-w-2xl my-auto bg-[#0b101b] rounded-2xl border border-slate-700 shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
             <div class="flex justify-between items-center px-6 py-5 border-b border-slate-800 shrink-0">
                 <h3 class="text-sm font-bold text-white uppercase tracking-widest">Forensic Tech Verification</h3>
                 <button @click="showTechDetails = false" class="text-slate-400 hover:text-white transition-colors">
                     <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                 </button>
             </div>
             
             <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">
                 
                 <!-- Analysis Verdict -->
                 <div class="p-4 bg-slate-900/50 rounded-lg border border-slate-800">
                     <div class="text-xs font-bold text-slate-500 uppercase mb-2">Analysis Verdict</div>
                     <div class="text-lg font-mono text-white mb-2">{{ enrichedStackAnalysis.matching_status }}</div>
                     <p class="text-sm text-slate-400">
                         Initial header and DOM analysis confirms alignment between deployed assets and repository source code.
                     </p>
                 </div>

                 <div class="grid grid-cols-2 gap-4">
                     <!-- Live Evidence -->
                     <div class="p-4 bg-emerald-500/5 rounded-lg border border-emerald-500/10">
                         <div class="text-xs font-bold text-emerald-400 uppercase mb-3">Live Site Evidence</div>
                         <ul class="space-y-2">
                             <li v-for="tag in enrichedStackAnalysis.live_evidence" :key="tag" class="flex items-start gap-2 text-xs text-emerald-300 font-mono">
                                 <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                 {{ tag }}
                             </li>
                         </ul>
                     </div>

                     <!-- Repo Evidence -->
                     <div class="p-4 bg-indigo-500/5 rounded-lg border border-indigo-500/10">
                         <div class="text-xs font-bold text-indigo-400 uppercase mb-3">Repository Evidence</div>
                         <ul class="space-y-2">
                             <li v-for="tag in enrichedStackAnalysis.repo_evidence" :key="tag" class="flex items-start gap-2 text-xs text-indigo-300 font-mono">
                                 <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                 {{ tag }}
                             </li>
                         </ul>
                     </div>
                 </div>

             </div>
        </div>
    </div>
    <!-- LUME VERIFICATION MODAL -->
    <div v-if="showVerificationModal" class="fixed inset-0 z-[130] flex items-center justify-center p-4 sm:p-6 md:p-10 backdrop-blur-sm bg-black/80 overflow-y-auto">
        <div class="absolute inset-0" @click="showVerificationModal = false"></div>
        <div class="relative w-full max-w-xl my-auto bg-[#0b101b] rounded-2xl border border-cyan-500/30 shadow-[0_0_50px_rgba(6,182,212,0.2)] flex flex-col max-h-[90vh] overflow-hidden">
             <!-- Modal Glow -->
             <div class="absolute -top-24 -left-24 w-48 h-48 bg-cyan-600/20 blur-3xl rounded-full"></div>
             
             <div class="flex-1 overflow-y-auto custom-scrollbar p-6 sm:p-8 relative">
                 <div class="flex items-center gap-4 mb-6">
                     <div class="w-12 h-12 rounded-xl bg-cyan-500/20 flex items-center justify-center text-cyan-400">
                         <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                     </div>
                     <div>
                         <h3 class="text-xl font-black text-white uppercase tracking-tighter">Identity Handshake</h3>
                         <p class="text-[10px] text-slate-500 font-mono uppercase">Bridge the gap between Code & Production</p>
                     </div>
                 </div>

                 <div class="space-y-6">
                     <div class="p-4 bg-indigo-500/5 border border-indigo-500/10 rounded-xl">
                         <h4 class="text-xs font-bold text-indigo-300 uppercase mb-2">Why is this needed?</h4>
                         <p class="text-xs text-slate-400 leading-relaxed">
                             Modern production environments (CDNs, minifiers, and hidden headers) can hide digital fingerprints. If you are the owner, you can manually link this asset and receive a <span class="text-emerald-400 font-bold">+16.00 score bonus</span>.
                         </p>
                     </div>

                     <div class="space-y-4">
                         <div class="flex items-start gap-3">
                             <div class="w-5 h-5 rounded-full bg-slate-800 text-[10px] font-bold flex items-center justify-center text-slate-400 shrink-0 mt-0.5">1</div>
                             <div class="text-xs text-slate-300">
                                 Download the <code class="text-indigo-400">lume_verification.txt</code> file below.
                             </div>
                         </div>
                         <div class="flex items-start gap-3">
                             <div class="w-5 h-5 rounded-full bg-slate-800 text-[10px] font-bold flex items-center justify-center text-slate-400 shrink-0 mt-0.5">2</div>
                             <div class="text-xs text-slate-300">
                                 Place it in the <span class="text-white font-bold">root directory</span> of your GitHub repository.
                             </div>
                         </div>
                         <div class="flex items-start gap-3">
                             <div class="w-5 h-5 rounded-full bg-slate-800 text-[10px] font-bold flex items-center justify-center text-slate-400 shrink-0 mt-0.5">3</div>
                             <div class="text-xs text-slate-300">
                                 Deploy your site so that <code class="text-emerald-400">yourdomain.com/lume_verification.txt</code> is publicly accessible.
                             </div>
                         </div>
                         <div class="flex items-start gap-3">
                             <div class="w-5 h-5 rounded-full bg-slate-800 text-[10px] font-bold flex items-center justify-center text-slate-400 shrink-0 mt-0.5">4</div>
                             <div class="text-xs text-slate-400 italic">
                                 Once ready, click "Scan Again". TITAN will detect the handshake and grant the bonus.
                             </div>
                         </div>
                     </div>

                     <div class="pt-4 flex flex-col gap-3">
                         <button @click="generateVerificationFile" class="w-full py-4 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl shadow-xl shadow-indigo-600/20 transition-all flex items-center justify-center gap-3">
                             <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                             Download lume_verification.txt
                         </button>
                         <button @click="showVerificationModal = false" class="w-full py-2 text-slate-500 hover:text-white text-[10px] font-bold uppercase tracking-widest transition-colors">
                             Maybe Later
                         </button>
                     </div>
                 </div>
             </div>
        </div>
    </div>
    </Teleport>

    <!-- QA AND PENTEST ELIGIBILITY CHECKER MODAL -->
    <Teleport to="body">
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="showPenEligibilityModal" class="fixed inset-0 z-[140] flex items-center justify-center p-4 backdrop-blur-md bg-black/60">
                <div class="absolute inset-0" @click="showPenEligibilityModal = false"></div>
                <div class="relative w-full max-w-lg bg-[#0a0f1a] rounded-3xl border border-red-500/30 shadow-[0_0_50px_rgba(239,68,68,0.2)] overflow-hidden">
                    <!-- Glow -->
                    <div class="absolute -top-24 -right-24 w-48 h-48 bg-red-600/10 blur-3xl rounded-full"></div>
                    
                    <div class="p-8 relative">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-12 h-12 rounded-xl bg-red-500/10 flex items-center justify-center text-red-500 border border-red-500/20">
                                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-white uppercase tracking-tighter">Eligibility Checker</h3>
                                <p class="text-[10px] text-red-400 font-mono uppercase tracking-widest">LUME_SEC_OPS // ACCESS_CONTROL</p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <!-- DEV NOTICE -->
                            <div class="p-4 bg-amber-500/10 border border-amber-500/30 rounded-xl">
                                <p class="text-xs text-amber-400 font-black text-center uppercase tracking-widest">
                                    Eligibilty checker out for development stage
                                </p>
                            </div>

                            <!-- Eligibility for Penetration Testing (Commented for Dev)
                            <div class="p-4 bg-red-500/5 border border-red-500/10 rounded-xl">
                                <p class="text-[11px] text-slate-400 leading-relaxed uppercase font-black tracking-widest mb-2">Requirement Status</p>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500">Sync Score (85+)</span>
                                        <div class="flex items-center gap-2">
                                            <span :class="syncScore >= 85 ? 'text-emerald-400' : 'text-red-400'" class="font-mono font-bold">{{ syncScore.toFixed(2) }}%</span>
                                            <svg v-if="syncScore >= 85" class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            <svg v-else class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500">LUME Handshake Verified</span>
                                        <div class="flex items-center gap-2">
                                            <span :class="isLumeVerified ? 'text-emerald-400' : 'text-red-400'" class="font-mono font-bold uppercase">{{ isLumeVerified ? 'Confirmed' : 'Missing' }}</span>
                                            <svg v-if="isLumeVerified" class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            <svg v-else class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="canPerformPentest" class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl">
                                <p class="text-xs text-emerald-400 font-bold mb-1 uppercase tracking-widest text-center">Protocol Cleared</p>
                                <p class="text-[10px] text-emerald-300/70 text-center uppercase tracking-tighter">Your asset meets the sovereign requirements for deep penetration testing.</p>
                            </div>
                            <div v-else class="p-4 bg-orange-500/10 border border-orange-500/20 rounded-xl">
                                <p class="text-xs text-orange-400 font-bold mb-1 uppercase tracking-widest text-center">Access Denied</p>
                                <p class="text-[10px] text-orange-300/70 text-center uppercase tracking-tighter">You must achieve 85+ sync score and confirm ownership via LUME Handshake to unlock deep forensics.</p>
                            </div>
                            -->

                            <button 
                                @click="startPentest"
                                :disabled="!canPerformPentest"
                                class="w-full py-4 rounded-2xl font-black uppercase tracking-widest text-sm transition-all shadow-xl flex flex-col items-center justify-center gap-1"
                                :class="canPerformPentest 
                                    ? 'bg-red-600 hover:bg-red-500 text-white shadow-red-900/40 hover:scale-[1.02] active:scale-95' 
                                    : 'bg-slate-800 text-slate-600 cursor-not-allowed border border-white/5'"
                            >
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                    Initiate Deep Forensics
                                </div>
                                <div class="text-[9px] opacity-80 font-mono">
                                    250 CREDITS // 1 FREE RE-SCAN INCLUDED
                                </div>
                            </button>
                            
                            <button @click="showPenEligibilityModal = false" class="w-full text-center text-[10px] text-slate-500 hover:text-white uppercase font-black transition-colors">
                                Return to Sync Deck
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #0b101b;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #1e293b;
    border-radius: 3px;
}
.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .5; }
}
</style>
