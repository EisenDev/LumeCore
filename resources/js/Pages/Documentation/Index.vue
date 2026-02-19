<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, computed, watch } from 'vue';

const props = defineProps<{
    section: string;
}>();

const currentSection = ref(props.section || 'introduction');
const isMobileMenuOpen = ref(false);
const activePhase = ref<number | null>(null);

const sections = [
    {
        id: 'introduction',
        title: 'Introduction',
        icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
    },
    {
        id: 'features',
        title: 'Features',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'
    },
    {
        id: 'benefits',
        title: 'Benefits',
        icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'
    },
    {
        id: 'how-to-use',
        title: 'How to Use',
        icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'
    },
    {
        id: 'security-protocol',
        title: 'Security Protocol',
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'
    },
    {
        id: 'roadmap',
        title: 'Roadmap',
        icon: 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894l4.816 2.408a2 2 0 001.474 0l5.526-2.763a2 2 0 011.474 0l5.526 2.763a1 1 0 011.447.894v10.764a1 1 0 01-.553.894L15 20l-6-3-6 3z'
    },
    {
        id: 'white-paper',
        title: 'White Paper',
        icon: 'M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
    }
];

const howToUseSteps = [
    { title: 'Initialize Entity', desc: 'Create your Organization workspace and verify ownership through cryptographic signing.', cmd: 'lume init --org "MyAgency"', status: 'Verified' },
    { title: 'Inject Asset', desc: 'Upload project repositories to the isolated Vault environment via secure tunnel.', cmd: 'lume push ./src --vault', status: 'Encrypted' },
    { title: 'Sovereign Scan', desc: 'Trigger the Forensic Engine to analyze code for vulnerabilities and IP purity.', cmd: 'lume audit --deep --forensic', status: 'Scanning' },
    { title: 'Ledger Settlement', desc: 'Mint the final Audit Certificate on-chain and settle usage fees automatically.', cmd: 'lume settle --confirm', status: 'Complete' }
];

