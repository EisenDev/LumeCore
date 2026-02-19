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
        <section class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
            <!-- Background Gradients -->
            <div class="absolute inset-0 pointer-events-none">
                 <div class="absolute top-[20%] left-[20%] w-[800px] h-[800px] bg-red-500/5 rounded-full blur-[150px] animate-pulse" style="animation-duration: 8s" />
                <div class="absolute bottom-0 right-0 w-[600px] h-[600px] bg-lume-primary/5 rounded-full blur-[120px]" />
            </div>

            <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center sm:text-left">
                <div class="reveal">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-500/10 border border-red-500/20 backdrop-blur-sm mb-6">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-red-500">Critical Infrastructure</span>
                    </div>
                    
                    <h1 class="text-5xl lg:text-9xl font-[1000] text-white tracking-tighter uppercase italic leading-[0.85] mb-8">
                        The Six <br><span class="text-transparent bg-clip-text bg-gradient-to-r from-red-500 via-orange-500 to-yellow-500">Vectors.</span>
                    </h1>
                    
                    <p class="text-lg lg:text-2xl text-gray-400 font-medium max-w-3xl leading-relaxed sm:border-l-2 sm:border-red-500/30 sm:pl-6">
                        Assets must survive the LUME 6-Vector Stress Test to verify sovereign integrity. Only code scoring <span class="text-white font-bold">85+ AIS</span> survives.
                    </p>
                </div>
            </div>
        </section>

        <!-- The 6 Vectors Grid -->
        <section class="relative pb-24 lg:pb-32">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                 <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                     
                     <!-- Vector 1 -->
                     <div class="reveal group relative overflow-hidden rounded-[2.5rem] bg-white/[0.02] border border-white/5 hover:border-red-500/50 hover:bg-white/[0.04] transition-all duration-500 p-8 lg:p-10">
                         <div class="absolute -right-10 -top-10 h-32 w-32 bg-red-500/10 blur-[50px] group-hover:bg-red-500/20 transition-all"></div>
                         <div class="relative z-10">
                             <div class="text-[120px] font-[1000] text-white/5 absolute -bottom-10 -right-6 select-none group-hover:text-red-500/10 transition-colors">01</div>
                             
                             <div class="h-12 w-12 rounded-xl bg-red-500/10 flex items-center justify-center text-red-500 mb-6 border border-red-500/20 shadow-[0_0_15px_rgba(239,68,68,0.2)]">
                                 <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                             </div>
                             
                             <h3 class="text-2xl font-black text-white italic uppercase tracking-tight mb-4">Strategic <br>Vulnerability</h3>
                             <p class="text-sm text-gray-400 font-medium leading-relaxed mb-6">
                                 Deep heuristic analysis for logic bombs, race conditions, and hidden backdoors undetectable by standard CVE scans.
                             </p>
                             
                             <div class="space-y-2">
                                 <div class="h-1 w-full bg-white/5 rounded-full overflow-hidden"><div class="h-full w-3/4 bg-red-500"></div></div>
                                 <div class="flex justify-between text-[8px] font-black uppercase text-gray-500 tracking-widest">
                                     <span>Severity</span>
                                     <span class="text-red-500">Critical</span>
                                 </div>
                             </div>
                         </div>
                     </div>

                     <!-- Vector 2 -->
                     <div class="reveal delay-100 group relative overflow-hidden rounded-[2.5rem] bg-white/[0.02] border border-white/5 hover:border-lume-primary/50 hover:bg-white/[0.04] transition-all duration-500 p-8 lg:p-10">
                         <div class="absolute -right-10 -top-10 h-32 w-32 bg-lume-primary/10 blur-[50px] group-hover:bg-lume-primary/20 transition-all"></div>
                         <div class="relative z-10">
                             <div class="text-[120px] font-[1000] text-white/5 absolute -bottom-10 -right-6 select-none group-hover:text-lume-primary/10 transition-colors">02</div>
                             
                             <div class="h-12 w-12 rounded-xl bg-lume-primary/10 flex items-center justify-center text-lume-primary mb-6 border border-lume-primary/20 shadow-[0_0_15px_rgba(16,185,129,0.2)]">
                                 <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                             </div>
                             
                             <h3 class="text-2xl font-black text-white italic uppercase tracking-tight mb-4">IP Purity <br>Verification</h3>
                             <p class="text-sm text-gray-400 font-medium leading-relaxed mb-6">
                                 Global database cross-referencing to ensure code originality. Detects license violations, plagiarism, and unauthorized forks.
                             </p>
                             
                              <div class="space-y-2">
                                 <div class="h-1 w-full bg-white/5 rounded-full overflow-hidden"><div class="h-full w-[90%] bg-lume-primary"></div></div>
                                 <div class="flex justify-between text-[8px] font-black uppercase text-gray-500 tracking-widest">
                                     <span>Originality</span>
                                     <span class="text-lume-primary">Mandatory</span>
                                 </div>
                             </div>
                         </div>
                     </div>

                     <!-- Vector 3 -->
                     <div class="reveal delay-200 group relative overflow-hidden rounded-[2.5rem] bg-white/[0.02] border border-white/5 hover:border-cyan-400/50 hover:bg-white/[0.04] transition-all duration-500 p-8 lg:p-10">
                         <div class="absolute -right-10 -top-10 h-32 w-32 bg-cyan-400/10 blur-[50px] group-hover:bg-cyan-400/20 transition-all"></div>
                         <div class="relative z-10">
                             <div class="text-[120px] font-[1000] text-white/5 absolute -bottom-10 -right-6 select-none group-hover:text-cyan-400/10 transition-colors">03</div>
                             
                             <div class="h-12 w-12 rounded-xl bg-cyan-400/10 flex items-center justify-center text-cyan-400 mb-6 border border-cyan-400/20 shadow-[0_0_15px_rgba(34,211,238,0.2)]">
                                 <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                             </div>
                             
                             <h3 class="text-2xl font-black text-white italic uppercase tracking-tight mb-4">Architectural <br>Robustness</h3>
                             <p class="text-sm text-gray-400 font-medium leading-relaxed mb-6">
                                 Assessment of code modularity, documentation density, and anti-pattern usage. Spaghetti code is rejected at the gate.
                             </p>
                             
                              <div class="space-y-2">
                                 <div class="h-1 w-full bg-white/5 rounded-full overflow-hidden"><div class="h-full w-1/2 bg-cyan-400"></div></div>
                                 <div class="flex justify-between text-[8px] font-black uppercase text-gray-500 tracking-widest">
                                     <span>Maintainability</span>
                                     <span class="text-cyan-400">High Standard</span>
                                 </div>
                             </div>
                         </div>
                     </div>

                     <!-- Vector 4 -->
                     <div class="reveal group relative overflow-hidden rounded-[2.5rem] bg-white/[0.02] border border-white/5 hover:border-purple-500/50 hover:bg-white/[0.04] transition-all duration-500 p-8 lg:p-10">
                         <div class="absolute -right-10 -top-10 h-32 w-32 bg-purple-500/10 blur-[50px] group-hover:bg-purple-500/20 transition-all"></div>
                         <div class="relative z-10">
                             <div class="text-[120px] font-[1000] text-white/5 absolute -bottom-10 -right-6 select-none group-hover:text-purple-500/10 transition-colors">04</div>
                             
                             <div class="h-12 w-12 rounded-xl bg-purple-500/10 flex items-center justify-center text-purple-500 mb-6 border border-purple-500/20 shadow-[0_0_15px_rgba(168,85,247,0.2)]">
                                 <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                             </div>
                             
                             <h3 class="text-2xl font-black text-white italic uppercase tracking-tight mb-4">Container <br>Isolation</h3>
                             <p class="text-sm text-gray-400 font-medium leading-relaxed mb-6">
                                 Verification of execution sandboxing. Does the app respect host boundaries? Privilege escalation attempts are flagged.
                             </p>
                         </div>
                     </div>

                     <!-- Vector 5 -->
                     <div class="reveal delay-100 group relative overflow-hidden rounded-[2.5rem] bg-white/[0.02] border border-white/5 hover:border-yellow-500/50 hover:bg-white/[0.04] transition-all duration-500 p-8 lg:p-10">
                         <div class="absolute -right-10 -top-10 h-32 w-32 bg-yellow-500/10 blur-[50px] group-hover:bg-yellow-500/20 transition-all"></div>
                         <div class="relative z-10">
                             <div class="text-[120px] font-[1000] text-white/5 absolute -bottom-10 -right-6 select-none group-hover:text-yellow-500/10 transition-colors">05</div>
                             
                             <div class="h-12 w-12 rounded-xl bg-yellow-500/10 flex items-center justify-center text-yellow-500 mb-6 border border-yellow-500/20 shadow-[0_0_15px_rgba(234,179,8,0.2)]">
                                 <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                             </div>
                             
                             <h3 class="text-2xl font-black text-white italic uppercase tracking-tight mb-4">Data <br>Sovereignty</h3>
                             <p class="text-sm text-gray-400 font-medium leading-relaxed mb-6">
                                 Local-first architecture verification. Ensuring no unauthorized telemetry or cloud syncing occurs without consent.
                             </p>
                         </div>
                     </div>

                     <!-- Vector 6 -->
                     <div class="reveal delay-200 group relative overflow-hidden rounded-[2.5rem] bg-white/[0.02] border border-white/5 hover:border-pink-500/50 hover:bg-white/[0.04] transition-all duration-500 p-8 lg:p-10">
                         <div class="absolute -right-10 -top-10 h-32 w-32 bg-pink-500/10 blur-[50px] group-hover:bg-pink-500/20 transition-all"></div>
                         <div class="relative z-10">
                             <div class="text-[120px] font-[1000] text-white/5 absolute -bottom-10 -right-6 select-none group-hover:text-pink-500/10 transition-colors">06</div>
                             
                             <div class="h-12 w-12 rounded-xl bg-pink-500/10 flex items-center justify-center text-pink-500 mb-6 border border-pink-500/20 shadow-[0_0_15px_rgba(236,72,153,0.2)]">
                                 <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                             </div>
                             
                             <h3 class="text-2xl font-black text-white italic uppercase tracking-tight mb-4">Ethical <br>Compliance</h3>
                             <p class="text-sm text-gray-400 font-medium leading-relaxed mb-6">
                                 Blacklist scanning for miner scripts, ransomware signatures, and dark patterns that deceive users.
                             </p>
                         </div>
                     </div>

                 </div>
            </div>
        </section>

        <!-- Final Statement -->
         <section class="py-24 border-t border-white/5 bg-white/[0.02]">
             <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center reveal">
                 <h2 class="text-3xl lg:text-5xl font-black text-white uppercase italic tracking-tighter mb-8">The Gold Standard.</h2>
                 <p class="text-lg text-gray-400 mb-12 max-w-2xl mx-auto">Assets that pass all 6 vectors are minted with a "Proof of Audit" NFT, creating a verifiable chain of trust.</p>
                 
                 <div class="inline-flex items-center gap-4 px-6 py-3 rounded-2xl bg-gradient-to-r from-lume-primary/10 to-transparent border border-lume-primary/20">
                     <span class="h-2 w-2 rounded-full bg-lume-primary animate-pulse"></span>
                     <span class="text-xs font-black uppercase tracking-[0.2em] text-lume-primary">Auditing Engine Online</span>
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
