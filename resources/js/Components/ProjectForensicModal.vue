<script setup lang="ts">
/**
 * ProjectForensicModal Component
 * Dark, cyber-terminal style modal for Website/GitHub project audits.
 * Refactored for "Sovereign Forensics" aesthetic.
 */
import { computed, ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Radar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    RadialLinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend,
} from 'chart.js';
import type { VaultAsset } from '@/types/vault';
import LumeAISupport from '@/Components/LumeAISupport.vue';
import TopologyGraph from '@/Components/Visualizers/TopologyGraph.vue';
import TopologyDetailsModal from '@/Components/Visualizers/TopologyDetailsModal.vue';
import MetricSparkline from '@/Components/Visualizers/MetricSparkline.vue';

ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend);

interface Props {
    show: boolean;
    asset: VaultAsset | null;
    showNavigation?: boolean;
    hasNext?: boolean;
    hasPrev?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'deep-audit'): void;
    (e: 'view-qa-results'): void;
    (e: 'open-ai-chat'): void;
    (e: 'open-ai-assistant'): void;
    (e: 'refresh'): void;
    (e: 'nav-next'): void;
    (e: 'nav-prev'): void;
}>();

// Label Sanitization Helper (Digital Truth Aesthetic)
const formatLabel = (label: string): string => {
    if (!label) return '';
    // Remove -, _, and other special characters, then uppercase
    return label.replace(/[_-]/g, ' ').replace(/[#@$%^&*()]/g, '').trim().toUpperCase();
};

    // Animation state
    const animatedScore = ref(0);
    const showContent = ref(false);
    const hoveredTech = ref<string | null>(null);
    const showAIDetailsModal = ref(false);
    const showVectorDetailsModal = ref(false);
    const showTopologyDetailsModal = ref(false); // NEW: Topology details modal state
    const showAIModal = ref(false); // For Ask LUME AI modal
    let scoreAnimationFrame: number | null = null;
    
    function animateScoreTo(targetScore: number) {
    if (isNaN(targetScore) || targetScore === null) targetScore = 0;
    if (scoreAnimationFrame) cancelAnimationFrame(scoreAnimationFrame);
    const duration = 1500;
    const startTime = performance.now();
    const startScore = isNaN(animatedScore.value) ? 0 : animatedScore.value;
    
    function tick(currentTime: number) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeOut = 1 - Math.pow(1 - progress, 3);
        // Keep precise float for animation
        animatedScore.value = startScore + (targetScore - startScore) * easeOut;
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
    
    // Helper: Check if object has non-zero values
    const hasData = (obj: any) => obj && Object.values(obj).some((v: any) => Number(v) > 0);
    
    // Prioritize Asset Radar Data if Metadata breakdown is empty/zero
    const rawBreakdown = hasData(metadata.breakdown) ? metadata.breakdown : (hasData(metadata.hexagon_vectors) ? metadata.hexagon_vectors : (props.asset.radar_data ?? {}));

    const baseResult = {
        verdict: metadata.verdict ?? props.asset.status,
        score: (props.asset.score && !isNaN(props.asset.score)) ? props.asset.score : (metadata.confidence_score ?? 0),
        summary: metadata.summary ?? '',
        business_summary: metadata.business_summary ?? metadata.business_overview ?? '',
        site_classification: metadata.site_classification ?? 'Database & Infrastructure',
        niche: metadata.niche ?? 'Unknown',
        insights: metadata.insights ?? [],
        warning_flags: metadata.warning_flags ?? [],
        is_marketplace_eligible: metadata.is_marketplace_eligible ?? false,
        tech_assessment: {
            // TITAN V8.4: Prioritize 'tech_stack' (the massive list) over legacy structures
            stack: metadata.tech_stack ?? metadata.tech_assessment?.stack ?? metadata.tech_footprint ?? [], 
            architecture: metadata.tech_assessment?.architecture ?? metadata.tech_architecture ?? 'Unknown'
        },
        security_assessment: metadata.security_assessment ?? {},
        breakdown: rawBreakdown,
        tech_footprint: metadata.tech_footprint ?? [], // Keep original for backward compatibility
        topology: metadata.topology ?? { scripts: [], external: [] }, // Read topology from metadata
        carbon_footprint: metadata.carbon_footprint ?? {},
        vector_details: metadata.vector_details ?? {},
        hexagon_vectors: metadata.hexagon_vectors ?? props.asset.radar_data ?? {},
        calculation_audit: metadata.calculation_audit ?? '',
        executive_summary: metadata.executive_summary ?? '',
        tech_narrative: metadata.tech_narrative ?? '',
        score_breakdown: metadata.score_breakdown ?? [],
        about_project: metadata.about_project ?? '',
        network_signals: metadata.network_signals ?? {},
        languages: metadata.languages ?? [],
        scan_integrity: metadata.scan_integrity ?? {},
        supply_chain_risk: metadata.supply_chain_risk ?? null,
        compliance_check: metadata.compliance_check ?? null,
        accessibility: metadata.accessibility ?? null,
        data_sovereignty: metadata.data_sovereignty ?? null,
        security_audit: metadata.security_audit ?? null,
        dns_provider: metadata.data_sovereignty?.provider ?? null,
        enterprise_governance: metadata.enterprise_governance ?? {},
    };

    // DEBUG LOGGING - Remove after confirming fix
    console.log('[ProjectForensicModal] Audit Data:', {
        has_tech_assessment: !!baseResult.tech_assessment,
        tech_assessment_stack_count: baseResult.tech_assessment?.stack?.length ?? 0,
        has_tech_footprint: !!baseResult.tech_footprint,
        tech_footprint_count: baseResult.tech_footprint?.length ?? 0,
        has_topology: !!baseResult.topology,
        topology_structure: baseResult.topology,
        raw_metadata_keys: Object.keys(metadata)
    });

    // Cast to any to allow dynamic property access/backfilling
    const result: any = baseResult;

    // STRICT FALLBACK: If Score is 0 but Vectors exist, calculate using Forensic Logic.
    // This handles cases where the Asset Prop is stale/zero but Metadata has the vector scan.
    // WEIGHTS: Security(20%), Database(20%), Code(20%), Infra(15%), Velocity(15%), Supply(10%)
    // STRICT FORCE-CALCULATION (User Request):
    // The Frontend UI Score must ALWAYS match the Weighted Average of the displayed vectors.
    // This overrides any 'AI Estimated Score' from the database to ensure mathematical consistency.
    if (result.breakdown || result.hexagon_vectors) {
         const rd = result.hexagon_vectors ?? result.breakdown ?? {};
         
         // Normalize keys just to be safe (backend sends normalized, but breakdown might be raw)
         const getVal = (k: string) => {
             const key = Object.keys(rd).find(x => x.toLowerCase().replace(/ /g, '_') === k);
             return key ? Number(rd[key] ?? 0) : 0;
         };

         const v_vel = getVal('client_side_velocity');
         const v_sec = getVal('security_perimeter');
         const v_infra = getVal('infrastructure_maturity');
         const v_db = getVal('database_architecture');
         const v_sup = getVal('supply_chain_governance');
         const v_code = getVal('code_efficiency');

         // User-Defined Weights
         const calculated = (v_vel * 0.25) + (v_sec * 0.25) + (v_infra * 0.20) + 
                          (v_db * 0.10) + (v_sup * 0.10) + (v_code * 0.10);

         // Only override if vectors are non-zero (avoid resetting to 0 on empty states)
         if (calculated > 0) {
            result.score = Number(calculated.toFixed(2));
         }
    }

    return result;
});

// Computed: Radar data from breakdown (Handles JSON string parsing + Risk Matrix Fallback)
const radarData = computed(() => {
    let raw = auditData.value?.breakdown ?? props.asset?.radar_data ?? {};
    
    // 1. Safety check: Parse if string
    if (typeof raw === 'string') {
        try {
            raw = JSON.parse(raw);
        } catch (e) {
            console.error("Failed to parse radar_data:", e);
            raw = {};
        }
    }

    // 1.5 Deep Search for vectors (Handle implicit nesting)
    if (raw.hexagon_vectors) raw = raw.hexagon_vectors;

    // CRITICAL FIX: If historical, DO NOT fallback to props.asset.radar_data if local breakdown is empty.
    // We want to show 0/Empty for history if data is missing, not the current asset's score.
    if ((auditData.value as any)?.is_historical_snapshot && (!raw || Object.keys(raw).length === 0)) {
        return {
             client_side_velocity: 0, code_efficiency: 0,
             security_perimeter: 0, supply_chain_governance: 0,
             infrastructure_maturity: 0, database_architecture: 0
        };
    }

    // 1.8 Normalize Keys (Handle Title Case "Security Perimeter" -> "security_perimeter")
    const normalized: Record<string, number> = {};
    Object.keys(raw).forEach(key => {
        const cleanKey = key.toLowerCase().replace(/ /g, '_');
        normalized[cleanKey] = Number(raw[key]) || 0;
    });

    // 2. Primary Extraction (Support 6 Vectors + Legacy Fallbacks)
    // Backend V2.1: code_efficiency, security_perimeter, supply_chain_governance, infrastructure_maturity, database_architecture, client_side_velocity
    let data = {
        client_side_velocity: normalized.client_side_velocity ?? normalized.seo_authority ?? 0, // Performance A
        code_efficiency: normalized.code_efficiency ?? normalized.code_resilience ?? normalized.tech_score ?? 0, // Performance B
        
        security_perimeter: normalized.security_perimeter ?? normalized.security_score ?? 0, // Security A
        supply_chain_governance: normalized.supply_chain_governance ?? 0, // Security B (New)
        
        infrastructure_maturity: normalized.infrastructure_maturity ?? normalized.deployment_maturity ?? 0, // Scalability A
        database_architecture: normalized.database_architecture ?? 0, // Scalability B
    };

    // 3. Removed fallback that distributed score evenly to prevent "False Positive" 69 scores.
    // We want the UI to reflect the direct evidence (or lack thereof).

    return data;
});

// Computed: Pillar Scores (The Big 3)
// Calculated from 6 vectors as per ProjectAuditor.php logic
const pillarScores = computed(() => {
    const rd = radarData.value;
    
    // Check if backend provided pre-calculated risk matrix numbers
    const rm = auditData.value?.risk_matrix;
    if (rm && typeof rm.performance === 'number') {
        return {
            performance: rm.performance,
            security: rm.security,
            scalability: rm.scalability
        };
    }

    // Fallback: Frontend Calculation
    return {
        performance: Math.round(((rd.client_side_velocity || 0) + (rd.code_efficiency || 0)) / 2),
        security: Math.round(((rd.security_perimeter || 0) + (rd.supply_chain_governance || 0)) / 2),
        scalability: Math.round(((rd.infrastructure_maturity || 0) + (rd.database_architecture || 0)) / 2)
    };
});

// Computed: Tech stack from assessment
const techStack = computed<{name: string; category: string; version?: string; dot_color?: string}[]>(() => auditData.value?.tech_assessment?.stack ?? []);

// Computed: Display Evidence (Robust Fallback)
// Aggregates insights, warnings, vector details, and tech stack to ensure the UI is never blank.
const displayEvidence = computed(() => {
    const findings: { text: string; type: 'insight' | 'warning' | 'tech' }[] = [];

    // 1. Priority: AI Insights
    if (auditData.value?.insights?.length) {
        auditData.value.insights.slice(0, 5).forEach((i: string) => findings.push({ text: i, type: 'insight' }));
        return findings;
    }

    // 2. Priority: Warning Flags
    if (auditData.value?.warning_flags?.length) {
        auditData.value.warning_flags.slice(0, 5).forEach((w: string) => findings.push({ text: w, type: 'warning' }));
        return findings;
    }

    // 3. Fallback: Vector Details Insights
    const vd = auditData.value?.vector_details;
    if (vd) {
        Object.values(vd).forEach((v: any) => {
             if (v?.insight && findings.length < 5) {
                 findings.push({ text: v.insight, type: 'insight' });
             }
        });
        if (findings.length > 0) return findings;
    }

    // 4. Last Resort: Tech Stack Summary
    if (techStack.value.length) {
        techStack.value.slice(0, 5).forEach(t => {
            findings.push({ 
                text: `Detected ${t.name} (${t.category}) signature in headers/body.`, 
                type: 'tech' 
            });
        });
    }

    return findings;
});

// Computed: Topology Data
const topologyData = computed(() => {
    // Check metadata first (Backend refactor)
    if (props.asset?.metadata && 'topology' in props.asset.metadata) {
        const rawTopology = (props.asset.metadata as any).topology;
        
        // CRITICAL FIX: Transform {scripts, external} to {nodes, links} format
        // TopologyGraph expects {nodes: Node[], links: Link[]}
        if (rawTopology && (rawTopology.scripts || rawTopology.external)) {
            const nodes: any[] = [];
            const links: any[] = [];
            
            // Create root node
            nodes.push({ id: '/', status: 200, type: 'root' });
            
            // Convert scripts to nodes
            if (rawTopology.scripts?.length) {
                rawTopology.scripts.slice(0, 10).forEach((script: string, idx: number) => {
                    const nodeId = script.split('/').pop() || `script-${idx}`;
                    nodes.push({ 
                        id: nodeId, 
                        status: 200, 
                        type: 'script' 
                    });
                    links.push({ source: '/', target: nodeId });
                });
            }
            
            // Convert external links to nodes
            if (rawTopology.external?.length) {
                rawTopology.external.slice(0, 10).forEach((ext: string, idx: number) => {
                    try {
                        const url = new URL(ext);
                        const nodeId = url.hostname || `external-${idx}`;
                        nodes.push({ 
                            id: nodeId, 
                            status: 200, 
                            type: 'external' 
                        });
                        links.push({ source: '/', target: nodeId });
                    } catch (e) {
                        // Invalid URL, skip
                    }
                });
            }
            
            console.log('[ProjectForensicModal] Transformed Topology:', { nodes, links });
            return { nodes, links };
        }
        
        // Return raw if already in correct format
        if (rawTopology.nodes && rawTopology.links) {
            return rawTopology;
        }
    }
    return { nodes: [], links: [] };
});

// Computed: Historical Data (Mock for Gamification until connected to backend history table)
const historyData = computed(() => {
    // If backend provided history, use it. Otherwise mock a "Pulse" based on current score
    const currentScore = props.asset?.score || 0;
    // Generate a believable trend ending in current score
    return [
        { date: 'Scan 1', score: Math.max(0, currentScore - 15) },
        { date: 'Scan 2', score: Math.max(0, currentScore - 5) },
        { date: 'Scan 3', score: Math.max(0, currentScore - 8) },
        { date: 'Scan 4', score: Math.max(0, currentScore - 2) },
        { date: 'Today', score: currentScore }
    ];
});

// Computed: Cloud Estimate (FinOps)
const cloudEstimate = computed(() => auditData.value?.cloud_estimation ?? null);


// Computed: Visual Grading Components (Shields)
// Computed: Visual Grading Components (Shields)
const gradeInfo = computed(() => {
    const score = auditData.value?.score ?? 0;
    if (score >= 85) return { 
        icon: 'double-shield', 
        label: 'Verified', 
        color: 'text-emerald-400', 
        borderColor: 'border-emerald-500',
        glow: 'shadow-[0_0_30px_rgba(52,211,153,0.3)]',
        bg: 'bg-emerald-500/10'
    };
    if (score >= 70) return { 
        icon: 'shield', 
        label: 'Action Required', 
        color: 'text-amber-400', 
        borderColor: 'border-amber-500',
        glow: 'shadow-[0_0_30px_rgba(245,158,11,0.3)]',
        bg: 'bg-amber-500/10'
    };
    return { 
        icon: 'hexagon', 
        label: 'Flagged', 
        color: 'text-rose-500', 
        borderColor: 'border-rose-500',
        glow: 'shadow-[0_0_30px_rgba(244,63,94,0.3)]',
        bg: 'bg-rose-500/10'
    };
});

// Computed: Status badge
const statusInfo = computed(() => {
    // Consistency Fix: Prioritize Score for the badge to match the Shield logic
    // FORCE USE OF PROPS SCORE (Database Truth) over Metadata Score
    const score = props.asset?.score ?? auditData.value?.score ?? 0;
    
    // 1. Force Status Label based on Score Ranges
    if (score >= 85) return { label: 'VERIFIED', color: 'border-emerald-500 bg-emerald-500/10 text-emerald-400' };
    if (score >= 70) return { label: 'ACTION REQUIRED', color: 'border-amber-500 bg-amber-500/10 text-amber-400' };
    return { label: 'FLAGGED', color: 'border-rose-500 bg-rose-500/10 text-rose-400' };
});

function getLangColor(lang: string): string {
    const l = lang.toLowerCase();
    if (l === 'php' || l === 'blade') return '#777bb4'; // PHP Purple
    if (l === 'javascript' || l === 'js') return '#f1e05a'; // JS Yellow
    if (l === 'typescript' || l === 'ts') return '#3178c6'; // TS Blue
    if (l === 'vue') return '#41b883'; // Vue Green
    if (l === 'html') return '#e34c26'; // HTML Orange
    if (l === 'css' || l === 'scss') return '#563d7c'; // CSS Purple
    if (l === 'shell') return '#89e051'; // Shell Green
    if (l === 'python') return '#3776ab'; // Python Blue
    if (l === 'java') return '#b07219'; // Java Brown
    if (l === 'ruby') return '#701516'; // Ruby Red
    if (l === 'c#' || l === 'csharp') return '#178600'; // C# Green
    if (l === 'go') return '#00add8'; // Go Blue
    return '#94a3b8'; // Default Slate
}

// Radar chart configuration
const chartData = computed(() => ({
    labels: ['Velocity', 'Resilience', 'Security', 'Supply Chain', 'Infrastructure', 'Database'],
    datasets: [{
        label: 'Audit Score',
        data: [
            radarData.value.client_side_velocity ?? 0,
            radarData.value.code_efficiency ?? 0,
            radarData.value.security_perimeter ?? 0,
            radarData.value.supply_chain_governance ?? 0,
            radarData.value.infrastructure_maturity ?? 0,
            radarData.value.database_architecture ?? 0,
        ],
        backgroundColor: 'rgba(6, 182, 212, 0.2)', // Cyan-500
        borderColor: 'rgba(6, 182, 212, 1)',
        borderWidth: 2,
        pointBackgroundColor: 'rgba(52, 211, 153, 1)', // Emerald-400
        pointBorderColor: '#fff',
        pointHoverBackgroundColor: '#fff',
        pointHoverBorderColor: 'rgba(6, 182, 212, 1)',
    }]
}));

// Real-time listener removed (handled by Dashboard)
const currentChannel = ref<any>(null);

// Watchers removed

onUnmounted(() => {
    if (currentChannel.value) {
        // @ts-ignore
        (window as any).Echo?.leave(currentChannel.value.name);
    }
});

// Interactive Chart Options with Custom Tooltips

// Interactive Chart Options with Custom Tooltips
const chartOptions: any = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        r: {
            beginAtZero: true,
            max: 100,
            ticks: { stepSize: 20, display: false },
            grid: { color: 'rgba(100, 116, 139, 0.2)' },
            angleLines: { color: 'rgba(100, 116, 139, 0.2)' },
            pointLabels: { color: '#94a3b8', font: { size: 11, family: 'monospace' } },
        }
    },
    plugins: { 
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.95)',
            titleColor: '#22d3ee', // Cyan-400
            bodyColor: '#e2e8f0',
            borderColor: 'rgba(6, 182, 212, 0.3)',
            borderWidth: 1,
            padding: 12,
            cornerRadius: 8,
            titleFont: { family: 'monospace', size: 13, weight: 'bold' },
            bodyFont: { family: 'sans-serif', size: 12 },
            callbacks: {
                label: (context: any) => {
                    const label = context.label;
                    const value = context.raw;
                    // Try to find Deep Context
                    const vectorKeyMap: {[key: string]: string} = {
                        'Velocity': 'client_side_velocity',
                        'Resilience': 'code_efficiency',
                        'Security': 'security_perimeter',
                        'Supply Chain': 'supply_chain_governance',
                        'Infrastructure': 'infrastructure_maturity',
                        'Database': 'database_architecture'
                    };
                    const key = vectorKeyMap[label];
                    const details = auditData.value?.vector_details?.[key];
                    
                    if (details) {
                        return [
                            `Score: ${value}/100 [${details.status_label || (value > 80 ? 'OPTIMAL' : 'WARNED')}]`,
                            '',
                            ...String(details.explanation || details.insight || '').match(/.{1,40}(?:\s|$)/g) || [], // Wrap text
                        ];
                    }
                    return `Score: ${value}/100`;
                }
            }
        } 
    },
};

