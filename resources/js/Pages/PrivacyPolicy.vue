<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { onMounted } from 'vue';

onMounted(() => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));
});

const scrollToSection = (id: string) => {
    const element = document.getElementById(id);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
    }
};
</script>

<template>
    <Head title="Privacy Policy" />

    <PublicLayout>
        <!-- Hero Section -->
        <section class="relative pt-32 pb-20 lg:pt-40 lg:pb-24 overflow-hidden">
            <!-- Background Gradients -->
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-lume-primary/5 rounded-full blur-[120px]" />
                <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-lume-primary/5 rounded-full blur-[120px]" />
            </div>

            <div class="relative z-10 mx-auto max-w-5xl px-6 lg:px-8">
                <div class="reveal">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#CBB48A]/10 border border-[#CBB48A]/20 backdrop-blur-sm mb-6">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#CBB48A] animate-pulse"></span>
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-[#CBB48A]">Legal framework</span>
                    </div>
                    
                    <h1 class="text-4xl lg:text-6xl font-bold text-white tracking-tight leading-tight mb-6">
                        Data sovereignty and <br><span class="text-transparent bg-clip-text bg-gradient-to-r from-[#CBB48A] to-[#F3E7C9]">privacy protocol.</span>
                    </h1>
                    
                    <p class="text-base lg:text-lg text-gray-400 font-medium max-w-3xl leading-relaxed border-l-2 border-[#CBB48A]/30 pl-6">
                        The Lume architecture is engineered to minimize personal data exposure while ensuring complete audit transparency. We prioritize mathematical verification over physical data ingestion.
                    </p>
                </div>
            </div>
        </section>

        <!-- Content Section -->
        <section class="relative pb-24 lg:pb-32">
            <div class="mx-auto max-w-5xl px-6 lg:px-8">
                <div class="grid lg:grid-cols-12 gap-12 lg:gap-16">
                    <!-- Navigation / Table of Contents -->
                    <div class="lg:col-span-4 hidden lg:block sticky top-32 self-start space-y-6">
                        <div class="p-6 rounded-2xl bg-white/[0.01] border border-white/5 backdrop-blur-sm">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-white mb-4">Quick jump</h3>
                            <ul class="space-y-3">
                                <li @click="scrollToSection('intro')" class="group flex items-center gap-3 text-xs font-semibold text-gray-400 hover:text-[#CBB48A] transition-colors cursor-pointer">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-800 group-hover:bg-[#CBB48A] transition-colors"></span>
                                    Core directive
                                </li>
                                <li @click="scrollToSection('collection')" class="group flex items-center gap-3 text-xs font-semibold text-gray-400 hover:text-[#CBB48A] transition-colors cursor-pointer">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-800 group-hover:bg-[#CBB48A] transition-colors"></span>
                                    Data collection vectors
                                </li>
                                <li @click="scrollToSection('usage')" class="group flex items-center gap-3 text-xs font-semibold text-gray-400 hover:text-[#CBB48A] transition-colors cursor-pointer">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-800 group-hover:bg-[#CBB48A] transition-colors"></span>
                                    Processing parameters
                                </li>
                                <li @click="scrollToSection('sharing')" class="group flex items-center gap-3 text-xs font-semibold text-gray-400 hover:text-[#CBB48A] transition-colors cursor-pointer">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-800 group-hover:bg-[#CBB48A] transition-colors"></span>
                                    Data egress protocols
                                </li>
                                <li @click="scrollToSection('security')" class="group flex items-center gap-3 text-xs font-semibold text-gray-400 hover:text-[#CBB48A] transition-colors cursor-pointer">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-800 group-hover:bg-[#CBB48A] transition-colors"></span>
                                    Retention & encryption
                                </li>
                                <li @click="scrollToSection('compliance')" class="group flex items-center gap-3 text-xs font-semibold text-gray-400 hover:text-[#CBB48A] transition-colors cursor-pointer">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-800 group-hover:bg-[#CBB48A] transition-colors"></span>
                                    Global standards compliance
                                </li>
                            </ul>
                        </div>
                        
                        <div class="p-6 rounded-2xl bg-[#CBB48A]/5 border border-[#CBB48A]/10">
                            <h3 class="text-[10px] font-bold uppercase tracking-wider text-[#CBB48A] mb-1">Last updated</h3>
                            <p class="text-lg font-bold text-white">June 2026</p>
                        </div>

                         <!-- Definitions -->
                        <div class="p-6 rounded-2xl bg-white/[0.01] border border-white/5 backdrop-blur-sm">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-white mb-4">Core definitions</h3>
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A]">Sovereign Node</dt>
                                    <dd class="text-xs text-gray-400 mt-1 leading-relaxed">A registered account possessing cryptographic credentials to interact with workspace sandboxes.</dd>
                                </div>
                                <div>
                                    <dt class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A]">CloudVault Storage</dt>
                                    <dd class="text-xs text-gray-400 mt-1 leading-relaxed">The isolated storage environment holding uploaded project assets during verification.</dd>
                                </div>
                                <div>
                                    <dt class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A]">Audit Metadata</dt>
                                    <dd class="text-xs text-gray-400 mt-1 leading-relaxed">Non-proprietary structural results, configuration stats, and compliance markers logged to the ledger.</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="lg:col-span-8 space-y-16 text-left">
                        <!-- Introduction -->
                        <div id="intro" class="reveal space-y-4">
                             <div class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A]">01 / Core directive</div>
                             <h2 class="text-2xl font-bold text-white tracking-tight">Zero-disclosure architecture</h2>
                             <div class="text-xs text-gray-400 space-y-4 leading-relaxed font-medium">
                                <p>
                                    Lume enforces a zero-knowledge data ingestion policy. We believe that your codebase is your most valuable intellectual property. Our platform is architected to ensure that your raw source files are parsed solely in transient, air-gapped memory space and are never stored in plain text formats across permanent databases.
                                </p>
                                <p>
                                    Our scanning engines map code schemas and compile structural dependency metrics to compute integrity scores. Once this structural verification concludes, the staging sandboxes are automatically terminated and all localized copies are destroyed. Only anonymized metadata remains.
                                </p>
                             </div>
                        </div>

                        <div class="w-full h-px bg-white/5"></div>

                        <!-- Data Collection -->
                        <div id="collection" class="reveal space-y-6">
                             <div class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A]">02 / Ingestion vectors</div>
                             <h2 class="text-2xl font-bold text-white tracking-tight">Information we collect and ingest</h2>
                             
                             <div class="space-y-4 text-xs text-gray-400 leading-relaxed font-medium">
                                 <div class="p-6 rounded-2xl bg-white/[0.01] border border-white/5 space-y-2">
                                     <h3 class="text-white font-bold text-sm">Account identity parameters</h3>
                                     <p>
                                         To provision sovereign workspaces, we collect identifiers including name, email address, and organization profiles. Access credentials, local API tokens, and cryptographic keys are hashed prior to database commits.
                                     </p>
                                 </div>
                                 <div class="p-6 rounded-2xl bg-white/[0.01] border border-white/5 space-y-2">
                                     <h3 class="text-white font-bold text-sm">Staged workspace assets</h3>
                                     <p>
                                         Codebases, configuration profiles, and project assets uploaded to CloudVault are encrypted in transit via TLS 1.3 and at rest using AES-256 keys. Staged assets reside within ephemeral sandboxes and are isolated strictly by organization namespace.
                                     </p>
                                 </div>
                                 <div class="p-6 rounded-2xl bg-white/[0.01] border border-white/5 space-y-2">
                                     <h3 class="text-white font-bold text-sm">System telemetry & transaction logs</h3>
                                     <p>
                                         We record system operations, transaction markers, workspace switch activities, and credits allocations. Telemetry logging excludes code snippets, structural text content, or environment parameters to prevent proprietary exposure.
                                     </p>
                                 </div>
                             </div>
                        </div>

                        <div class="w-full h-px bg-white/5"></div>

                        <!-- Usage -->
                        <div id="usage" class="reveal space-y-4">
                             <div class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A]">03 / Processing parameters</div>
                             <h2 class="text-2xl font-bold text-white tracking-tight">How we process information</h2>
                             <div class="text-xs text-gray-400 space-y-4 leading-relaxed font-medium">
                                 <p>
                                     Ingested details are processed strictly under the following parameters to ensure operational compliance:
                                 </p>
                                 <ul class="space-y-3 pl-4">
                                     <li class="flex items-start gap-3">
                                         <span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-[#CBB48A] shrink-0"></span>
                                         <span><strong>Pipeline verification:</strong> To run vulnerability scans, dependency graph evaluations, license compliance audits, and architectural risk scoring.</span>
                                     </li>
                                     <li class="flex items-start gap-3">
                                         <span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-[#CBB48A] shrink-0"></span>
                                         <span><strong>Ledger settlement:</strong> To process credit consumption loops, verify balances, and record immutable audit verification histories on the Lume accounting ledger.</span>
                                     </li>
                                     <li class="flex items-start gap-3">
                                         <span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-[#CBB48A] shrink-0"></span>
                                         <span><strong>System security:</strong> To monitor for denial-of-service attempts, coordinate container kill-switches, and defend against infrastructure exploits.</span>
                                     </li>
                                 </ul>
                             </div>
                        </div>

                        <div class="w-full h-px bg-white/5"></div>

                        <!-- Sharing -->
                        <div id="sharing" class="reveal space-y-6">
                             <div class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A]">04 / Data egress</div>
                             <h2 class="text-2xl font-bold text-white tracking-tight">Data egress and sharing policies</h2>
                             
                             <div class="grid md:grid-cols-2 gap-6 text-xs font-medium leading-relaxed text-gray-400">
                                 <div class="p-6 rounded-2xl bg-white/[0.01] border border-white/5 space-y-3">
                                     <h3 class="text-white font-bold text-sm flex items-center gap-2">
                                         <span class="h-2 w-2 rounded-full bg-red-500"></span> 
                                         Strictly prohibited
                                     </h3>
                                     <p>
                                         Lume does not sell, license, or monetize codebase files, account parameters, or usage histories to advertisers, data brokers, LLM training registries, or commercial third parties.
                                     </p>
                                 </div>
                                 
                                 <div class="p-6 rounded-2xl bg-white/[0.01] border border-white/5 space-y-3">
                                     <h3 class="text-white font-bold text-sm flex items-center gap-2">
                                         <span class="h-2 w-2 rounded-full bg-[#CBB48A]"></span> 
                                         Authorized subprocessors
                                     </h3>
                                     <p>
                                         Data transfers are limited strictly to trusted infrastructure partners (e.g. cloud hosting utilities, payment processing portals) under binding Data Processing Agreements (DPA).
                                     </p>
                                 </div>
                             </div>
                        </div>

                         <div class="w-full h-px bg-white/5"></div>

                         <!-- Security -->
                        <div id="security" class="reveal space-y-4">
                             <div class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A]">05 / Fortification</div>
                             <h2 class="text-2xl font-bold text-white tracking-tight">Retention, encryption, and destruction</h2>
                             <div class="text-xs text-gray-400 space-y-4 leading-relaxed font-medium">
                                 <p>
                                     Staged repositories reside within highly isolated, air-gapped Docker instances. Host networking interfaces are disabled during processing to prevent code extraction. Asset keys, system data, and configuration parameters are stored on isolated database blocks.
                                 </p>
                                 <p>
                                     Upon account deletion, all associated workspace data is programmatically purged, and virtual encryption keys are destroyed, rendering past backups unrecoverable.
                                 </p>
                             </div>
                        </div>

                        <div class="w-full h-px bg-white/5"></div>

                        <!-- Compliance -->
                        <div id="compliance" class="reveal space-y-4">
                             <div class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A]">06 / Global standards</div>
                             <h2 class="text-2xl font-bold text-white tracking-tight">GDPR, CCPA, and compliance control</h2>
                             <div class="text-xs text-gray-400 space-y-4 leading-relaxed font-medium">
                                 <p>
                                     We align our processing systems with standard data privacy guidelines, including the General Data Protection Regulation (GDPR) and the California Consumer Privacy Act (CCPA). Under these frameworks, users retain rights to access, update, export, or request deletion of their personal identity parameters.
                                 </p>
                                 <p>
                                     To exercise your data sovereignty rights, or to request a complete export of your ledger transaction details, you can contact our compliance desk using the secure channels listed below.
                                 </p>
                             </div>
                        </div>

                    </div>
                </div>
            </div>
        </section>

        <!-- Final Statement / Contact -->
        <section class="py-24 border-t border-white/5 bg-white/[0.01]">
             <div class="mx-auto max-w-5xl px-6 lg:px-8 text-center reveal">
                 <h2 class="text-2xl font-bold text-white tracking-tight mb-4">Request a data export or deletion</h2>
                 <p class="text-sm text-gray-400 mb-10 max-w-xl mx-auto font-medium leading-relaxed">Our compliance and legal desk is available to assist you with data requests or specific compliance alignment reports.</p>
                 <a href="mailto:lumecore.tech@gmail.com" class="inline-flex items-center justify-center px-6 py-3.5 text-xs font-semibold text-slate-950 bg-white rounded-lg hover:scale-[1.02] transition-transform">
                     Contact compliance desk
                 </a>
                 <div class="mt-4 text-[10px] font-mono text-gray-500 uppercase tracking-widest">
                     lumecore.tech@gmail.com
                 </div>
             </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
.reveal {
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, transform;
}

.reveal.active {
    opacity: 1;
    transform: translateY(0);
}

.delay-100 { transition-delay: 100ms; }
.delay-200 { transition-delay: 200ms; }
</style>
