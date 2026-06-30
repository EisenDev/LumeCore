<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { Radar } from 'vue-chartjs';
import axios from 'axios';
import {
    Chart as ChartJS,
    RadialLinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend,
} from 'chart.js';

// Import visualizers and sub-modals
import LumeAISupport from '@/Components/LumeAISupport.vue';
import TopologyGraph from '@/Components/Visualizers/TopologyGraph.vue';
import TopologyDetailsModal from '@/Components/Visualizers/TopologyDetailsModal.vue';
import MetricSparkline from '@/Components/Visualizers/MetricSparkline.vue';
import PenetrationAndAQTesting from '@/Components/PenetrationAndAQTesting.vue';
import QAPenetrationResultsModal from '@/Components/QAPenetrationResultsModal.vue';
import ProjectAnalystModal from '@/Components/ProjectAnalystModal.vue';
import CreditPurchaseModal from '@/Components/CreditPurchaseModal.vue';
import UniversalScanning from '@/Components/Scanner/UniversalScanning.vue';

ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend);

interface VaultAsset {
    id: number | string;
    user_id: number;
    file_name: string;
    file_path: string;
    file_size: number | null;
    mime_type: string | null;
    status: string;
    metadata: any;
    synced_metadata: any;
    website_metadata: any;
    repository_metadata: any;
    sync_score: number | null;
    score: number | null;
    is_for_sale: boolean;
    price: number | null;
    radar_data: any;
    created_at: string;
    updated_at: string;
}

interface Props {
    asset: VaultAsset;
    hash: string;
    history: any[];
}

const props = defineProps<Props>();

// Tabs state
const tabs = [
    { id: 'overview', label: 'Overview' },
    { id: 'forensic', label: 'Forensic Analysis' },
    { id: 'topology', label: 'Site Topology' },
    { id: 'technologies', label: 'Technologies' },
    { id: 'evidence', label: 'Evidence' },
    { id: 'recommendations', label: 'Recommendations' },
    { id: 'history', label: 'History' }
];
const activeTab = ref('overview');

// Dialog sub-modals
const showAIDetailsModal = ref(false);
const showVectorDetailsModal = ref(false);
const showTopologyDetailsModal = ref(false);
const showAIModal = ref(false);

const showDeepScanConfirmation = ref(false);
const showQAResultsModal = ref(false);
const showProjectAnalystModal = ref(false);
const showPentestAiModal = ref(false);
const showPurchaseModal = ref(false);
const showSecurityScanningModal = ref(false);

// Scanning animation parameters
const animatedScore = ref(0);
const showContent = ref(false);
const hoveredTech = ref<string | null>(null);

// Evidence Page filter and search
const activeEvidenceTab = ref('all'); // all, network, http, code, config, file, other
const evidenceSearchQuery = ref('');

// Recommendations Page filter
const activeRecFilter = ref('all'); // all, critical, high, medium, low

const auditProgress = ref<any>({
    step: 'Idle',
    progress: 0,
    details: ''
});

