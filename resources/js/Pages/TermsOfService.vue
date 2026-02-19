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
    <Head title="Terms of Service" />

    <PublicLayout>
        <!-- Hero Section -->
        <section class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute top-[-10%] left-[-10%] w-[600px] h-[600px] bg-lume-primary/5 rounded-full blur-[150px]" />
                <div class="absolute bottom-[-10%] right-[-10%] w-[600px] h-[600px] bg-sovereign-primary/5 rounded-full blur-[150px]" />
            </div>

            <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="reveal text-center max-w-4xl mx-auto">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 backdrop-blur-sm mb-8 mx-auto">
                         <span class="w-1.5 h-1.5 rounded-full bg-sovereign-primary animate-pulse"></span>
                         <span class="text-[10px] font-black uppercase tracking-[0.2em] text-sovereign-primary">Protocol Governance</span>
                    </div>
                    
                    <h1 class="text-5xl lg:text-9xl font-[1000] text-white tracking-tighter uppercase italic leading-[0.85] mb-8">
                        Terms of <br><span class="text-transparent bg-clip-text bg-gradient-to-br from-white to-gray-500">Operation.</span>
                    </h1>
                    
                    <p class="text-lg lg:text-2xl text-gray-400 font-medium leading-relaxed">
                        The definitive rules of engagement for the LUME Sovereign Network.
                    </p>
                </div>
            </div>
        </section>

        <!-- Use Cases / Final CTA -->
        <section class="relative pb-24 lg:pb-32">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-12 gap-12 lg:gap-24">
                     <!-- Navigation / Table of Contents -->
                     <div class="lg:col-span-4 hidden lg:block">
                        <div class="sticky top-32 space-y-8">
                            <!-- Status -->
                            <div class="p-8 rounded-[2rem] bg-gradient-to-br from-sovereign-primary/10 to-transparent border border-sovereign-primary/10">
                                <h3 class="text-xs font-black uppercase tracking-[0.3em] text-sovereign-primary mb-2">Document Status</h3>
                                <div class="flex items-center gap-2">
                                    <span class="relative flex h-3 w-3">
                                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                      <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                                    </span>
                                    <p class="text-xl font-black text-white italic tracking-tighter">ACTIVE - ENFORCED</p>
                                </div>
                            </div>

                            <div class="p-8 rounded-[2rem] bg-white/[0.02] border border-white/5 backdrop-blur-sm">
                                <h3 class="text-xs font-black uppercase tracking-[0.3em] text-white mb-6">Quick Jump</h3>
                                <ul class="space-y-4">
                                     <li @click="scrollToSection('compliance')" class="group flex items-center gap-3 text-sm font-bold text-gray-500 hover:text-sovereign-primary transition-colors cursor-pointer">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-700 group-hover:bg-sovereign-primary transition-colors"></span>
                                        Protocol Agreement
                                    </li>
                                     <li @click="scrollToSection('accounts')" class="group flex items-center gap-3 text-sm font-bold text-gray-500 hover:text-sovereign-primary transition-colors cursor-pointer">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-700 group-hover:bg-sovereign-primary transition-colors"></span>
                                        Account Terms
                                    </li>
                                     <li @click="scrollToSection('assets')" class="group flex items-center gap-3 text-sm font-bold text-gray-500 hover:text-sovereign-primary transition-colors cursor-pointer">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-700 group-hover:bg-sovereign-primary transition-colors"></span>
                                        Asset Sovereignty
                                    </li>
                                     <li @click="scrollToSection('conduct')" class="group flex items-center gap-3 text-sm font-bold text-gray-500 hover:text-sovereign-primary transition-colors cursor-pointer">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-700 group-hover:bg-sovereign-primary transition-colors"></span>
                                        Acceptable Use
                                    </li>
                                    <li @click="scrollToSection('liability')" class="group flex items-center gap-3 text-sm font-bold text-gray-500 hover:text-sovereign-primary transition-colors cursor-pointer">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-700 group-hover:bg-sovereign-primary transition-colors"></span>
                                        Liability & Risk
                                    </li>
                                </ul>
                            </div>

                            <!-- Contract Specs -->
                            <div class="p-8 rounded-[2rem] bg-white/[0.02] border border-white/5 backdrop-blur-sm">
                                <h3 class="text-xs font-black uppercase tracking-[0.3em] text-white mb-6">Contract Specs</h3>
                                <dl class="space-y-6">
                                    <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-sovereign-primary">Jurisdiction</dt>
                                        <dd class="text-xs text-gray-500 mt-2 leading-relaxed">Special Economic Zone (SEZ) / International Digital Waters.</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-sovereign-primary">Governing Law</dt>
                                        <dd class="text-xs text-gray-500 mt-2 leading-relaxed">Sovereign Protocol Consensus V2.0 & Common Law.</dd>
                                    </div>
                                     <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-sovereign-primary">Arbitration</dt>
                                        <dd class="text-xs text-gray-500 mt-2 leading-relaxed">Binding cryptographic arbitration via LUME Ledger disputes.</dd>
                                    </div>
                                </dl>
                            </div>

                             <!-- Download -->
                            <div class="p-6 rounded-[2rem] border border-white/5 hover:border-white/10 transition-colors group cursor-pointer bg-white/[0.02]">
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="text-xs font-black uppercase tracking-[0.3em] text-white">Full Text</h3>
                                    <svg class="w-4 h-4 text-gray-500 group-hover:text-sovereign-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                </div>
                                <p class="text-[10px] text-gray-500 font-mono mb-4">Terms_of_Operation_v1.0.pdf</p>
                                <div class="w-full bg-white/5 h-0.5 rounded-full overflow-hidden">
                                    <div class="bg-sovereign-primary w-1/3 h-full group-hover:w-full transition-all duration-700 ease-out"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-8 space-y-16">
                         <!-- Section 1 -->
                        <div id="compliance" class="reveal group relative pl-8 lg:pl-12 border-l-2 border-white/5 hover:border-lume-primary transition-colors duration-500">
                            <div class="absolute -left-[9px] top-0 h-4 w-4 rounded-full bg-brand-dark border-2 border-white/10 group-hover:border-lume-primary group-hover:bg-lume-primary transition-colors duration-500"></div>
                            <h2 class="text-2xl lg:text-4xl font-black text-white uppercase italic tracking-tight mb-6 flex items-center gap-4">
                                <span class="text-lume-primary opacity-50 text-lg lg:text-xl">01.</span>
                                Acceptance of Protocol
                            </h2>
                            <div class="prose prose-invert prose-lg text-gray-400">
                                <p class="leading-relaxed">
                                    By initializing a connection to the LUME Network, whether via API, web interface, or CLI, you explicitly consent to become a node within our governance structure. This is a binding agreement between you ("The Operator") and LUME Protocol ("The Core").
                                </p>
                            </div>
                        </div>

                         <!-- Section 2: Account Terms -->
                        <div id="accounts" class="reveal group relative pl-8 lg:pl-12 border-l-2 border-white/5 hover:border-lume-primary transition-colors duration-500">
                            <div class="absolute -left-[9px] top-0 h-4 w-4 rounded-full bg-brand-dark border-2 border-white/10 group-hover:border-lume-primary group-hover:bg-lume-primary transition-colors duration-500"></div>
                            <h2 class="text-2xl lg:text-4xl font-black text-white uppercase italic tracking-tight mb-6 flex items-center gap-4">
                                <span class="text-lume-primary opacity-50 text-lg lg:text-xl">02.</span>
                                Sovereign Account Terms
                            </h2>
                            <div class="prose prose-invert prose-lg text-gray-400">
                                <ul class="space-y-4">
                                    <li class="flex items-start gap-4">
                                        <div class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0"></div>
                                        <span><strong>Human Requirement:</strong> Accounts registered by "bots" or automated scripts are unauthorized unless explicitly authenticated via API Gateway keys.</span>
                                    </li>
                                    <li class="flex items-start gap-4">
                                        <div class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0"></div>
                                        <span><strong>Age Gate:</strong> You must be 13 years or older (or the age of digital majority in your jurisdiction) to operate a node.</span>
                                    </li>
                                    <li class="flex items-start gap-4">
                                         <div class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0"></div>
                                        <span><strong>Security Responsibility:</strong> You are solely responsible for maintaining the confidentiality of your private keys and credentials. LUME cannot recover assets lost due to compromised local security.</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Section 3: Asset Sovereignty -->
                        <div id="assets" class="reveal delay-100 group relative pl-8 lg:pl-12 border-l-2 border-white/5 hover:border-sovereign-primary transition-colors duration-500">
                            <div class="absolute -left-[9px] top-0 h-4 w-4 rounded-full bg-brand-dark border-2 border-white/10 group-hover:border-sovereign-primary group-hover:bg-sovereign-primary transition-colors duration-500"></div>
                            <h2 class="text-2xl lg:text-4xl font-black text-white uppercase italic tracking-tight mb-6 flex items-center gap-4">
                                <span class="text-sovereign-primary opacity-50 text-lg lg:text-xl">03.</span>
                                Asset Sovereignty
                            </h2>
                            <div class="prose prose-invert prose-lg text-gray-400">
                                <p class="leading-relaxed">
                                    You retain absolute cryptographic ownership of all assets submitted to the Sovereign Vault. LUME acts solely as a verification engine and settlement layer. We claim no IP rights over your code, data, or creative works.
                                </p>
                                <ul class="mt-6 space-y-4">
                                    <li class="flex items-start gap-3 text-sm font-bold text-gray-300">
                                        <span class="text-sovereign-primary">Grant //</span>
                                        You grant LUME a limited, non-exclusive license solely to host, parse, scan, and display your Content as necessary to provide the Service (including forensic auditing).
                                    </li>
                                    <li class="flex items-start gap-3 text-sm font-bold text-gray-300">
                                        <span class="text-sovereign-primary">Private Vaults //</span>
                                        Content in private repositories remains confidential to you and authorized collaborators. LUME personnel do not access private content unless required for security reasons or support (with your consent).
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Section 4: Conduct -->
                        <div id="conduct" class="reveal delay-200 group relative pl-8 lg:pl-12 border-l-2 border-white/5 hover:border-red-500 transition-colors duration-500">
                            <div class="absolute -left-[9px] top-0 h-4 w-4 rounded-full bg-brand-dark border-2 border-white/10 group-hover:border-red-500 group-hover:bg-red-500 transition-colors duration-500"></div>
                            <h2 class="text-2xl lg:text-4xl font-black text-white uppercase italic tracking-tight mb-6 flex items-center gap-4">
                                <span class="text-red-500 opacity-50 text-lg lg:text-xl">04.</span>
                                Malicious Vectors & Abuse
                            </h2>
                            <div class="prose prose-invert prose-lg text-gray-400">
                                <p class="leading-relaxed">
                                    Any attempt to inject malicious code, exploit zero-day vulnerabilities in the LUME infrastructure, or use the platform for distribution of malware will result in immediate termination. You agree not to:
                                </p>
                                <ul class="mt-4 space-y-2 list-disc pl-5">
                                    <li>Upload code containing active malware, viruses, or ransomware.</li>
                                    <li>Attempt to reverse-engineer the LUME Forensic Engine.</li>
                                    <li>Use the Service for illegal activities or to promote harm.</li>
                                </ul>
                            </div>
                        </div>
                        
                         <!-- Section 5: Liability -->
                        <div id="liability" class="reveal group relative pl-8 lg:pl-12 border-l-2 border-white/5 hover:border-emerald-400 transition-colors duration-500">
                            <div class="absolute -left-[9px] top-0 h-4 w-4 rounded-full bg-brand-dark border-2 border-white/10 group-hover:border-emerald-400 group-hover:bg-emerald-400 transition-colors duration-500"></div>
                            <h2 class="text-2xl lg:text-4xl font-black text-white uppercase italic tracking-tight mb-6 flex items-center gap-4">
                                <span class="text-emerald-400 opacity-50 text-lg lg:text-xl">05.</span>
                                Disclaimer & Limitation of Liability
                            </h2>
                            <div class="prose prose-invert prose-lg text-gray-400">
                                <p class="leading-relaxed mb-6">
                                    <strong>"AS IS" BASIS:</strong> The Service is provided "AS IS" and "AS AVAILABLE". LUME disclaims all warranties, express or implied.
                                </p>
                                <p class="leading-relaxed">
                                    <strong>FORENSIC LIMITATION:</strong> The Integrity Score is an algorithmic probability, not a guarantee of security. LUME is not liable for damages resulting from vulnerabilities that the Forensic Engine fails to detect. In no event will LUME be liable for lost profits or data.
                                </p>
                            </div>
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
