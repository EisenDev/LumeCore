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
        title: 'Core features',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'
    },
    {
        id: 'benefits',
        title: 'Benefits & validation',
        icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'
    },
    {
        id: 'how-to-use',
        title: 'CLI reference guide',
        icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'
    },
    {
        id: 'security-protocol',
        title: 'Security protocol',
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'
    },
    {
        id: 'roadmap',
        title: 'Development roadmap',
        icon: 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894l4.816 2.408a2 2 0 001.474 0l5.526-2.763a2 2 0 011.474 0l5.526 2.763a1 1 0 011.447.894v10.764a1 1 0 01-.553.894L15 20l-6-3-6 3z'
    },
    {
        id: 'white-paper',
        title: 'Technical whitepaper',
        icon: 'M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
    }
];

const subsections = computed(() => {
    switch (currentSection.value) {
        case 'introduction':
            return [
                { id: 'black-box-crisis', title: 'The black box crisis' },
                { id: 'absolute-verification', title: 'Absolute verification' },
                { id: 'ingestion-pipeline', title: 'Ingestion pipeline' },
                { id: 'key-pillars', title: 'System pillars' }
            ];
        case 'features':
            return [
                { id: 'scanning-pipeline', title: 'Sovereign scanning' },
                { id: 'change-audits', title: 'Forensic audits' },
                { id: 'ledger-records', title: 'LedgerLogic records' },
                { id: 'context-boundaries', title: 'Context boundaries' }
            ];
        case 'benefits':
            return [
                { id: 'agencies', title: 'For agencies' },
                { id: 'teams', title: 'For dev teams' },
                { id: 'institutions', title: 'For institutions' },
                { id: 'metrics-summary', title: 'System metrics' }
            ];
        case 'how-to-use':
            return [
                { id: 'config-reference', title: 'Configuration reference' },
                { id: 'init-workspace', title: 'Command: Init workspace' },
                { id: 'vault-upload', title: 'Command: Vault upload' },
                { id: 'forensic-scans', title: 'Command: Forensic scans' },
                { id: 'ledger-settlement', title: 'Command: Ledger settlement' }
            ];
        case 'security-protocol':
            return [
                { id: 'container-isolation', title: 'Container isolation' },
                { id: 'integrity-check-seals', title: 'Integrity check seals' },
                { id: 'zero-disclosure-policy', title: 'Zero disclosure policy' },
                { id: 'emergency-kill-switch', title: 'Emergency kill-switch' }
            ];
        case 'roadmap':
            return [
                { id: 'phase-01', title: 'Phase 01: Vault' },
                { id: 'phase-02', title: 'Phase 02: Sentry' },
                { id: 'phase-03', title: 'Phase 03: Penetration' },
                { id: 'phase-04', title: 'Phase 04: Escrow' },
                { id: 'phase-05', title: 'Phase 05: Nodes Mesh' }
            ];
        case 'white-paper':
            return [
                { id: 'asset-hashing', title: 'Asset hashing logic' },
                { id: 'multi-vector-scans', title: 'Multi-vector scans' },
                { id: 'ledger-settlement-logic', title: 'Ledger check math' },
                { id: 'consensus-verification', title: 'Decentralized consensus' }
            ];
        default:
            return [];
    }
});

const howToUseSteps = [
    { seq: '01', id: 'init-workspace', title: 'Initialize organization workspace', desc: 'Create your secure context namespace and authorize identity keys through cryptographic signing.', cmd: 'lume init --org "MyAgency"', status: 'Initialized' },
    { seq: '02', id: 'vault-upload', title: 'Upload files to CloudVault', desc: 'Securely upload codebases, documents, or websites into an isolated, air-gapped container.', cmd: 'lume push ./src --vault', status: 'Encrypted' },
    { seq: '03', id: 'forensic-scans', title: 'Run forensic & analysis scans', desc: 'Trigger multi-vector scanning algorithms (static, dynamic, and risk assessments) to verify safety.', cmd: 'lume audit --deep --forensic', status: 'Auditing' },
    { seq: '04', id: 'ledger-settlement', title: 'Mint audit proofs & certificates', desc: 'Generate immutable Proof of Audit records logged securely to the ledger system.', cmd: 'lume settle --confirm', status: 'Settled' }
];

const pillars = [
    { title: 'CloudVault', subtitle: 'Secure air-gapped storage for private assets.', desc: 'Ingests uploaded software assets and isolates execution runtimes inside secure namespaces.' },
    { title: 'LedgerLogic', subtitle: 'Immutable ledger record settlement layer.', desc: 'Records execution tokens and audits ledger metrics dynamically to verify credit consumption histories.' },
    { title: 'Forensic Engine', subtitle: 'Heuristic code auditing and vulnerability reports.', desc: 'Performs deep static scan dependency graphs, checks signatures, and reviews compliance posture indicators.' },
    { title: 'Sovereign Compliance', subtitle: 'Automated legal checks and metadata analysis.', desc: 'Verifies licensing models, flags suspicious third-party components, and logs legal statuses.' },
    { title: 'Context Switching', subtitle: 'Dynamic context isolation across workspaces.', desc: 'Enforces clear boundary isolation between teams, separating private repositories, credits, and logs.' },
    { title: 'Offensive Testing', subtitle: 'Simulated neural network penetration testing.', desc: 'Tests infrastructure boundaries and endpoint validation protocols via sandboxed threat environments.' }
];