// Format label utilities
const formatLabel = (label: string): string => {
    if (!label) return '';
    return label.replace(/[_-]/g, ' ').replace(/[#@$%^&*()]/g, '').trim().toUpperCase();
};

function animateScoreTo(targetScore: number) {
    const duration = 1200;
    const startTime = performance.now();
    const startScore = animatedScore.value;
    
    function tick(currentTime: number) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeOut = 1 - Math.pow(1 - progress, 3);
        animatedScore.value = Math.round(startScore + (targetScore - startScore) * easeOut);
        if (progress < 1) {
            requestAnimationFrame(tick);
        }
    }
    requestAnimationFrame(tick);
}

onMounted(() => {
    showContent.value = true;
    animateScoreTo(props.asset.score || 32);

    // Setup Echo listener if active scan
    const page = usePage();
    const user = page.props.auth.user;
    if (user?.id && (window as any).Echo) {
        (window as any).Echo.private(`user.${user.id}`)
            .listen('.scan.progress', (e: any) => {
                if (e.step) auditProgress.value.step = e.step;
                if (e.progress !== undefined) auditProgress.value.progress = e.progress;
                if (e.details) auditProgress.value.details = e.details;
            });
    }
});

// Extracts metadata fields safely
const auditData = computed(() => {
    return props.asset.metadata || {};
});

// Mocked / formatted sparkline history
const historyData = computed(() => {
    if (props.history && props.history.length > 0) {
        return props.history.map(h => h.score || 0).reverse();
    }
    return [40, 50, 45, 60, 55, props.asset.score || 32];
});

// Counts risks breakdown
const riskBreakdown = computed(() => {
    const meta = auditData.value;
    const counts = { crit: 8, high: 11, med: 7, low: 4, info: 2, total: 32 };
    
    if (meta.findings_breakdown) {
        counts.crit = meta.findings_breakdown.critical ?? 8;
        counts.high = meta.findings_breakdown.high ?? 11;
        counts.med = meta.findings_breakdown.medium ?? 7;
        counts.low = meta.findings_breakdown.low ?? 4;
        counts.info = meta.findings_breakdown.info ?? 2;
    }
    counts.total = counts.crit + counts.high + counts.med + counts.low + counts.info;
    return counts;
});

// Retrieves technologies stack list
const techStack = computed(() => {
    const meta = auditData.value;
    if (meta.tech_stack && Array.isArray(meta.tech_stack)) {
        return meta.tech_stack;
    }
    // Default fallback technologies for infosoft mockup
    return [
        { name: 'Laravel 11.x', category: 'Backend', dot_color: '#ef4444' },
        { name: 'WordPress 6.4.x', category: 'CMS', dot_color: '#3b82f6' },
        { name: 'Vue.js 3.x', category: 'Frontend', dot_color: '#10b981' },
        { name: 'Tailwind CSS 3.x', category: 'CSS Framework', dot_color: '#06b6d4' },
        { name: 'Cloudflare', category: 'CDN / Proxy', dot_color: '#f97316' },
        { name: 'MySQL 8.0', category: 'Database', dot_color: '#3b82f6' },
        { name: 'Nginx', category: 'Web Server', dot_color: '#10b981' },
        { name: 'Google Analytics', category: 'Analytics', dot_color: '#f59e0b' }
    ];
});

// Category resolver
function getTechCategory(name: string): string {
    const n = name.toLowerCase();
    if (n.includes('laravel') || n.includes('php') || n.includes('node') || n.includes('python')) return 'Backend';
    if (n.includes('vue') || n.includes('react') || n.includes('js') || n.includes('ts')) return 'Frontend';
    if (n.includes('tailwind') || n.includes('css')) return 'CSS Framework';
    if (n.includes('cloudflare')) return 'CDN / Security';
    if (n.includes('mysql') || n.includes('postgres') || n.includes('database')) return 'Database';
    if (n.includes('nginx') || n.includes('apache')) return 'Server';
    if (n.includes('wordpress') || n.includes('cms')) return 'CMS';
    return 'Library';
}

function getTechBadgeColor(name: string): string {
    const cat = getTechCategory(name);
    if (cat === 'Backend') return 'bg-rose-500/10 border-rose-500/20 text-rose-400';
    if (cat === 'Frontend') return 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400';
    if (cat === 'CSS Framework') return 'bg-cyan-500/10 border-cyan-500/20 text-cyan-400';
    if (cat === 'CDN / Security') return 'bg-orange-500/10 border-orange-500/20 text-orange-400';
    if (cat === 'Database') return 'bg-blue-500/10 border-blue-500/20 text-blue-400';
    return 'bg-slate-500/10 border-slate-500/20 text-slate-400';
}

function getTechExplanation(name: string): string {
    const n = name.toLowerCase();
    if (n.includes('laravel')) return 'Laravel is an open-source PHP framework utilized for robust backend application design.';
    if (n.includes('wordpress')) return 'WordPress is a PHP-based content management system used for site layouts.';
    if (n.includes('vue')) return 'Vue.js is an open-source model-view-viewmodel front-end JavaScript framework.';
    if (n.includes('tailwind')) return 'Tailwind CSS is an open-source utility-first CSS framework for custom markup layouts.';
    return `Detected ${name} signature running on target host headers and composition logs.`;
}

// Retrieves radar coordinates
const radarData = computed(() => {
    return props.asset.radar_data || auditData.value.hexagon_vectors || {
        infrastructure_maturity: 80,
        security_perimeter: 70,
        database_architecture: 75,
        supply_chain_governance: 60,
        code_efficiency: 70,
        client_side_velocity: 66
    };
});

// Radar Chart Config
const chartData = computed(() => ({
    labels: ['Architecture', 'Access Control', 'Data Exposure', 'Security Posture', 'Third-Party'],
    datasets: [{
        label: 'Security Level',
        data: [
            radarData.value.infrastructure_maturity ?? 80,
            radarData.value.security_perimeter ?? 75,
            radarData.value.database_architecture ?? 60,
            radarData.value.supply_chain_governance ?? 70,
            radarData.value.code_efficiency ?? 65
        ],
        backgroundColor: 'rgba(239, 68, 68, 0.1)',
        borderColor: '#ef4444',
        borderWidth: 1.5,
        pointBackgroundColor: '#ef4444',
        pointBorderColor: '#fff',
        pointHoverBackgroundColor: '#fff',
        pointHoverBorderColor: '#ef4444',
    }]
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        r: {
            beginAtZero: true,
            max: 100,
            ticks: { stepSize: 20, display: false },
            grid: { color: 'rgba(255, 255, 255, 0.05)' },
            angleLines: { color: 'rgba(255, 255, 255, 0.05)' },
            pointLabels: { color: '#64748b', font: { size: 9, family: 'monospace', weight: 'bold' } },
        }
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(10, 10, 12, 0.95)',
            titleColor: '#fff',
            bodyColor: '#94a3b8',
            borderColor: 'rgba(255, 255, 255, 0.08)',
            borderWidth: 1,
            padding: 10,
            cornerRadius: 8
        }
    }
};

// Site Topology Data Mapper
const topologyData = computed(() => {
    const rootName = props.asset.file_name;
    const nodes = [
        { id: rootName, label: 'Main Domain', status: 200, type: 'root' },
        { id: 'www.infosoft.poolreno.com', label: 'Web Application', status: 200, type: 'subdomain' },
        { id: 'api.infosoft.poolreno.com', label: 'API Gateway', status: 200, type: 'subdomain' },
        { id: 'cdn.infosoft.poolreno.com', label: 'Static Assets', status: 200, type: 'subdomain' },
        { id: 'mail.infosoft.poolreno.com', label: 'Email Service', status: 200, type: 'external' },
        { id: 'Cloudflare WAF', label: 'WAF Proxy', status: 200, type: 'security' },
        { id: 'MySQL 8.0', label: 'Database', status: 200, type: 'database' },
        { id: 'AWS S3 Bucket', label: 'File Storage', status: 200, type: 'database' },
        { id: 'Cloudflare', label: 'CDN / Security', status: 200, type: 'security' }
    ];
    const links = [
        { source: rootName, target: 'www.infosoft.poolreno.com', type: 'primary' },
        { source: rootName, target: 'api.infosoft.poolreno.com', type: 'primary' },
        { source: rootName, target: 'cdn.infosoft.poolreno.com', type: 'primary' },
        { source: rootName, target: 'mail.infosoft.poolreno.com', type: 'secondary' },
        { source: rootName, target: 'Cloudflare WAF', type: 'security' },
        { source: 'api.infosoft.poolreno.com', target: 'MySQL 8.0', type: 'primary' },
        { source: 'cdn.infosoft.poolreno.com', target: 'AWS S3 Bucket', type: 'secondary' },
        { source: rootName, target: 'Cloudflare', type: 'security' }
    ];
    return { nodes, links };
});

// Interactive Topology selection
const selectedTopologyNodeId = ref<string>('/');
const handleTopologyNodeClick = (node: any) => {
    selectedTopologyNodeId.value = node.id;
};

const activeTopologyNode = computed(() => {
    const id = selectedTopologyNodeId.value;
    if (id === '/' || id === props.asset.file_name) {
        return {
            id: props.asset.file_name,
            label: 'Main Domain',
            type: 'Web Application',
            status: '200 OK',
            ip: '103.21.244.0',
            protocol: 'HTTPS / TLS 1.3',
            server: 'nginx',
            location: 'Singapore (SG)',
            lastSeen: 'Just now',
            ports: [
                { port: 80, protocol: 'HTTP', active: true },
                { port: 443, protocol: 'HTTPS', active: true },
                { port: 8080, protocol: 'HTTP-Alt', active: false }
            ]
        };
    }
    
    // Check specific nodes matching graph mock layout
    if (id.includes('api.infosoft')) {
        return {
            id: 'api.infosoft.poolreno.com',
            label: 'API Gateway',
            type: 'API Gateway Subdomain',
            status: '200 OK',
            ip: '103.21.244.12',
            protocol: 'HTTPS / TLS 1.3',
            server: 'Go / Fiber',
            location: 'Singapore (SG)',
            lastSeen: '2s ago',
            ports: [
                { port: 443, protocol: 'HTTPS', active: true },
                { port: 80, protocol: 'HTTP', active: true }
            ]
        };
    }
    if (id.includes('cdn.infosoft')) {
        return {
            id: 'cdn.infosoft.poolreno.com',
            label: 'Static Assets',
            type: 'CDN Subdomain',
            status: '200 OK',
            ip: '103.21.244.15',
            protocol: 'HTTPS / TLS 1.3',
            server: 'Nginx',
            location: 'Singapore (SG)',
            lastSeen: '1m ago',
            ports: [
                { port: 443, protocol: 'HTTPS', active: true }
            ]
        };
    }
    if (id.includes('MySQL') || id.includes('database')) {
        return {
            id: 'MySQL 8.0',
            label: 'MySQL Database',
            type: 'Database Engine',
            status: 'Internal Connection',
            ip: '10.0.4.52 (VPC IP)',
            protocol: 'TCP / MySQL Protocol',
            server: 'MySQL 8.0.32-log',
            location: 'Internal VPC Area',
            lastSeen: 'Just now',
            ports: [
                { port: 3306, protocol: 'MySQL', active: true }
            ]
        };
    }
    if (id.includes('S3') || id.includes('Bucket')) {
        return {
            id: 'AWS S3 Bucket',
            label: 'AWS S3 Bucket',
            type: 'File / Storage Node',
            status: 'Active',
            ip: 's3.ap-southeast-1.amazonaws.com',
            protocol: 'HTTPS / S3 REST',
            server: 'AmazonS3',
            location: 'Singapore (SG)',
            lastSeen: '15s ago',
            ports: [
                { port: 443, protocol: 'HTTPS', active: true }
            ]
        };
    }
    if (id.includes('WAF')) {
        return {
            id: 'Cloudflare WAF',
            label: 'Cloudflare WAF',
            type: 'Web Application Firewall',
            status: 'Filtering Traffic',
            ip: '172.67.138.45',
            protocol: 'HTTPS / Anycast',
            server: 'cloudflare',
            location: 'Global Anycast Edge',
            lastSeen: 'Just now',
            ports: [
                { port: 80, protocol: 'HTTP', active: true },
                { port: 443, protocol: 'HTTPS', active: true }
            ]
        };
    }
    if (id.includes('mail')) {
        return {
            id: 'mail.infosoft.poolreno.com',
            label: 'Email Service',
            type: 'Mail Exchanger / SMTP',
            status: 'Active',
            ip: '104.21.32.1',
            protocol: 'SMTP / SMTPS',
            server: 'Postfix Mailer',
            location: 'Singapore (SG)',
            lastSeen: '4m ago',
            ports: [
                { port: 25, protocol: 'SMTP', active: true },
                { port: 587, protocol: 'SMTPS', active: true }
            ]
        };
    }
    
    // Default Fallback
    return {
        id: id,
        label: id,
        type: 'Discovered Dependency',
        status: 'Active',
        ip: '104.21.32.20',
        protocol: 'HTTPS / HTTP',
        server: 'nginx',
        location: 'Singapore (SG)',
        lastSeen: 'Just now',
        ports: [
            { port: 80, protocol: 'HTTP', active: true },
            { port: 443, protocol: 'HTTPS', active: true }
        ]
    };
});

// Findings parser
const keyFindings = computed(() => {
    return [
        { title: 'Outdated framework detected', desc: 'Laravel 11.x with known vulnerabilities.', severity: 'critical', impact: 'High Impact', color: 'text-red-400 bg-rose-950/20 border-rose-500/20' },
        { title: 'Exposed administrative endpoints', desc: '2 admin panels accessible without additional protection.', severity: 'critical', impact: 'High Impact', color: 'text-red-400 bg-rose-950/20 border-rose-500/20' },
        { title: 'Third-party risk', desc: '7 third-party scripts with medium to high risk.', severity: 'high', impact: 'Medium Impact', color: 'text-orange-400 bg-orange-950/20 border-orange-500/20' },
        { title: 'Missing security headers', desc: 'Important security headers are not implemented.', severity: 'medium', impact: 'Medium Impact', color: 'text-yellow-400 bg-yellow-950/20 border-yellow-500/20' },
        { title: 'Information disclosure', desc: 'Server version and technology stack exposed.', severity: 'low', impact: 'Low Impact', color: 'text-emerald-400 bg-emerald-950/20 border-emerald-500/20' }
    ];
});

// Status configuration
const statusInfo = computed(() => {
    const score = props.asset.score ?? 32;
    if (score >= 85) return { label: 'VERIFIED', color: 'border-[#CBB48A] bg-[#CBB48A]/10 text-[#CBB48A]' };
    if (score >= 75) return { label: 'ACTION REQUIRED', color: 'border-amber-500 bg-amber-500/10 text-amber-400' };
    return { label: 'FLAGGED', color: 'border-rose-500/30 bg-rose-500/5 text-rose-400' };
});

const formattedScanDate = computed(() => {
    const d = props.asset.created_at ? new Date(props.asset.created_at) : new Date();
    return {
        date: d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
        time: d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
    };
});

const scanDuration = computed(() => {
    return props.asset.metadata?.scan_duration || '2m 14s';
});

// Close / Back handler
function handleClose() {
    router.visit(route('scans.index'));
}

function handleDownloadReport() {
    window.print();
}

async function refreshSelectedAsset() {
    try {
        const response = await axios.get(`/api/vault/assets/${props.asset.id}`);
        if (response.data?.asset) {
            router.reload({ only: ['asset'] });
        }
    } catch (e) {
        console.error("Error refreshing asset:", e);
    }
}

async function handleDeepScanConfirm(customPrompt: string, isRescan: boolean = false) {
    try {
        await axios.post('/api/vault/deep-audit', {
            asset_id: props.asset.id,
            custom_prompt: customPrompt,
            is_rescan: isRescan
        });
        showQAResultsModal.value = false;
        showSecurityScanningModal.value = true;
    } catch (err) {
        console.error("Deep Scan trigger error:", err);
    }
}

function handleCloseScanningModal() {
    showSecurityScanningModal.value = false;
}
</script>

<template>
    <Head :title="`${props.asset.file_name} - Forensic Results`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 w-full">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-white/[0.01] border border-rose-500/30 flex items-center justify-center text-rose-500 relative shrink-0 shadow-inner">
                        <div class="absolute inset-0 bg-rose-500/5 rounded-2xl"></div>
                        <svg class="w-6 h-6 text-rose-500 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <polygon points="12,2 22,7 22,17 12,22 2,17 2,7" fill="none" stroke="currentColor" stroke-width="1.5"/>
                            <circle cx="12" cy="12" r="3" fill="currentColor"/>
                        </svg>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <div class="flex items-center gap-2.5">
                            <h2 class="text-xl font-black text-white truncate max-w-lg tracking-tight leading-tight">
                                {{ props.asset.file_name }}
                            </h2>
                            <span 
                                class="px-2.5 py-0.5 text-[9px] font-black rounded-full border tracking-widest uppercase"
                                :class="statusInfo.color"
                            >
                                {{ statusInfo.label }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2.5 mt-1.5 font-mono">
                            <span class="text-[9px] font-black uppercase tracking-widest px-2.5 py-0.5 rounded border border-rose-500/20 text-rose-400 bg-rose-500/5">
                                LUME SOVEREIGN FORENSICS
                            </span>
                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">
                                AGENCY PORTFOLIO / SERVICE PORTAL
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-4 font-sans">
                    <!-- Sparkline Pulse -->
                    <div class="hidden md:block w-36 h-10">
                        <MetricSparkline :history="historyData" />
                    </div>
                    <button @click="handleDownloadReport" class="flex items-center gap-2 px-4 py-2 bg-white/[0.01] border border-white/5 hover:bg-white/[0.04] hover:border-white/10 text-slate-400 hover:text-white transition-all text-xs font-bold rounded-xl cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Download Report
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            
            <!-- Breadcrumbs link -->
            <div class="flex items-center gap-2 text-[10px] font-black font-mono text-slate-500 tracking-wider">
                <span class="hover:text-slate-350 cursor-pointer" @click="handleClose">SCANS</span>
                <span>/</span>
                <span class="text-slate-400">WEBSITE FORENSICS</span>
            </div>

            <!-- Top Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
                <!-- Risk Score Card -->
                <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex items-center gap-4 shadow-lg shadow-black/40">
                    <div class="relative w-14 h-14 flex items-center justify-center shrink-0">
                        <svg class="absolute inset-0 w-full h-full drop-shadow-[0_0_10px_rgba(239,68,68,0.2)]" viewBox="0 0 100 100">
                            <polygon points="50,5 95,28 95,72 50,95 5,72 5,28" fill="none" stroke="#ef4444" stroke-width="4"/>
                        </svg>
                        <div class="relative text-lg font-black font-mono text-white pt-0.5">{{ animatedScore }}</div>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest leading-none font-mono">Risk Score</span>
                        <span class="text-xs font-black mt-1 uppercase text-rose-500">HIGH RISK</span>
                        <span class="text-[8px] text-[#CBB48A] mt-1 font-mono uppercase tracking-wider font-bold bg-[#CBB48A]/5 border border-[#CBB48A]/15 px-1 py-0.2 rounded truncate" title="Software Development Agency">Niche: Software</span>
                    </div>
                </div>

                <!-- Severity Card -->
                <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex items-center gap-4 shadow-lg shadow-black/40">
                    <div class="w-10 h-10 rounded-xl bg-white/[0.02] border border-white/5 flex items-center justify-center shrink-0 text-rose-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest leading-none font-mono">Severity</span>
                        <span class="text-xs font-black text-rose-400 mt-1 uppercase">HIGH</span>
                        <span class="text-[9px] text-slate-500 mt-0.5 font-medium leading-none">Likely to be exploited</span>
                    </div>
                </div>

                <!-- Confidence Card -->
                <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex items-center gap-4 shadow-lg shadow-black/40">
                    <div class="w-10 h-10 rounded-xl bg-white/[0.02] border border-white/5 flex items-center justify-center shrink-0 text-amber-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest leading-none font-mono">Confidence</span>
                        <span class="text-xs font-black text-amber-500 mt-1">85%</span>
                        <span class="text-[9px] text-slate-500 mt-0.5 font-medium leading-none">High confidence</span>
                    </div>
                </div>

                <!-- Scan Date Card -->
                <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex items-center gap-4 shadow-lg shadow-black/40">
                    <div class="w-10 h-10 rounded-xl bg-white/[0.02] border border-white/5 flex items-center justify-center shrink-0 text-slate-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25" />
                        </svg>
                    </div>
                    <div class="flex flex-col font-mono">
                        <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest leading-none">Scan Date</span>
                        <span class="text-xs font-black text-white mt-1">Jun 22, 2026</span>
                        <span class="text-[9px] text-slate-500 mt-0.5 font-bold">4:58 PM</span>
                    </div>
                </div>

                <!-- Duration Card -->
                <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex items-center gap-4 shadow-lg shadow-black/40">
                    <div class="w-10 h-10 rounded-xl bg-white/[0.02] border border-white/5 flex items-center justify-center shrink-0 text-slate-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest leading-none font-mono">Duration</span>
                        <span class="text-xs font-black text-white mt-1">2m 14s</span>
                        <span class="text-[9px] text-slate-500 mt-0.5 font-medium leading-none">Full forensic scan</span>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs Bar -->
            <div class="border-b border-white/5 flex items-center justify-between pb-px pt-2">
                <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1">
                    <button 
                        v-for="tab in tabs" 
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        class="px-4 py-3 text-[10px] font-black tracking-widest transition-all border-b-2 uppercase whitespace-nowrap cursor-pointer text-shadow"
                        :class="activeTab === tab.id ? 'border-[#CBB48A] text-white' : 'border-transparent text-slate-500 hover:text-slate-350'"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <!-- Main Tab Content Area (rendered directly on page background) -->
            <div class="space-y-6 min-h-[450px] pt-2">
                
                <!-- TAB: OVERVIEW -->
                <div v-if="activeTab === 'overview'" class="space-y-6">
                    <!-- Row 1: Risk Breakdown, Forensic Overview, Asset Topology Overview -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        <!-- RISK BREAKDOWN -->
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5 font-mono">
                                        RISK BREAKDOWN
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 111.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    </h4>
                                </div>
                                <div class="space-y-3.5">
                                    <div class="space-y-1 font-sans">
                                        <div class="flex justify-between text-[10px] font-bold text-slate-400">
                                            <span>Critical</span>
                                            <span class="font-mono text-white">8</span>
                                        </div>
                                        <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden border border-white/5">
                                            <div class="h-full bg-rose-600 rounded-full transition-all duration-500" style="width: 53%;"></div>
                                        </div>
                                    </div>
                                    <div class="space-y-1 font-sans">
                                        <div class="flex justify-between text-[10px] font-bold text-slate-400">
                                            <span>High</span>
                                            <span class="font-mono text-white">11</span>
                                        </div>
                                        <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden border border-white/5">
                                            <div class="h-full bg-pink-500 rounded-full transition-all duration-500" style="width: 73%;"></div>
                                        </div>
                                    </div>
                                    <div class="space-y-1 font-sans">
                                        <div class="flex justify-between text-[10px] font-bold text-slate-400">
                                            <span>Medium</span>
                                            <span class="font-mono text-white">7</span>
                                        </div>
                                        <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden border border-white/5">
                                            <div class="h-full bg-orange-500 rounded-full transition-all duration-500" style="width: 47%;"></div>
                                        </div>
                                    </div>
                                    <div class="space-y-1 font-sans">
                                        <div class="flex justify-between text-[10px] font-bold text-slate-400">
                                            <span>Low</span>
                                            <span class="font-mono text-white">4</span>
                                        </div>
                                        <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden border border-white/5">
                                            <div class="h-full bg-yellow-400 rounded-full transition-all duration-500" style="width: 27%;"></div>
                                        </div>
                                    </div>
                                    <div class="space-y-1 font-sans">
                                        <div class="flex justify-between text-[10px] font-bold text-slate-400">
                                            <span>Informational</span>
                                            <span class="font-mono text-white">2</span>
                                        </div>
                                        <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden border border-white/5">
                                            <div class="h-full bg-cyan-400 rounded-full transition-all duration-500" style="width: 13%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FORENSIC OVERVIEW -->
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                            <div>
                                <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5 mb-4 font-mono">
                                    FORENSIC OVERVIEW
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 1 1 1.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                </h4>
                                <div class="grid grid-cols-4 gap-2 text-center mb-5 font-sans">
                                    <div class="bg-white/[0.01] border border-white/5 rounded-xl p-2 flex flex-col items-center">
                                        <svg class="w-4 h-4 text-rose-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3Z"/></svg>
                                        <span class="text-xs font-bold text-white font-mono">14</span>
                                        <span class="text-[7px] font-bold text-slate-500 uppercase tracking-wider mt-0.5 leading-none">Findings</span>
                                    </div>
                                    <div class="bg-white/[0.01] border border-white/5 rounded-xl p-2 flex flex-col items-center">
                                        <svg class="w-4 h-4 text-emerald-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                        <span class="text-xs font-bold text-white font-mono">24</span>
                                        <span class="text-[7px] font-bold text-slate-500 uppercase tracking-wider mt-0.5 leading-none">Techs</span>
                                    </div>
                                    <div class="bg-white/[0.01] border border-white/5 rounded-xl p-2 flex flex-col items-center">
                                        <svg class="w-4 h-4 text-blue-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7h.01M12 17h.01M12 12h.01"/></svg>
                                        <span class="text-xs font-bold text-white font-mono">7</span>
                                        <span class="text-[7px] font-bold text-slate-500 uppercase tracking-wider mt-0.5 leading-none">Assets</span>
                                    </div>
                                    <div class="bg-white/[0.01] border border-white/5 rounded-xl p-2 flex flex-col items-center">
                                        <svg class="w-4 h-4 text-purple-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                                        <span class="text-xs font-bold text-white font-mono">8</span>
                                        <span class="text-[7px] font-bold text-slate-500 uppercase tracking-wider mt-0.5 leading-none">Stores</span>
                                    </div>
                                </div>
                                <div class="space-y-3 font-sans">
                                    <div class="flex justify-between items-center text-[10px] font-bold text-slate-500">
                                        <span>SCAN COVERAGE</span>
                                        <span class="text-orange-400 font-mono">100%</span>
                                    </div>
                                    <div class="w-full bg-slate-950 h-1.5 rounded-full overflow-hidden border border-white/5">
                                        <div class="h-full bg-orange-400 rounded-full" style="width: 100%;"></div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 pt-2 text-[10px] text-slate-400 font-bold">
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Infrastructure <span class="ml-auto font-mono text-white text-[9px]">100%</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Applications <span class="ml-auto font-mono text-white text-[9px]">100%</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Network <span class="ml-auto font-mono text-white text-[9px]">100%</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Security <span class="ml-auto font-mono text-white text-[9px]">100%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ASSET TOPOLOGY OVERVIEW -->
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between relative group shadow-lg shadow-black/40">
                            <div class="absolute top-4 right-4 z-20 flex items-center gap-1.5 bg-[#CBB48A]/5 border border-[#CBB48A]/20 px-2 py-0.5 rounded-full">
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#CBB48A] opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-[#CBB48A]"></span>
                                </span>
                                <span class="text-[8px] font-black text-[#CBB48A] tracking-wider uppercase font-mono">Live View</span>
                            </div>
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5 font-mono">
                                    ASSET TOPOLOGY OVERVIEW
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 1 1 1.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                </h4>
                            </div>
                            <div class="relative w-full h-[120px] bg-black/20 rounded-xl border border-white/5 overflow-hidden">
                                <TopologyGraph :data="topologyData" :hide-hud="true" />
                            </div>
                            <button @click="activeTab = 'topology'" class="w-full mt-3 py-2 border border-white/5 hover:border-white/10 bg-white/[0.01] hover:bg-white/[0.04] rounded-xl text-[10px] font-black text-slate-400 hover:text-white transition-all uppercase tracking-wider cursor-pointer flex items-center justify-center gap-1.5 border-dashed">
                                View Full Topology
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- EXECUTIVE SUMMARY PANEL -->
                    <div class="w-full p-6 bg-[#070709] border border-white/5 rounded-2xl flex flex-col lg:flex-row items-center justify-between gap-6 relative overflow-hidden shadow-lg shadow-black/40">
                        <div class="absolute inset-y-0 right-0 w-80 bg-gradient-to-l from-red-500/5 to-transparent blur-3xl rounded-full pointer-events-none"></div>
                        
                        <div class="flex-1 space-y-4">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3Z"/></svg>
                                <h4 class="text-xs font-black text-white uppercase tracking-widest font-mono">EXECUTIVE SUMMARY</h4>
                            </div>
                            <p class="text-xs text-slate-450 font-semibold leading-relaxed max-w-2xl font-sans">
                                Infosoft appears to be a digital agency managing a highly unstable technical stack. The infrastructure shows multiple high-risk misconfigurations, outdated components, and exposed sensitive endpoints that could lead to full system compromise.
                            </p>
                            <button @click="activeTab = 'forensic'" class="text-[10px] font-black text-[#CBB48A] hover:underline uppercase tracking-wider flex items-center gap-1 cursor-pointer font-mono">
                                View full analysis
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-4 w-full lg:w-auto shrink-0 font-mono text-[10px]">
                            <div class="flex items-center gap-2.5 bg-black/40 border border-white/5 rounded-xl px-4 py-2.5 min-w-[200px]">
                                <div class="w-7 h-7 rounded-lg bg-rose-500/10 border border-rose-500/30 flex items-center justify-center text-rose-500 shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3Z"/></svg></div>
                                <div class="flex flex-col font-sans">
                                    <span class="text-white font-bold uppercase text-[9px]">HIGH RISK EXPOSURE</span>
                                    <span class="text-slate-500 text-[8px]">Critical issues identified</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2.5 bg-black/40 border border-white/5 rounded-xl px-4 py-2.5 min-w-[200px]">
                                <div class="w-7 h-7 rounded-lg bg-orange-500/10 border border-orange-500/30 flex items-center justify-center text-orange-500 shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></div>
                                <div class="flex flex-col font-sans">
                                    <span class="text-white font-bold uppercase text-[9px]">ATTACK SURFACE</span>
                                    <span class="text-slate-500 text-[8px]">Wide attack surface detected</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2.5 bg-black/40 border border-white/5 rounded-xl px-4 py-2.5 min-w-[200px]">
                                <div class="w-7 h-7 rounded-lg bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-purple-400 shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg></div>
                                <div class="flex flex-col font-sans">
                                    <span class="text-white font-bold uppercase text-[9px]">DATA EXPOSURE</span>
                                    <span class="text-slate-500 text-[8px]">Potential sensitive data at risk</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2.5 bg-black/40 border border-white/5 rounded-xl px-4 py-2.5 min-w-[200px]">
                                <div class="w-7 h-7 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></div>
                                <div class="flex flex-col font-sans">
                                    <span class="text-white font-bold uppercase text-[9px]">REMEDIATION NEEDED</span>
                                    <span class="text-slate-500 text-[8px]">Immediate actions recommended</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Top Risks, Technology Stack, External Footprint, Activity Feed -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        
                        <!-- TOP RISKS -->
                        <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                            <div>
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-1 font-mono">
                                        TOP RISKS
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 1 1 1.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    </h4>
                                    <button @click="activeTab = 'forensic'" class="text-[9px] font-black text-[#CBB48A] hover:underline uppercase tracking-wider cursor-pointer font-mono">View All Findings</button>
                                </div>
                                <div class="space-y-3 font-sans text-xs">
                                    <div v-for="(finding, i) in keyFindings" :key="i" class="flex items-center gap-2 pb-2.5 border-b border-white/5 last:border-0 last:pb-0">
                                        <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="finding.severity === 'critical' ? 'bg-red-500' : 'bg-orange-400'"></span>
                                        <span class="text-slate-300 font-semibold truncate flex-1">{{ finding.title }}</span>
                                        <span class="text-[8px] font-black uppercase tracking-wider px-1.5 py-0.2 rounded font-mono shrink-0 border" :class="finding.severity === 'critical' ? 'border-red-500/30 text-red-400 bg-red-500/5' : 'border-orange-500/30 text-orange-400 bg-orange-500/5'">{{ finding.severity }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TECHNOLOGY STACK -->
                        <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                            <div>
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-1 font-mono">
                                        TECHNOLOGY STACK
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 1 1 1.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    </h4>
                                    <button @click="activeTab = 'technologies'" class="text-[9px] font-black text-[#CBB48A] hover:underline uppercase tracking-wider cursor-pointer font-mono">View All</button>
                                </div>
                                <div class="space-y-3 font-mono text-xs">
                                    <div v-for="tech in techStack.slice(0, 5)" :key="tech.name" class="flex justify-between items-center py-0.5 border-b border-white/5 last:border-0">
                                        <div class="flex items-center gap-2">
                                            <div class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: tech.dot_color }"></div>
                                            <span class="text-slate-300 font-bold text-[11px]">{{ tech.name }}</span>
                                        </div>
                                        <span class="text-[8px] font-black tracking-wider px-1.5 py-0.5 rounded border border-white/10 text-slate-400 bg-white/[0.01] uppercase">{{ getTechCategory(tech.name) }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-bold tracking-wide text-center pt-2">
                                        + 19 more technologies
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- EXTERNAL FOOTPRINT -->
                        <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                            <div>
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-1 font-mono">
                                        EXTERNAL FOOTPRINT
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 1 1 1.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    </h4>
                                    <button @click="activeTab = 'evidence'" class="text-[9px] font-black text-[#CBB48A] hover:underline uppercase tracking-wider cursor-pointer font-mono">View Details</button>
                                </div>
                                <div class="space-y-3 font-mono text-xs">
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">Subdomains</span>
                                        <span class="text-white font-bold">11</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">Open Ports</span>
                                        <span class="text-white font-bold">6</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">IP Addresses</span>
                                        <span class="text-white font-bold">4</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">SSL/TLS</span>
                                        <span class="text-emerald-400 font-bold flex items-center gap-1.5 text-[10px]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Valid
                                        </span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">Hosting Provider</span>
                                        <span class="text-white font-bold text-[10px] truncate max-w-[100px] text-right" title="Cloudflare, Inc.">Cloudflare, Inc.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ACTIVITY FEED -->
                        <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                            <div>
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-1 font-mono">
                                        ACTIVITY FEED
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 1 1 1.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    </h4>
                                    <button @click="activeTab = 'history'" class="text-[9px] font-black text-[#CBB48A] hover:underline uppercase tracking-wider cursor-pointer font-mono">View All</button>
                                </div>
                                <div class="space-y-3 font-sans text-xs">
                                    <div class="flex items-start gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1.5"></span>
                                        <div class="flex flex-col">
                                            <span class="text-slate-300 font-semibold leading-tight">Scan completed</span>
                                            <span class="text-[9px] text-slate-500 font-mono mt-0.5">2m ago</span>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mt-1.5"></span>
                                        <div class="flex flex-col">
                                            <span class="text-slate-300 font-semibold leading-tight">Evidence collected</span>
                                            <span class="text-[9px] text-slate-500 font-mono mt-0.5">3m ago</span>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-purple-500 mt-1.5"></span>
                                        <div class="flex flex-col">
                                            <span class="text-slate-300 font-semibold leading-tight">Topology mapped</span>
                                            <span class="text-[9px] text-slate-500 font-mono mt-0.5">3m ago</span>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-yellow-400 mt-1.5"></span>
                                        <div class="flex flex-col">
                                            <span class="text-slate-300 font-semibold leading-tight">Technologies detected</span>
                                            <span class="text-[9px] text-slate-500 font-mono mt-0.5">4m ago</span>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1.5"></span>
                                        <div class="flex flex-col">
                                            <span class="text-slate-300 font-semibold leading-tight">Network analysis complete</span>
                                            <span class="text-[9px] text-slate-500 font-mono mt-0.5">4m ago</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- TAB: FORENSIC ANALYSIS -->
                <div v-if="activeTab === 'forensic'" class="space-y-6">
                    <!-- Header Details -->
                    <div class="flex-shrink-0">
                        <h4 class="text-sm font-black text-white uppercase tracking-widest font-mono">FORENSIC ANALYSIS DETAIL</h4>
                        <p class="text-xs text-slate-500 font-medium font-sans mt-0.5">In-depth analysis of the asset's architecture, configuration and operational posture.</p>
                    </div>
                    
                    <!-- Row 1: Narrative vs Business Context -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 p-6 bg-[#070709] rounded-2xl border border-white/5 flex gap-4 items-start shadow-lg shadow-black/40">
                            <div class="w-12 h-12 rounded-full bg-[#CBB48A]/10 border border-[#CBB48A]/30 flex items-center justify-center text-[#CBB48A] shrink-0">
                                <svg class="w-6 h-6 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 21l8.982-11.795M10.896 11H18l-9 9.25" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m0-12.728l.707.707m11.314 11.314l.707-.707" />
                                </svg>
                            </div>
                            <div class="flex flex-col space-y-1">
                                <h5 class="text-xs font-black text-[#CBB48A] uppercase tracking-wider font-mono">Architecture Narrative</h5>
                                <p class="text-xs text-slate-300 leading-relaxed font-medium font-sans">
                                    The application exhibits severe 'Framework Bloat' and architectural incoherence, simultaneously attempting to leverage Laravel, WordPress, and Django. This suggests a fragmented legacy environment or a 'Frankenstein' deployment where multiple disparate systems are being proxied under a single domain. The presence of both Vue.js and React further indicates a lack of a unified frontend strategy, likely resulting in significant technical debt and maintenance overhead.
                                </p>
                            </div>
                        </div>
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-start space-y-2 shadow-lg shadow-black/40">
                            <h5 class="text-xs font-black text-indigo-400 uppercase tracking-wider font-mono">Business Context</h5>
                            <p class="text-xs text-slate-305 leading-relaxed font-medium font-sans">
                                Infosoft appears to be a digital agency managing a highly unstable technical stack. The current infrastructure is not suitable for enterprise-grade scaling due to conflicting backend technologies.
                            </p>
                        </div>
                    </div>

                    <!-- Row 2: Key Findings vs Radar & Supply Chain -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        
                        <!-- KEY FINDINGS -->
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                            <div>
                                <div class="flex justify-between items-center mb-4">
                                    <h5 class="text-xs font-black text-white uppercase tracking-widest font-mono">Key Findings <span class="ml-1 text-[10px] text-slate-500 font-bold bg-white/5 px-2 py-0.5 rounded font-sans">37 TOTAL</span></h5>
                                </div>
                                <div class="space-y-3 font-sans text-xs">
                                    <div v-for="(finding, i) in keyFindings" :key="i" class="flex gap-4 items-center p-3 bg-white/[0.01] border border-white/5 rounded-xl hover:bg-white/[0.03] transition-all cursor-pointer">
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 border border-white/5 bg-slate-950">
                                            <svg v-if="finding.severity === 'critical'" class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3Z"/></svg>
                                            <svg v-else-if="finding.severity === 'high'" class="w-4 h-4 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                                            <svg v-else class="w-4 h-4 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 111.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex justify-between items-center">
                                                <span class="text-white font-bold truncate">{{ finding.title }}</span>
                                                <span class="text-[8px] font-black uppercase font-mono tracking-wider text-slate-500 border border-white/5 px-1.5 py-0.5 rounded bg-white/[0.01]">{{ finding.impact }}</span>
                                            </div>
                                            <p class="text-[10px] text-slate-400 font-medium truncate mt-0.5 leading-relaxed">{{ finding.desc }}</p>
                                        </div>
                                        <svg class="w-4 h-4 text-slate-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                    </div>
                                </div>
                            </div>
                            <button @click="showAIDetailsModal = true" class="w-full mt-4 py-2 bg-white/[0.01] hover:bg-white/[0.04] border border-white/5 rounded-xl text-[10px] font-black text-slate-400 hover:text-white transition-all uppercase tracking-wider flex items-center justify-center gap-1.5 border-dashed">
                                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                View All Findings (37)
                            </button>
                        </div>

                        <!-- IMPACT & RISK SUMMARY (Radar) -->
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 grid grid-cols-1 md:grid-cols-12 gap-6 relative shadow-lg shadow-black/40">
                            <div class="md:col-span-8 flex flex-col justify-between">
                                <h5 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 font-mono">IMPACT & RISK SUMMARY</h5>
                                <div class="relative w-full aspect-square max-h-[220px] flex items-center justify-center mx-auto">
                                    <Radar :data="chartData" :options="chartOptions" />
                                </div>
                            </div>
                            <div class="md:col-span-4 flex flex-col justify-center space-y-5 divide-y divide-white/5">
                                <div class="flex flex-col space-y-1">
                                    <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest leading-none font-mono">Overall Impact</span>
                                    <span class="text-lg font-black text-rose-500 uppercase leading-none tracking-tight">High</span>
                                    <p class="text-[9px] text-slate-400 leading-relaxed font-semibold font-sans mt-1">
                                        Multiple high-risk issues impacting integrity, confidentiality and availability.
                                    </p>
                                </div>
                                <div class="flex flex-col space-y-1 pt-4">
                                    <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest leading-none font-mono">Supply Chain Score</span>
                                    <div class="flex items-baseline gap-1 mt-1">
                                        <span class="text-2xl font-black text-rose-500 font-mono">20</span>
                                        <span class="text-slate-500 text-xs font-bold font-mono">/100</span>
                                    </div>
                                    <p class="text-[9px] text-slate-400 leading-relaxed font-semibold font-sans mt-1">
                                        Low integrity across third-party dependencies.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: Network Signals vs Compliance & Privacy -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            
                            <!-- NETWORK SIGNALS -->
                            <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 grid grid-cols-1 md:grid-cols-12 gap-6 items-center shadow-lg shadow-black/40">
                                <div class="md:col-span-7 space-y-4">
                                    <h5 class="text-xs font-black text-slate-400 uppercase tracking-widest font-mono">NETWORK SIGNALS</h5>
                                    <div class="space-y-3 font-mono text-xs">
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500 font-bold">Protocol</span>
                                            <span class="text-white font-bold">HTTPS/TLS 1.3</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500 font-bold">DNS Authority</span>
                                            <span class="text-white font-bold">Cloudflare</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500 font-bold">Server Signature</span>
                                            <span class="text-white font-bold">nginx</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500 font-bold">CDN / WAF</span>
                                            <span class="text-white font-bold">Cloudflare</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500 font-bold">IP Reputation</span>
                                            <span class="text-emerald-400 font-bold flex items-center gap-1.5">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                Clean
                                            </span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500 font-bold">Geo Location</span>
                                            <span class="text-white font-bold flex items-center gap-1">
                                                Singapore (SG)
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="md:col-span-5 relative h-36 bg-black/25 rounded-xl border border-white/5 overflow-hidden flex items-center justify-center">
                                    <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#CBB48A_1px,transparent_1px)] [background-size:16px_16px]"></div>
                                    <div class="relative flex flex-col items-center">
                                        <svg class="w-16 h-16 text-rose-500 opacity-60 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503-3.461C14.002 12.07 12 12 12 12c-3.196 0-6.1-1.248-8.25-3.285M12 21c-5.176-1.332-9-6.03-9-11.622c0-1.31.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.248-8.25-3.285" />
                                        </svg>
                                        <span class="text-[8px] text-slate-500 font-bold font-mono tracking-widest uppercase mt-2">Geo Location: SG</span>
                                    </div>
                                </div>
                            </div>

                            <!-- COMPLIANCE & PRIVACY -->
                            <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 grid grid-cols-1 md:grid-cols-12 gap-6 items-center shadow-lg shadow-black/40">
                                <div class="md:col-span-7 space-y-4">
                                    <h5 class="text-xs font-black text-slate-400 uppercase tracking-widest font-mono">COMPLIANCE & PRIVACY</h5>
                                    <div class="space-y-3 font-mono text-xs">
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500 font-bold">GDPR Compliance</span>
                                            <span class="text-rose-500 font-bold uppercase tracking-wider text-[10px]">Liability Risk</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500 font-bold">License</span>
                                            <span class="text-white font-bold">UNKNOWN</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500 font-bold">Data Handling</span>
                                            <span class="text-white font-bold">Not Verified</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500 font-bold">Privacy Policy</span>
                                            <span class="text-rose-500 font-bold">Not Found</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500 font-bold">Supply Chain Score</span>
                                            <span class="text-rose-400 font-bold">20/100</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="md:col-span-5 relative h-36 bg-black/25 rounded-xl border border-white/5 overflow-hidden flex items-center justify-center">
                                    <div class="absolute inset-0 opacity-15 border border-dashed border-[#CBB48A]/20 rounded-full scale-75 animate-spin [animation-duration:15s]"></div>
                                    <div class="absolute inset-0 opacity-25 border border-[#CBB48A]/10 rounded-full scale-50"></div>
                                    <div class="relative flex flex-col items-center">
                                        <svg class="w-10 h-10 text-orange-400/80 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- TAB: SITE TOPOLOGY -->
                    <div v-if="activeTab === 'topology'" class="grid grid-cols-1 lg:grid-cols-12 gap-6 animate-fade-in">
                        
                        <!-- Left Panel: Node Counts & Health -->
                        <div class="lg:col-span-2 space-y-6">
                            <div class="p-4 bg-[#070709] rounded-2xl border border-white/5 space-y-4 shadow-lg shadow-black/40">
                                <h5 class="text-[10px] font-black text-slate-500 uppercase tracking-widest font-mono">NODE DISTRIBUTION</h5>
                                <div class="space-y-3 font-mono text-xs">
                                    <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                        <span class="text-slate-400">Web App</span>
                                        <span class="text-rose-500 font-bold">1</span>
                                    </div>
                                    <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                        <span class="text-slate-400">Subdomain</span>
                                        <span class="text-emerald-400 font-bold">4</span>
                                    </div>
                                    <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                        <span class="text-slate-400">External Service</span>
                                        <span class="text-yellow-400 font-bold">3</span>
                                    </div>
                                    <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                        <span class="text-slate-400">Database</span>
                                        <span class="text-blue-400 font-bold">2</span>
                                    </div>
                                    <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                        <span class="text-slate-400">CDN / Network</span>
                                        <span class="text-purple-400 font-bold">2</span>
                                    </div>
                                    <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                        <span class="text-slate-400">File / Storage</span>
                                        <span class="text-cyan-400 font-bold">2</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-4 bg-[#070709] rounded-2xl border border-white/5 space-y-2 shadow-lg shadow-black/40">
                                <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest font-mono">NETWORK HEALTH</span>
                                <div class="flex justify-between items-center text-xs font-sans">
                                    <span class="text-emerald-400 font-bold flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                                        Healthy
                                    </span>
                                    <span class="text-slate-500 font-mono text-[10px]">99.8%</span>
                                </div>
                                <div class="h-8">
                                    <MetricSparkline :history="[95, 96, 98, 97, 99, 99.8]" />
                                </div>
                            </div>
                        </div>

                        <!-- Center Panel: interactive graph -->
                        <div class="lg:col-span-7 p-6 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between min-h-[420px] relative shadow-lg shadow-black/40">
                            <div class="absolute top-4 left-4 z-20 flex items-center gap-2">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                </span>
                                <span class="text-[9px] font-black text-emerald-400 uppercase tracking-widest font-mono">LIVE MONITORING ACTIVE</span>
                            </div>
                            
                            <div class="flex-1 w-full relative min-h-[300px]">
                                <TopologyGraph :data="topologyData" @node-click="handleTopologyNodeClick" />
                            </div>

                            <!-- Legend -->
                            <div class="flex flex-wrap items-center justify-center gap-4 text-[9px] font-mono text-slate-500 border-t border-white/5 pt-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3.5 h-0.5 bg-rose-500 inline-block"></span>
                                    <span>Primary Connection</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3.5 h-0.5 bg-white/20 inline-block"></span>
                                    <span>Secondary Connection</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3.5 h-0.5 border-t border-dashed border-white/40 inline-block"></span>
                                    <span>Data Flow</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-400 inline-block animate-pulse"></span>
                                    <span>Live Traffic</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Panel: Selected node details -->
                        <div class="lg:col-span-3">
                            <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between h-full space-y-4 shadow-lg shadow-black/40">
                                <div>
                                    <span class="text-[9px] font-black text-[#CBB48A] uppercase tracking-widest font-mono">SELECTED ASSET</span>
                                    <div class="flex gap-3 items-center mt-3 mb-4">
                                        <div class="w-10 h-10 rounded-xl bg-white/[0.01] border border-rose-500/20 flex items-center justify-center text-rose-500 shrink-0">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        </div>
                                        <div class="flex flex-col min-w-0">
                                            <h4 class="text-xs font-black text-white truncate max-w-[160px]" :title="activeTopologyNode.id">{{ activeTopologyNode.id }}</h4>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                <span class="text-[9px] font-bold text-slate-500 uppercase tracking-wide font-sans">{{ activeTopologyNode.type }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-2.5 font-mono text-[10px]">
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500">IP Address</span>
                                            <span class="text-white">{{ activeTopologyNode.ip }}</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500">Status</span>
                                            <span class="text-emerald-400 font-bold">{{ activeTopologyNode.status }}</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500">Protocol</span>
                                            <span class="text-white">{{ activeTopologyNode.protocol }}</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500">Server</span>
                                            <span class="text-[#CBB48A]">{{ activeTopologyNode.server }}</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500">Location</span>
                                            <span class="text-white">{{ activeTopologyNode.location }}</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-white/5">
                                            <span class="text-slate-500">Last Seen</span>
                                            <span class="text-slate-400">{{ activeTopologyNode.lastSeen }}</span>
                                        </div>
                                    </div>

                                    <div class="mt-4 space-y-2">
                                        <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest font-mono">OPEN PORTS</span>
                                        <div class="space-y-1.5 font-mono text-[9px] pt-1">
                                            <div v-for="port in activeTopologyNode.ports" :key="port.port" class="flex items-center gap-2">
                                                <span class="w-1.5 h-1.5 rounded-full" :class="port.active ? 'bg-emerald-500' : 'bg-slate-700'"></span>
                                                <span class="text-white font-bold">{{ port.port }}</span>
                                                <span class="text-slate-500 uppercase">{{ port.protocol }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button @click="showTopologyDetailsModal = true" class="w-full py-2 bg-white/[0.01] hover:bg-white/[0.04] border border-white/5 rounded-xl text-[9px] font-black text-slate-400 hover:text-white uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 font-mono">
                                    View Asset Details
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- TAB: TECHNOLOGIES -->
                    <div v-if="activeTab === 'technologies'" class="grid grid-cols-1 lg:grid-cols-12 gap-6 animate-fade-in">
                        
                        <!-- Left Panel: Categories -->
                        <div class="lg:col-span-3 space-y-4">
                            <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 space-y-4 shadow-lg shadow-black/40">
                                <h5 class="text-[10px] font-black text-slate-500 uppercase tracking-widest font-mono">TECHNOLOGY CATEGORIES</h5>
                                <div class="space-y-3 font-mono text-xs">
                                    <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                        <span class="text-slate-400">Backend</span>
                                        <span class="text-white font-bold bg-white/5 px-2 py-0.5 rounded text-[10px]">3</span>
                                    </div>
                                    <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                        <span class="text-slate-400">Frontend</span>
                                        <span class="text-white font-bold bg-white/5 px-2 py-0.5 rounded text-[10px]">4</span>
                                    </div>
                                    <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                        <span class="text-slate-400">Infrastructure</span>
                                        <span class="text-white font-bold bg-white/5 px-2 py-0.5 rounded text-[10px]">3</span>
                                    </div>
                                    <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                        <span class="text-slate-400">Database</span>
                                        <span class="text-white font-bold bg-white/5 px-2 py-0.5 rounded text-[10px]">2</span>
                                    </div>
                                    <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                        <span class="text-slate-400">CDN / Network</span>
                                        <span class="text-white font-bold bg-white/5 px-2 py-0.5 rounded text-[10px]">2</span>
                                    </div>
                                    <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                        <span class="text-slate-400">Security</span>
                                        <span class="text-white font-bold bg-white/5 px-2 py-0.5 rounded text-[10px]">2</span>
                                    </div>
                                    <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                        <span class="text-slate-400">Analytics</span>
                                        <span class="text-white font-bold bg-white/5 px-2 py-0.5 rounded text-[10px]">2</span>
                                    </div>
                                    <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                        <span class="text-slate-400">Third Party</span>
                                        <span class="text-white font-bold bg-white/5 px-2 py-0.5 rounded text-[10px]">6</span>
                                    </div>
                                </div>
                                <button class="w-full mt-2 py-2 border border-white/10 bg-white/[0.01] hover:bg-white/[0.04] rounded-xl text-[10px] font-bold text-slate-400 transition-all uppercase tracking-wider text-center cursor-pointer font-mono">
                                    View All Technologies (24)
                                </button>
                            </div>
                        </div>

                        <!-- Center Panel: Spoke Map -->
                        <div class="lg:col-span-6 p-6 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between min-h-[400px] relative shadow-lg shadow-black/40">
                            <div class="flex justify-between items-center mb-3">
                                <h5 class="text-xs font-black text-slate-400 uppercase tracking-widest font-mono">TECHNOLOGY RELATIONSHIP MAP</h5>
                                <div class="flex items-center gap-2 bg-black/40 border border-white/5 rounded-lg p-0.5">
                                    <button class="px-2.5 py-1 text-[9px] font-black uppercase tracking-wider bg-[#CBB48A] text-slate-900 rounded font-mono">Graph View</button>
                                    <button class="px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-slate-400 hover:text-white rounded font-mono">List View</button>
                                </div>
                            </div>

                            <div class="flex-1 flex items-center justify-center relative min-h-[300px]">
                                <!-- Custom SVG Circular spoke model matching image 5 -->
                                <svg class="w-full h-full min-h-[300px]" viewBox="0 0 600 350">
                                    <defs>
                                        <radialGradient id="glow" cx="50%" cy="50%" r="50%">
                                            <stop offset="0%" stop-color="#CBB48A" stop-opacity="0.1" />
                                            <stop offset="100%" stop-color="#CBB48A" stop-opacity="0" />
                                        </radialGradient>
                                    </defs>
                                    <circle cx="300" cy="175" r="130" fill="url(#glow)" />
                                    
                                    <!-- Spoke Lines -->
                                    <g stroke="rgba(255, 255, 255, 0.06)" stroke-width="1.5">
                                        <line x1="300" y1="175" x2="160" y2="80" />
                                        <line x1="300" y1="175" x2="300" y2="50" style="stroke-dasharray: 4,4;" />
                                        <line x1="300" y1="175" x2="440" y2="80" />
                                        <line x1="300" y1="175" x2="470" y2="175" />
                                        <line x1="300" y1="175" x2="440" y2="270" />
                                        <line x1="300" y1="175" x2="300" y2="300" />
                                        <line x1="300" y1="175" x2="160" y2="270" />
                                        <line x1="300" y1="175" x2="130" y2="175" />
                                    </g>

                                    <!-- Central Node -->
                                    <g transform="translate(300, 175)">
                                        <circle r="26" fill="#050507" stroke="#CBB48A" stroke-width="1.5" />
                                        <path d="M-8,0 A8,8 0 0,0 8,0 A8,8 0 0,0 -8,0 M0,-8 L0,8 M-8,0 L8,0" fill="none" stroke="#CBB48A" stroke-width="1" />
                                        <text y="38" text-anchor="middle" fill="#94a3b8" font-size="8" font-family="monospace" font-weight="bold">infosoft.poolreno.com</text>
                                    </g>

                                    <!-- Spoke Nodes -->
                                    <!-- Node 1: Laravel -->
                                    <g transform="translate(160, 80)" class="cursor-pointer" @mouseenter="hoveredTech = 'Laravel'" @mouseleave="hoveredTech = null">
                                        <circle r="18" fill="#050507" stroke="#ef4444" stroke-width="1.5" />
                                        <text text-anchor="middle" y="3" fill="#ef4444" font-size="8" font-family="monospace" font-weight="bold">Laravel</text>
                                        <text y="-25" text-anchor="middle" fill="#64748b" font-size="7" font-family="monospace">Backend</text>
                                    </g>
                                    <!-- Node 2: Cloudflare -->
                                    <g transform="translate(300, 50)" class="cursor-pointer" @mouseenter="hoveredTech = 'Cloudflare'" @mouseleave="hoveredTech = null">
                                        <circle r="18" fill="#050507" stroke="#f97316" stroke-width="1.5" />
                                        <text text-anchor="middle" y="3" fill="#f97316" font-size="8" font-family="monospace" font-weight="bold">CF</text>
                                        <text y="-25" text-anchor="middle" fill="#64748b" font-size="7" font-family="monospace">CDN / Security</text>
                                    </g>
                                    <!-- Node 3: Vue.js -->
                                    <g transform="translate(440, 80)" class="cursor-pointer" @mouseenter="hoveredTech = 'Vue'" @mouseleave="hoveredTech = null">
                                        <circle r="18" fill="#050507" stroke="#10b981" stroke-width="1.5" />
                                        <text text-anchor="middle" y="3" fill="#10b981" font-size="8" font-family="monospace" font-weight="bold">Vue</text>
                                        <text y="-25" text-anchor="middle" fill="#64748b" font-size="7" font-family="monospace">Frontend</text>
                                    </g>
                                    <!-- Node 4: Tailwind CSS -->
                                    <g transform="translate(470, 175)" class="cursor-pointer" @mouseenter="hoveredTech = 'Tailwind'" @mouseleave="hoveredTech = null">
                                        <circle r="18" fill="#050507" stroke="#06b6d4" stroke-width="1.5" />
                                        <text text-anchor="middle" y="3" fill="#06b6d4" font-size="7" font-family="monospace" font-weight="bold">CSS</text>
                                        <text y="30" text-anchor="middle" fill="#64748b" font-size="7" font-family="monospace">CSS Framework</text>
                                    </g>
                                    <!-- Node 5: Google Analytics -->
                                    <g transform="translate(440, 270)" class="cursor-pointer" @mouseenter="hoveredTech = 'Analytics'" @mouseleave="hoveredTech = null">
                                        <circle r="18" fill="#050507" stroke="#f59e0b" stroke-width="1.5" />
                                        <text text-anchor="middle" y="3" fill="#f59e0b" font-size="7" font-family="monospace" font-weight="bold">Analytics</text>
                                        <text y="30" text-anchor="middle" fill="#64748b" font-size="7" font-family="monospace">Analytics</text>
                                    </g>
                                    <!-- Node 6: Nginx -->
                                    <g transform="translate(300, 300)" class="cursor-pointer" @mouseenter="hoveredTech = 'Nginx'" @mouseleave="hoveredTech = null">
                                        <circle r="18" fill="#050507" stroke="#10b981" stroke-width="1.5" />
                                        <text text-anchor="middle" y="3" fill="#10b981" font-size="8" font-family="monospace" font-weight="bold">Nginx</text>
                                        <text y="30" text-anchor="middle" fill="#64748b" font-size="7" font-family="monospace">Web Server</text>
                                    </g>
                                    <!-- Node 7: MySQL -->
                                    <g transform="translate(160, 270)" class="cursor-pointer" @mouseenter="hoveredTech = 'MySQL'" @mouseleave="hoveredTech = null">
                                        <circle r="18" fill="#050507" stroke="#3b82f6" stroke-width="1.5" />
                                        <text text-anchor="middle" y="3" fill="#3b82f6" font-size="8" font-family="monospace" font-weight="bold">MySQL</text>
                                        <text y="30" text-anchor="middle" fill="#64748b" font-size="7" font-family="monospace">Database</text>
                                    </g>
                                    <!-- Node 8: PHP -->
                                    <g transform="translate(130, 175)" class="cursor-pointer" @mouseenter="hoveredTech = 'PHP'" @mouseleave="hoveredTech = null">
                                        <circle r="18" fill="#050507" stroke="#8b5cf6" stroke-width="1.5" />
                                        <text text-anchor="middle" y="3" fill="#8b5cf6" font-size="8" font-family="monospace" font-weight="bold">PHP</text>
                                        <text y="30" text-anchor="middle" fill="#64748b" font-size="7" font-family="monospace">Language</text>
                                    </g>
                                </svg>
                            </div>

                            <!-- Legend -->
                            <div class="flex items-center justify-center gap-4 text-[9px] font-mono text-slate-500 border-t border-white/5 pt-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3.5 h-0.5 bg-[#CBB48A] inline-block"></span>
                                    <span>Primary Technology</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3.5 h-0.5 border-t border-dashed border-white/40 inline-block"></span>
                                    <span>Supporting Technology</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3.5 h-0.5 border-t border-dotted border-white/40 inline-block"></span>
                                    <span>Data Flow</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Panel: Summary Stats -->
                        <div class="lg:col-span-3 space-y-6">
                            <!-- Tech Stack Summary -->
                            <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 space-y-4 shadow-lg shadow-black/40">
                                <h5 class="text-[9px] font-black text-slate-500 uppercase tracking-widest font-mono">TECH STACK SUMMARY</h5>
                                <div class="flex items-baseline gap-1 mt-1 font-sans">
                                    <span class="text-2xl font-black text-white font-mono">24</span>
                                    <span class="text-slate-505 text-[10px] font-bold tracking-wider ml-1 uppercase">Technologies Detected</span>
                                </div>
                                <div class="space-y-2.5 font-mono text-[10px] pt-1">
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-400 flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                            Custom / Proprietary
                                        </span>
                                        <span class="text-white font-bold">6</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-400 flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Open Source
                                        </span>
                                        <span class="text-white font-bold">13</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-400 flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            Third Party Services
                                        </span>
                                        <span class="text-white font-bold">5</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Technology Maturity -->
                            <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 space-y-4 shadow-lg shadow-black/40">
                                <h5 class="text-[9px] font-black text-slate-500 uppercase tracking-widest font-mono">TECHNOLOGY MATURITY</h5>
                                <div class="flex items-center gap-6">
                                    <!-- Radial gauge -->
                                    <div class="relative w-16 h-16 flex items-center justify-center shrink-0">
                                        <svg class="absolute inset-0 w-full h-full" viewBox="0 0 36 36">
                                            <circle cx="18" cy="18" r="16" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="2.5"></circle>
                                            <circle cx="18" cy="18" r="16" fill="none" stroke="#10b981" stroke-width="2.5" stroke-dasharray="78, 100" stroke-linecap="round"></circle>
                                        </svg>
                                        <span class="text-[10px] font-black font-mono text-white">78%</span>
                                    </div>
                                    <div class="space-y-1 text-[9px] font-mono">
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Modern <span class="text-white font-bold ml-1">78%</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span>
                                            Legacy <span class="text-white font-bold ml-1">14%</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            Outdated <span class="text-white font-bold ml-1">8%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stack Health Score -->
                            <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 space-y-3.5 shadow-lg shadow-black/40">
                                <h5 class="text-[9px] font-black text-slate-500 uppercase tracking-widest font-mono">STACK HEALTH SCORE</h5>
                                <div class="flex justify-between items-baseline font-mono">
                                    <span class="text-white font-black text-lg">84<span class="text-slate-505 text-xs font-bold">/100</span></span>
                                    <span class="text-emerald-400 text-[10px] font-bold uppercase tracking-wider font-mono">Healthy</span>
                                </div>
                                <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden border border-white/5">
                                    <div class="h-full bg-emerald-500 rounded-full" style="width: 84%;"></div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- TAB: EVIDENCE -->
                    <div v-if="activeTab === 'evidence'" class="space-y-6 animate-fade-in">
                        <!-- Header Search & Filters -->
                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-white/5 pb-4">
                            <div>
                                <h4 class="text-sm font-black text-white uppercase tracking-widest font-mono">EVIDENCE OVERVIEW</h4>
                                <p class="text-xs text-slate-500 font-medium font-sans mt-0.5">Raw forensic evidence collected during the scan. All times shown in UTC.</p>
                            </div>
                            
                            <!-- Search -->
                            <div class="flex items-center gap-3 w-full md:w-auto">
                                <div class="relative flex-1 md:w-64">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    </span>
                                    <input 
                                        type="text" 
                                        placeholder="Search evidence..." 
                                        v-model="evidenceSearchQuery"
                                        class="block w-full pl-9 pr-3 py-1.5 bg-black/40 border border-white/5 rounded-xl text-xs text-gray-300 placeholder-slate-500 focus:outline-none focus:border-[#CBB48A]/40 focus:ring-0 transition-all font-mono"
                                    />
                                </div>
                                <button class="p-2 bg-white/[0.01] hover:bg-white/[0.04] border border-white/5 rounded-xl text-slate-400 hover:text-white transition-all cursor-pointer">
                                    <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.477 8 1.4V9.6a3.5 3.5 0 01-1.025 2.475l-4 4A3.5 3.5 0 0112.5 17h-1a3.5 3.5 0 01-2.475-1.025l-4-4A3.5 3.5 0 014 9.6V4.4A19.5 19.5 0 0112 3z"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Filter Pill buttons -->
                        <div class="flex flex-wrap gap-2">
                            <button @click="activeEvidenceTab = 'all'" :class="[activeEvidenceTab === 'all' ? 'bg-[#CBB48A] text-slate-900 font-black' : 'bg-white/5 text-slate-400 hover:text-white', 'px-3 py-1.5 rounded-lg text-[9px] font-bold tracking-widest uppercase transition-all font-mono']">All Evidence <span class="ml-1 text-[8px] opacity-70">64</span></button>
                            <button @click="activeEvidenceTab = 'network'" :class="[activeEvidenceTab === 'network' ? 'bg-[#CBB48A] text-slate-900 font-black' : 'bg-white/5 text-slate-400 hover:text-white', 'px-3 py-1.5 rounded-lg text-[9px] font-bold tracking-widest uppercase transition-all font-mono']">Network <span class="ml-1 text-[8px] opacity-70">14</span></button>
                            <button @click="activeEvidenceTab = 'http'" :class="[activeEvidenceTab === 'http' ? 'bg-[#CBB48A] text-slate-900 font-black' : 'bg-white/5 text-slate-400 hover:text-white', 'px-3 py-1.5 rounded-lg text-[9px] font-bold tracking-widest uppercase transition-all font-mono']">HTTP <span class="ml-1 text-[8px] opacity-70">18</span></button>
                            <button @click="activeEvidenceTab = 'code'" :class="[activeEvidenceTab === 'code' ? 'bg-[#CBB48A] text-slate-900 font-black' : 'bg-white/5 text-slate-400 hover:text-white', 'px-3 py-1.5 rounded-lg text-[9px] font-bold tracking-widest uppercase transition-all font-mono']">Code <span class="ml-1 text-[8px] opacity-70">12</span></button>
                            <button @click="activeEvidenceTab = 'config'" :class="[activeEvidenceTab === 'config' ? 'bg-[#CBB48A] text-slate-900 font-black' : 'bg-white/5 text-slate-400 hover:text-white', 'px-3 py-1.5 rounded-lg text-[9px] font-bold tracking-widest uppercase transition-all font-mono']">Configuration <span class="ml-1 text-[8px] opacity-70">10</span></button>
                            <button @click="activeEvidenceTab = 'file'" :class="[activeEvidenceTab === 'file' ? 'bg-[#CBB48A] text-slate-900 font-black' : 'bg-white/5 text-slate-400 hover:text-white', 'px-3 py-1.5 rounded-lg text-[9px] font-bold tracking-widest uppercase transition-all font-mono']">File & Data <span class="ml-1 text-[8px] opacity-70">6</span></button>
                            <button @click="activeEvidenceTab = 'other'" :class="[activeEvidenceTab === 'other' ? 'bg-[#CBB48A] text-slate-900 font-black' : 'bg-white/5 text-slate-400 hover:text-white', 'px-3 py-1.5 rounded-lg text-[9px] font-bold tracking-widest uppercase transition-all font-mono']">Other <span class="ml-1 text-[8px] opacity-70">4</span></button>
                        </div>

                        <!-- Row 1: HTTP, Network, Code/File Lists -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            
                            <!-- HTTP Evidence -->
                            <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between min-h-[220px] shadow-lg shadow-black/40">
                                <div class="space-y-4">
                                    <h5 class="text-xs font-black text-slate-400 uppercase tracking-widest font-mono flex items-center gap-2">
                                        <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                        HTTP Evidence (18)
                                    </h5>
                                    <div class="space-y-2.5 font-mono text-[10px]">
                                        <div class="flex justify-between items-center py-1 border-b border-white/5">
                                            <span class="text-slate-300 truncate max-w-[120px]">/admin/login.php</span>
                                            <span class="text-slate-505 font-bold">HTTP 200</span>
                                            <span class="text-blue-400 font-bold bg-blue-950/20 border border-blue-500/20 px-1.5 py-0.2 rounded uppercase text-[8px]">Web Response</span>
                                        </div>
                                        <div class="flex justify-between items-center py-1 border-b border-white/5">
                                            <span class="text-slate-300 truncate max-w-[120px]">/api/v1/contact</span>
                                            <span class="text-slate-505 font-bold">HTTP 200</span>
                                            <span class="text-blue-400 font-bold bg-blue-950/20 border border-blue-500/20 px-1.5 py-0.2 rounded uppercase text-[8px]">Web Response</span>
                                        </div>
                                        <div class="flex justify-between items-center py-1 border-b border-white/5">
                                            <span class="text-slate-300 truncate max-w-[120px]">/wp-json/wp/v2/</span>
                                            <span class="text-slate-505 font-bold">HTTP 200</span>
                                            <span class="text-blue-400 font-bold bg-blue-950/20 border border-blue-500/20 px-1.5 py-0.2 rounded uppercase text-[8px]">Web Response</span>
                                        </div>
                                        <div class="flex justify-between items-center py-1 border-b border-white/5">
                                            <span class="text-slate-300 truncate max-w-[120px]">/.env.example</span>
                                            <span class="text-slate-550 font-bold">HTTP 200</span>
                                            <span class="text-blue-400 font-bold bg-blue-950/20 border border-blue-500/20 px-1.5 py-0.2 rounded uppercase text-[8px]">Web Response</span>
                                        </div>
                                        <div class="flex justify-between items-center py-1 border-b border-white/5">
                                            <span class="text-slate-300 truncate max-w-[120px]">/server-status</span>
                                            <span class="text-red-400 font-bold">HTTP 403</span>
                                            <span class="text-blue-400 font-bold bg-blue-950/20 border border-blue-500/20 px-1.5 py-0.2 rounded uppercase text-[8px]">Web Response</span>
                                        </div>
                                    </div>
                                </div>
                                <button class="w-full mt-3 py-1.5 text-[9px] font-black text-slate-400 hover:text-white uppercase tracking-wider text-center cursor-pointer flex items-center justify-center gap-1 font-mono">
                                    View all 18 HTTP evidence
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                </button>
                            </div>

                            <!-- Network Evidence -->
                            <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between min-h-[220px] shadow-lg shadow-black/40">
                                <div class="space-y-4">
                                    <h5 class="text-xs font-black text-slate-400 uppercase tracking-widest font-mono flex items-center gap-2">
                                        <svg class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Network Evidence (14)
                                    </h5>
                                    <div class="space-y-2.5 font-mono text-[10px]">
                                        <div class="flex justify-between items-center py-1 border-b border-white/5">
                                            <span class="text-slate-300">DNS A Record</span>
                                            <span class="text-slate-500 font-bold truncate max-w-[80px]">103.21.244.0</span>
                                            <span class="text-yellow-500 font-bold bg-yellow-950/20 border border-yellow-500/20 px-1.5 py-0.2 rounded uppercase text-[8px]">DNS</span>
                                        </div>
                                        <div class="flex justify-between items-center py-1 border-b border-white/5">
                                            <span class="text-slate-300">DNS MX Record</span>
                                            <span class="text-slate-500 font-bold truncate max-w-[80px]">mail.infosoft.poolreno.com</span>
                                            <span class="text-yellow-500 font-bold bg-yellow-950/20 border border-yellow-500/20 px-1.5 py-0.2 rounded uppercase text-[8px]">DNS</span>
                                        </div>
                                        <div class="flex justify-between items-center py-1 border-b border-white/5">
                                            <span class="text-slate-300">Open Port 443 (HTTPS)</span>
                                            <span class="text-slate-500 font-bold">TCP</span>
                                            <span class="text-orange-400 font-bold bg-orange-950/20 border border-orange-500/20 px-1.5 py-0.2 rounded uppercase text-[8px]">Network</span>
                                        </div>
                                        <div class="flex justify-between items-center py-1 border-b border-white/5">
                                            <span class="text-slate-300">Open Port 80 (HTTP)</span>
                                            <span class="text-slate-500 font-bold">TCP</span>
                                            <span class="text-orange-400 font-bold bg-orange-950/20 border border-orange-500/20 px-1.5 py-0.2 rounded uppercase text-[8px]">Network</span>
                                        </div>
                                        <div class="flex justify-between items-center py-1 border-b border-white/5">
                                            <span class="text-slate-300">TLS Certificate</span>
                                            <span class="text-slate-505 font-bold truncate max-w-[80px]">*.infosoft.p...</span>
                                            <span class="text-purple-400 font-bold bg-purple-950/20 border border-purple-500/20 px-1.5 py-0.2 rounded uppercase text-[8px]">TLS</span>
                                        </div>
                                    </div>
                                </div>
                                <button class="w-full mt-3 py-1.5 text-[9px] font-black text-slate-400 hover:text-white uppercase tracking-wider text-center cursor-pointer flex items-center justify-center gap-1 font-mono">
                                    View all 14 network evidence
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                </button>
                            </div>

                            <!-- Code & File Evidence -->
                            <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between min-h-[220px] shadow-lg shadow-black/40">
                                <div class="space-y-4">
                                    <h5 class="text-xs font-black text-slate-400 uppercase tracking-widest font-mono flex items-center gap-2">
                                        <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        Code & File Evidence (12)
                                    </h5>
                                    <div class="space-y-2.5 font-mono text-[10px]">
                                        <div class="flex justify-between items-center py-1 border-b border-white/5">
                                            <span class="text-slate-300">package.json</span>
                                            <span class="text-slate-500 font-bold">Discovered</span>
                                            <span class="text-emerald-400 font-bold bg-emerald-950/20 border border-emerald-500/20 px-1.5 py-0.2 rounded uppercase text-[8px]">File</span>
                                        </div>
                                        <div class="flex justify-between items-center py-1 border-b border-white/5">
                                            <span class="text-slate-300">composer.json</span>
                                            <span class="text-slate-500 font-bold">Discovered</span>
                                            <span class="text-emerald-400 font-bold bg-emerald-950/20 border border-emerald-500/20 px-1.5 py-0.2 rounded uppercase text-[8px]">File</span>
                                        </div>
                                        <div class="flex justify-between items-center py-1 border-b border-white/5">
                                            <span class="text-slate-300">.git/config</span>
                                            <span class="text-slate-505 font-bold">Discovered</span>
                                            <span class="text-emerald-400 font-bold bg-emerald-950/20 border border-emerald-500/20 px-1.5 py-0.2 rounded uppercase text-[8px]">File</span>
                                        </div>
                                        <div class="flex justify-between items-center py-1 border-b border-white/5">
                                            <span class="text-slate-300">webpack.config.js</span>
                                            <span class="text-slate-505 font-bold">Discovered</span>
                                            <span class="text-emerald-400 font-bold bg-emerald-950/20 border border-emerald-500/20 px-1.5 py-0.2 rounded uppercase text-[8px]">File</span>
                                        </div>
                                        <div class="flex justify-between items-center py-1 border-b border-white/5">
                                            <span class="text-slate-300">robots.txt</span>
                                            <span class="text-slate-500 font-bold">Discovered</span>
                                            <span class="text-emerald-400 font-bold bg-emerald-950/20 border border-emerald-500/20 px-1.5 py-0.2 rounded uppercase text-[8px]">File</span>
                                        </div>
                                    </div>
                                </div>
                                <button class="w-full mt-3 py-1.5 text-[9px] font-black text-slate-400 hover:text-white uppercase tracking-wider text-center cursor-pointer flex items-center justify-center gap-1 font-mono">
                                    View all 12 file evidence
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                </button>
                            </div>

                        </div>

                        <!-- Row 2: Response Header Editor, Technology Fingerprints, Passive DNS History -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            
                            <!-- Response Header Evidence -->
                            <div class="lg:col-span-2 p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                                <div class="space-y-3">
                                    <h5 class="text-xs font-black text-slate-400 uppercase tracking-widest font-mono">Response Header Evidence</h5>
                                    <!-- Stylized Code Editor -->
                                    <div class="relative bg-[#020203] border border-white/5 rounded-xl p-5 font-mono text-[10px] text-slate-300 overflow-x-auto select-all h-60 custom-scrollbar leading-relaxed">
                                        <div class="flex gap-4">
                                            <div class="text-slate-600 select-none text-right w-3">
                                                1<br>2<br>3<br>4<br>5<br>6<br>7<br>8
                                            </div>
                                            <div>
                                                HTTP/1.1 200 OK<br>
                                                Server: nginx<br>
                                                Date: Sun, 22 Jun 2026 16:54:21 GMT<br>
                                                Content-Type: text/html; charset=UTF-8<br>
                                                X-Powered-By: PHP/8.2.12<br>
                                                Set-Cookie: PHPSESSID=***; path=/; secure; HttpOnly<br>
                                                Cache-Control: no-cache, private<br>
                                                X-Frame-Options: SAMEORIGIN
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center text-[9px] font-mono text-slate-505 mt-3 pt-2.5 border-t border-white/5">
                                    <span>Captured from: <span class="text-slate-400">https://infosoft.poolreno.com/</span></span>
                                    <span>Jun 22, 2026 16:54:21</span>
                                </div>
                            </div>

                            <!-- Right-side components stack: Technology Fingerprints & Passive DNS -->
                            <div class="flex flex-col gap-6">
                                <!-- Technology Fingerprints -->
                                <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between flex-1 shadow-lg shadow-black/40">
                                    <div class="space-y-3.5">
                                        <h5 class="text-xs font-black text-slate-400 uppercase tracking-widest font-mono">Technology Fingerprints</h5>
                                        <div class="space-y-2.5 font-mono text-[10px]">
                                            <div class="flex justify-between py-1 border-b border-white/5">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-[#ef4444]"></span>
                                                    Laravel
                                                </div>
                                                <span class="text-slate-500">11.x</span>
                                                <span class="text-rose-400 font-bold bg-rose-500/5 px-1 rounded text-[9px]">Backend</span>
                                            </div>
                                            <div class="flex justify-between py-1 border-b border-white/5">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-[#3b82f6]"></span>
                                                    WordPress
                                                </div>
                                                <span class="text-slate-500">6.4.x</span>
                                                <span class="text-blue-400 font-bold bg-blue-500/5 px-1 rounded text-[9px]">CMS</span>
                                            </div>
                                            <div class="flex justify-between py-1 border-b border-white/5">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-[#10b981]"></span>
                                                    Vue.js
                                                </div>
                                                <span class="text-slate-500">3.3.x</span>
                                                <span class="text-emerald-400 font-bold bg-emerald-500/5 px-1 rounded text-[9px]">Frontend</span>
                                            </div>
                                            <div class="flex justify-between py-1 border-b border-white/5">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-[#06b6d4]"></span>
                                                    Tailwind CSS
                                                </div>
                                                <span class="text-slate-500">3.x</span>
                                                <span class="text-cyan-400 font-bold bg-cyan-500/5 px-1 rounded text-[9px]">CSS Framework</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button class="w-full mt-3 py-1.5 text-[9px] font-black text-slate-400 hover:text-white uppercase tracking-wider text-center cursor-pointer flex items-center justify-center gap-1 font-mono">
                                        View all fingerprints
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                    </button>
                                </div>

                                <!-- Passive DNS History -->
                                <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between flex-1 shadow-lg shadow-black/40">
                                    <div class="space-y-3.5">
                                        <div class="flex justify-between items-center">
                                            <h5 class="text-xs font-black text-slate-400 uppercase tracking-widest font-mono">Passive DNS History</h5>
                                            <button class="text-[8px] font-black uppercase text-[#CBB48A] hover:underline flex items-center gap-0.5 cursor-pointer font-mono">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                                View Full History
                                            </button>
                                        </div>
                                        <div class="space-y-2.5 font-mono text-[9px]">
                                            <div class="flex justify-between py-1 border-b border-white/5">
                                                <span class="text-slate-300">infosoft.poolreno.com</span>
                                                <span class="text-slate-505">A</span>
                                                <span class="text-slate-400 font-semibold">103.21.244.0</span>
                                                <span class="text-slate-505">Jun 22, 2026</span>
                                            </div>
                                            <div class="flex justify-between py-1 border-b border-white/5">
                                                <span class="text-slate-300">infosoft.poolreno.com</span>
                                                <span class="text-slate-550">A</span>
                                                <span class="text-slate-400 font-semibold">172.67.138.45</span>
                                                <span class="text-slate-505">Jun 20, 2026</span>
                                            </div>
                                            <div class="flex justify-between py-1 border-b border-white/5">
                                                <span class="text-slate-300">infosoft.poolreno.com</span>
                                                <span class="text-slate-550">A</span>
                                                <span class="text-slate-400 font-semibold">104.21.80.1</span>
                                                <span class="text-slate-555">Jun 18, 2026</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button class="w-full mt-3 py-1.5 text-[9px] font-black text-slate-400 hover:text-white uppercase tracking-wider text-center cursor-pointer flex items-center justify-center gap-1 font-mono">
                                        View all DNS history
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                    </button>
                                </div>
                            </div>

                        </div>

                    </div>

                    <!-- TAB: RECOMMENDATIONS -->
                    <div v-if="activeTab === 'recommendations'" class="grid grid-cols-1 lg:grid-cols-12 gap-6 animate-fade-in">
                        
                        <!-- Main Roadmap List -->
                        <div class="lg:col-span-8 p-6 bg-[#070709] rounded-2xl border border-white/5 space-y-5 shadow-lg shadow-black/40">
                            <div>
                                <h4 class="text-sm font-black text-white uppercase tracking-widest font-mono">RECOMMENDATION ROADMAP</h4>
                                <p class="text-xs text-slate-500 font-medium font-sans mt-0.5">Prioritized security actions to reduce risk, improve resilience, and increase your LUME score.</p>
                            </div>
                            
                            <!-- Filters -->
                            <div class="flex flex-wrap gap-2 pt-1 font-mono">
                                <button @click="activeRecFilter = 'all'" :class="[activeRecFilter === 'all' ? 'bg-[#CBB48A] text-slate-900 font-black' : 'bg-white/5 text-slate-400 hover:text-white', 'px-3 py-1 rounded-lg text-[9px] font-bold tracking-widest uppercase transition-all']">All (12)</button>
                                <button @click="activeRecFilter = 'critical'" :class="[activeRecFilter === 'critical' ? 'bg-rose-500 text-slate-950 font-black' : 'bg-white/5 text-rose-400 hover:text-rose-350', 'px-3 py-1 rounded-lg text-[9px] font-bold tracking-widest uppercase transition-all']">Critical (3)</button>
                                <button @click="activeRecFilter = 'high'" :class="[activeRecFilter === 'high' ? 'bg-orange-500 text-slate-950 font-black' : 'bg-white/5 text-orange-400 hover:text-orange-300', 'px-3 py-1 rounded-lg text-[9px] font-bold tracking-widest uppercase transition-all']">High (4)</button>
                                <button @click="activeRecFilter = 'medium'" :class="[activeRecFilter === 'medium' ? 'bg-yellow-400 text-slate-950 font-black' : 'bg-white/5 text-yellow-400 hover:text-yellow-300', 'px-3 py-1 rounded-lg text-[9px] font-bold tracking-widest uppercase transition-all']">Medium (3)</button>
                                <button @click="activeRecFilter = 'low'" :class="[activeRecFilter === 'low' ? 'bg-emerald-500 text-slate-950 font-black' : 'bg-white/5 text-emerald-400 hover:text-emerald-300', 'px-3 py-1 rounded-lg text-[9px] font-bold tracking-widest uppercase transition-all']">Low (2)</button>
                            </div>
                            
                            <!-- Roadmap List -->
                            <div class="space-y-4 pt-2 font-sans text-xs">
                                <!-- Action 1 -->
                                <div class="p-4 bg-white/[0.01] border border-white/5 rounded-xl hover:bg-white/[0.03] transition-all flex items-center justify-between gap-4 cursor-pointer">
                                    <div class="flex gap-4 items-start">
                                        <span class="w-8 h-8 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-500 font-mono font-black text-sm flex items-center justify-center shrink-0">1</span>
                                        <div class="flex flex-col space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[8px] font-black uppercase tracking-widest px-1.5 py-0.2 rounded border border-red-500/30 text-red-400 bg-red-500/5 font-mono">CRITICAL</span>
                                                <span class="text-white font-bold leading-tight">Restrict Administrative Access</span>
                                            </div>
                                            <p class="text-[10px] text-slate-400 leading-relaxed font-medium">Limit access to admin endpoints (/admin, /login, /wp-admin) using IP allowlists, VPN, or authentication gateways. Exposure increases risk of unauthorized access.</p>
                                            <span class="text-[9px] font-black text-[#CBB48A] hover:underline uppercase tracking-wider font-mono">Why this matters -></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-8 shrink-0 font-mono text-[9px]">
                                        <div class="flex flex-col items-center">
                                            <span class="text-slate-500">IMPACT</span>
                                            <span class="text-rose-500 font-bold font-sans text-xs">+12</span>
                                            <span class="text-slate-650 text-[8px]">Security Score</span>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <span class="text-slate-505">DIFFICULTY</span>
                                            <span class="text-emerald-400 font-bold font-sans">Low</span>
                                            <div class="flex gap-0.5 mt-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-700"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-700"></span>
                                            </div>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <span class="text-slate-505">TIME ESTIMATE</span>
                                            <span class="text-white font-bold font-sans text-xs">15 min</span>
                                            <span class="text-slate-650 text-[8px]">&nbsp;</span>
                                        </div>
                                        <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                    </div>
                                </div>

                                <!-- Action 2 -->
                                <div class="p-4 bg-white/[0.01] border border-white/5 rounded-xl hover:bg-white/[0.03] transition-all flex items-center justify-between gap-4 cursor-pointer">
                                    <div class="flex gap-4 items-start">
                                        <span class="w-8 h-8 rounded-xl bg-orange-500/10 border border-orange-500/30 text-orange-400 font-mono font-black text-sm flex items-center justify-center shrink-0">2</span>
                                        <div class="flex flex-col space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[8px] font-black uppercase tracking-widest px-1.5 py-0.2 rounded border border-orange-500/30 text-orange-400 bg-orange-500/5 font-mono">HIGH</span>
                                                <span class="text-white font-bold leading-tight">Apply Security Headers</span>
                                            </div>
                                            <p class="text-[10px] text-slate-400 leading-relaxed font-medium">Implement HSTS, X-Frame-Options, X-Content-Type-Options, and CSP headers to mitigate common web vulnerabilities and clickjacking attacks.</p>
                                            <span class="text-[9px] font-black text-[#CBB48A] hover:underline uppercase tracking-wider font-mono">Why this matters -></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-8 shrink-0 font-mono text-[9px]">
                                        <div class="flex flex-col items-center">
                                            <span class="text-slate-500">IMPACT</span>
                                            <span class="text-orange-400 font-bold font-sans text-xs">+8</span>
                                            <span class="text-slate-655 text-[8px]">Security Score</span>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <span class="text-slate-505">DIFFICULTY</span>
                                            <span class="text-yellow-400 font-bold font-sans">Medium</span>
                                            <div class="flex gap-0.5 mt-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-400"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-400"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-700"></span>
                                            </div>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <span class="text-slate-505">TIME ESTIMATE</span>
                                            <span class="text-white font-bold font-sans text-xs">30 min</span>
                                            <span class="text-slate-655 text-[8px]">&nbsp;</span>
                                        </div>
                                        <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                    </div>
                                </div>

                                <!-- Action 3 -->
                                <div class="p-4 bg-white/[0.01] border border-white/5 rounded-xl hover:bg-white/[0.03] transition-all flex items-center justify-between gap-4 cursor-pointer">
                                    <div class="flex gap-4 items-start">
                                        <span class="w-8 h-8 rounded-xl bg-orange-500/10 border border-orange-500/30 text-orange-400 font-mono font-black text-sm flex items-center justify-center shrink-0">3</span>
                                        <div class="flex flex-col space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[8px] font-black uppercase tracking-widest px-1.5 py-0.2 rounded border border-orange-500/30 text-orange-400 bg-orange-500/5 font-mono">HIGH</span>
                                                <span class="text-white font-bold leading-tight">Update Outdated Frameworks</span>
                                            </div>
                                            <p class="text-[10px] text-slate-400 leading-relaxed font-medium">Laravel 11.x and other outdated components contain known vulnerabilities. Keep all frameworks and dependencies up to date.</p>
                                            <span class="text-[9px] font-black text-[#CBB48A] hover:underline uppercase tracking-wider font-mono">Why this matters -></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-8 shrink-0 font-mono text-[9px]">
                                        <div class="flex flex-col items-center">
                                            <span class="text-slate-505">IMPACT</span>
                                            <span class="text-[#CBB48A] font-bold font-sans text-xs">+10</span>
                                            <span class="text-slate-655 text-[8px]">Security Score</span>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <span class="text-slate-505">DIFFICULTY</span>
                                            <span class="text-yellow-400 font-bold font-sans">Medium</span>
                                            <div class="flex gap-0.5 mt-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-400"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-400"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-700"></span>
                                            </div>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <span class="text-slate-505">TIME ESTIMATE</span>
                                            <span class="text-white font-bold font-sans text-xs">45 min</span>
                                            <span class="text-slate-655 text-[8px]">&nbsp;</span>
                                        </div>
                                        <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                    </div>
                                </div>

                                <!-- Action 4 -->
                                <div class="p-4 bg-white/[0.01] border border-white/5 rounded-xl hover:bg-white/[0.03] transition-all flex items-center justify-between gap-4 cursor-pointer">
                                    <div class="flex gap-4 items-start">
                                        <span class="w-8 h-8 rounded-xl bg-yellow-500/10 border border-yellow-500/30 text-yellow-400 font-mono font-black text-sm flex items-center justify-center shrink-0">4</span>
                                        <div class="flex flex-col space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[8px] font-black uppercase tracking-widest px-1.5 py-0.2 rounded border border-yellow-500/30 text-yellow-400 bg-yellow-500/5 font-mono">MEDIUM</span>
                                                <span class="text-white font-bold leading-tight">Enforce Strong Authentication</span>
                                            </div>
                                            <p class="text-[10px] text-slate-400 leading-relaxed font-medium">Implement MFA for all administrator accounts and enforce strong password policies to prevent brute-force and credential stuffing attacks.</p>
                                            <span class="text-[9px] font-black text-[#CBB48A] hover:underline uppercase tracking-wider font-mono">Why this matters -></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-8 shrink-0 font-mono text-[9px]">
                                        <div class="flex flex-col items-center">
                                            <span class="text-slate-505">IMPACT</span>
                                            <span class="text-orange-400 font-bold font-sans text-xs">+6</span>
                                            <span class="text-slate-655 text-[8px]">Security Score</span>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <span class="text-slate-505">DIFFICULTY</span>
                                            <span class="text-emerald-400 font-bold font-sans">Low</span>
                                            <div class="flex gap-0.5 mt-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-700"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-700"></span>
                                            </div>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <span class="text-slate-550">TIME ESTIMATE</span>
                                            <span class="text-white font-bold font-sans text-xs">20 min</span>
                                            <span class="text-slate-655 text-[8px]">&nbsp;</span>
                                        </div>
                                        <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center pt-2">
                                <button class="px-5 py-2 bg-white/5 hover:bg-white/10 text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all inline-flex items-center gap-1 cursor-pointer">
                                    Show 7 more recommendations
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Right Panel: Speedometer Potential Score -->
                        <div class="lg:col-span-4 space-y-6 animate-fade-in">
                            
                            <button class="w-full py-3 bg-[#CBB48A]/5 hover:bg-[#CBB48A]/10 border border-[#CBB48A]/30 rounded-2xl text-xs font-black text-[#CBB48A] uppercase tracking-widest flex items-center justify-center gap-2 cursor-pointer shadow-lg font-mono">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                View Implementation Guide
                            </button>

                            <!-- Improvement Potential card -->
                            <div class="p-5 bg-[#070709] border border-white/5 rounded-2xl text-center space-y-4 relative shadow-lg shadow-black/40">
                                <h5 class="text-[9px] font-black text-slate-500 uppercase tracking-widest font-mono">IMPROVEMENT POTENTIAL</h5>
                                <div class="flex flex-col items-center">
                                    <span class="text-2xl font-black text-emerald-400 font-mono leading-none">+28</span>
                                    <span class="text-[9px] text-slate-500 font-bold font-sans uppercase tracking-widest mt-1">Potential Score Increase</span>
                                </div>
                                <div class="relative w-40 h-24 mx-auto flex items-end justify-center overflow-hidden">
                                    <!-- speedometer semi-circle arc SVG -->
                                    <svg class="w-full h-20" viewBox="0 0 100 50">
                                        <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="6" stroke-linecap="round"></path>
                                        <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="#CBB48A" stroke-width="6" stroke-linecap="round" stroke-dasharray="45, 126"></path>
                                    </svg>
                                    <div class="absolute bottom-0 text-[10px] font-mono font-bold text-slate-400 flex justify-between w-full px-2">
                                        <span>32<br><span class="text-[8px] text-slate-650 font-sans">Current</span></span>
                                        <span>60<br><span class="text-[8px] text-slate-650 font-sans">Potential</span></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Top Risk Areas list -->
                            <div class="p-5 bg-[#070709] border border-white/5 rounded-2xl space-y-4 shadow-lg shadow-black/40">
                                <h5 class="text-[9px] font-black text-slate-500 uppercase tracking-widest font-mono">TOP RISK AREAS</h5>
                                <div class="space-y-3 font-mono text-xs">
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-400 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            Access Control
                                        </span>
                                        <span class="text-rose-500 font-bold">3</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-400 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-orange-400"></span>
                                            Outdated Components
                                        </span>
                                        <span class="text-orange-400 font-bold">2</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-400 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-orange-400"></span>
                                            Security Headers
                                        </span>
                                        <span class="text-orange-400 font-bold">2</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-400 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-400"></span>
                                            Third-Party Exposure
                                        </span>
                                        <span class="text-yellow-400 font-bold">2</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-400 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                            Authentication
                                        </span>
                                        <span class="text-emerald-400 font-bold">1</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Implementation Notes -->
                            <div class="p-5 bg-white/[0.01] border border-white/5 rounded-2xl flex gap-3 items-start shadow-lg shadow-black/40">
                                <div class="w-7 h-7 rounded-lg bg-[#CBB48A]/10 border border-[#CBB48A]/30 flex items-center justify-center text-[#CBB48A] shrink-0 font-sans">
                                    <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.746 3.746 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>
                                </div>
                                <div class="flex flex-col space-y-1">
                                    <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest font-mono">IMPLEMENTATION NOTES</span>
                                    <p class="text-[10px] text-slate-400 font-medium leading-relaxed font-sans">
                                        Fixing the top 3 critical issues can improve your score by up to 20 points and significantly reduce your risk exposure.
                                    </p>
                                </div>
                            </div>

                        </div>

                    </div>

                    <!-- TAB: HISTORY -->
                    <div v-if="activeTab === 'history'" class="space-y-6 animate-fade-in">
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 shadow-lg shadow-black/40">
                            <h4 class="text-sm font-bold text-white uppercase tracking-widest mb-4 font-mono">Historical Scans Log</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left font-mono text-xs">
                                    <thead>
                                        <tr class="border-b border-white/5 text-slate-500">
                                            <th class="pb-3 uppercase">Date</th>
                                            <th class="pb-3 uppercase">Audit Type</th>
                                            <th class="pb-3 uppercase">Status</th>
                                            <th class="pb-3 uppercase text-right">Risk Score</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5">
                                        <tr v-for="h in props.history" :key="h.id" class="text-slate-300">
                                            <td class="py-3">{{ new Date(h.created_at).toLocaleString() }}</td>
                                            <td class="py-3 uppercase">Website</td>
                                            <td class="py-3 font-bold text-emerald-400">{{ h.status }}</td>
                                            <td class="py-3 text-right text-rose-400 font-bold">{{ h.score }}</td>
                                        </tr>
                                        <tr v-if="!props.history.length">
                                            <td colspan="4" class="py-6 text-center text-slate-600 italic">No historical runs recorded for this website.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                    </div>
                </div>
            </div>

            <!-- Footer Bar Info -->
            <div class="border-t border-white/5 pt-6 flex flex-col md:flex-row items-center justify-between gap-4 text-xs font-mono">
                <div class="flex items-center gap-2 text-slate-500">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                    <span>SCAN INTEGRITY: <span class="text-emerald-500 font-bold">VERIFIED (SHA-256)</span></span>
                </div>

                <div class="flex items-center gap-2 text-slate-500">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span>LIVE FORENSIC FEED ACCESS</span>
                </div>

                <div class="flex items-center gap-6">
                    <div class="hidden sm:flex items-center gap-2 text-slate-500">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                        </svg>
                        <span>AI LATENCY: <span class="text-slate-400">128ms (LUME-V4)</span></span>
                    </div>
                    <button 
                        @click="showAIModal = true"
                        class="flex items-center gap-2 px-4 py-2 border border-[#CBB48A]/30 bg-[#CBB48A]/5 hover:bg-[#CBB48A]/10 text-[#CBB48A] text-xs font-bold rounded-xl transition-all cursor-pointer font-sans"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                        </svg>
                        Ask LUME AI
                    </button>
                </div>
            </div>

        </div>

        <!-- DIALOG SUBMODALS -->

        <!-- 1. AI Findings Detail Modal -->
        <div v-if="showAIDetailsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/85 backdrop-blur-sm" @click="showAIDetailsModal = false"></div>
            <div class="relative z-10 w-full max-w-2xl bg-[#0e0f12] border border-white/10 rounded-2xl shadow-2xl overflow-hidden font-sans">
                <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                    <h3 class="text-base font-bold text-white uppercase tracking-wider">AI Findings Detail</h3>
                    <button @click="showAIDetailsModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>
                <div class="p-6 max-h-[60vh] overflow-y-auto custom-scrollbar space-y-3">
                    <div v-for="(finding, idx) in keyFindings" :key="idx" class="p-4 rounded-xl border border-white/5 bg-white/[0.01]">
                        <div class="flex justify-between items-center mb-1.5">
                            <span class="text-xs font-bold text-white">{{ finding.title }}</span>
                            <span class="text-[9px] font-mono bg-white/5 border border-white/15 px-1.5 py-0.2 rounded font-bold uppercase text-slate-400">{{ finding.severity }}</span>
                        </div>
                        <p class="text-xs text-slate-400 font-light leading-relaxed">{{ finding.desc }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Detailed Vector Analysis Dialog -->
        <div v-if="showVectorDetailsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 font-sans">
            <div class="absolute inset-0 bg-black/85 backdrop-blur-sm" @click="showVectorDetailsModal = false"></div>
            <div class="relative z-10 w-full max-w-4xl bg-[#0e0f12] border border-white/10 rounded-2xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                    <h3 class="text-base font-bold text-white uppercase tracking-wider">Detailed Security Vector Analysis</h3>
                    <button @click="showVectorDetailsModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[70vh] overflow-y-auto custom-scrollbar bg-black/20">
                    <div v-for="(value, key) in radarData" :key="key" class="p-4 rounded-xl border border-white/5 bg-white/[0.01]">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="text-xs font-bold text-slate-300 uppercase font-mono tracking-wider">{{ formatLabel(String(key)) }}</h4>
                            <span class="text-base font-mono font-bold text-[#CBB48A]">{{ Number(value).toFixed(0) }}</span>
                        </div>
                        <div class="w-full bg-slate-900 h-1.5 rounded-full overflow-hidden mb-3">
                            <div class="h-full bg-[#CBB48A] rounded-full" :style="{ width: value + '%' }"></div>
                        </div>
                        <p class="text-xs text-slate-400 font-light">Comprehensive verification of metrics against standard network topology rules.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. LUME AI Support analytic chat -->
        <LumeAISupport 
            v-if="asset"
            :show="showAIModal" 
            mode="analytic"
            :asset="asset"
            @close="showAIModal = false" 
        />

        <!-- 4. Topology Details Modal -->
        <TopologyDetailsModal 
            :show="showTopologyDetailsModal"
            :nodes="topologyData?.nodes || []"
            @close="showTopologyDetailsModal = false"
        />

        <!-- 5. QA Testing and Penetration specification -->
        <PenetrationAndAQTesting
            v-if="asset"
            :show="showDeepScanConfirmation"
            :asset="asset"
            @close="showDeepScanConfirmation = false"
            @confirm="handleDeepScanConfirm"
            @open-credit-modal="showPurchaseModal = true"
            @completion="showDeepScanConfirmation = false"
        />

        <!-- 6. Project Analyst AI Assistant Modal -->
        <ProjectAnalystModal
            v-if="asset"
            :show="showProjectAnalystModal"
            :asset="asset"
            @close="showProjectAnalystModal = false"
        />

        <!-- 7. Credit Purchase Modal -->
        <CreditPurchaseModal
            :show="showPurchaseModal"
            @close="showPurchaseModal = false"
        />

        <!-- 8. QA Results Modal -->
        <QAPenetrationResultsModal
            v-if="asset"
            :show="showQAResultsModal"
            :asset="asset"
            @close="showQAResultsModal = false"
            @open-ai-chat="showPentestAiModal = true"
            @re-scan="showDeepScanConfirmation = true"
        />

        <!-- Universal progress monitor -->
        <UniversalScanning
            v-if="showSecurityScanningModal"
            :show="showSecurityScanningModal"
            type="security"
            :target-name="asset.file_name"
            :progress="auditProgress?.progress || 0"
            :step="auditProgress?.step || 'Initializing scan...'"
            :details="auditProgress?.details"
            @view-results="async () => { await refreshSelectedAsset(); showSecurityScanningModal = false; showQAResultsModal = true; }"
            @close="showSecurityScanningModal = false"
        />

    </AuthenticatedLayout>
</template>

<style scoped>
.text-shadow {
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
}
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 20px;
    border: 2px solid transparent;
    background-clip: content-box;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.15);
    background-clip: content-box;
}
</style>