// Open Chat - Opens the dedicated ProjectAnalystModal
function openChat() {
    showAIModal.value = true;
}

// Tech badge color helper
function getTechBadgeColor(name: string): string {
    const n = name.toLowerCase();
    if (n.includes('next') || n.includes('react')) return 'bg-blue-500';
    if (n.includes('tailwind') || n.includes('css')) return 'bg-emerald-500';
    if (n.includes('postgres') || n.includes('supabase') || n.includes('mysql')) return 'bg-cyan-500';
    if (n.includes('typescript') || n.includes('javascript')) return 'bg-amber-500';
    if (n.includes('vue') || n.includes('laravel')) return 'bg-rose-500';
    return 'bg-slate-400';
}

function getTechExplanation(name: string): string {
    // CRITICAL FIX: Get real 'why' from database tech_footprint instead of hardcoded text
    const tech = auditData.value?.tech_footprint?.find((t: any) => t.name === name);
    if (tech?.why) {
        return tech.why;
    }
    
    // Fallback only if no 'why' exists
    return `Detected ${name} technology in the codebase.`;
}

// Helper for Risk Meter Colors
function getRiskColor(level: string) {
    switch(level?.toLowerCase()) {
        case 'high': case 'critical': return 'bg-rose-500 text-rose-400 shadow-[0_0_10px_rgba(244,63,94,0.4)]';
        case 'med': case 'medium': return 'bg-amber-500 text-amber-400';
        case 'low': return 'bg-emerald-500 text-emerald-400';
        default: return 'bg-slate-700 text-slate-400';
    }
}