// Content Data
const pillars = [
    { title: 'CloudVault', subtitle: 'Direct-to-Edge Secure Storage', icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10', color: 'text-emerald-400', bg: 'from-emerald-500/10 to-transparent' },
    { title: 'LedgerLogic', subtitle: 'Double-Entry Immutable Finance', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: 'text-cyan-400', bg: 'from-cyan-500/10 to-transparent' },
    { title: 'Sovereign Auditor', subtitle: 'Multi-Modal AI Forensics', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', color: 'text-purple-400', bg: 'from-purple-500/10 to-transparent' },
    { title: 'Licensing Protocol', subtitle: 'Smart Contracts for IP Rights', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', color: 'text-amber-400', bg: 'from-amber-500/10 to-transparent' },
    { title: 'Compliance Engine', subtitle: 'Automated Cross-Border Tax/Legal', icon: 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3', color: 'text-rose-400', bg: 'from-rose-500/10 to-transparent' },
    { title: 'Arbitration DAO', subtitle: 'AI-Driven Dispute Resolution', icon: 'M3 21v-8a2 2 0 012-2h14a2 2 0 012 2v8M3 21h18M3 21a2 2 0 002 2h14a2 2 0 002-2m0-10V7a2 2 0 00-2-2h-4a2 2 0 00-2 2v4M5 7h4m-4 0v4m4-4v4m-2 4h4', color: 'text-blue-400', bg: 'from-blue-500/10 to-transparent' }
];

const features = [
    {
        title: 'Sovereign Audit',
        desc: 'Deep-State Code Analysis using proprietary heuristic engines to detect zero-day vulnerabilities before deployment.',
        details: [
            'Recursive Dependency Mapping',
            'Static & Dynamic Analysis (SAST/DAST)',
            'Compliance Verification (ISO/SOC2)'
        ],
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        status: 'Operational'
    },
    {
        title: 'Forensic Scanner',
        desc: 'Real-time threat detection engine that monitors project repositories for unauthorized modifications and injection attacks.',
        details: [
            '24/7 Repository Surveillance',
            'Anomaly Detection AI',
            'Automated Rollback Triggers'
        ],
        icon: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
        status: 'Active'
    },
    {
        title: 'LedgerLogic',
        desc: 'Immutable financial settlement layer ensuring every transaction is cryptographically verified and permanently recorded.',
        details: [
            'Double-Entry Blockchain Accounting',
            'Smart Contract Escrow',
            'Multi-Currency Settlement'
        ],
        icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        status: 'Live'
    },
    {
        title: 'Context Engine',
        desc: 'Switch between organizational contexts instantly, isolating assets, team roles, and billing cycles securely.',
        details: [
            'Role-Based Access Control (RBAC)',
            'Isolated Workspace Environments',
            'Unified Dashboard View'
        ],
        icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
        status: 'Beta'
    }
];

const benefits = [
    {
        role: 'For Agencies',
        headline: 'Zero-Liability Handover',
        desc: 'Transfer ownership with absolute legal and technical certainty. Once LUME verifies the asset, your liability ends.',
        stats: [
            { label: 'Audit Speed', value: '< 24h' },
            { label: 'Client Trust', value: '100%' }
        ],
        color: 'text-emerald-400',
        bg: 'bg-emerald-500/10'
    },
    {
        role: 'For Investors',
        headline: 'Diligence-as-a-Service',
        desc: 'Never buy blind. Get a full forensic report on the technical health and IP ownership of any digital asset before purchase.',
        stats: [
            { label: 'Risk Reduction', value: '98%' },
            { label: 'Asset Clarity', value: 'Total' }
        ],
        color: 'text-cyan-400',
        bg: 'bg-cyan-500/10'
    },
    {
        role: 'For Institutions',
        headline: 'Regulatory Autopilot',
        desc: 'Automate cross-border tax compliance, IP licensing, and audit trails. LUME handles the bureaucracy.',
        stats: [
            { label: 'Compliance', value: 'Auto' },
            { label: 'Audit Trail', value: 'Immutable' }
        ],
        color: 'text-purple-400',
        bg: 'bg-purple-500/10'
    }
];

const roadmapPhases = [
    {
        phase: 'PHASE 01: THE FOUNDATION',
        timeline: 'Current - Q1 2026',
        status: 'LIVE',
        statusColor: 'bg-emerald-500',
        value: 'Establishing the Source of Truth for Digital Assets.',
        features: [
            'Sovereign Document Audit',
            'Forensic Project Scanner',
            '5-Vector Radar Analysis',
            'Surface-Level Penetration Testing',
            'LedgerLogic Settlement'
        ],
        deepDive: 'The Foundation Phase checks the integrity of digital assets through SHA-256 fingerprinting and recursive dependency scanning. All analyzed projects are stored in an isolated Vault environment, preventing cross-contamination from potentially malicious codebases.',
        icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'
    },
    {
        phase: 'PHASE 02: THE WATCHTOWER',
        timeline: 'Q3 2026 - Q4 2026',
        status: 'UPCOMING',
        statusColor: 'bg-cyan-500',
        value: 'From One-Time Audit to Continuous Protection.',
        features: [
            'CI/CD Sentinel: Real-time Github Webhooks',
            'Uptime Guard: 24/7 Availability Monitoring',
            'Dependency Watch: Automated CVE alerts'
        ],
        deepDive: 'The Watchtower introduces active monitoring protocols. Instead of static snapshots, LUME integrates directly into the development lifecycle via Github Actions, ensuring that every commit is pre-validated against known vulnerability databases before deployment.',
        icon: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z m6 0c0 4.418-4.03 8-9 8s-9-3.582-9-8 4.03-8 9-8 9 3.582 9 8z'
    },
    {
        phase: 'PHASE 03: THE OFFENSIVE GRID',
        timeline: 'Q1 2027',
        status: 'LOCKED',
        statusColor: 'bg-purple-500',
        value: 'AI-Powered Penetration Testing & Ethical Hacking.',
        features: [
            'Local LLM Integration: Private Neural Models',
            'Smart Brute Force: AI-Driven Pattern Recognition',
            'Terminal-Based White Hat Hacking Protocols'
        ],
        deepDive: 'The Offensive Grid introduces active "White Hat" algorithms. Utilizing isolated Local LLMs, LUME simulates sophisticated attack vectors—including intelligent brute force and context-aware SQL injection—directly via a secure PowerShell/Terminal environment, ensuring robust defense without external data exposure.',
        icon: 'M13 10V3L4 14h7v7l9-11h-7z'
    },
    {
        phase: 'PHASE 04: THE MARKETPLACE PROTOCOL',
        timeline: 'Q3 2027',
        status: 'LOCKED',
        statusColor: 'bg-amber-500',
        value: 'The Liquidity Layer for Intellectual Property.',
        features: [
            'Autonomous M&A: Smart Contract Transfers',
            'Escrow-as-a-Service: Audit Engine API',
            'Cross-Border Tax Compliance'
        ],
        deepDive: 'This protocol allows for the frictionless transfer of project ownership. LUME acts as the trusted escrow, holding funds until the code passes audit and the repository ownership is cryptographically verified as transferred.',
        icon: 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3'
    },
    {
        phase: 'PHASE 05: THE DECENTRALIZED MESH',
        timeline: '2028+',
        status: 'LOCKED',
        statusColor: 'bg-rose-500',
        value: 'The Global Standard for Digital Trust.',
        features: [
            'Federated Node Scanning',
            'Blockchain Proof: Immutable Audit History',
            'Universal Identity: LUME ID'
        ],
        deepDive: 'The final phase decentralizes the audit engine itself. A mesh of verified nodes contributes compute power to scan projects, making the system resilient to IP blocking and capable of analyzing massive distributed systems.',
        icon: 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9'
    }
];

// Logic
const setSection = (id: string) => {
    currentSection.value = id;
    window.location.hash = id;
    isMobileMenuOpen.value = false;
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const toggleMobileMenu = () => {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
};

const togglePhase = (index: number) => {
    activePhase.value = activePhase.value === index ? null : index;
};

// Navigation
const currentIndex = computed(() => sections.findIndex(s => s.id === currentSection.value));
const prevSection = computed(() => currentIndex.value > 0 ? sections[currentIndex.value - 1] : null);
const nextSection = computed(() => currentIndex.value < sections.length - 1 ? sections[currentIndex.value + 1] : null);

// Animations
onMounted(() => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-slide-up');
            }
        });
    }, { threshold: 0.1 });

    // Observe initially present elements
    setTimeout(() => {
         document.querySelectorAll('.scroll-reveal').forEach(el => observer.observe(el));
    }, 100);
    
    // Watch for section changes to re-observe new elements
    watch(currentSection, () => {
        setTimeout(() => {
             document.querySelectorAll('.scroll-reveal').forEach(el => observer.observe(el));
        }, 100);
    });

    if (typeof window !== 'undefined' && window.location.hash) {
        const hash = window.location.hash.replace('#', '');
        if (sections.find(s => s.id === hash)) {
            currentSection.value = hash;
        }
    }
});
</script>