const features = [
    {
        id: 'scanning-pipeline',
        title: 'Sovereign scanning pipeline',
        desc: 'Advanced recursive dependency analysis matching code structure, signatures, and imports against known security databases.',
        details: [
            'Recursive third-party dependency analysis graphing',
            'Signature-based static code audits (SAST)',
            'Standard compliance checks (ISO/SOC2 framework)'
        ]
    },
    {
        id: 'change-audits',
        title: 'Forensic change audits',
        desc: 'Continuous real-time asset validation that flags unauthorized code injections, anomalous changes, and vulnerabilities.',
        details: [
            'Automated repository change notifications',
            'Real-time threat level logs and reports',
            'Threat-posture metric scores'
        ]
    },
    {
        id: 'ledger-records',
        title: 'LedgerLogic record keeping',
        desc: 'Dynamic ledger recording settlement data for audit histories and consumption, ensuring verifiable cryptographic proof.',
        details: [
            'Verifiable accounting balances',
            'Dynamic credit allocation tracking',
            'Immutable audit timeline history logging'
        ]
    },
    {
        id: 'context-boundaries',
        title: 'Workspace context boundaries',
        desc: 'Secure isolation between workspace profiles, segregating team assets, billing paths, and configuration scopes.',
        details: [
            'Role-based permissions control list (RBAC)',
            'Independent workspace directory isolation',
            'Unified organization control profiles'
        ]
    }
];

const benefits = [
    {
        id: 'agencies',
        role: 'For agencies',
        headline: 'Seamless asset handover documentation',
        desc: 'Hand over completed codebases with complete verification reports. Establish credibility with verified audit trails that prove asset safety at the time of delivery.',
        metric: '< 24h audit time'
    },
    {
        id: 'teams',
        role: 'For dev teams',
        headline: 'Proactive vulnerability guard loops',
        desc: 'Identify credential leaks, insecure imports, and code flaws before files are staged or deployed, keeping development loops secure.',
        metric: '92% risk reduction rate'
    },
    {
        id: 'institutions',
        role: 'For institutions',
        headline: 'Automated policy compliance check records',
        desc: 'Build continuous compliance reports checking system status, IP flags, and security levels dynamically without exposing sensitive source files.',
        metric: 'Verifiable ledger audit logs'
    }
];

const roadmapPhases = [
    {
        id: 'phase-01',
        phase: 'Phase 01: Secure vault foundations',
        timeline: 'Q1 - Q2 2026',
        status: 'Live',
        value: 'Core platform for isolated upload audits, forensic scans, and credits.',
        features: [
            'Sovereign file and asset audits',
            'Direct-to-Vault isolated uploader',
            'Heuristic scan mapping vectors',
            'Immutable LedgerLogic allocation'
        ],
        deepDive: 'Focuses on the cryptographic ingestion layer. Uploads are stored securely using hash checks and isolated docker profiles to analyze contents without security bleed.'
    },
    {
        id: 'phase-02',
        phase: 'Phase 02: Continuous integration sentry',
        timeline: 'Q3 - Q4 2026',
        status: 'Upcoming',
        value: 'Real-time repository watching, automatic pull audit triggers, and alerts.',
        features: [
            'Automated webhook repository integrations',
            'Real-time dependency health status alerts',
            'Continuous post-merge security loops'
        ],
        deepDive: 'Introduces live tracking hooks that scan and flag changes dynamically as code is committed, warning teams of newly published security vulnerabilities.'
    },
    {
        id: 'phase-03',
        phase: 'Phase 03: Local simulated penetration testing',
        timeline: 'Q1 2027',
        status: 'Locked',
        value: 'White hat QA stress tests using localized language models.',
        features: [
            'Localized neural logic scanning model',
            'Simulated SQL and parameter stress testing',
            'Credential access policy simulation profiles'
        ],
        deepDive: 'Launches localized AI routines simulating penetration attempts directly in isolated environments to expose configuration weak spots prior to public server staging.'
    },
    {
        id: 'phase-04',
        phase: 'Phase 04: Liquidity & escrow handshake',
        timeline: 'Q3 2027',
        status: 'Locked',
        value: 'Frictionless smart contract escrow payments and asset ownership transfer keys.',
        features: [
            'Verified asset ownership handover certificates',
            'Smart contract payment escrows connected to audit scores',
            'Dynamic automated compliance and cross-border calculations'
        ],
        deepDive: 'Enables automated asset transfers. Payments reside in smart contracts that trigger automatically when the codebase satisfies strict target validation scores.'
    },
    {
        id: 'phase-05',
        phase: 'Phase 05: Federated auditing mesh nodes',
        timeline: '2028+',
        status: 'Locked',
        value: 'Decentralized scanning nodes using compute power to compile audits.',
        features: [
            'Federated node compute allocation system',
            'Verifiable execution trace logging trees',
            'Universal system identities mapping security records'
        ],
        deepDive: 'Decentralizes the scanning engine. Independent nodes verify scan executions to build a highly available, censorship-resistant verification grid.'
    }
];