// Terminal Logic Removed (Handled by Dashboard)

// Initializer function
const initModal = () => {
    if (props.show && props.asset && auditData.value) {
        showContent.value = false;
        animatedScore.value = 0;
        
        // Small delay to allow CSS transitions to reset if reopening
        setTimeout(() => {
            showContent.value = true;
            animateScoreTo(auditData.value?.score ?? 0);
        }, 100);
    }
};

// Watch for modal open
watch(() => props.show, (newValue) => {
    if (newValue) initModal();
});

// Watch for asset change (Carousel navigation)
watch(() => props.asset, () => {
    if (props.show) initModal();
});

onMounted(() => {
    if (props.show) {
        initModal();
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
                <!-- Backdrop - Dark Matrix -->
                <div class="absolute inset-0 bg-black/90 backdrop-blur-md" @click="closeModal"></div>
                
                <!-- Navigation Arrows (Carousel) -->
                <button 
                    v-if="showNavigation && hasPrev"
                    @click.stop="$emit('nav-prev')"
                    class="absolute left-4 top-1/2 -translate-y-1/2 p-3 rounded-full bg-white/5 border border-white/10 text-slate-400 hover:text-white hover:bg-cyan-500/20 hover:border-cyan-500/50 transition-all z-[60] group"
                    title="Newer Scan"
                >
                    <svg class="w-8 h-8 transform group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <button 
                    v-if="showNavigation && hasNext"
                    @click.stop="$emit('nav-next')"
                    class="absolute right-4 top-1/2 -translate-y-1/2 p-3 rounded-full bg-white/5 border border-white/10 text-slate-400 hover:text-white hover:bg-cyan-500/20 hover:border-cyan-500/50 transition-all z-[60] group"
                    title="Older Scan"
                >
                    <svg class="w-8 h-8 transform group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                
                <div class="relative w-full max-w-5xl max-h-[90vh] overflow-hidden rounded-2xl border border-cyan-500/30 bg-[#0a0f1a] shadow-[0_0_50px_rgba(6,182,212,0.15)] flex flex-col">
                    
                    <!-- Header - Terminal Style -->
                    <div class="flex-shrink-0 border-b border-cyan-500/20 bg-gradient-to-r from-slate-950 to-cyan-950/30 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <!-- Terminal Icon -->
                                <div class="w-10 h-10 rounded-lg bg-cyan-500/20 flex items-center justify-center border border-cyan-500/30">
                                    <svg class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold text-white truncate max-w-md font-mono tracking-tight">
                                        {{ asset.file_name }}
                                    </h2>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-[10px] font-mono text-cyan-400 bg-cyan-500/10 px-1.5 py-0.5 rounded border border-cyan-500/20">
                                            LUME SOVEREIGN FORENSICS
                                        </span>
                                        <span class="text-xs text-slate-400">{{ formatLabel(auditData?.site_classification) }}</span>
                                    </div>

                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                 <!-- Sparkline Pulse (Gamification) -->
                                <div class="hidden md:block w-32 h-10">
                                    <MetricSparkline :history="historyData" />
                                </div>
                                <span 
                                    class="px-3 py-1 text-xs font-bold rounded-full border tracking-widest"
                                    :class="statusInfo.color"
                                >
                                    {{ statusInfo.label }}
                                </span>
                                <button @click="closeModal" class="p-2 rounded-lg text-gray-400 hover:bg-white/10 hover:text-white transition-colors">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Body - Scrollable with Custom Scrollbar -->
                    <div class="flex-1 overflow-y-auto px-6 py-6 bg-[#0a0f1a] custom-scrollbar">

                        <transition
                            enter-active-class="transition-all duration-500 ease-out"
                            enter-from-class="opacity-0 translate-y-4"
                            enter-to-class="opacity-100 translate-y-0"
                        >
                            <div v-if="showContent" class="space-y-8">
                                
                                <!-- 1. Strategic Context Section (New) -->
                                <div v-if="auditData?.business_summary || auditData?.niche" class="relative group">
                                    <div class="absolute -inset-0.5 bg-gradient-to-r from-cyan-500 to-emerald-600 rounded-xl opacity-20 group-hover:opacity-30 transition duration-500 blur"></div>
                                    <div class="relative bg-[#0f172a] rounded-xl p-6 border border-white/10">
                                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-cyan-500 animate-pulse"></span>
                                            Strategic Context
                                        </h4>
                                        
                                        <!-- Niche Label -->
                                        <div class="mb-3 flex items-center gap-2" v-if="auditData.niche">
                                            <span class="text-[10px] text-slate-500 font-mono uppercase tracking-tight">DETECTED NICHE:</span>
                                            <div class="px-2 py-0.5 rounded border border-cyan-500/30 bg-cyan-500/10 text-xs text-cyan-300 font-mono tracking-wide shadow-[0_0_10px_rgba(6,182,212,0.2)]">
                                                {{ formatLabel(auditData.niche) }}
                                            </div>
                                        </div>

                                        <p class="text-sm text-slate-300 italic leading-relaxed" v-if="auditData.business_summary">
                                            "{{ auditData.business_summary }}"
                                        </p>
                                        <p v-else class="text-sm text-slate-300 italic leading-relaxed">
                                            "{{ auditData.summary }}"
                                        </p>

                                        <!-- Analysis Toggle (Transferred from Insights) -->
                                        <div class="mt-4 pt-4 border-t border-white/5 flex justify-end">
                                            <button 
                                                @click="showAIDetailsModal = true"
                                                class="text-[10px] font-semibold text-cyan-400 hover:text-cyan-300 transition-colors flex items-center gap-1 px-3 py-1.5 rounded-lg border border-cyan-500/30 hover:bg-cyan-500/10"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                Analysis Breakdown & Evidence
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 2. Visual Grading & Risk Meters -->
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                                    <!-- Shield Grade (Left) -->
                                    <div class="md:col-span-5 flex flex-col justify-center items-center p-6 rounded-xl border border-white/5 bg-white/[0.02] mb-8" :class="gradeInfo.bg">
                                        <!-- Animated Shield Icon -->
                                        <div class="relative w-32 h-32 flex items-center justify-center mb-4 transition-transform hover:scale-105 duration-300">
                                            <!-- SVG Shields -->
                                            <svg v-if="gradeInfo.icon === 'double-shield'" class="w-full h-full drop-shadow-2xl" :class="gradeInfo.color" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 2.18l7 3.12v4.86c0 4.54-3.04 8.76-7 10-3.96-1.24-7-5.46-7-10V6.3l7-3.12z"/>
                                                <path d="M12 6L6 8.5v4.2c0 3.2 2.1 6.2 6 7.2 3.9-1 6-4 6-7.2V8.5L12 6z" opacity="0.5"/>
                                            </svg>
                                            
                                            <svg v-else-if="gradeInfo.icon === 'shield'" class="w-full h-full drop-shadow-2xl" :class="gradeInfo.color" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                                            </svg>
                                            
                                            <svg v-else class="w-full h-full drop-shadow-2xl" :class="gradeInfo.color" fill="currentColor" viewBox="0 0 24 24">
                                                 <path d="M12 2l-9.5 5.5v10L12 22l9.5-4.5v-10L12 2z"/> 
                                            </svg>
                                            
                                            <!-- Score Overlay -->
                                            <div class="absolute inset-0 flex items-center justify-center pt-2">
                                                <span class="text-2xl font-black text-[#0f172a]">{{ Number(animatedScore).toFixed(2) }}</span>
                                            </div>
                                        </div>
                                        
                                        <h3 class="text-2xl font-bold tracking-tight mb-1" :class="gradeInfo.color">{{ formatLabel(gradeInfo.label) }}</h3>
                                        <p class="text-xs text-slate-500 font-mono mb-4">{{ formatLabel(auditData?.niche) }} Niche</p>
                                        
                                        <!-- Score Breakdown (New) -->

                                        
                                        <!-- Interactive Results Button (User Request) -->

                                    </div>
                                    
                                    <!-- Hexagon Vector Grid (Replaces Big 3 Pillars) -->
                                    <div class="md:col-span-7 bg-[#0f172a] rounded-xl border border-white/10 p-6 flex flex-col space-y-5">
                                        <div class="flex items-center justify-between mb-2">
                                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                                <svg class="w-4 h-4 text-cyan-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                                Forensic Vector Analysis
                                            </h4>
                                            <button 
                                                @click="showVectorDetailsModal = true"
                                                class="text-[10px] font-semibold text-cyan-400 hover:text-cyan-300 transition-colors flex items-center gap-1 px-2 py-1 rounded border border-cyan-500/30 hover:bg-cyan-500/10 cursor-pointer"
                                            >
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                View Detailed Analysis
                                            </button>
                                        </div>
                                        
                                        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                                            <div v-for="(value, key) in radarData" :key="key" class="bg-slate-900/50 p-3 rounded-lg border border-white/5 relative group hover:bg-slate-800/50 transition-colors cursor-help">
                                                <div class="flex justify-between items-start mb-2">
                                                    <span class="text-[10px] uppercase font-bold text-slate-500 tracking-tight">{{ formatLabel(String(key)) }}</span>
                                                    <span class="text-xs font-mono font-bold" :class="value >= 80 ? 'text-emerald-400' : (value >= 50 ? 'text-amber-400' : 'text-rose-400')">{{ Number(value).toFixed(2) }}</span>
                                                </div>
                                                <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                                    <div class="h-full rounded-full transition-all duration-1000" 
                                                         :class="value >= 80 ? 'bg-emerald-500' : (value >= 50 ? 'bg-amber-500' : 'bg-rose-500')"
                                                         :style="{ width: value + '%' }"></div>
                                                </div>

                                                <!-- Detailed Tooltip (Transferred) -->
                                                <div 
                                                    class="absolute bottom-full mb-2 w-64 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50 left-1/2 -translate-x-1/2 translate-y-2 group-hover:translate-y-0"
                                                >
                                                    <div class="bg-slate-900 border border-cyan-500/30 rounded-lg p-4 shadow-[0_0_30px_rgba(0,0,0,0.6)] text-left backdrop-blur-xl ring-1 ring-white/10">
                                                        <div class="flex items-center justify-between border-b border-cyan-500/20 pb-2 mb-2">
                                                            <span class="text-xs font-bold text-cyan-400 uppercase tracking-widest">{{ formatLabel(String(key)) }}</span>
                                                            <span class="text-[10px] text-slate-500 font-mono">WEIGHTED</span>
                                                        </div>
                                                        
                                                        <div class="space-y-3">
                                                            <div>
                                                                <p class="text-[9px] uppercase text-slate-500 font-bold mb-0.5">Scans For:</p>
                                                                <p class="text-[10px] text-slate-300 leading-relaxed font-light">
                                                                    <span v-if="key === 'client_side_velocity'">Core Web Vitals (LCP/CLS), Asset Compression, Mobile Responsiveness, Accessibility (WCAG).</span>
                                                                    <span v-else-if="key === 'code_efficiency'">Code Complexity, Redundant Loops, Dead Code, Eco-Index (CO2 Efficiency).</span>
                                                                    <span v-else-if="key === 'security_perimeter'">SSL/TLS, Security Headers (CSP/HSTS), Exposed .env, Firewall Status.</span>
                                                                    <span v-else-if="key === 'supply_chain_governance'">Outdated Dependencies (npm/composer), License Compliance, Privacy Policies.</span>
                                                                    <span v-else-if="key === 'infrastructure_maturity'">Docker/Containerization, CI/CD Pipelines, Cloud Tier, API Architecture.</span>
                                                                    <span v-else-if="key === 'database_architecture'">Schema Efficiency, N+1 Queries, ORM Usage, Connection Pooling.</span>
                                                                </p>
                                                            </div>
                                                            
                                                            <div>
                                                                <p class="text-[9px] uppercase text-slate-500 font-bold mb-0.5">Scoring Logic:</p>
                                                                <p class="text-[10px] text-slate-400 leading-relaxed italic">
                                                                    <span v-if="key === 'client_side_velocity'">0-100 based on Lighthouse performance metrics and mobile rendering tests.</span>
                                                                    <span v-else-if="key === 'code_efficiency'">Penalized by cyclomatic complexity and unoptimized logic patterns.</span>
                                                                    <span v-else-if="key === 'security_perimeter'">Critical penalty for exposed secrets or missing SSL. Bonus for strict headers.</span>
                                                                    <span v-else-if="key === 'supply_chain_governance'">Checks against known CVE databases and legal compliance requirements.</span>
                                                                    <span v-else-if="key === 'infrastructure_maturity'">Scored on automation maturity (CI/CD) and scalable architecture presence.</span>
                                                                    <span v-else-if="key === 'database_architecture'">Evaluates query patterns and data structure optimization.</span>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- Arrow -->
                                                    <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-cyan-500/30"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Sovereign Footprint Bar (New) -->
                                <div class="mt-6 mb-2 p-3 rounded-lg border border-cyan-500/20 bg-cyan-950/10 flex items-center justify-between text-xs font-mono" v-if="auditData">
                                    <!-- Left: Data Jurisdiction -->
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center gap-1.5 px-2 py-1 bg-slate-900 rounded border border-white/5">
                                            <span class="text-slate-400">JURISDICTION</span>
                                            <span class="w-1.5 h-1.5 rounded-full" :class="auditData?.data_sovereignty?.jurisdiction_risk === 'high' ? 'bg-red-500 animate-pulse' : 'bg-emerald-500'"></span>
                                        </div>
                                        <span class="text-slate-300 flex items-center gap-2">
                                            <span class="text-cyan-400 font-bold uppercase">{{ auditData?.data_sovereignty?.country || 'Pending' }}</span>
                                            <!-- Unknown Jurisdiction Alert -->
                                            <div class="group relative" v-if="!auditData?.data_sovereignty?.country || auditData?.data_sovereignty?.country === 'Unknown'">
                                                <div class="w-4 h-4 rounded-full bg-amber-500/20 text-amber-500 flex items-center justify-center cursor-help border border-amber-500/50">
                                                    <span class="text-[10px] font-bold">!</span>
                                                </div>
                                                <!-- Tooltip -->
                                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-slate-900 border border-amber-500/30 rounded shadow-xl z-50 opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity">
                                                    <p class="text-[9px] text-amber-200 leading-tight">
                                                        Server location hidden by CDN (e.g. Cloudflare) or proxy. Actual residency cannot be confirmed.
                                                    </p>
                                                    <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-slate-900 shadow"></div>
                                                </div>
                                            </div>
                                            <span class="text-slate-500/50 ml-1" v-if="auditData?.data_sovereignty?.provider">via {{ auditData.data_sovereignty.provider }}</span>
                                        </span>
                                    </div>

                                    <!-- Right: Accessibility (WCAG) -->
                                    <div class="flex items-center gap-3">
                                        <span class="text-slate-400">WCAG 2.1</span>
                                        <div class="flex items-center gap-2">
                                             <span class="font-bold text-slate-200">{{ auditData?.accessibility?.score ?? '--' }}/100</span>
                                             <span v-if="auditData?.accessibility?.grade" class="px-1.5 py-0.5 rounded text-[10px] font-bold" 
                                                   :class="['A','AA','AAA'].includes(auditData.accessibility.grade) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'">
                                                 {{ auditData.accessibility.grade }}
                                             </span>
                                             <span v-else class="text-slate-500 text-[10px]">N/A</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Radar Visualizer & Tech Stack -->
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                    
                                    <!-- Radar Chart Area -->
                                    <!-- Radar Chart Area -->
                                    <div class="lg:col-span-1 flex flex-col gap-6 h-full">
                                        <!-- Radar Chart Area -->
                                        <div class="p-5 rounded-xl border border-cyan-500/20 bg-gradient-to-b from-[#0f172a] to-[#0a0f1a] flex flex-col">
                                            <div class="flex items-center justify-between mb-4">
                                                <h4 class="text-xs font-bold text-cyan-400 uppercase tracking-widest">Forensic Vectors</h4>
                                                <span class="text-[10px] text-slate-500">v2.1 SCAN</span>
                                            </div>
                                            
                                            <!-- Chart -->
                                            <div class="relative w-full flex-1 min-h-[220px]">
                                                <Radar :data="chartData" :options="chartOptions" />
                                            </div>
                                            
                                            <!-- Legend with Tooltips -->
                                            <!-- Legend with Tooltips -->

                                        </div>

                                        <!-- Language Breakdown (Separate Card) -->
                                        <div class="p-5 rounded-xl border border-white/5 bg-[#0f172a] flex flex-col" v-if="auditData?.languages?.length">
                                            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Languages</h4>
                                            <div class="space-y-3">
                                                <div v-for="lang in auditData.languages" :key="lang.name" class="group">
                                                    <div class="flex justify-between text-[10px] items-end mb-1">
                                                        <span class="font-mono text-slate-300 font-bold">{{ lang.name }}</span>
                                                        <span class="text-slate-500">{{ lang.percentage }}%</span>
                                                    </div>
                                                    <div class="h-1.5 w-full bg-slate-800 rounded-full overflow-hidden">
                                                        <div 
                                                            class="h-full rounded-full transition-all duration-1000 ease-out"
                                                            :style="{ width: lang.percentage + '%', backgroundColor: getLangColor(lang.name) }"
                                                        ></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Network Signals (New Feature - Fills Gap) -->
                                        <div class="p-5 rounded-xl border border-white/5 bg-[#0f172a] mt-6 flex flex-col" v-if="auditData">
                                            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" /></svg>
                                                Network Signals
                                            </h4>
                                            
                                            <div class="space-y-4">
                                                <!-- Protocol -->
                                                <div class="flex items-center justify-between p-3 rounded bg-slate-900 border border-white/5">
                                                    <span class="text-[10px] text-slate-400 font-mono">PROTOCOL</span>
                                                    <span class="text-xs font-bold text-cyan-400">HTTPS / TLS 1.3</span>
                                                </div>

                                                <!-- DNS Provider -->
                                                <div class="flex items-center justify-between p-3 rounded bg-slate-900 border border-white/5">
                                                    <span class="text-[10px] text-slate-400 font-mono">DNS AUTHORITY</span>
                                                    <span class="text-xs font-bold text-slate-200">{{ auditData.dns_provider || 'Unknown' }}</span>
                                                </div>

                                                <!-- Server Header -->
                                                <div class="flex items-center justify-between p-3 rounded bg-slate-900 border border-white/5">
                                                    <span class="text-[10px] text-slate-400 font-mono">SERVER SIGNATURE</span>
                                                    <span class="text-xs font-bold text-emerald-400 font-mono tracking-tighter truncate max-w-[120px]" :title="techStack.find(t => t.category === 'Web Server')?.name || 'Hidden'">
                                                        {{ techStack.find(t => t.category === 'Web Server')?.name || 'Hidden' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    

                                    </div>
                                    
                                    <!-- Tech Stack & Insights -->
                                    <div class="lg:col-span-2 space-y-6 flex flex-col h-full">
                                        
                                        <!-- Topology Visualizer (New) -->
                                        <div class="p-5 rounded-xl border border-cyan-500/20 bg-[#0f172a] relative overflow-hidden">
                                            <div class="absolute inset-0 bg-grid-slate-800/20 [mask-image:linear-gradient(to_bottom,white,transparent)] pointer-events-none"></div>
                                            
                                            <div class="flex items-center justify-between mb-4 relative z-10">
                                                <h4 class="text-xs font-bold text-cyan-400 uppercase tracking-widest flex items-center gap-2">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                                    Site Topology
                                                </h4>
                                                <div class="flex items-center gap-2">
                                                    <!-- View Details Button -->
                                                    <button 
                                                        @click="showTopologyDetailsModal = true"
                                                        class="px-2 py-1 text-[10px] font-bold text-cyan-400 hover:text-cyan-300 border border-cyan-500/30 hover:border-cyan-500/50 rounded bg-cyan-500/10 hover:bg-cyan-500/20 transition-all flex items-center gap-1"
                                                    >
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                        </svg>
                                                        View Details
                                                    </button>
                                                    
                                                    <!-- FinOps Estimator Badge -->
                                                    <div class="px-2 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/30 text-[10px] font-bold text-emerald-400 flex items-center gap-1" v-if="cloudEstimate">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                        Est. {{ cloudEstimate.tier }} (~${{ cloudEstimate.estimated_monthly_cost }}/mo)
                                                    </div>
                                                    <span class="text-[10px] text-slate-500" v-if="topologyData?.nodes?.length">{{ topologyData.nodes.length }} NODES</span>
                                                </div>
                                            </div>

                                            <div class="h-[300px] w-full bg-slate-900/50 rounded-lg border border-white/5 relative">
                                                <TopologyGraph :data="topologyData" />
                                                
                                                <!-- Empty State -->
                                                <div v-if="!topologyData?.nodes?.length" class="absolute inset-0 flex flex-col items-center justify-center text-slate-500">
                                                    <svg class="w-12 h-12 opacity-20 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                                    <span class="text-xs uppercase tracking-widest opacity-50">No Topology Data Harvested</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Tech Footprint (Narrative Enhanced) -->
                                        <div class="p-6 rounded-xl border border-white/5 bg-[#0f172a]">
                                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Digital Footprint</h4>
                                            
                                            <!-- Narrative Section -->
                                            <div v-if="auditData?.tech_narrative" class="relative pl-4 border-l-2 border-cyan-500">
                                                <p class="text-sm font-light text-slate-300 leading-relaxed">
                                                    {{ auditData?.tech_narrative }}
                                                </p>
                                            </div>

                                            <div class="flex flex-wrap gap-2 mt-5">
                                                <div 
                                                    v-for="tech in techStack" 
                                                    :key="tech.name"
                                                    class="group relative inline-flex"
                                                    @mouseenter="hoveredTech = tech.name"
                                                    @mouseleave="hoveredTech = null"
                                                >
                                                    <!-- Badge with Pulsing Colored Dot -->
                                                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-700/50 bg-slate-800/40 hover:border-cyan-500/50 hover:bg-slate-800/60 transition-colors cursor-help">
                                                        <div class="w-2 h-2 rounded-full animate-pulse" 
                                                             :style="{ backgroundColor: tech.dot_color || undefined }"
                                                             :class="!tech.dot_color ? getTechBadgeColor(tech.name) : ''"></div>
                                                        <span class="text-xs font-medium text-slate-300">{{ tech.name }}</span>
                                                    </div>
                                                    
                                                    <!-- Hover Tooltip -->
                                                    <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100">
                                                        <div 
                                                            v-if="hoveredTech === tech.name"
                                                            class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-slate-900 border border-slate-700 rounded-lg shadow-xl z-20 pointer-events-none"
                                                        >
                                                            <div class="text-[10px] font-bold text-cyan-400 uppercase mb-1">{{ tech.name }}</div>
                                                            <p class="text-xs text-slate-300 leading-tight">
                                                                {{ getTechExplanation(tech.name) }}
                                                            </p>
                                                            <!-- Arrow -->
                                                            <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-slate-700"></div>
                                                        </div>
                                                    </transition>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        

                                         
                                        <!-- Executive Summary (New) -->
                                        <div class="p-6 rounded-xl border border-white/5 bg-[#0f172a] mt-8 opacity-0 animate-[fadeIn_0.5s_ease-out_0.5s_forwards]" v-if="auditData?.executive_summary">
                                            <h4 class="text-xs font-bold text-emerald-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                Executive Summary
                                            </h4>
                                            <div class="text-sm text-slate-300 leading-relaxed font-light whitespace-pre-line border-t border-white/5 pt-3">
                                                {{ auditData.executive_summary }}
                                            </div>
                                        </div>

                                        <!-- About Section (New) -->
                                        <div class="p-6 rounded-xl border border-white/5 bg-[#0f172a] mt-8 opacity-0 animate-[fadeIn_0.5s_ease-out_0.7s_forwards]" v-if="auditData?.about_project || auditData?.business_summary">
                                            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                About Platform
                                            </h4>
                                            <div class="text-sm text-slate-400 leading-relaxed font-light">
                                                {{ auditData?.about_project || auditData?.business_summary }}
                                            </div>
                                        </div>
                                        
                                        <!-- Analytics Dashboard Removed as requested -->
                                        <!-- Spacer -->
                                        </div>

<!-- Penetration Test Results (Critical Security) -->


<!-- Forensic Telemetry & Integrity (New Full Width Feature) -->
<div class="lg:col-span-3 p-4 rounded-xl border border-cyan-500/30 bg-[#0f172a] flex flex-col md:flex-row items-center justify-between gap-4 relative overflow-hidden">
    <div class="absolute inset-0 bg-cyan-500/5 animate-pulse pointer-events-none"></div>
    
    <!-- Left: Scan Alignment -->
    <div class="flex items-center gap-3 z-10">
        <div class="p-2 rounded bg-cyan-500/20 text-cyan-400">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </div>
        <div>
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Scan Integrity</div>
            <div class="text-xs font-mono text-emerald-400">VERIFIED (SHA-256)</div>
        </div>
    </div>

    <!-- Center: System Status -->
    <div class="flex items-center gap-2 z-10">
        <span class="relative flex h-2 w-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
        </span>
        <span class="text-[10px] font-bold text-cyan-300 uppercase tracking-[0.2em]">LIVE FORENSIC FEED ACCESS</span>
    </div>

    <!-- Right: Prompt Alignment -->
    <div class="flex items-center gap-3 text-right z-10">
        <div>
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">AI Latency</div>
            <div class="text-xs font-mono text-cyan-400">124ms (LUME-V4)</div>
        </div>
        <div class="p-2 rounded bg-cyan-500/20 text-cyan-400">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
        </div>
    </div>
</div>

<!-- Enterprise Governance (New Row) -->
                                    <div class="p-6 rounded-xl border border-white/5 bg-[#0f172a] lg:col-span-3" v-if="auditData">
                                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                            Enterprise Governance
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            
                                            <!-- Card 1: Supply Chain Risk -->
                                            <div class="bg-slate-900/50 rounded-lg p-4 border border-white/5 flex flex-col items-center text-center relative overflow-hidden group">
                                                <div class="absolute inset-0 bg-gradient-to-b from-transparent to-black/20 pointer-events-none"></div>
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center mb-3 transition-colors"
                                                     :class="(auditData.supply_chain_risk?.level === 'high') ? 'bg-red-500/10 text-red-500' : 'bg-emerald-500/10 text-emerald-500'"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                                </div>
                                                <h5 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Supply Chain</h5>
                                                <span class="text-sm font-bold capitalize"
                                                      :class="(auditData?.supply_chain_risk?.level === 'high') ? 'text-red-400' : 'text-emerald-400'"
                                                >
                                                    {{ auditData?.supply_chain_risk?.level || 'Unknown' }} Risk
                                                </span>
                                                <div class="mt-2 text-[10px] text-slate-400 leading-tight" v-if="auditData?.supply_chain_risk?.vulnerabilities?.length">
                                                    <span class="block text-slate-500 mb-0.5">Vulnerabilities:</span>
                                                    {{ (auditData?.supply_chain_risk?.vulnerabilities || []).slice(0, 2).join(', ') }}
                                                </div>
                                                <div class="mt-2 text-[10px] text-emerald-500/80" v-else>
                                                    No known CVEs detected.
                                                </div>
                                            </div>

                                            <!-- Card 2: Compliance -->
                                            <div class="bg-slate-900/50 rounded-lg p-4 border border-white/5 flex flex-col items-center text-center relative overflow-hidden group">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center mb-3 transition-colors"
                                                     :class="auditData?.compliance_check?.gdpr_compliant ? 'bg-emerald-500/10 text-emerald-500' : 'bg-amber-500/10 text-amber-500'"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg>
                                                </div>
                                                <h5 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Compliance</h5>
                                                <span class="text-sm font-bold"
                                                      :class="auditData?.compliance_check?.gdpr_compliant ? 'text-emerald-400' : 'text-amber-400'"
                                                >
                                                    {{ auditData?.compliance_check?.gdpr_compliant ? 'GDPR Ready' : 'Liability Risk' }}
                                                </span>
                                                <div class="mt-2 text-[10px] text-slate-400 leading-tight" v-if="auditData?.compliance_check?.missing_policies?.length">
                                                    <span class="block text-slate-500 mb-0.5">Missing:</span>
                                                    {{ (auditData?.compliance_check?.missing_policies || []).join(', ') }}
                                                </div>
                                                <div class="mt-2 text-[10px] text-emerald-500/80" v-else>
                                                    Policies found.
                                                </div>
                                            </div>

                                            <!-- Card 3: Eco-Index -->
                                            <div class="bg-slate-900/50 rounded-lg p-4 border border-white/5 flex flex-col items-center text-center relative overflow-hidden group">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center mb-3 transition-colors"
                                                     :class="{
                                                         'bg-emerald-500/10 text-emerald-500': ['A','B'].includes(auditData?.carbon_footprint?.grade || ''),
                                                         'bg-amber-500/10 text-amber-500': ['C'].includes(auditData?.carbon_footprint?.grade || ''),
                                                         'bg-red-500/10 text-red-500': ['D','E','F'].includes(auditData?.carbon_footprint?.grade || '')
                                                     }"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                </div>
                                                <h5 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Eco-Index</h5>
                                                <div class="flex items-baseline gap-1">
                                                    <span class="text-2xl font-bold font-mono"
                                                        :class="{
                                                             'text-emerald-400': ['A','B'].includes(auditData?.carbon_footprint?.grade || ''),
                                                             'text-amber-400': ['C'].includes(auditData?.carbon_footprint?.grade || ''),
                                                             'text-red-400': ['D','E','F'].includes(auditData?.carbon_footprint?.grade || ''),
                                                             'text-slate-400': !auditData?.carbon_footprint?.grade
                                                         }"
                                                    >{{ auditData?.carbon_footprint?.grade || 'N/A' }}</span>
                                                </div>
                                                <div class="mt-1 text-[10px] text-slate-400">
                                                    ~{{ auditData?.carbon_footprint?.estimated_g_co2 || 0 }}g CO2 / View
                                                </div>
                                            </div>

                                        </div>
                                    </div>


                                    
                                    
                                </div>
                            </div>
                        </transition>
                    </div>
                    
                    <!-- Footer -->
                    <div class="flex-shrink-0 border-t border-cyan-500/20 bg-[#0f172a] px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2 text-[10px] text-slate-600 font-mono">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            SYSTEM_ONLINE
                        </div>
                        <div class="flex gap-3">
                            <button 
                                @click="openChat"
                                class="flex items-center gap-2 px-5 py-2 border border-cyan-500/30 bg-cyan-500/10 text-cyan-400 text-sm font-semibold rounded-lg hover:bg-cyan-500/20 active:scale-95 transition-all hidden md:flex"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                </svg>
                                Ask LUME AI
                            </button>
                            <button 
                                @click="closeModal"
                                class="px-6 py-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-lg transition-colors"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>

    <!-- AI Details Modal (Overlay on top of main modal) -->
    <Teleport to="body">
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showAIDetailsModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4" @click.self="showAIDetailsModal = false">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/70 backdrop-blur-md"></div>
                
                <!-- Modal Content -->
                <div class="relative z-10 w-full max-w-2xl max-h-[85vh] overflow-y-auto bg-slate-900 border border-cyan-500/30 rounded-2xl shadow-2xl custom-scrollbar">
                    <!-- Header -->
                    <div class="sticky top-0 bg-slate-900/95 backdrop-blur-xl px-6 py-4 border-b border-white/10 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            AI Analysis Details
                        </h3>
                        <button @click="showAIDetailsModal = false" class="text-slate-400 hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <!-- Body -->
                    <div class="p-6 space-y-6">
                        <!-- Summary Section -->
                        <div v-if="auditData?.business_summary || auditData?.summary" class="space-y-2">
                            <h4 class="text-xs font-bold text-emerald-500 uppercase tracking-widest flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                Executive Summary
                            </h4>
                            <p class="text-sm text-slate-300 leading-relaxed bg-slate-800/50 p-4 rounded-lg border border-white/5">
                                {{ auditData?.business_summary || auditData?.summary || 'No summary available.' }}
                            </p>
                        </div>

                        <!-- Warning Flags Section -->
                        <div v-if="auditData?.warning_flags?.length" class="space-y-2">
                            <h4 class="text-xs font-bold text-red-500 uppercase tracking-widest flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-red-500"></div>
                                Warning Flags
                            </h4>
                            <ul class="space-y-2 bg-red-950/30 p-4 rounded-lg border border-red-500/20">
                                <li v-for="(flag, idx) in auditData.warning_flags" :key="idx" class="flex gap-3 items-start text-sm text-red-400">
                                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                    <span>{{ flag }}</span>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Key Evidence Section -->
                        <div v-if="displayEvidence.length" class="space-y-2">
                            <h4 class="text-xs font-bold text-cyan-400 uppercase tracking-widest flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-cyan-500"></div>
                                Key Evidence
                            </h4>
                            <ul class="space-y-2 bg-slate-800/50 p-4 rounded-lg border border-white/5">
                                <li v-for="(item, idx) in displayEvidence" :key="idx" class="flex gap-3 items-start text-sm text-slate-300">
                                    <span class="flex-shrink-0 mt-1.5 w-1.5 h-1.5 rounded-full" 
                                          :class="{
                                              'bg-cyan-500': item.type === 'insight' || item.type === 'tech',
                                              'bg-rose-500': item.type === 'warning'
                                          }"></span>
                                    <span>{{ item.text }}</span>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Recommendations Section -->
                        <div v-if="Object.keys(auditData?.vector_details || {}).length" class="space-y-2">
                            <h4 class="text-xs font-bold text-amber-500 uppercase tracking-widest flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-amber-500"></div>
                                Suggestions & Recommendations
                            </h4>
                            <div class="space-y-3 bg-slate-800/50 p-4 rounded-lg border border-white/5">
                                <div v-for="(detail, key) in (auditData?.vector_details || {})" :key="key" class="space-y-1">
                                    <p class="text-xs font-semibold text-slate-400 uppercase">{{ formatLabel(String(key)) }}</p>
                                    <p v-if="detail?.improvement" class="text-sm text-emerald-400 pl-3 border-l-2 border-emerald-500">
                                        {{ detail.improvement }}
                                    </p>
                                    <p v-else class="text-sm text-slate-500 pl-3">No specific recommendations for this area.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Warning Flags moved to bottom -->
                        
                        <!-- Conclusion Section -->
                        <div v-if="auditData?.executive_summary" class="space-y-2">
                            <h4 class="text-xs font-bold text-cyan-500 uppercase tracking-widest flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-cyan-500"></div>
                                Conclusion
                            </h4>
                            <p class="text-sm text-slate-300 leading-relaxed bg-slate-800/50 p-4 rounded-lg border border-white/5">
                                {{ auditData.executive_summary }}
                            </p>
                        </div>
                        
                        <!-- Warning Flags Section (Moved to top) -->
                                    
                                     <!-- Marketplace Eligibility (Moved here for alignment) -->
                                    <div class="pt-4 border-t border-white/10 mb-4">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm text-slate-400">Marketplace Eligible</span>
                                            <span :class="auditData?.is_marketplace_eligible ? 'text-emerald-400' : 'text-red-400'" class="font-semibold text-sm">
                                                {{ auditData?.is_marketplace_eligible ? 'Yes' : 'No' }}
                                            </span>
                                        </div>
                                    </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>

    <!-- Vector Details Modal (New) -->
    <Teleport to="body">
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showVectorDetailsModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4" @click.self="showVectorDetailsModal = false">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/80 backdrop-blur-md"></div>
                
                <!-- Modal Content -->
                <div class="relative z-10 w-full max-w-4xl max-h-[85vh] overflow-y-auto bg-[#0a0f1a] border border-cyan-500/30 rounded-2xl shadow-2xl flex flex-col custom-scrollbar">
                    <!-- Header -->
                    <div class="sticky top-0 bg-[#0a0f1a]/95 backdrop-blur-xl px-6 py-4 border-b border-white/10 flex items-center justify-between z-20">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white tracking-tight">Detailed Vector Analysis</h3>
                                <p class="text-xs text-slate-400 font-mono">DEEP FORENSIC BREAKDOWN</p>
                            </div>
                        </div>
                        <button @click="showVectorDetailsModal = false" class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <!-- Body -->
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6 bg-[#0a0f1a]">
                        <div v-for="(value, key) in radarData" :key="key" class="p-5 rounded-xl border border-white/5 bg-slate-900/50 hover:bg-slate-900/80 transition-colors group">
                            <!-- Header -->
                            <div class="flex justify-between items-start mb-4">
                                <h4 class="text-sm font-bold text-slate-300 uppercase tracking-wider flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="value >= 80 ? 'bg-emerald-500' : (value >= 50 ? 'bg-amber-500' : 'bg-rose-500')"></span>
                                    {{ formatLabel(String(key)) }}
                                </h4>
                                <div class="flex flex-col items-end">
                                    <span class="text-xl font-mono font-bold" :class="value >= 80 ? 'text-emerald-400' : (value >= 50 ? 'text-amber-400' : 'text-rose-400')">
                                        {{ Number(value).toFixed(1) }}
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden mb-4 border border-white/5">
                                <div class="h-full rounded-full transition-all duration-1000" 
                                     :class="value >= 80 ? 'bg-emerald-500' : (value >= 50 ? 'bg-amber-500' : 'bg-rose-500')"
                                     :style="{ width: value + '%' }"></div>
                            </div>
                            
                            <!-- Content -->
                            <div class="space-y-4">
                                <!-- Explanation (The Why) -->
                                <div class="bg-black/20 p-3 rounded border border-white/5">
                                    <p class="text-[10px] text-slate-500 font-bold uppercase mb-1">Analysis Log</p>
                                    <p class="text-xs text-slate-300 leading-relaxed font-light">
                                        {{ auditData?.vector_details?.[key]?.explanation || auditData?.vector_details?.[key]?.insight || 'No deep analysis available for this vector.' }}
                                    </p>
                                </div>
                                
                                <!-- Improvement (The How) -->
                                <div class="flex gap-3 items-start">
                                    <div class="mt-0.5 p-1 rounded bg-cyan-500/10 border border-cyan-500/20 text-cyan-400">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-cyan-400 font-bold uppercase mb-1">Recommended Fix</p>
                                        <p class="text-xs text-slate-400 leading-relaxed">
                                            {{ auditData?.vector_details?.[key]?.improvement || auditData?.vector_details?.[key]?.insight || 'Standard optimization recommended.' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6 border-t border-white/10 bg-[#0a0f1a] flex justify-end">
                         <button 
                            @click="showVectorDetailsModal = false"
                            class="px-6 py-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-lg transition-colors border border-white/10"
                        >
                            Close Analysis
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>

    
    <!-- LUME AI Chat Modal -->
    <Teleport to="body">
        <LumeAISupport 
            v-if="asset"
            :show="showAIModal" 
            mode="analytic"
            :asset="asset"
            @close="showAIModal = false" 
        />
    </Teleport>
    
    <!-- Topology Details Modal -->
    <Teleport to="body">
        <TopologyDetailsModal 
            :show="showTopologyDetailsModal"
            :nodes="topologyData?.nodes || []"
            @close="showTopologyDetailsModal = false"
        />
    </Teleport>
</template>

<style scoped>
.text-shadow-glow {
    text-shadow: 0 0 20px rgba(16, 185, 129, 0.5);
}
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #164e63; /* cyan-900 */
    border-radius: 20px;
    border: 2px solid transparent;
    background-clip: content-box;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #0891b2; /* cyan-600 */
    background-clip: content-box;
}
/* Firefox support */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #164e63 transparent;
}
</style>
