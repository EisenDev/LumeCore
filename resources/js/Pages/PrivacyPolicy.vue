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
        <section class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
            <!-- Background Gradients -->
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-lume-primary/10 rounded-full blur-[120px]" />
                <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-sovereign-primary/10 rounded-full blur-[120px]" />
            </div>

            <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="reveal">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 backdrop-blur-sm mb-8">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-400">Legal Protocol</span>
                    </div>
                    
                    <h1 class="text-5xl lg:text-8xl font-[1000] text-white tracking-tighter uppercase italic leading-[0.85] mb-8">
                        Data <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-cyan-400">Sovereignty.</span>
                    </h1>
                    
                    <p class="text-lg lg:text-2xl text-gray-400 font-medium max-w-2xl leading-relaxed border-l-2 border-emerald-500/30 pl-6">
                        LUME CORE architecture is effectively designed to minimize PII exposure while maximizing forensic utility.
                    </p>
                </div>
            </div>
        </section>

        <!-- Content Section -->
        <section class="relative pb-24 lg:pb-32">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-12 gap-12 lg:gap-24">
                    <!-- Navigation / Table of Contents -->
                    <div class="lg:col-span-4 hidden lg:block">
                        <div class="sticky top-32 space-y-8">
                            <div class="p-8 rounded-[2rem] bg-white/[0.02] border border-white/5 backdrop-blur-sm">
                                <h3 class="text-xs font-black uppercase tracking-[0.3em] text-white mb-6">Quick Jump</h3>
                                <ul class="space-y-4">
                                    <li @click="scrollToSection('intro')" class="group flex items-center gap-3 text-sm font-bold text-gray-500 hover:text-emerald-400 transition-colors cursor-pointer">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-700 group-hover:bg-emerald-400 transition-colors"></span>
                                        Introduction
                                    </li>
                                    <li @click="scrollToSection('collection')" class="group flex items-center gap-3 text-sm font-bold text-gray-500 hover:text-emerald-400 transition-colors cursor-pointer">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-700 group-hover:bg-emerald-400 transition-colors"></span>
                                        Data Collection
                                    </li>
                                    <li @click="scrollToSection('usage')" class="group flex items-center gap-3 text-sm font-bold text-gray-500 hover:text-emerald-400 transition-colors cursor-pointer">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-700 group-hover:bg-emerald-400 transition-colors"></span>
                                        How We Use Data
                                    </li>
                                    <li @click="scrollToSection('sharing')" class="group flex items-center gap-3 text-sm font-bold text-gray-500 hover:text-emerald-400 transition-colors cursor-pointer">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-700 group-hover:bg-emerald-400 transition-colors"></span>
                                        Data Sharing
                                    </li>
                                    <li @click="scrollToSection('security')" class="group flex items-center gap-3 text-sm font-bold text-gray-500 hover:text-emerald-400 transition-colors cursor-pointer">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-700 group-hover:bg-emerald-400 transition-colors"></span>
                                        Security & Retention
                                    </li>
                                    <li @click="scrollToSection('contact')" class="group flex items-center gap-3 text-sm font-bold text-gray-500 hover:text-emerald-400 transition-colors cursor-pointer">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-700 group-hover:bg-emerald-400 transition-colors"></span>
                                        Contact Legal
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="p-8 rounded-[2rem] bg-gradient-to-br from-emerald-900/10 to-transparent border border-emerald-500/10">
                                <h3 class="text-xs font-black uppercase tracking-[0.3em] text-emerald-400 mb-2">Last Updated</h3>
                                <p class="text-2xl font-black text-white italic tracking-tighter">JAN 2026</p>
                            </div>

                             <!-- Definitions -->
                            <div class="p-8 rounded-[2rem] bg-white/[0.02] border border-white/5 backdrop-blur-sm">
                                <h3 class="text-xs font-black uppercase tracking-[0.3em] text-white mb-6">Core Definitions</h3>
                                <dl class="space-y-6">
                                    <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-lume-primary">Sovereign Node</dt>
                                        <dd class="text-xs text-gray-500 mt-2 leading-relaxed">A verified user account with full cryptographic control over their data vault and assets.</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-lume-primary">Forensic Engine</dt>
                                        <dd class="text-xs text-gray-500 mt-2 leading-relaxed">The AI-driven auditing system that analyzes codebases for security, quality, and originality.</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-lume-primary">Vault Content</dt>
                                        <dd class="text-xs text-gray-500 mt-2 leading-relaxed">Code, documents, and media uploaded by a Node. Remains strictly owned by the Node.</dd>
                                    </div>
                                </dl>
                            </div>

                             <!-- Legal Downloads -->
                            <div class="p-6 rounded-[2rem] border border-white/5 hover:border-white/10 transition-colors group cursor-pointer bg-white/[0.02]">
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="text-xs font-black uppercase tracking-[0.3em] text-white">Legal Assets</h3>
                                    <svg class="w-4 h-4 text-gray-500 group-hover:text-emerald-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                </div>
                                <p class="text-[10px] text-gray-500 font-mono mb-4">Signed_Privacy_Protocol_v2.4.pdf</p>
                                <div class="w-full bg-white/5 h-0.5 rounded-full overflow-hidden">
                                    <div class="bg-emerald-500 w-1/3 h-full group-hover:w-full transition-all duration-700 ease-out"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="lg:col-span-8 space-y-16">
                        <!-- Introduction -->
                        <div id="intro" class="reveal delay-100">
                             <div class="text-[10px] font-black uppercase tracking-[0.3em] text-lume-primary mb-4">01 // The Core Directive</div>
                             <h2 class="text-3xl lg:text-4xl font-black text-white mb-6 uppercase italic tracking-tight">Zero-Knowledge First.</h2>
                             <div class="prose prose-invert prose-lg text-gray-400 leading-relaxed">
                                <p>
                                    At LUME, we take data privacy seriously. Our AI-powered <span class="text-white font-bold">Sovereign Vault Auditor</span> is designed to analyze documents for professional quality while detecting and protecting Personal Identifiable Information (PII). We don't just "comply" with privacy laws; we architect our system to make data exploitation mathematically impossible.
                                </p>
                             </div>
                        </div>

                        <div class="w-full h-px bg-white/5"></div>

                        <!-- Data Collection -->
                        <div id="collection" class="reveal delay-200">
                             <div class="text-[10px] font-black uppercase tracking-[0.3em] text-lume-primary mb-4">02 // Surveillance Vectors</div>
                             <h2 class="text-3xl lg:text-4xl font-black text-white mb-8 uppercase italic tracking-tight">Information We Collect.</h2>
                             
                             <div class="space-y-6">
                                 <div class="p-6 rounded-3xl bg-white/[0.02] border border-white/5">
                                     <h3 class="text-white font-black uppercase tracking-wide text-sm mb-3">Account Information</h3>
                                     <p class="text-sm text-gray-400 leading-relaxed">
                                         To create a Sovereign Node (Account), you must provide a username and a verified email address. If you purchase paid services, we (or our payment processors) collect billing details. We do not store full credit card numbers on our servers.
                                     </p>
                                 </div>
                                 <div class="p-6 rounded-3xl bg-white/[0.02] border border-white/5">
                                     <h3 class="text-white font-black uppercase tracking-wide text-sm mb-3">Usage and Device Information</h3>
                                     <p class="text-sm text-gray-400 leading-relaxed">
                                         We collect telemetry regarding your interaction with the Forensic Engine, including IP addresses, browser types, and device identifiers. This data is strictly used for security auditing and ensuring the integrity of the scanning process.
                                     </p>
                                 </div>
                                 <div class="p-6 rounded-3xl bg-white/[0.02] border border-white/5">
                                     <h3 class="text-white font-black uppercase tracking-wide text-sm mb-3">Sovereign Vault Content</h3>
                                     <p class="text-sm text-gray-400 leading-relaxed">
                                         When you upload codebases or documents to the Sovereign Vault, we store and process this content to provide our forensic services. This content is encrypted at rest and in transit. You retain full ownership and sovereignty over this data.
                                     </p>
                                 </div>
                             </div>
                        </div>

                        <div class="w-full h-px bg-white/5"></div>

                        <!-- Usage -->
                        <div id="usage" class="reveal delay-200">
                             <div class="text-[10px] font-black uppercase tracking-[0.3em] text-lume-primary mb-4">03 // Operation Parameters</div>
                             <h2 class="text-3xl lg:text-4xl font-black text-white mb-8 uppercase italic tracking-tight">How We Use Your Data.</h2>
                             <ul class="space-y-4 text-gray-400 leading-relaxed">
                                 <li class="flex items-start gap-4">
                                     <div class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0"></div>
                                     <span><strong>Service Provisioning:</strong> To operate the Forensic Engine, manage your account, and settle transactions on the LUME Ledger.</span>
                                 </li>
                                 <li class="flex items-start gap-4">
                                     <div class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0"></div>
                                     <span><strong>Security & Forensics:</strong> To detect abuse, prevent malware proliferation, and maintain the integrity of the Sovereign Network.</span>
                                 </li>
                                 <li class="flex items-start gap-4">
                                     <div class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0"></div>
                                     <span><strong>Communications:</strong> To send transactional emails (invoices, scan completion reports) and critical infrastructure updates.</span>
                                 </li>
                             </ul>
                        </div>

                        <div class="w-full h-px bg-white/5"></div>

                        <!-- Sharing -->
                        <div id="sharing" class="reveal delay-200">
                             <div class="text-[10px] font-black uppercase tracking-[0.3em] text-lume-primary mb-4">04 // Data Egress</div>
                             <h2 class="text-3xl lg:text-4xl font-black text-white mb-8 uppercase italic tracking-tight">Data Sharing Protocol.</h2>
                             
                             <div class="grid md:grid-cols-2 gap-8">
                                 <div>
                                     <h3 class="flex items-center gap-3 text-lg font-black uppercase italic text-red-500 mb-4">
                                         <span class="flex h-6 w-6 items-center justify-center rounded-full bg-red-500/20 text-[10px]">✕</span> 
                                         Strictly Prohibited
                                     </h3>
                                     <ul class="space-y-4">
                                         <li class="flex items-start gap-4 p-4 rounded-xl bg-red-500/[0.03] border border-red-500/10">
                                             <div class="text-xs font-medium text-gray-400 leading-relaxed">
                                                 <strong class="text-white block mb-1">Data Sales</strong>
                                                 We do NOT sell, rent, or trade your Personal Data or Vault Content to data brokers, advertisers, or third parties.
                                             </div>
                                         </li>
                                     </ul>
                                 </div>
                                 
                                 <div>
                                     <h3 class="flex items-center gap-3 text-lg font-black uppercase italic text-emerald-400 mb-4">
                                         <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20 text-[10px]">✓</span> 
                                         Authorized Egress
                                     </h3>
                                     <ul class="space-y-4">
                                         <li class="flex items-start gap-4 p-4 rounded-xl bg-emerald-500/[0.03] border border-emerald-500/10">
                                             <div class="text-xs font-medium text-gray-400 leading-relaxed">
                                                 <strong class="text-white block mb-1">Infrastructure Providers</strong>
                                                 We share data with trusted subprocessors (e.g., Cloudflare, Stripe) solely to power the infrastructure. They are bound by strict DPA (Data Processing Agreements).
                                             </div>
                                         </li>
                                          <li class="flex items-start gap-4 p-4 rounded-xl bg-emerald-500/[0.03] border border-emerald-500/10">
                                             <div class="text-xs font-medium text-gray-400 leading-relaxed">
                                                 <strong class="text-white block mb-1">Legal Compulsion</strong>
                                                 We may disclose data if compelled by valid legal process (e.g., specific court order), but only after exhausting all available legal challenges.
                                             </div>
                                         </li>
                                     </ul>
                                 </div>
                             </div>
                        </div>

                         <!-- Security -->
                        <div id="security" class="reveal delay-200">
                             <div class="text-[10px] font-black uppercase tracking-[0.3em] text-lume-primary mb-4">05 // Fortification</div>
                             <h2 class="text-3xl lg:text-4xl font-black text-white mb-8 uppercase italic tracking-tight">Security & Retention.</h2>
                             <p class="text-gray-400 leading-relaxed mb-6">
                                 LUME utilizes military-grade encryption (AES-256) for data at rest and TLS 1.3 for data in transit. Access to production systems is strictly limited to core engineers with multi-factor authentication (MFA).
                             </p>
                             <p class="text-gray-400 leading-relaxed">
                                 We retain your data for as long as your account is active or needed to provide services. Upon account deletion, cryptographic keys are shredded, rendering data unrecoverable, subject to standard backup retention cycles (30 days).
                             </p>
                        </div>

                    </div>
                </div>
            </div>
        </section>

        <!-- Use Cases / Final CTA -->
        <section id="contact" class="py-24 border-t border-white/5 bg-white/[0.02]">
             <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center reveal">
                 <h2 class="text-2xl font-black text-white uppercase italic tracking-tight mb-6">Need Clarity on your specific use case?</h2>
                 <p class="text-gray-500 mb-10 max-w-xl mx-auto">Our sovereign legal team is available for encrypted consultation regarding high-value asset processing.</p>
                 <a href="mailto:lumecore.tech@gmail.com" class="inline-flex items-center justify-center px-8 py-4 text-xs font-black uppercase tracking-[0.2em] text-black bg-white rounded-full hover:scale-105 transition-transform">
                     Contact Legal
                 </a>
                 <div class="mt-4 text-xs font-black tracking-[0.2em] text-gray-600">
                     LUMECORE.TECH@GMAIL.COM
                 </div>
             </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
.reveal {
    opacity: 0;
    transform: translateY(30px);
    transition: all 1s cubic-bezier(0.2, 0.8, 0.2, 1);
    will-change: opacity, transform;
}

.reveal.active {
    opacity: 1;
    transform: translateY(0);
}

.delay-100 { transition-delay: 100ms; }
.delay-200 { transition-delay: 200ms; }
</style>