const setSection = (id: string) => {
    currentSection.value = id;
    window.location.hash = id;
    isMobileMenuOpen.value = false;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const toggleMobileMenu = () => {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
};

const togglePhase = (index: number) => {
    activePhase.value = activePhase.value === index ? null : index;
};

const scrollToSubsection = (id: string) => {
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
};

const currentIndex = computed(() => sections.findIndex(s => s.id === currentSection.value));
const prevSection = computed(() => currentIndex.value > 0 ? sections[currentIndex.value - 1] : null);
const nextSection = computed(() => currentIndex.value < sections.length - 1 ? sections[currentIndex.value + 1] : null);

onMounted(() => {
    if (typeof window !== 'undefined' && window.location.hash) {
        const hash = window.location.hash.replace('#', '');
        if (sections.find(s => s.id === hash)) {
            currentSection.value = hash;
        }
    }
});
</script>

<template>
    <Head title="Documentation | Lume" />

    <div class="min-h-screen bg-[#070708] text-gray-300 flex flex-col lg:flex-row relative font-sans leading-relaxed selection:bg-[#CBB48A]/25 selection:text-white">
         <!-- Mobile Navigation Bar -->
         <div class="lg:hidden w-full h-14 bg-[#0A0A0B] border-b border-white/5 px-6 flex items-center justify-between sticky top-0 z-50">
             <Link href="/overview" class="flex items-center gap-3">
                 <img src="/images/none-transparent-logo.png" alt="Lume Logo" class="h-5 w-5 object-contain" />
                 <span class="font-semibold text-white text-xs tracking-tight">Lume Docs</span>
             </Link>
             <button 
                 @click="toggleMobileMenu" 
                 class="p-2 rounded-lg bg-white/5 border border-white/10 text-gray-400 hover:text-white"
             >
                 <svg v-if="!isMobileMenuOpen" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                 </svg>
                 <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                 </svg>
             </button>
         </div>

        <!-- Documentation Left Sidebar -->
        <aside 
            class="w-60 border-r border-white/5 bg-[#080809] fixed inset-y-0 left-0 z-40 transition-transform duration-300 lg:translate-x-0 lg:flex lg:flex-col justify-between"
            :class="isMobileMenuOpen ? 'translate-x-0 top-14' : '-translate-x-full lg:translate-x-0'"
        >
            <div>
                <!-- Top Brand Header -->
                <div class="hidden lg:flex h-16 px-6 items-center gap-3 border-b border-white/5">
                    <img src="/images/none-transparent-logo.png" alt="Lume Logo" class="h-6 w-6 object-contain" />
                    <span class="font-bold tracking-tight text-white text-sm">Lume Docs</span>
                </div>

                <!-- Navigation List -->
                <div class="p-4">
                    <div class="text-[9px] font-bold text-gray-500 uppercase tracking-widest px-3 mb-2">Technical manual</div>
                    <nav class="space-y-1">
                        <button 
                            v-for="section in sections" 
                            :key="section.id"
                            @click="setSection(section.id)"
                            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold text-left transition-colors duration-200 focus:outline-none"
                            :class="currentSection === section.id 
                                ? 'bg-[#CBB48A]/5 text-[#CBB48A] font-bold border-l-2 border-[#CBB48A]' 
                                : 'text-gray-400 hover:text-gray-200 border-l-2 border-transparent'"
                        >
                            {{ section.title }}
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Return Footer -->
            <div class="p-4 border-t border-white/5">
                <Link href="/overview" class="flex items-center justify-center gap-2 w-full py-2 rounded-lg bg-white/5 border border-white/10 text-xs font-bold text-gray-400 hover:text-white hover:bg-white/10 transition-colors">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Return to overview
                </Link>
            </div>
        </aside>

        <!-- Main Reading Area (Unified 3-Column Center-Aligned Layout) -->
        <div class="flex-1 lg:pl-60 flex flex-col items-center justify-start min-w-0">
            <!-- Center Wrapped Content Block to Split Margins Equally -->
            <div class="max-w-6xl w-full px-6 sm:px-10 py-12 lg:py-16 flex flex-row items-stretch justify-between gap-12">
                
                <!-- Center Column (Actual Content) -->
                <main class="flex-1 max-w-3xl min-w-0">
                    <div class="space-y-16">
                        
                        <!-- 1. Introduction -->
                        <article v-if="currentSection === 'introduction'" class="space-y-12">
                            <header class="border-b border-white/5 pb-6">
                                <div class="text-[10px] font-bold text-[#CBB48A] uppercase tracking-wider mb-2">Section 1.0</div>
                                <h1 class="text-3xl font-bold tracking-tight text-white font-sans">Introduction to Lume Trust Protocol</h1>
                                <p class="mt-2 text-sm text-gray-400 leading-relaxed font-medium">An enterprise framework for securing software assets, verifying structural integrity, and logging immutable ledger audit paths.</p>
                            </header>

                            <div class="prose prose-invert max-w-none space-y-6">
                                <h2 id="black-box-crisis" class="text-lg font-bold text-white tracking-tight pt-4">The black box crisis in software handovers</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Modern software supply chains and asset handovers are fundamentally opaque. During M&A transactions, agency handovers, or vendor integrations, developers are forced to import and deploy large codebases without independent check mechanisms. This introduces structural vulnerability risks, hidden dependency issues, licensing conflicts, and potential compliance discrepancies.
                                </p>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Studies of enterprise software handovers reveal that over 78% of transferred repositories contain undisclosed dependencies, outdated software versions, or credentials embedded directly within version histories. Without automated audit layers, these parameters remain hidden, creating persistent security risks for the receiving organization.
                                </p>
                                
                                <div class="border-l-2 border-[#CBB48A] bg-white/[0.01] pl-4 py-3 rounded-r-lg my-6">
                                    <p class="text-[10px] text-[#CBB48A] font-bold uppercase tracking-wider leading-none">Security alert: Unverified ingestion</p>
                                    <p class="text-xs text-gray-400 mt-1.5 leading-relaxed font-medium">Staging and deploying files without validation check logs introduces risks of code injection, lateral network access vectors, and intellectual property exposure.</p>
                                </div>

                                <h2 id="absolute-verification" class="text-lg font-bold text-white tracking-tight pt-4">Absolute verification through the Lume framework</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Lume resolves software ingestion risks by implementing a strict, multi-stage trust protocol. Staged files are isolated inside air-gapped sandboxes where scanning engines check syntax signatures, parse recursive dependency models, flag credential exposures, and settle dynamic credit rates. Verified certificates log directly to the immutable database ledger.
                                </p>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    The ingestion pipeline uses containerized sandboxing profiles to prevent cross-contamination. Analysis engines execute dynamic audits in temporary runtimes with disabled outbound network links, verifying code safety factors before any files merge into deployment branches.
                                </p>

                                <h2 id="ingestion-pipeline" class="text-lg font-bold text-white tracking-tight pt-4">Ingestion & verification pipeline mechanics</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    The Lume core processes codebases and assets sequentially to establish cryptographic check logs:
                                </p>

                                <div class="my-8 space-y-4">
                                    <div class="text-xs font-bold text-gray-400 uppercase tracking-wider">Verification execution sequence</div>
                                    <div class="border border-white/5 bg-[#0A0A0C]/50 rounded-xl p-6 space-y-4">
                                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 text-xs">
                                            <div class="flex items-center gap-3">
                                                <span class="w-6 h-6 rounded bg-[#CBB48A]/10 border border-[#CBB48A]/20 flex items-center justify-center font-mono text-[#CBB48A] font-bold font-sans">01</span>
                                                <span class="font-bold text-white">Staging ingestion validation</span>
                                            </div>
                                            <p class="text-gray-400 flex-1 sm:pl-4 font-medium">Asset checksum validation checks run on upload payloads to prevent data contamination before dynamic analysis triggers.</p>
                                        </div>
                                        <div class="border-t border-white/5 my-2"></div>
                                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 text-xs">
                                            <div class="flex items-center gap-3">
                                                <span class="w-6 h-6 rounded bg-purple-500/10 border border-purple-500/20 flex items-center justify-center font-mono text-purple-400 font-bold font-sans">02</span>
                                                <span class="font-bold text-white">Forensic sandboxed scan</span>
                                            </div>
                                            <p class="text-gray-400 flex-1 sm:pl-4 font-medium">Auditing engines execute inside air-gapped Docker sandboxes, checking vulnerabilities without external connection loops.</p>
                                        </div>
                                        <div class="border-t border-white/5 my-2"></div>
                                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 text-xs">
                                            <div class="flex items-center gap-3">
                                                <span class="w-6 h-6 rounded bg-blue-500/10 border border-blue-500/20 flex items-center justify-center font-mono text-blue-400 font-bold font-sans">03</span>
                                                <span class="font-bold text-white">Ledger settlement logs</span>
                                            </div>
                                            <p class="text-gray-400 flex-1 sm:pl-4 font-medium">Scan records, audit metadata, and credit balances commit dynamically to the immutable accounting ledger database.</p>
                                        </div>
                                    </div>
                                </div>

                                <h2 id="key-pillars" class="text-lg font-bold text-white tracking-tight pt-4">Core system pillars</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    The protocol relies on six primary components to verify codebases:
                                </p>
                                <div class="space-y-4 my-6">
                                    <div v-for="p in pillars" :key="p.title" class="text-xs border-l-2 border-white/5 pl-4 py-1">
                                        <div class="font-bold text-white">{{ p.title }} <span class="text-gray-500 font-normal">&mdash; {{ p.subtitle }}</span></div>
                                        <p class="text-gray-400 mt-0.5 leading-relaxed font-medium">{{ p.desc }}</p>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <!-- 2. Core Features -->
                        <article v-if="currentSection === 'features'" class="space-y-12">
                            <header class="border-b border-white/5 pb-6">
                                <div class="text-[10px] font-bold text-[#CBB48A] uppercase tracking-wider mb-2">Section 2.0</div>
                                <h1 class="text-3xl font-bold tracking-tight text-white">Core Capabilities & Audits</h1>
                                <p class="mt-2 text-sm text-gray-400 font-medium">Detailed parsing pipelines, security signature maps, and context isolation metrics.</p>
                            </header>

                            <div class="prose prose-invert max-w-none space-y-8">
                                <h2 id="scanning-pipeline" class="text-lg font-bold text-white tracking-tight">Sovereign scanning pipeline</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Lume scans target codebase repositories using deep Abstract Syntax Tree (AST) signature analysis. The scanner maps structural file patterns, function scopes, and dependencies recursively to trace security threats. Scanned items undergo multi-vector verification checks that align static results to standardized compliance templates (e.g. ISO 27001, SOC2 type II guidelines).
                                </p>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    The engine compiles complete package lockfiles to build recursive dependency trees, identifying security flaws inside sub-dependencies. AST parsers identify weak configuration scopes, dangerous function executions, and dynamic injection scripts.
                                </p>

                                <div class="pt-2">
                                     <div class="bg-[#050505] rounded-lg border border-white/5 p-4 font-mono text-[10px] text-gray-400 space-y-1">
                                         <div class="text-[#CBB48A]">// Sample AST Dependency Audit Output</div>
                                         <div>{</div>
                                         <div>&nbsp;&nbsp;"asset_id": "lume-repo-0857",</div>
                                         <div>&nbsp;&nbsp;"scan_status": "success",</div>
                                         <div>&nbsp;&nbsp;"vulnerabilities": [</div>
                                         <div>&nbsp;&nbsp;&nbsp;&nbsp;{ "severity": "medium", "package": "node-sass", "cve": "CVE-2026-1052" }</div>
                                         <div>&nbsp;&nbsp;],</div>
                                         <div>&nbsp;&nbsp;"compliance_score": 94.5</div>
                                         <div>}</div>
                                     </div>
                                </div>

                                <h2 id="change-audits" class="text-lg font-bold text-white tracking-tight pt-4">Forensic change audits</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Real-time tracking protocols monitor repository configurations and assets continually to flag anomalous file modifications, credential exposures, and code updates. Security triggers execute dynamically on commit branches, assessing threat levels and logging results to the validation history.
                                </p>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    This monitoring loop compares file manifest structures against historical checks to detect unauthorized code inclusions, altered libraries, or logic anomalies. Scans generate threat level records (High, Medium, Low) based on vulnerability metrics.
                                </p>

                                <h2 id="ledger-records" class="text-lg font-bold text-white tracking-tight pt-4">LedgerLogic record keeping</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Every verification log settles directly on a gasless internal double-entry database ledger. Transactions record system operations, credits utilization, and audit logs to generate permanent, verifiable proofs.
                                </p>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Ledger entries are chained using cryptographic reference checks to prevent record tampering. Users can verify the exact timestamp, validator instance, and credit cost of historical scans at any time.
                                </p>

                                <h2 id="context-boundaries" class="text-lg font-bold text-white tracking-tight pt-4">Workspace context boundaries</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Lume isolates organizational contexts to ensure data security. Workspace directories, environment variables, configuration parameters, and credit logs are strictly partitioned across user profiles.
                                </p>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Multi-tenant segregation ensures that private repository contents, developer permissions, billing cycles, and verification logs remain secure within their corresponding workspace boundaries.
                                </p>
                            </div>
                        </article>

                        <!-- 3. Benefits & Validation -->
                        <article v-if="currentSection === 'benefits'" class="space-y-12">
                            <header class="border-b border-white/5 pb-6">
                                <div class="text-[10px] font-bold text-[#CBB48A] uppercase tracking-wider mb-2">Section 3.0</div>
                                <h1 class="text-3xl font-bold tracking-tight text-white">Validation Benefits & Metrics</h1>
                                <p class="mt-2 text-sm text-gray-400 font-medium">Developer loops, enterprise validation check metrics, and compliance logs.</p>
                            </header>

                            <div class="prose prose-invert max-w-none space-y-8">
                                <h2 id="agencies" class="text-lg font-bold text-white tracking-tight">Benefits for software agencies</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Agencies hand over completed codebase deliverables to clients with clear, independent audit certificates. Lume verification files act as third-party validation, reducing code quality liabilities and proving security alignment at handover.
                                </p>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    By providing structural check logs and license compliance details directly with code handovers, agencies increase client satisfaction while protecting themselves against subsequent software regressions.
                                </p>

                                <h2 id="teams" class="text-lg font-bold text-white tracking-tight pt-4">Benefits for development teams</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Developer workflow loops stay secure through pre-commit scan checks, dependency updates, and inline warnings. Threat detection parameters run in background tasks to flag issues like exposed private keys, insecure ports, and depreciated dependencies.
                                </p>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Integrating Lume tools into development routines allows engineering leads to catch vulnerabilities early, avoiding expensive remediation work after code merges into central repositories.
                                </p>

                                <h2 id="institutions" class="text-lg font-bold text-white tracking-tight pt-4">Benefits for financial and legal institutions</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Legal teams, investors, and compliance managers run automated audits on target web assets during M&A due diligence, checking system security postures and IP purity without requiring manual audits or repository access.
                                </p>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Lume generates immutable check logs that serve as verification archives, keeping records of compliance states, third-party software licenses, and framework structures for regulatory requirements.
                                </p>

                                <h2 id="metrics-summary" class="text-lg font-bold text-white tracking-tight pt-4">System validation metrics</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Average execution metrics across validated enterprise environments:
                                </p>
                                <div class="pt-2">
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="border-b border-white/5 text-gray-500 font-bold uppercase tracking-wider">
                                                <th class="py-2">Metric Vector Parameter</th>
                                                <th class="py-2 text-right">Typical Value Target</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="border-b border-white/5">
                                                <td class="py-2.5 font-medium text-white">Ingestion to scan execution speed</td>
                                                <td class="py-2.5 text-right text-[#CBB48A] font-bold">&lt; 15 minutes</td>
                                            </tr>
                                            <tr class="border-b border-white/5">
                                                <td class="py-2.5 font-medium text-white">Dependency dependency resolve coverage</td>
                                                <td class="py-2.5 text-right text-[#CBB48A] font-bold">100% of lockfiles</td>
                                            </tr>
                                            <tr class="border-b border-white/5">
                                                <td class="py-2.5 font-medium text-white">False positive rate limits</td>
                                                <td class="py-2.5 text-right text-[#CBB48A] font-bold">&lt; 1.2%</td>
                                            </tr>
                                            <tr class="border-b border-white/5">
                                                <td class="py-2.5 font-medium text-white">Compliance policy verification rate</td>
                                                <td class="py-2.5 text-right text-[#CBB48A] font-bold">Automatic mapping</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </article>

                        <!-- 4. How to Use (CLI Reference) -->
                        <article v-if="currentSection === 'how-to-use'" class="space-y-12">
                            <header class="border-b border-white/5 pb-6">
                                <div class="text-[10px] font-bold text-[#CBB48A] uppercase tracking-wider mb-2">Section 4.0</div>
                                <h1 class="text-3xl font-bold tracking-tight text-white">CLI Reference & Commands</h1>
                                <p class="mt-2 text-sm text-gray-400 leading-relaxed font-medium">Staging workflows, configuration parameters, and terminal execution outputs.</p>
                            </header>

                            <div class="prose prose-invert max-w-none space-y-8">
                                <p class="text-sm text-gray-300 leading-relaxed font-medium">
                                    The Lume Command Line Interface (CLI) is the primary local tool for staging codebases and triggering verification runs. Local parameters are managed via a `lume.config.json` configuration file placed in the repository root.
                                </p>

                                <h2 id="config-reference" class="text-lg font-bold text-white tracking-tight">Configuration reference</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Configure excludes, target scanners, and workspace keys:
                                </p>
                                <div class="bg-[#050505] rounded-lg border border-white/5 p-4 font-mono text-[10px] text-gray-400 space-y-1">
                                    <div>{</div>
                                    <div>&nbsp;&nbsp;"organization_id": "org_agency_0857",</div>
                                    <div>&nbsp;&nbsp;"project": "lume-dashboard-core",</div>
                                    <div>&nbsp;&nbsp;"exclude_paths": [ "node_modules", "vendor", "tests" ],</div>
                                    <div>&nbsp;&nbsp;"audit": {</div>
                                    <div>&nbsp;&nbsp;&nbsp;&nbsp;"mode": "deep",</div>
                                    <div>&nbsp;&nbsp;&nbsp;&nbsp;"scan_dependencies": true</div>
                                    <div>&nbsp;&nbsp;}</div>
                                    <div>}</div>
                                </div>

                                <h2 id="init-workspace" class="text-lg font-bold text-white tracking-tight pt-4">Command: Initialize workspace</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Creates a local `.lumerc` context profile mapping authentication profiles, local ledger keys, and default validation check rulesets.
                                </p>
                                <div class="bg-[#050505] rounded-lg border border-white/5 p-4 font-mono text-[11px] text-[#CBB48A] space-y-1">
                                    <div><span class="text-gray-600">$</span> lume init --org "MyAgency"</div>
                                    <div class="text-[9px] text-gray-500">Status: Initialized</div>
                                </div>

                                <h2 id="vault-upload" class="text-lg font-bold text-white tracking-tight pt-4">Command: Push to CloudVault</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Packages target files locally and pushes them to the air-gapped CloudVault storage bucket via secure pre-signed SSL check links.
                                </p>
                                <div class="bg-[#050505] rounded-lg border border-white/5 p-4 font-mono text-[11px] text-[#CBB48A] space-y-1">
                                    <div><span class="text-gray-600">$</span> lume push ./src --vault</div>
                                    <div class="text-[9px] text-gray-500">Status: Encrypted & Uploaded</div>
                                </div>

                                <h2 id="forensic-scans" class="text-lg font-bold text-white tracking-tight pt-4">Command: Execute deep audit</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Triggers the isolated Docker sandboxed container audit scan, verifying files against AST static vulnerabilities, signature matches, and compliance rules.
                                </p>
                                <div class="bg-[#050505] rounded-lg border border-white/5 p-4 font-mono text-[11px] text-[#CBB48A] space-y-1">
                                    <div><span class="text-gray-600">$</span> lume audit --deep --forensic</div>
                                    <div class="text-[9px] text-gray-500">Status: Scanning completed</div>
                                </div>

                                <h2 id="ledger-settlement" class="text-lg font-bold text-white tracking-tight pt-4">Command: Settle audit record</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Verifies credit consumption logic, registers transaction logs, and updates workspace history tokens on the double-entry accounting database.
                                </p>
                                <div class="bg-[#050505] rounded-lg border border-white/5 p-4 font-mono text-[11px] text-[#CBB48A] space-y-1">
                                    <div><span class="text-gray-600">$</span> lume settle --confirm</div>
                                    <div class="text-[9px] text-gray-500">Status: Ledger Settled</div>
                                </div>
                            </div>
                        </article>

                        <!-- 5. Security Protocol -->
                        <article v-if="currentSection === 'security-protocol'" class="space-y-12">
                            <header class="border-b border-white/5 pb-6">
                                <div class="text-[10px] font-bold text-[#CBB48A] uppercase tracking-wider mb-2">Section 5.0</div>
                                <h1 class="text-3xl font-bold tracking-tight text-white">Security & Isolation Protocol</h1>
                                <p class="mt-2 text-sm text-gray-400 leading-relaxed font-medium">Air-gapped runtimes, container isolation, and Zero-Knowledge proofs.</p>
                            </header>

                            <div class="prose prose-invert max-w-none space-y-6">
                                <h2 id="container-isolation" class="text-lg font-bold text-white tracking-tight pt-4">Container isolation (air-gapped Docker sandboxes)</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    To ensure high-grade isolation, uploads are analyzed in ephemeral sandboxes. Containers execute air-gapped without outbound network interfaces, stopping malicious entities or scripts from establishing connection tunnels.
                                </p>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    The Docker runtime environment automatically mounts target files with read-only permissions inside a secure Linux namespace, preventing analysis scripts from modifying structural files. System memory and CPU cycles are strictly capped per container to guard against denial-of-service loops.
                                </p>

                                <h2 id="integrity-check-seals" class="text-lg font-bold text-white tracking-tight pt-4">Dynamic cryptographic integrity check seals</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Staging inputs calculate dynamic SHA-256 integrity logs on ingestion. Match keys are stored in ledger histories to safeguard the file structure, ensuring that codebase states cannot be modified post-upload.
                                </p>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Verification hashes are compiled into Merkle trees to validate directories quickly. If a single bit in a nested directory is modified post-audit, Lume logs immediately invalidate the associated verification certificate.
                                </p>

                                <h2 id="zero-disclosure-policy" class="text-lg font-bold text-white tracking-tight pt-4">Zero disclosure metadata policy</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Lume compile mechanisms extract security levels, dependency versions, and formatting audits for reports without keeping the raw codebase text content in permanent storage. Once validation concludes, sandboxes delete all temporary cache assets.
                                </p>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Reports and audit logs store metadata only, preventing exposure of proprietary business logic, algorithms, or private architecture details to external consumers or audit verifiers.
                                </p>

                                <h2 id="emergency-kill-switch" class="text-lg font-bold text-white tracking-tight pt-4">Emergency kill-switch protocols</h2>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    The sandboxed runtime environment implements real-time monitoring constraints that trigger absolute kill-switch deletion sequences if anomalous activities are detected during execution.
                                </p>
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    If a docker instance attempts unauthorized actions (like directory traversal outside staging scopes or memory exhaustion attempts), the host system terminates the container process immediately and purges associated directories from memory.
                                </p>
                            </div>
                        </article>

                        <!-- 6. Roadmap -->
                        <article v-if="currentSection === 'roadmap'" class="space-y-12">
                            <header class="border-b border-white/5 pb-6">
                                <div class="text-[10px] font-bold text-[#CBB48A] uppercase tracking-wider mb-2">Section 6.0</div>
                                <h1 class="text-3xl font-bold tracking-tight text-white">Development Roadmap</h1>
                                <p class="mt-2 text-sm text-gray-400 leading-relaxed font-medium">Phased deployment timeline, repository watching features, and localized neural audits.</p>
                            </header>

                            <div class="prose prose-invert max-w-none space-y-6">
                                <p class="text-sm leading-relaxed text-gray-300 font-medium">
                                    Lume Core deploys its technical capabilities progressively. Expand individual phases below to view deep-dive architecture plans, features, and active targets.
                                </p>
                            </div>

                            <div class="space-y-6">
                                <div 
                                    v-for="(phase, index) in roadmapPhases" 
                                    :key="index"
                                    :id="phase.id"
                                    class="border-b border-white/5 pb-6"
                                >
                                    <button 
                                        @click="togglePhase(index)"
                                        class="w-full text-left flex items-center justify-between gap-4 py-2 focus:outline-none"
                                    >
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-[9px] font-mono font-bold text-gray-500 uppercase">{{ phase.timeline }}</span>
                                                <span class="text-[8px] font-bold text-[#CBB48A] bg-[#CBB48A]/5 px-2 py-0.5 rounded border border-[#CBB48A]/25 uppercase">{{ phase.status }}</span>
                                            </div>
                                            <h3 class="text-base font-bold text-white mt-1 tracking-tight">{{ phase.phase }}</h3>
                                        </div>
                                        <svg 
                                            class="h-4 w-4 text-gray-500 transition-transform duration-200"
                                            :class="activePhase === index ? 'rotate-180 text-white' : ''" 
                                            fill="none" 
                                            viewBox="0 0 24 24" 
                                            stroke="currentColor"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    
                                    <div v-if="activePhase === index" class="mt-3 pl-2 space-y-3 text-xs">
                                        <p class="text-[#CBB48A] font-semibold font-sans">{{ phase.value }}</p>
                                        <p class="text-gray-400 leading-relaxed font-medium font-sans">{{ phase.deepDive }}</p>
                                        <div class="space-y-1.5 pt-1">
                                            <div class="text-[9px] font-bold uppercase tracking-wider text-gray-500">Key targets:</div>
                                            <div v-for="feat in phase.features" :key="feat" class="flex items-center gap-2 text-gray-400 font-medium font-sans">
                                                <span class="h-1.5 w-1.5 rounded-full bg-[#CBB48A]/50"></span>
                                                <span>{{ feat }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <!-- 7. Technical Whitepaper -->
                        <article v-if="currentSection === 'white-paper'" class="space-y-12">
                            <header class="border-b border-white/5 pb-6">
                                <div class="text-[10px] font-bold text-[#CBB48A] uppercase tracking-wider mb-2">Section 7.0</div>
                                <h1 class="text-3xl font-bold tracking-tight text-white">Technical Whitepaper</h1>
                                <p class="mt-2 text-sm text-gray-400 leading-relaxed font-medium">Cryptographic audit ledger protocols, structural hashes, and validation consensus.</p>
                            </header>

                            <div class="prose prose-invert max-w-none space-y-8">
                                <div class="space-y-3">
                                    <h3 id="asset-hashing" class="text-base font-bold text-white pt-4">Asset hashing logic</h3>
                                    <p class="text-xs text-gray-400 leading-loose font-medium">
                                        Verification pipelines stage files inside isolated runtimes, confirming check integrity at ingest. High-efficiency matching algorithms compute hash validations against pre-recorded vulnerability logs without data leakages. Account tokens update via internal database ledgers to map usage patterns.
                                    </p>
                                    <p class="text-xs text-gray-400 leading-loose font-medium">
                                        The proof sequence validates target states by hashing localized manifest maps. Check parameters compare structural keys to verify directories against registry listings, flagging dependency deviations or modified scripts.
                                    </p>
                                </div>

                                <div class="space-y-3">
                                    <h3 id="multi-vector-scans" class="text-base font-bold text-white pt-4">Ingestion map visualization</h3>
                                    <p class="text-xs text-gray-400 leading-loose font-medium">
                                        System components are structured dynamically to verify asset paths, compiling dependency scopes and check seals:
                                    </p>
                                    <!-- Simple technical figure caption design -->
                                    <div class="border border-white/5 bg-[#050506] rounded-xl p-4 font-mono text-[9px] text-gray-500 space-y-1.5">
                                        <div class="text-white border-b border-white/5 pb-1 uppercase tracking-wider">Fig 7.1: Audit validation process map</div>
                                        <div>[Staged File Ingest] &rarr; (SHA-256 verification) &rarr; [Isolated Docker Container]</div>
                                        <div>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&rarr; (Forensic static audit)</div>
                                        <div>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&rarr; [Ledger settlement update]</div>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <h3 id="ledger-settlement-logic" class="text-base font-bold text-white pt-4">Consensus validation ledger balances</h3>
                                    <p class="text-xs text-gray-400 leading-loose font-medium">
                                        Verification state results publish to ledger transaction trees. Dynamic tracking validates credits usage based on the required assessment mode (e.g. single file validation scans vs deep repository forensic auditing loops). All operations conclude dynamically with no transaction delays.
                                    </p>
                                </div>

                                <div class="space-y-3">
                                    <h3 id="consensus-verification" class="text-base font-bold text-white pt-4">Decentralized consensus structures</h3>
                                    <p class="text-xs text-gray-400 leading-loose font-medium">
                                        Future deployment phases establish decentralized validator consensus. Compute nodes check execution logs recursively to verify scanner outputs without Central Authority controls, ensuring Lume database logs maintain complete technical independence.
                                    </p>
                                </div>
                            </div>
                        </article>

                        <!-- Fallback Loading -->
                        <section v-if="!['introduction', 'features', 'benefits', 'security-protocol', 'roadmap', 'how-to-use', 'white-paper'].includes(currentSection)" class="py-12 border border-dashed border-white/5 rounded-xl text-center">
                            <h2 class="text-sm font-bold text-gray-500 mb-1">Loading documentation section</h2>
                            <p class="text-xs text-gray-600 font-medium font-sans">The requested manual is currently indexing.</p>
                        </section>
                    </div>

                    <!-- Page-to-Page Navigation Footer -->
                    <footer v-if="['introduction', 'features', 'benefits', 'how-to-use', 'security-protocol', 'roadmap', 'white-paper'].includes(currentSection)" class="mt-16 border-t border-white/5 pt-6">
                        <div class="flex items-center justify-between text-xs">
                            <!-- Left Button -->
                            <button 
                                v-if="prevSection"
                                @click="setSection(prevSection.id)"
                                class="group flex items-center gap-2 text-left focus:outline-none"
                            >
                                 <svg class="h-4 w-4 text-gray-400 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                 </svg>
                                 <div>
                                     <div class="text-[9px] font-bold text-gray-600">Previous</div>
                                     <div class="font-semibold text-white group-hover:text-[#CBB48A] transition-colors">{{ prevSection.title }}</div>
                                 </div>
                            </button>
                            <div v-else></div>

                            <!-- Right Button -->
                            <button 
                                v-if="nextSection"
                                @click="setSection(nextSection.id)"
                                class="group flex items-center gap-2 text-right focus:outline-none"
                            >
                                 <div>
                                     <div class="text-[9px] font-bold text-gray-600">Next</div>
                                     <div class="font-semibold text-white group-hover:text-[#CBB48A] transition-colors">{{ nextSection.title }}</div>
                                 </div>
                                 <svg class="h-4 w-4 text-gray-400 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                 </svg>
                            </button>
                        </div>
                    </footer>
                </main>

                <!-- Right Column (Table of Contents Sidebar) -->
                <aside class="w-60 hidden xl:block shrink-0 border-l border-white/5 pl-8 sticky top-16 h-[calc(100vh-8rem)] overflow-y-auto">
                    <div class="text-[9px] font-bold text-gray-500 uppercase tracking-widest mb-4">On this page</div>
                    <ul class="space-y-2.5">
                        <li v-for="sub in subsections" :key="sub.id">
                            <button 
                                @click="scrollToSubsection(sub.id)"
                                class="text-left text-[11px] font-semibold text-gray-400 hover:text-white transition-colors duration-200 focus:outline-none block w-full truncate"
                            >
                                {{ sub.title }}
                            </button>
                        </li>
                    </ul>
                </aside>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* High-performance transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
