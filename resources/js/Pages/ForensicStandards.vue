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
</script>

<template>
    <Head title="Forensic Standards" />

    <PublicLayout>
        <!-- Hero Section -->
        <section class="relative pt-32 pb-20 lg:pt-40 lg:pb-24 overflow-hidden">
            <!-- Background Gradients -->
            <div class="absolute inset-0 pointer-events-none">
                 <div class="absolute top-[20%] left-[20%] w-[800px] h-[800px] bg-lume-primary/5 rounded-full blur-[150px] animate-pulse" style="animation-duration: 8s" />
                <div class="absolute bottom-0 right-0 w-[600px] h-[600px] bg-lume-primary/5 rounded-full blur-[120px]" />
            </div>

            <div class="relative z-10 mx-auto max-w-5xl px-6 lg:px-8 text-left">
                <div class="reveal">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#CBB48A]/10 border border-[#CBB48A]/20 backdrop-blur-sm mb-6">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#CBB48A] animate-pulse"></span>
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-[#CBB48A]">Compliance framework</span>
                    </div>
                    
                    <h1 class="text-4xl lg:text-6xl font-bold text-white tracking-tight leading-tight mb-6">
                        The six vectors of <br><span class="text-transparent bg-clip-text bg-gradient-to-r from-[#CBB48A] via-[#DCC8A5] to-[#F3E7C9]">sovereign integrity.</span>
                    </h1>
                    
                    <p class="text-base lg:text-lg text-gray-400 font-medium max-w-3xl leading-relaxed border-l-2 border-[#CBB48A]/30 pl-6">
                        To guarantee high-grade compliance, staging codebases must survive the Lume multi-vector stress testing pipeline. Every asset is audited across six distinct verification tracks to establish an absolute chain of trust.
                    </p>
                </div>
            </div>
        </section>

        <!-- The 6 Vectors Grid -->
        <section class="relative pb-24 lg:pb-32">
            <div class="mx-auto max-w-5xl px-6 lg:px-8">
                 <div class="grid md:grid-cols-2 gap-8">
                     
                     <!-- Vector 1 -->
                     <div class="reveal group relative overflow-hidden rounded-3xl bg-white/[0.01] border border-white/5 hover:border-[#CBB48A]/30 hover:bg-white/[0.02] transition-all duration-500 p-8 lg:p-10">
                          <div class="absolute -right-10 -top-10 h-32 w-32 bg-[#CBB48A]/5 blur-[50px] group-hover:bg-[#CBB48A]/10 transition-all"></div>
                          <div class="relative z-10 space-y-4">
                              <div class="text-6xl font-bold text-white/5 absolute top-2 right-2 select-none group-hover:text-[#CBB48A]/10 transition-colors font-sans">01</div>
                              
                              <div class="h-10 w-10 rounded-xl bg-[#CBB48A]/10 flex items-center justify-center text-[#CBB48A] border border-[#CBB48A]/20">
                                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                              </div>
                              
                              <h3 class="text-lg font-bold text-white tracking-tight">Strategic vulnerability audits</h3>
                              <p class="text-xs text-gray-400 font-medium leading-relaxed">
                                  Static and dynamic heuristic scans examine code structures to isolate high-risk constructs, race conditions, logic bombs, and credentials committed in version history metadata. Standard vulnerability databases (such as CVE listings) are checked dynamically, but our primary focus remains custom heuristic modeling.
                              </p>
                              <p class="text-xs text-gray-400 font-medium leading-relaxed">
                                  By modeling code execution flows as Directed Acyclic Graphs (DAGs), Lume is able to trace untrusted user inputs to their evaluation sinks, exposing potential injection vulnerabilities before the codebase runs in staging environments.
                              </p>
                          </div>
                     </div>

                     <!-- Vector 2 -->
                     <div class="reveal delay-100 group relative overflow-hidden rounded-3xl bg-white/[0.01] border border-white/5 hover:border-[#CBB48A]/30 hover:bg-white/[0.02] transition-all duration-500 p-8 lg:p-10">
                          <div class="absolute -right-10 -top-10 h-32 w-32 bg-[#CBB48A]/5 blur-[50px] group-hover:bg-[#CBB48A]/10 transition-all"></div>
                          <div class="relative z-10 space-y-4">
                              <div class="text-6xl font-bold text-white/5 absolute top-2 right-2 select-none group-hover:text-[#CBB48A]/10 transition-colors font-sans">02</div>
                              
                              <div class="h-10 w-10 rounded-xl bg-[#CBB48A]/10 flex items-center justify-center text-[#CBB48A] border border-[#CBB48A]/20">
                                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                              </div>
                              
                              <h3 class="text-lg font-bold text-white tracking-tight">IP purity verification</h3>
                              <p class="text-xs text-gray-400 font-medium leading-relaxed">
                                  Ensuring code originalism and licensing alignment is key during transfers. We cross-reference imports against millions of public repositories to audit licensing schemas (e.g. GPL copyleft alerts, LGPL restrictions, or MIT/Apache permissions).
                              </p>
                              <p class="text-xs text-gray-400 font-medium leading-relaxed">
                                  The engine highlights potential software plagiarism risks, missing notices, or conflicting legal boundaries that could compromise the intellectual property value of the staged asset.
                              </p>
                          </div>
                     </div>

                     <!-- Vector 3 -->
                     <div class="reveal delay-200 group relative overflow-hidden rounded-3xl bg-white/[0.01] border border-white/5 hover:border-[#CBB48A]/30 hover:bg-white/[0.02] transition-all duration-500 p-8 lg:p-10">
                          <div class="absolute -right-10 -top-10 h-32 w-32 bg-[#CBB48A]/5 blur-[50px] group-hover:bg-[#CBB48A]/10 transition-all"></div>
                          <div class="relative z-10 space-y-4">
                              <div class="text-6xl font-bold text-white/5 absolute top-2 right-2 select-none group-hover:text-[#CBB48A]/10 transition-colors font-sans">03</div>
                              
                              <div class="h-10 w-10 rounded-xl bg-[#CBB48A]/10 flex items-center justify-center text-[#CBB48A] border border-[#CBB48A]/20">
                                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                              </div>
                              
                              <h3 class="text-lg font-bold text-white tracking-tight">Architectural robustness</h3>
                              <p class="text-xs text-gray-400 font-medium leading-relaxed">
                                  Our parser analyzes overall codebase architectural quality, measuring metrics such as cyclomatic complexity, code coupling, module isolation, anti-patterns, and documentation coverage rates.
                              </p>
                              <p class="text-xs text-gray-400 font-medium leading-relaxed">
                                  Codebases that fail to maintain modular encapsulation, contain excessive spaghetti logic, or lack basic configuration standard profiles are flagged to prevent engineering debt accumulation.
                              </p>
                          </div>
                     </div>

                     <!-- Vector 4 -->
                     <div class="reveal group relative overflow-hidden rounded-3xl bg-white/[0.01] border border-white/5 hover:border-[#CBB48A]/30 hover:bg-white/[0.02] transition-all duration-500 p-8 lg:p-10">
                          <div class="absolute -right-10 -top-10 h-32 w-32 bg-[#CBB48A]/5 blur-[50px] group-hover:bg-[#CBB48A]/10 transition-all"></div>
                          <div class="relative z-10 space-y-4">
                              <div class="text-6xl font-bold text-white/5 absolute top-2 right-2 select-none group-hover:text-[#CBB48A]/10 transition-colors font-sans">04</div>
                              
                              <div class="h-10 w-10 rounded-xl bg-[#CBB48A]/10 flex items-center justify-center text-[#CBB48A] border border-[#CBB48A]/20">
                                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                              </div>
                              
                              <h3 class="text-lg font-bold text-white tracking-tight">Container sandbox isolation</h3>
                              <p class="text-xs text-gray-400 font-medium leading-relaxed">
                                  Verifying execution safety boundaries. The analysis environment checks target files to ensure that container configurations, host mounts, and networking boundaries respect strict sandboxing standards.
                              </p>
                              <p class="text-xs text-gray-400 font-medium leading-relaxed">
                                  Any attempt to perform unauthorized root privilege escalation, break volume scopes, or bypass hypervisor kernels during isolated evaluation phases triggers instant kill-switch sequences.
                              </p>
                          </div>
                     </div>

                     <!-- Vector 5 -->
                     <div class="reveal delay-100 group relative overflow-hidden rounded-3xl bg-white/[0.01] border border-white/5 hover:border-[#CBB48A]/30 hover:bg-white/[0.02] transition-all duration-500 p-8 lg:p-10">
                          <div class="absolute -right-10 -top-10 h-32 w-32 bg-[#CBB48A]/5 blur-[50px] group-hover:bg-[#CBB48A]/10 transition-all"></div>
                          <div class="relative z-10 space-y-4">
                              <div class="text-6xl font-bold text-white/5 absolute top-2 right-2 select-none group-hover:text-[#CBB48A]/10 transition-colors font-sans">05</div>
                              
                              <div class="h-10 w-10 rounded-xl bg-[#CBB48A]/10 flex items-center justify-center text-[#CBB48A] border border-[#CBB48A]/20">
                                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                              </div>
                              
                              <h3 class="text-lg font-bold text-white tracking-tight">Data sovereignty audit</h3>
                              <p class="text-xs text-gray-400 font-medium leading-relaxed">
                                  Scanning for compliance with data collection regulations (e.g. GDPR, CCPA). This vector audits network requests, outbound connections, APIs endpoints, and trackers in the code.
                              </p>
                              <p class="text-xs text-gray-400 font-medium leading-relaxed">
                                  Any background telemetry flows, unconsented analytics sync paths, or unauthorized profiling protocols are flagged to safeguard operator and client data sovereignty.
                              </p>
                          </div>
                     </div>

                     <!-- Vector 6 -->
                     <div class="reveal delay-200 group relative overflow-hidden rounded-3xl bg-white/[0.01] border border-white/5 hover:border-[#CBB48A]/30 hover:bg-white/[0.02] transition-all duration-500 p-8 lg:p-10">
                          <div class="absolute -right-10 -top-10 h-32 w-32 bg-[#CBB48A]/5 blur-[50px] group-hover:bg-[#CBB48A]/10 transition-all"></div>
                          <div class="relative z-10 space-y-4">
                              <div class="text-6xl font-bold text-white/5 absolute top-2 right-2 select-none group-hover:text-[#CBB48A]/10 transition-colors font-sans">06</div>
                              
                              <div class="h-10 w-10 rounded-xl bg-[#CBB48A]/10 flex items-center justify-center text-[#CBB48A] border border-[#CBB48A]/20">
                                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                              </div>
                              
                              <h3 class="text-lg font-bold text-white tracking-tight">Ethical compliance checks</h3>
                              <p class="text-xs text-gray-400 font-medium leading-relaxed">
                                  Reviewing codebases for dark patterns, concealed functions, background crypto-miners, ransomware footprints, and deceptive user interface mechanics.
                              </p>
                              <p class="text-xs text-gray-400 font-medium leading-relaxed">
                                  Our verification algorithms audit codebase imports and binary signatures to ensure the asset aligns with standard consumer safety rules and fair operating agreements.
                              </p>
                          </div>
                     </div>

                 </div>
            </div>
        </section>

        <!-- Final Statement -->
         <section class="py-24 border-t border-white/5 bg-white/[0.01]">
             <div class="mx-auto max-w-5xl px-6 lg:px-8 text-center reveal">
                 <h2 class="text-2xl lg:text-3xl font-bold text-white tracking-tight mb-4">The sovereign validation standard</h2>
                 <p class="text-sm text-gray-400 mb-10 max-w-xl mx-auto font-medium leading-relaxed">Assets that successfully survive all validation tracks are certified with a Proof of Audit verification mark, creating an immutable history of repository integrity.</p>
                 
                 <div class="inline-flex items-center gap-4 px-6 py-3 rounded-2xl bg-gradient-to-r from-[#CBB48A]/10 to-transparent border border-[#CBB48A]/20">
                     <span class="h-2 w-2 rounded-full bg-[#CBB48A] animate-pulse"></span>
                     <span class="text-[10px] font-semibold uppercase tracking-wider text-[#CBB48A]">Auditing pipeline online</span>
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