<template>
    <Head title="Documentation | LUMECORE" />

    <div class="min-h-screen bg-[#050505] text-white flex flex-col lg:flex-row relative">
         <!-- Mobile Toggle Button -->
        <button 
            @click="toggleMobileMenu" 
            class="lg:hidden fixed top-4 right-4 z-[60] p-3 rounded-xl bg-white/5 border border-white/10 text-white shadow-lg backdrop-blur-xl"
        >
            <svg v-if="!isMobileMenuOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <svg v-else class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Documentation Sidebar -->
        <aside 
            class="w-52 border-r border-white/5 bg-[#0A0A0B] fixed h-screen z-50 transition-transform duration-300 lg:translate-x-0"
            :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="p-6 border-b border-white/5">
                <Link href="/" class="flex items-center gap-3 group">
                    <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-emerald-400 to-cyan-400 p-0.5 shadow-lg shadow-emerald-400/20">
                        <div class="w-full h-full bg-[#0A0A0B] rounded-[0.4rem] flex items-center justify-center">
                            <svg class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                        </div>
                    </div>
                    <span class="font-black italic tracking-tighter text-white uppercase">DOCS</span>
                </Link>
            </div>

            <nav class="flex-1 overflow-y-auto p-4 space-y-2">
                <button 
                    v-for="section in sections" 
                    :key="section.id"
                    @click="setSection(section.id)"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all text-xs font-bold uppercase tracking-widest text-left"
                    :class="currentSection === section.id ? 'bg-emerald-400/10 text-emerald-400 border border-emerald-400/20 shadow-[0_0_20px_rgba(52,211,153,0.1)]' : 'text-gray-500 hover:text-white hover:bg-white/5'"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="section.icon" />
                    </svg>
                    {{ section.title }}
                </button>
            </nav>

            <div class="p-6 border-t border-white/5">
                <Link href="/dashboard" class="flex items-center justify-center gap-2 w-full py-3 rounded-xl bg-white/5 border border-white/10 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-white hover:bg-white/10 transition-all">
                    Return to Terminal
                </Link>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 lg:ml-52 p-8 lg:p-20 max-w-7xl mx-auto w-full min-h-screen flex flex-col justify-between">
            <div>
                 <!-- Introduction -->
                <section v-if="currentSection === 'introduction'" class="space-y-32">
                     <!-- Hero Section -->
                    <div class="text-center space-y-8 animate-in pt-20">
                        <h1 class="text-7xl lg:text-9xl font-black italic tracking-tighter uppercase relative inline-block">
                            <span class="bg-gradient-to-r from-emerald-400 via-cyan-400 to-emerald-400 bg-clip-text text-transparent bg-[length:200%_auto] animate-gradient">LUME</span>
                            <span class="text-white">CORE</span>
                            <div class="absolute -top-10 -right-10 text-[10px] font-black uppercase tracking-[0.3em] text-cyan-400 animate-pulse border border-cyan-500/30 px-3 py-1 rounded-full">System Active</div>
                        </h1>
                        <p class="text-2xl lg:text-3xl text-gray-400 font-bold uppercase tracking-widest max-w-4xl mx-auto leading-tight">
                            The Operating System for <span class="text-white border-b-2 border-emerald-400">Digital Trust</span>.
                        </p>
                    </div>

                    <!-- Problem / Solution Narrative -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-20 items-center scroll-reveal opacity-0 translate-y-8 transition-all duration-1000">
                        <div class="space-y-8">
                            <h2 class="text-red-500 font-black uppercase tracking-[0.2em] text-sm">/// The Black Box Crisis</h2>
                            <h3 class="text-4xl font-black italic tracking-tighter uppercase text-white">Trust is <span class="text-gray-600 line-through decoration-red-500">Broken</span></h3>
                            <p class="text-gray-400 leading-relaxed font-medium uppercase tracking-wide">
                                The modern digital economy operates in the dark. Buyers cannot verify code quality, sellers cannot prove ownership, and transactions are fraught with risk. The "Black Box" of unverified assets stifles innovation.
                            </p>
                        </div>
                         <div class="relative p-1 rounded-[3rem] bg-gradient-to-br from-emerald-500/20 to-cyan-500/20">
                             <div class="bg-[#050505] rounded-[2.9rem] p-10 border border-emerald-500/20 relative overflow-hidden">
                                 <div class="absolute inset-0 bg-grid-white/[0.03] bg-[length:20px_20px]"></div>
                                  <div class="space-y-8 relative z-10">
                                    <h2 class="text-emerald-400 font-black uppercase tracking-[0.2em] text-sm">/// The Sovereign Engine</h2>
                                    <h3 class="text-4xl font-black italic tracking-tighter uppercase text-white">Verification is <span class="text-emerald-400">Absolute</span></h3>
                                    <p class="text-gray-400 leading-relaxed font-medium uppercase tracking-wide">
                                        LUMECORE fixes this by combining Forensic AI with Immutable Financial Ledgers. We don't just host assets; we <span class="text-white font-bold">verify their soul</span>.
                                    </p>
                                 </div>
                             </div>
                         </div>
                    </div>

                    <!-- 6-Pillar Grid -->
                    <div class="space-y-12 scroll-reveal opacity-0 translate-y-8 transition-all duration-1000 delay-200">
                        <div class="text-center">
                            <h2 class="text-5xl font-black italic tracking-tighter uppercase mb-4">System <span class="text-cyan-400">Architecture</span></h2>
                            <p class="text-gray-500 font-bold uppercase tracking-widest text-sm">Six Pillars of Sovereign Intelligence</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div 
                                v-for="(pillar, index) in pillars" 
                                :key="index"
                                class="group relative p-1 rounded-3xl transition-all duration-500 hover:scale-[1.02]"
                            >
                                <div class="absolute inset-0 rounded-3xl bg-gradient-to-br opacity-0 group-hover:opacity-100 transition-opacity duration-500" :class="pillar.bg"></div>
                                <div class="bg-[#0A0A0B] h-full rounded-[1.4rem] p-8 border border-white/5 group-hover:border-white/20 transition-colors relative z-10 overflow-hidden">
                                    <div class="absolute -right-10 -top-10 h-32 w-32 blur-[60px] opacity-0 group-hover:opacity-20 transition-opacity duration-500" :class="pillar.bg.replace('/10','').replace('from-', 'bg-')"></div>
                                    
                                    <svg class="h-10 w-10 mb-6 transition-colors duration-300" :class="pillar.color" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="pillar.icon" />
                                    </svg>
                                    <h3 class="text-xl font-black uppercase italic tracking-tighter text-white mb-2">{{ pillar.title }}</h3>
                                    <p class="text-xs font-bold uppercase tracking-widest text-gray-500 group-hover:text-white transition-colors">{{ pillar.subtitle }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SVG Wireframe Visualization -->
                     <div class="py-20 scroll-reveal opacity-0 translate-y-8 transition-all duration-1000 delay-300">
                        <div class="relative w-full aspect-[2/1] border border-white/10 rounded-3xl bg-white/[0.01] flex items-center justify-center overflow-hidden">
                             <div class="absolute inset-0 bg-grid-white/[0.02]"></div>
                             <!-- Abstract Wireframe -->
                             <svg class="w-full h-full opacity-30" viewBox="0 0 800 400" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M400 200L100 100M400 200L700 100M400 200L400 350" stroke="currentColor" stroke-width="2" class="text-emerald-500 animate-pulse"/>
                                <circle cx="400" cy="200" r="10" class="fill-white animate-ping"/>
                                <circle cx="100" cy="100" r="5" class="fill-cyan-500"/>
                                <circle cx="700" cy="100" r="5" class="fill-purple-500"/>
                                <circle cx="400" cy="350" r="5" class="fill-rose-500"/>
                             </svg>
                             <div class="absolute bottom-4 right-6 text-[10px] font-black uppercase tracking-widest text-gray-600">
                                 Simulated Network Topology
                             </div>
                        </div>
                     </div>
                </section>

                <!-- Features Section -->
                <section v-if="currentSection === 'features'" class="animate-in fade-in space-y-24">
                    <div class="text-center space-y-6 pt-10">
                         <h1 class="text-6xl font-black italic tracking-tighter uppercase">Core <span class="bg-gradient-to-r from-emerald-400 to-cyan-400 bg-clip-text text-transparent">Capabilities</span></h1>
                         <p class="text-xl text-gray-400 font-bold uppercase tracking-widest max-w-2xl mx-auto">High-precision tools for the analysis and exchange of digital assets.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div v-for="(feat, idx) in features" :key="idx" class="bg-[#0A0A0B] rounded-[2.5rem] p-8 border border-white/5 hover:border-emerald-500/30 transition-all group overflow-hidden relative scroll-reveal opacity-0 translate-y-8 duration-700" :style="`transition-delay: ${idx * 100}ms`">
                             <div class="absolute -right-20 -top-20 h-48 w-48 bg-emerald-500/10 blur-[80px] rounded-full group-hover:bg-emerald-500/20 transition-colors"></div>
                             
                             <div class="flex items-start justify-between mb-8 relative z-10">
                                 <div class="p-3 rounded-2xl bg-white/5 border border-white/10 group-hover:border-emerald-500/50 transition-colors">
                                     <svg class="h-8 w-8 text-gray-400 group-hover:text-emerald-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="feat.icon" />
                                     </svg>
                                 </div>
                                 <span class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-500 bg-emerald-500/10 px-3 py-1 rounded-full border border-emerald-500/20 animate-pulse">
                                     {{ feat.status }}
                                 </span>
                             </div>

                             <h3 class="text-2xl font-black italic uppercase text-white mb-4 tracking-tight">{{ feat.title }}</h3>
                             <p class="text-sm font-bold text-gray-500 uppercase tracking-widest leading-relaxed mb-8 h-20">{{ feat.desc }}</p>

                             <ul class="space-y-3 border-t border-white/5 pt-6">
                                 <li v-for="d in feat.details" :key="d" class="flex items-center gap-3 text-xs font-black uppercase tracking-wider text-gray-400 group-hover:text-white transition-colors">
                                     <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                     {{ d }}
                                 </li>
                             </ul>
                        </div>
                    </div>
                </section>

                <!-- Benefits Section -->
                 <section v-if="currentSection === 'benefits'" class="animate-in fade-in space-y-24">
                     <div class="text-center space-y-6 pt-10">
                         <h1 class="text-6xl font-black italic tracking-tighter uppercase relative">
                             Unfair <span class="text-white line-through decoration-emerald-500">Risk</span> <span class="text-emerald-400">Advantage</span>
                         </h1>
                         <p class="text-xl text-gray-400 font-bold uppercase tracking-widest max-w-2xl mx-auto">Why the world's leading agencies and institutions choose LUME.</p>
                    </div>

                    <div class="space-y-8">
                        <div v-for="(benefit, idx) in benefits" :key="idx" 
                            class="relative p-1 rounded-[3rem] group scroll-reveal opacity-0 translate-y-8 duration-700"
                            :style="`transition-delay: ${idx * 150}ms`"
                        >
                            <div class="absolute inset-0 rounded-[3rem] bg-gradient-to-r opacity-30 group-hover:opacity-100 transition-opacity duration-500 blur-sm" :class="benefit.bg.replace('bg-', 'from-').replace('/10', '/30') + ' to-transparent'"></div>
                            
                            <div class="bg-[#0A0A0B] rounded-[2.9rem] p-10 lg:p-12 border border-white/5 relative z-10 flex flex-col lg:flex-row items-center gap-12">
                                <div class="flex-1 space-y-6">
                                    <span class="text-xs font-black uppercase tracking-[0.3em]" :class="benefit.color">/// {{ benefit.role }}</span>
                                    <h2 class="text-4xl lg:text-5xl font-black italic uppercase text-white tracking-tighter">{{ benefit.headline }}</h2>
                                    <p class="text-gray-400 font-bold uppercase tracking-widest leading-relaxed max-w-xl">{{ benefit.desc }}</p>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 w-full lg:w-auto">
                                    <div v-for="stat in benefit.stats" :key="stat.label" class="bg-white/5 rounded-2xl p-6 text-center border border-white/10 min-w-[140px]">
                                        <div class="text-3xl font-black italic text-white mb-2">{{ stat.value }}</div>
                                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ stat.label }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                 </section>

                <!-- How to Use Section -->
                <section v-if="currentSection === 'how-to-use'" class="animate-in fade-in space-y-24">
                    <div class="text-center space-y-6 pt-10">
                         <h1 class="text-6xl font-black italic tracking-tighter uppercase relative">
                             Operational <span class="text-emerald-400">Workflow</span>
                         </h1>
                         <p class="text-xl text-gray-400 font-bold uppercase tracking-widest max-w-2xl mx-auto">Initiate the Sovereign Protocol in 4 stages.</p>
                    </div>

                    <div class="relative pl-8 border-l border-white/10 space-y-20">
                        <div v-for="(step, idx) in howToUseSteps" :key="idx" class="relative group scroll-reveal opacity-0 translate-y-8 duration-700">
                             <div class="absolute -left-[41px] top-0 h-5 w-5 rounded-full border-4 border-[#050505] bg-emerald-500/50 group-hover:bg-emerald-400 group-hover:scale-125 transition-all shadow-[0_0_20px_rgba(16,185,129,0.5)]"></div>
                             
                             <div class="bg-[#0A0A0B] rounded-[2rem] p-8 lg:p-10 border border-white/5 group-hover:border-emerald-500/30 transition-all relative overflow-hidden">
                                 <div class="flex flex-col lg:flex-row gap-10 items-start">
                                     <div class="flex-1 space-y-4">
                                         <span class="text-[10px] font-black uppercase tracking-[0.3em] text-emerald-500">/// Sequence 0{{ idx + 1 }}</span>
                                         <h3 class="text-3xl font-black italic uppercase text-white">{{ step.title }}</h3>
                                         <p class="text-gray-400 font-bold uppercase tracking-widest leading-relaxed">{{ step.desc }}</p>
                                     </div>
                                     <div class="w-full lg:w-96 bg-[#050505] rounded-xl border border-white/10 p-4 font-mono text-xs text-emerald-400 shadow-inner overflow-x-auto">
                                         <div class="flex items-center gap-2 mb-2 border-b border-white/5 pb-2">
                                             <div class="h-2 w-2 rounded-full bg-red-500"></div>
                                             <div class="h-2 w-2 rounded-full bg-yellow-500"></div>
                                             <div class="h-2 w-2 rounded-full bg-green-500"></div>
                                             <span class="ml-auto text-[10px] text-gray-600 uppercase">bash</span>
                                         </div>
                                         <span class="text-gray-500">$</span> {{ step.cmd }}<span class="animate-pulse">_</span>
                                         <div class="mt-2 text-[10px] text-gray-500 uppercase tracking-widest">Status: <span class="text-emerald-500">{{ step.status }}</span></div>
                                     </div>
                                 </div>
                             </div>
                        </div>
                    </div>
                </section>

                <!-- Security Protocol Section (Revamped) -->
                <section v-if="currentSection === 'security-protocol'" class="animate-in fade-in space-y-24">
                    <div class="text-center space-y-6 pt-10">
                        <h1 class="text-6xl font-black italic tracking-tighter uppercase mb-8">The <span class="text-emerald-400">Shield</span> Architecture</h1>
                         <p class="text-xl text-gray-400 font-bold uppercase tracking-widest max-w-2xl mx-auto">Defense-in-depth layout ensuring absolute asset isolation.</p>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                         <!-- Feature 1 -->
                         <div class="col-span-1 lg:col-span-3 bg-gradient-to-br from-emerald-500/10 to-transparent p-1 rounded-[3rem]">
                             <div class="bg-[#0A0A0B] h-full rounded-[2.9rem] p-12 border border-emerald-500/20 relative overflow-hidden flex flex-col md:flex-row items-center gap-12">
                                 <div class="relative h-40 w-40 flex items-center justify-center">
                                     <div class="absolute inset-0 border-4 border-emerald-500/20 rounded-full animate-ping"></div>
                                     <svg class="h-20 w-20 text-emerald-400 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                                 </div>
                                 <div class="flex-1 space-y-4">
                                     <h3 class="text-3xl font-black italic uppercase text-white">Iso-Vault Technology</h3>
                                     <p class="text-gray-400 font-bold uppercase tracking-widest leading-relaxed">
                                         Every uploaded project is encapsulated in an ephemeral Docker container with zero network access (Air-Gapped). This prevents malicious code from "phoning home" or spreading laterally during the audit process.
                                     </p>
                                 </div>
                             </div>
                         </div>

                         <!-- Feature 2 -->
                         <div class="bg-[#0A0A0B] rounded-[2.5rem] p-10 border border-white/5 hover:border-emerald-500/30 transition-all">
                             <svg class="h-10 w-10 text-cyan-400 mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                             <h3 class="text-xl font-black italic uppercase text-white mb-4">Cryptographic Custody</h3>
                             <p class="text-xs font-bold uppercase tracking-widest text-gray-500">All artifacts are signed with SHA-256 hashes at ingress and egress, ensuring bit-perfect integrity.</p>
                         </div>

                           <!-- Feature 3 -->
                         <div class="bg-[#0A0A0B] rounded-[2.5rem] p-10 border border-white/5 hover:border-emerald-500/30 transition-all">
                             <svg class="h-10 w-10 text-purple-400 mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                             <h3 class="text-xl font-black italic uppercase text-white mb-4">Zero-Knowledge Proofs</h3>
                             <p class="text-xs font-bold uppercase tracking-widest text-gray-500">Prove asset ownership and validation status without revealing the underlying source code to public verifiers.</p>
                         </div>

                           <!-- Feature 4 -->
                         <div class="bg-[#0A0A0B] rounded-[2.5rem] p-10 border border-white/5 hover:border-emerald-500/30 transition-all">
                             <svg class="h-10 w-10 text-rose-400 mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                             <h3 class="text-xl font-black italic uppercase text-white mb-4">Kill-Switch Protocols</h3>
                             <p class="text-xs font-bold uppercase tracking-widest text-gray-500">Automated deletion of vault contents upon detection of unauthorized access attempts or security breaches.</p>
                         </div>
                    </div>
                </section>

                <!-- Roadmap (Existing) -->
                <section v-if="currentSection === 'roadmap'" class="animate-in fade-in slide-in-from-bottom-4 duration-700 pt-20">
                    <h1 class="text-6xl font-black italic tracking-tighter uppercase mb-4">Strategic <span class="bg-gradient-to-r from-cyan-400 to-emerald-400 bg-clip-text text-transparent">Evolution Plan</span></h1>
                    <p class="text-lg text-gray-500 font-bold uppercase tracking-widest mb-16 max-w-2xl">
                        Architectural timeline for the deployment of sovereign intelligence and decentralized security grids.
                    </p>
                    
                    <div class="relative space-y-16 pl-8 lg:pl-0">
                        <!-- Vertical Glowing Laser Line -->
                        <div class="absolute left-[38px] lg:left-1/2 top-0 bottom-0 w-1 bg-gradient-to-b from-emerald-500 via-cyan-500 to-purple-500 opacity-20 hidden lg:block"></div>
                        <div class="absolute left-8 top-0 bottom-0 w-1 bg-white/10 lg:hidden"></div>

                        <!-- Phases -->
                        <div 
                            v-for="(phase, index) in roadmapPhases" 
                            :key="index"
                            class="relative flex flex-col lg:flex-row items-center gap-12 group"
                        >
                            <!-- Center Node -->
                            <div class="absolute left-0 lg:left-1/2 -translate-x-1/2 z-10 hidden lg:flex h-8 w-8 rounded-full bg-[#050505] border-2 border-white/20 items-center justify-center transition-all duration-500 shadow-[0_0_20px_rgba(0,0,0,1)]"
                            :class="activePhase === index ? 'border-emerald-400 scale-125' : ''"
                            >
                                <div class="h-2 w-2 rounded-full transition-colors" :class="activePhase === index ? 'bg-emerald-400' : 'bg-white'"></div>
                            </div>

                            <!-- Content Card (Left or Right) -->
                            <div 
                                class="relative w-full lg:w-[calc(50%-40px)] transition-all duration-500"
                                :class="[
                                    index % 2 === 0 ? 'lg:mr-auto' : 'lg:ml-auto lg:order-last',
                                    activePhase === index ? 'scale-105' : 'hover:-translate-y-2'
                                ]"
                            >
                                <button 
                                    @click="togglePhase(index)"
                                    class="w-full text-left h-full bg-[#0A0A0B] rounded-[2.4rem] p-8 lg:p-10 border transition-all overflow-hidden relative group"
                                    :class="activePhase === index ? 'border-emerald-500/50 shadow-[0_0_50px_rgba(16,185,129,0.1)]' : 'border-white/5 hover:border-emerald-500/30'"
                                >
                                    <div class="absolute -right-20 -top-20 h-64 w-64 blur-[100px] opacity-20 rounded-full transition-colors duration-500" :class="phase.statusColor"></div>
                                    
                                    <div class="relative z-10">
                                        <div class="flex items-center justify-between mb-6">
                                            <span class="px-4 py-1.5 rounded-full border border-white/10 bg-white/5 text-[10px] font-black uppercase tracking-[0.2em] text-gray-300">
                                                {{ phase.timeline }}
                                            </span>
                                            <div class="flex items-center gap-2">
                                                <span class="relative flex h-2 w-2" v-if="phase.status === 'LIVE'">
                                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 bg-emerald-400"></span>
                                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                                </span>
                                                <span class="text-[10px] font-black uppercase tracking-widest text-white/60">
                                                    [{{ phase.status }}]
                                                </span>
                                            </div>
                                        </div>

                                        <div class="mb-8">
                                            <h3 class="text-2xl font-black italic tracking-tighter text-white uppercase mb-2 group-hover:text-transparent group-hover:bg-clip-text group-hover:bg-gradient-to-r group-hover:from-white group-hover:to-gray-400 transition-all">
                                                {{ phase.phase }}
                                            </h3>
                                            <p class="text-sm font-bold text-gray-500 uppercase tracking-widest leading-relaxed border-b border-white/5 pb-4 mb-4">
                                                {{ phase.value }}
                                            </p>
                                        </div>

                                        <ul class="space-y-3 mb-8">
                                            <li 
                                                v-for="(feature, fIndex) in phase.features" 
                                                :key="fIndex"
                                                class="flex items-center gap-3 text-xs font-bold uppercase tracking-wider text-gray-400 prose-sm group-hover:text-gray-300 transition-colors"
                                            >
                                                <svg class="h-4 w-4 text-emerald-500/50 group-hover:text-emerald-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                {{ feature }}
                                            </li>
                                        </ul>

                                        <div class="flex items-center justify-between mt-auto pt-6 border-t border-white/5">
                                            <svg class="h-8 w-8 text-gray-600 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="phase.icon" />
                                            </svg>
                                            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 group-hover:text-emerald-400 transition-colors flex items-center gap-2">
                                                {{ activePhase === index ? 'Close Intel' : 'View Deep Dive' }}
                                                <svg class="w-3 h-3 transition-transform" :class="activePhase === index ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                            </span>
                                        </div>
                                    </div>
                                </button>
                            </div>

                            <!-- Details Panel -->
                            <Transition name="panel-slide">
                                <div 
                                    v-if="activePhase === index"
                                    class="relative w-full lg:w-[calc(50%-40px)]"
                                    :class="index % 2 === 0 ? '' : 'lg:order-first'"
                                >
                                    <div class="bg-[#0A0A0B]/80 backdrop-blur-3xl rounded-[2.4rem] p-8 lg:p-10 border border-emerald-500/20 relative overflow-hidden">
                                        <div class="absolute inset-0 bg-grid-white/[0.02] bg-[length:20px_20px]"></div>
                                        <div class="relative z-10">
                                            <h4 class="text-emerald-400 font-black uppercase tracking-widest text-xs mb-4">Technical Deep Dive</h4>
                                            <p class="text-sm font-bold text-gray-400 leading-loose uppercase">
                                                {{ phase.deepDive }}
                                            </p>
                                            <div class="mt-6 pt-6 border-t border-white/5 flex items-center justify-end">
                                                <button v-if="phase.status !== 'LIVE'" class="px-6 py-3 rounded-xl bg-white/5 hover:bg-emerald-500 hover:text-black border border-white/10 hover:border-emerald-500 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 transition-all">
                                                    Notify Me
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </section>

                <!-- White Paper Section -->
                <section v-if="currentSection === 'white-paper'" class="animate-in fade-in space-y-24">
                    <div class="text-center space-y-6 pt-10">
                        <span class="px-4 py-1.5 rounded-full border border-white/10 bg-white/5 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">
                            Version 1.0.4 - Release Candidate
                        </span>
                        <h1 class="text-6xl font-black italic tracking-tighter uppercase mb-4">The Sovereign <span class="bg-gradient-to-r from-white to-gray-500 bg-clip-text text-transparent">Manifesto</span></h1>
                        <p class="text-xl text-gray-400 font-bold uppercase tracking-widest max-w-2xl mx-auto">
                            A technical deep dive into the consensus mechanisms and cryptographic proofs powering the LUME Network.
                        </p>
                        <div class="pt-8">
                             <button class="px-8 py-4 rounded-xl bg-white text-black font-black uppercase tracking-widest hover:bg-emerald-400 transition-colors shadow-[0_0_30px_rgba(255,255,255,0.1)] hover:shadow-[0_0_30px_rgba(16,185,129,0.4)] flex items-center gap-3 mx-auto">
                                 <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                 Download Technical PDF
                             </button>
                        </div>
                    </div>

                    <div class="max-w-4xl mx-auto space-y-20">
                        <!-- Chapter 1 -->
                        <div class="space-y-8 pb-12 border-b border-white/5">
                            <h2 class="text-4xl font-black italic uppercase text-white">01. The Trust <span class="text-red-500">Deficit</span></h2>
                            <div class="prose prose-invert prose-lg max-w-none text-gray-400 font-medium leading-loose">
                                <p>
                                    In the current digital economy, the verification of asset integrity is a centralized, opaque, and highly fallible process. Buyers of software, intellectual property, and digital infrastructure are forced to trust "Black Box" deliverables without the means to independently verify their security posture, ownership history, or technical debt.
                                </p>
                                <p>
                                    This "Trust Deficit" introduces systemic risk, stifles liquidity, and creates a market for lemons where high-quality engineering is indistinguishable from malicious or incompetent code. LUME proposes a trustless alternative: a decentralized verification layer that cryptographically guarantees the "soul" of every digital asset.
                                </p>
                            </div>
                        </div>

                        <!-- Chapter 2 -->
                        <div class="space-y-8 pb-12 border-b border-white/5">
                            <h2 class="text-4xl font-black italic uppercase text-white">02. The Sovereign <span class="text-emerald-400">Engine</span></h2>
                            <div class="prose prose-invert prose-lg max-w-none text-gray-400 font-medium leading-loose">
                                <p>
                                    At the core of the LUME Network lies the Sovereign Engine, a multi-modal forensic analysis unit. Unlike static code analyzers, the Sovereign Engine operates within an air-gapped <strong>Iso-Vault</strong> container. It performs three layers of verification:
                                </p>
                                <ul class="list-disc pl-4 space-y-2 marker:text-emerald-500">
                                    <li><strong>Static Heuristics:</strong> Pattern matching against a proprietary database of 14,000+ known vulnerability signatures (CVEs) and malicious code fragments.</li>
                                    <li><strong>Dynamic Fuzzing:</strong> Active runtime simulation to detect logic bombs, race conditions, and unauthorized network calls.</li>
                                    <li><strong>Ownership Provenance:</strong> Cross-referencing git commit history with verified developer identities to build a Chain of Custody graph.</li>
                                </ul>
                            </div>
                            
                            <!-- Visualization Placeholder -->
                            <div class="w-full h-64 border border-white/10 rounded-2xl bg-[#050505] relative overflow-hidden flex items-center justify-center">
                                <div class="absolute inset-0 bg-grid-white/[0.02]"></div>
                                <div class="text-[10px] font-black uppercase tracking-widest text-gray-600">Fig 2.1: Iso-Vault Architecture Diagram</div>
                            </div>
                        </div>

                         <!-- Chapter 3 -->
                        <div class="space-y-8 pb-12 border-b border-white/5">
                            <h2 class="text-4xl font-black italic uppercase text-white">03. Proof of <span class="text-cyan-400">Audit</span></h2>
                            <div class="prose prose-invert prose-lg max-w-none text-gray-400 font-medium leading-loose">
                                <p>
                                    Upon the completion of a Sovereign Scan, the engine generates a <strong>Proof of Audit (PoA)</strong>. This is a non-fungible cryptographic artifact minted on the LUME Ledger. The PoA asserts that a specific snapshot of code (identified by its SHA-256 Merkle Root) has passed specific verification vectors.
                                </p>
                                <p>
                                    This certificate is immutable and composable. It can be embedded into smart contracts to automate payment release (Escrow-as-a-Service), ensuring that funds are only transferred when the asset is proven secure.
                                </p>
                            </div>
                        </div>

                        <!-- Chapter 4 -->
                        <div class="space-y-8 pb-12 border-b border-white/5">
                            <h2 class="text-4xl font-black italic uppercase text-white">04. The <span class="text-purple-400">Consensus</span></h2>
                            <div class="prose prose-invert prose-lg max-w-none text-gray-400 font-medium leading-loose">
                                <p>
                                    LUME utilizes a Federated Proof-of-Compute (PoC) consensus mechanism. Verified "Watcher Nodes" validate the execution traces of the Sovereign Engine to ensure that the audit results have not been tampered with. This decentralized verification layer removes the single point of failure and ensures that LUME itself remains a neutral arbiter of truth.
                                </p>
                            </div>
                        </div>

                        <!-- Chapter 5 -->
                        <div class="space-y-8">
                            <h2 class="text-4xl font-black italic uppercase text-white">05. The Offensive <span class="text-rose-500">Neural Grid</span></h2>
                            <div class="prose prose-invert prose-lg max-w-none text-gray-400 font-medium leading-loose">
                                <p>
                                    Passive analysis is insufficient for modern threats. LUME introduces the <strong>Offensive Neural Grid</strong>, a specialized module for active penetration testing. This system utilizes <strong>Local Large Language Models (LLMs)</strong> engaged within the user's local environment or the secure vault to orchestrate complex attack simulations.
                                </p>
                                <p>
                                    <strong>White Hat Brute Force Protocol:</strong>
                                    Unlike dumb dictionary attacks, the LUME AI analyzes the target's semantic structure (variable names, business logic) to generate probability-weighted password candidates and attack patterns. This allows for high-efficiency stress testing of authentication gates via simulated PowerShell and Bash terminal interfaces, identifying weak security policies before adversaries can exploit them.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Fallback for other sections -->
                <section v-if="!['introduction', 'features', 'benefits', 'security-protocol', 'roadmap', 'how-to-use', 'white-paper'].includes(currentSection)" class="mt-20 text-center py-20 bg-white/[0.02] border border-dashed border-white/10 rounded-[3rem]">
                    <h2 class="text-3xl font-black italic tracking-tighter uppercase text-gray-700 mb-4">Transmission Pending</h2>
                    <p class="text-[10px] font-black uppercase tracking-[.4em] text-gray-800">Section {{ currentSection.replace('-', ' ') }} is currently being encrypted for public release.</p>
                </section>
            </div>

            <!-- Global Navigation Footer -->
            <div v-if="['introduction', 'features', 'benefits', 'how-to-use', 'security-protocol', 'roadmap', 'white-paper'].includes(currentSection)" class="mt-32 border-t border-white/5 pt-12">
                <div class="flex items-center justify-between">
                    <button 
                        v-if="prevSection"
                        @click="setSection(prevSection.id)"
                        class="group flex items-center gap-4 text-left"
                    >
                         <div class="h-12 w-12 rounded-full border border-white/10 bg-white/5 flex items-center justify-center group-hover:bg-white/10 group-hover:border-white/30 transition-all">
                             <svg class="h-5 w-5 text-gray-400 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                         </div>
                         <div>
                             <div class="text-[10px] font-black uppercase tracking-widest text-gray-600 group-hover:text-gray-400 transition-colors">Previous Directive</div>
                             <div class="text-lg font-black italic uppercase text-white tracking-tighter">{{ prevSection.title }}</div>
                         </div>
                    </button>
                    <div v-else></div> <!-- Spacer -->

                    <button 
                        v-if="nextSection"
                        @click="setSection(nextSection.id)"
                        class="group flex items-center gap-4 text-right"
                    >
                        <div>
                             <div class="text-[10px] font-black uppercase tracking-widest text-gray-600 group-hover:text-emerald-400 transition-colors">Next Directive</div>
                             <div class="text-lg font-black italic uppercase text-white tracking-tighter">{{ nextSection.title }}</div>
                         </div>
                         <div class="h-12 w-12 rounded-full border border-emerald-500/20 bg-emerald-500/10 flex items-center justify-center group-hover:bg-emerald-500 group-hover:text-black transition-all shadow-[0_0_20px_rgba(16,185,129,0.1)]">
                             <svg class="h-5 w-5 text-emerald-400 group-hover:text-black transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                         </div>
                    </button>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
@keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes slide-in-from-bottom { from { transform: translateY(2rem); } to { transform: translateY(0); } }
@keyframes gradient { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }

.animate-in { animation: fade-in 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards, slide-in-from-bottom 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.animate-gradient { animation: gradient 3s ease infinite; }
.prose-sm { max-width: 65ch; }

.animate-slide-up { animation: fade-in 0.8s ease-out forwards, slide-in-from-bottom 0.8s ease-out forwards; }

/* Panel Animation */
.panel-slide-enter-active,
.panel-slide-leave-active {
  transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}

.panel-slide-enter-from,
.panel-slide-leave-to {
  opacity: 0;
  transform: scale(0.95) translateY(10px);
}
</style>
