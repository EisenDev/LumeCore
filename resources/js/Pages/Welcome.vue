<script setup lang="ts">
/**
 * LUME Welcome/Landing Page
 * Professional landing page with hero section, features, and pricing
 */
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import LumeAISupport from '@/Components/LumeAISupport.vue';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
}>();

// Mobile menu state
const mobileMenuOpen = ref(false);

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
    <Head title="LUME - The Sovereign Operating System for High-Value Assets" />

    <div class="min-h-screen bg-brand-dark selection:bg-lume-primary/30 selection:text-white font-sans antialiased overflow-x-hidden">
        <!-- Grain Overlay -->
        <div class="fixed inset-0 z-[100] pointer-events-none opacity-[0.03] bg-[url('https://grainy-gradients.vercel.app/noise.svg')] brightness-100 contrast-150" />

        <!-- Navigation Bar -->
        <nav class="sticky top-0 z-[60] border-b border-white/5 bg-brand-dark/40 backdrop-blur-2xl transition-all duration-500">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-20 items-center justify-between">
                    <!-- Left: Logo -->
                    <div class="flex items-center gap-4 group cursor-pointer">
                        <div class="relative">
                            <div class="absolute -inset-2 bg-gradient-to-r from-lume-primary to-sovereign-primary rounded-xl blur-lg opacity-20 group-hover:opacity-40 transition-opacity" />
                            <div class="relative flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-lume-primary to-sovereign-primary shadow-xl shadow-lume-primary/20 transition-transform group-hover:scale-105">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                        </div>
                        <span class="text-2xl font-black tracking-tighter text-white uppercase italic">LUME<span class="text-lume-primary not-italic">CORE</span></span>
                    </div>

                    <!-- Middle: Links (Desktop) -->
                    <div class="hidden items-center gap-10 md:flex">
                        <Link :href="route('marketplace.index')" class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-400 transition-all hover:text-white hover:tracking-[0.4em]">
                            Marketplace
                        </Link>
                        <a href="#forensics" class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-400 transition-all hover:text-white hover:tracking-[0.4em]">
                            Forensics
                        </a>
                        <a href="#sync" class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-400 transition-all hover:text-white hover:tracking-[0.4em]">
                            Asset Sync
                        </a>
                        <a href="#pricing" class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-400 transition-all hover:text-white hover:tracking-[0.4em]">
                            Pricing
                        </a>
                    </div>

                    <!-- Right: Auth Buttons -->
                    <div class="flex items-center gap-6">
                        <template v-if="$page.props.auth.user">
                            <Link
                                :href="route('dashboard')"
                                class="relative group rounded-full bg-white px-6 py-2.5 text-xs font-black uppercase tracking-widest text-black transition-all hover:scale-105 active:scale-95 shadow-[0_0_20px_rgba(255,255,255,0.2)]"
                            >
                                <span class="relative z-10">Dashboard</span>
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                v-if="canLogin"
                                :href="route('login')"
                                class="hidden px-4 py-2 text-xs font-black uppercase tracking-widest text-gray-400 transition-all hover:text-white hover:tracking-[0.2em] sm:block border-r border-white/5 pr-8"
                            >
                                Log In
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="relative group rounded-full bg-gradient-to-r from-lume-primary to-lume-secondary px-8 py-3 text-xs font-black uppercase tracking-[0.2em] text-white shadow-xl shadow-lume-primary/30 transition-all hover:brightness-110 active:scale-95"
                            >
                                <span class="relative z-10">Get Access</span>
                            </Link>
                        </template>

                        <!-- Mobile Menu Button -->
                        <button
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            class="rounded-xl p-2.5 text-gray-400 hover:bg-white/5 hover:text-white md:hidden ring-1 ring-white/5 backdrop-blur-sm"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Mobile Menu -->
                <transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="transform -translate-y-4 opacity-0"
                    enter-to-class="transform translate-y-0 opacity-100"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="transform translate-y-0 opacity-100"
                    leave-to-class="transform -translate-y-4 opacity-0"
                >
                    <div v-if="mobileMenuOpen" class="border-t border-white/5 py-8 md:hidden bg-brand-dark/95 backdrop-blur-2xl px-4 absolute left-0 right-0 top-full shadow-2xl">
                        <div class="flex flex-col gap-6">
                            <Link :href="route('marketplace.index')" class="text-xs font-black uppercase tracking-[0.3em] text-gray-400 transition-colors hover:text-white">
                                Marketplace
                            </Link>
                            <a href="#forensics" class="text-xs font-black uppercase tracking-[0.3em] text-gray-400 transition-colors hover:text-white">
                                Forensic Core
                            </a>
                            <a href="#pricing" class="text-xs font-black uppercase tracking-[0.3em] text-gray-400 transition-colors hover:text-white">
                                Pricing
                            </a>
                            <Link v-if="canLogin && !$page.props.auth.user" :href="route('login')" class="text-xs font-black uppercase tracking-[0.3em] text-gray-400 transition-colors hover:text-white mt-4 pt-4 border-t border-white/5">
                                Log In
                            </Link>
                        </div>
                    </div>
                </transition>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="relative min-h-[85vh] flex flex-col items-center justify-center pt-20 pb-12 lg:pt-32 lg:pb-32 overflow-hidden">
            <!-- Advanced Mesh Gradients -->
            <div class="absolute inset-0 z-0">
                <div class="absolute left-1/2 top-[-10%] h-[600px] w-[900px] -translate-x-1/2 rounded-full bg-lume-primary/20 blur-[140px] animate-pulse transition-all duration-[4s]" />
                <div class="absolute right-[10%] top-[20%] h-[400px] w-[400px] rounded-full bg-sovereign-primary/10 blur-[120px] animate-pulse [animation-delay:2s] duration-[6s]" />
                <div class="absolute left-[5%] bottom-[10%] h-[500px] w-[500px] rounded-full bg-lume-secondary/10 blur-[110px] animate-pulse [animation-delay:1s] duration-[5s]" />
            </div>
            
            <div class="relative z-10 mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                <!-- Floating Badge -->
                <div class="reveal mb-8 lg:mb-12 inline-flex items-center gap-3 rounded-full border border-white/10 bg-white/[0.03] px-5 py-2 lg:px-6 lg:py-2.5 backdrop-blur-md shadow-2xl transition-all hover:scale-105 hover:bg-white/[0.06] active:scale-95 group cursor-pointer ring-1 ring-white/5 mx-auto">
                    <span class="relative flex h-2 w-2 lg:h-2.5 lg:w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-lume-primary opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 lg:h-2.5 lg:w-2.5 bg-lume-primary"></span>
                    </span>
                    <span class="text-[8px] lg:text-[10px] font-black uppercase tracking-[0.3em] lg:tracking-[0.4em] text-white/90 group-hover:text-white transition-colors">LUME CORE V6.6 LIVE</span>
                    <div class="h-3 lg:h-4 w-[1px] bg-white/10" />
                    <span class="text-[8px] lg:text-[10px] font-black uppercase tracking-[0.3em] lg:tracking-[0.4em] text-lume-primary italic font-bold">Sovereign Edition</span>
                </div>

                <!-- Epic Headline Responsive -->
                <div class="relative perspective-[1000px]">
                    <h1 class="reveal delay-100 mx-auto max-w-[90rem] text-3xl font-[1000] tracking-[-0.05em] text-white sm:text-6xl lg:text-[6rem] xl:text-[7.5rem] leading-[0.85] mb-8 lg:mb-12 select-none uppercase">
                        <span class="block transform hover:rotate-x-12 transition-transform duration-500 ease-out cursor-default opacity-90 hover:opacity-100">THE STANDARD OF</span>
                        <span class="block bg-gradient-to-r from-emerald-400 via-cyan-400 to-emerald-500 bg-clip-text text-transparent italic tracking-[-0.07em] hover:scale-[1.01] transition-transform duration-700 pb-2">DIGITAL TRUTH.</span>
                    </h1>
                </div>

                <!-- Subtext Refined Responsive -->
                <div class="reveal delay-200 mx-auto max-w-3xl text-center lg:text-left border-l-0 lg:border-l-2 border-lume-primary/30 lg:pl-10 mt-8 lg:mt-16 group hover:border-lume-primary transition-colors">
                    <p class="text-base text-gray-400 lg:text-2xl font-medium leading-[1.4] tracking-tight px-4 lg:px-0">
                        The autonomous <span class="text-white font-bold">forensic engine</span> that verifies codebase integrity, 
                        security posture, and <span class="text-white/80 italic">ownership provenance</span> in real-time.
                    </p>
                </div>

                <!-- Premium CTA Buttons Responsive -->
                <div class="reveal delay-300 mt-12 lg:mt-20 flex flex-col items-center justify-center gap-4 sm:flex-row lg:gap-8">
                    <Link
                        :href="route('login')"
                        class="group relative flex w-full sm:w-auto items-center justify-center overflow-hidden rounded-full lg:rounded-[2.5rem] bg-white px-8 py-4 lg:px-14 lg:py-6 text-xs lg:text-sm font-black uppercase tracking-[0.2em] lg:tracking-[0.3em] text-black shadow-xl lg:shadow-[0_20px_50px_rgba(255,255,255,0.15)] transition-all hover:scale-105 active:scale-95"
                    >
                        <div class="absolute inset-0 bg-gradient-to-r from-emerald-400/20 via-transparent to-cyan-400/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <span class="relative z-10 transition-transform group-hover:translate-x-1">Start for free</span>
                        <svg class="relative z-10 h-4 w-4 ml-2 transform group-hover:translate-x-2 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M14 5l7 7m0 0l-7 7m7-7H3" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </Link>
                    <Link
                        :href="route('marketplace.index')"
                        class="flex w-full sm:w-auto items-center justify-center gap-4 rounded-full lg:rounded-[2.5rem] border border-white/5 bg-white/[0.02] px-8 py-4 lg:px-12 lg:py-6 text-xs lg:text-sm font-black uppercase tracking-[0.2em] lg:tracking-[0.3em] text-white backdrop-blur-2xl transition-all hover:bg-white/[0.06] hover:border-white/20 hover:scale-105 group shadow-2xl ring-1 ring-white/5"
                    >
                        Explore Marketplace
                        <div class="relative flex h-1.5 w-1.5 lg:h-2 lg:w-2">
                           <div class="absolute inset-0 rounded-full bg-cyan-400/40 animate-ping" />
                           <div class="relative h-1.5 w-1.5 lg:h-2 lg:w-2 rounded-full bg-cyan-400" />
                        </div>
                    </Link>
                </div>

                <!-- Hero Browser Mockup Responsive -->
                <div class="mt-16 lg:mt-32 relative px-4 lg:px-0 group max-w-6xl mx-auto">
                    <!-- Dynamic Outer Glow -->
                    <div class="absolute -inset-6 lg:-inset-10 rounded-[2rem] lg:rounded-[4rem] bg-gradient-to-b from-lume-primary/10 via-emerald-400/10 to-transparent blur-[80px] lg:blur-[120px] opacity-40 group-hover:opacity-70 transition-opacity duration-1000" />
                    
                    <!-- Floating Container Responsive -->
                    <div class="reveal delay-300 relative overflow-hidden rounded-[1.5rem] lg:rounded-[3rem] border border-white/10 bg-[#0A0A0B]/60 backdrop-blur-3xl shadow-2xl transform transition-all duration-1000 group-hover:scale-[1.01] group-hover:-translate-y-2 lg:group-hover:-translate-y-4 ring-1 ring-white/10">
                        <!-- Browser Header Responsive -->
                        <div class="flex items-center justify-between border-b border-white/5 bg-white/[0.04] px-4 py-3 lg:px-10 lg:py-6">
                            <div class="flex gap-2 lg:gap-3">
                                <div class="h-2 w-2 lg:h-3.5 lg:w-3.5 rounded-full bg-red-500/20 ring-1 ring-red-500/20" />
                                <div class="h-2 w-2 lg:h-3.5 lg:w-3.5 rounded-full bg-yellow-500/20 ring-1 ring-yellow-500/20" />
                                <div class="h-2 w-2 lg:h-3.5 lg:w-3.5 rounded-full bg-emerald-500/20 ring-1 ring-emerald-500/20" />
                            </div>
                            <div class="flex items-center gap-2 lg:gap-6 rounded-xl lg:rounded-2xl bg-black/60 px-3 lg:px-8 py-1.5 lg:py-2.5 ring-1 ring-white/10 text-[8px] lg:text-[11px] text-gray-500 font-black tracking-widest lg:tracking-[0.2em] shadow-inner font-mono truncate max-w-[50%] lg:max-w-none">
                                <div class="flex items-center gap-1 lg:gap-2">
                                    <svg class="h-3 w-3 lg:h-4 lg:w-4 text-emerald-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    <span class="opacity-50 hidden sm:inline">HTTPS://</span><span class="text-white/80">CORE.LUME.AI/</span><span class="text-emerald-400 italic">SECURE-QUANT-SCAN</span>
                                </div>
                            </div>
                            <div class="flex gap-2 lg:gap-6 items-center">
                               <div class="hidden sm:block h-1.5 lg:h-2 w-8 lg:w-12 rounded-full bg-white/5" />
                               <div class="h-6 w-6 lg:h-8 lg:w-8 rounded-full bg-gradient-to-br from-emerald-400/40 to-cyan-400/40 p-[1px]"><div class="h-full w-full rounded-full bg-black/40" /></div>
                            </div>
                        </div>

                        <!-- Mockup Content Responsive -->
                        <div class="relative flex flex-col lg:grid lg:grid-cols-12 gap-6 lg:gap-10 p-6 lg:p-12 bg-[#050505] min-h-[300px] lg:min-h-[500px]">
                            <!-- Inner Atmosphere Glows -->
                            <div class="absolute top-0 right-[-10%] h-[300px] lg:h-[600px] w-[300px] lg:w-[600px] bg-emerald-400/10 blur-[100px] lg:blur-[150px] animate-pulse" />
                            <div class="absolute bottom-[-10%] left-[-5%] h-[250px] lg:h-[500px] w-[250px] lg:w-[500px] bg-cyan-400/5 blur-[80px] lg:blur-[120px] animate-pulse [animation-delay:2s]" />

                             <!-- Sidebar Responsive -->
                             <div class="hidden lg:block lg:col-span-3 space-y-10 relative z-10">
                                <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/5 space-y-6">
                                    <div class="flex items-center gap-3">
                                        <div class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                                        <div class="h-2 w-24 rounded bg-white/10" />
                                    </div>
                                    <div class="space-y-4 text-left">
                                        <div class="h-10 w-full rounded-xl bg-lume-primary/20 border border-lume-primary/30 flex items-center px-4 text-[9px] text-lume-primary font-black tracking-[0.2em] italic">PRECISION SCAN</div>
                                        <div class="h-10 w-full rounded-xl bg-white/[0.02] border border-white/5 flex items-center px-4 text-[9px] text-gray-500 font-black tracking-[0.2em]">THREAT LEDGER</div>
                                        <div class="h-10 w-full rounded-xl bg-white/[0.02] border border-white/5 flex items-center px-4 text-[9px] text-gray-500 font-black tracking-[0.2em]">SOVEREIGN SYNC</div>
                                    </div>
                                </div>
                             </div>

                             <!-- Main Content Responsive -->
                             <div class="lg:col-span-9 space-y-6 lg:space-y-10 relative z-10">
                                <div class="flex flex-col sm:flex-row items-start sm:items-end justify-between border-b border-white/5 pb-6 lg:pb-10 gap-4 sm:gap-0">
                                    <div class="space-y-2 lg:space-y-4 text-left">
                                         <div class="inline-flex items-center gap-2 px-2 py-0.5 lg:px-3 lg:py-1 rounded-md bg-emerald-500/10 text-[7px] lg:text-[8px] font-black text-emerald-400 tracking-widest ring-1 ring-emerald-500/20">SYSTEMS NOMINAL</div>
                                         <h3 class="text-2xl lg:text-4xl font-black text-white tracking-[-0.04em] uppercase italic">Codebase <span class="text-emerald-400">Auditor.</span></h3>
                                         <p class="text-[9px] lg:text-xs text-gray-500 font-black tracking-[0.1em]">Target: HIMSOG-DIGITAL.SOL</p>
                                    </div>
                                    <div class="text-left sm:text-right">
                                         <div class="text-4xl lg:text-6xl font-[1000] text-white tracking-tighter italic leading-none">98<span class="text-emerald-400 opacity-50 not-italic">%</span></div>
                                         <div class="text-[7px] lg:text-[9px] font-black text-gray-500 tracking-[0.3em] uppercase mt-1 lg:mt-2">Integrity Score</div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
                                    <div class="md:col-span-2 h-48 lg:h-64 rounded-[1.5rem] lg:rounded-[2.5rem] bg-gradient-to-br from-white/[0.04] to-transparent border border-white/10 p-6 lg:p-8 relative group/card">
                                         <div class="absolute inset-x-10 top-0 h-[1px] bg-gradient-to-r from-transparent via-emerald-400/50 to-transparent" />
                                         <div class="grid grid-cols-1 sm:grid-cols-2 h-full gap-6 lg:gap-8">
                                             <div class="space-y-4 lg:space-y-6 text-left">
                                                 <div class="text-[8px] lg:text-[9px] font-black text-gray-500 tracking-[0.3em] uppercase">Forensic Waves</div>
                                                 <div class="space-y-3 lg:space-y-4">
                                                     <div class="h-1 lg:h-1.5 w-full bg-white/5 rounded-full overflow-hidden"><div class="h-full w-full bg-emerald-400 opacity-60" /></div>
                                                     <div class="h-1 lg:h-1.5 w-4/5 bg-white/5 rounded-full overflow-hidden"><div class="h-full w-full bg-cyan-400 opacity-60" /></div>
                                                     <div class="h-1 lg:h-1.5 w-3/4 bg-white/5 rounded-full overflow-hidden"><div class="h-full w-full bg-white/40" /></div>
                                                 </div>
                                             </div>
                                             <div class="hidden sm:flex items-center justify-center">
                                                 <div class="h-20 w-20 lg:h-32 lg:w-32 rounded-full border-[6px] lg:border-[10px] border-white/5 relative border-t-emerald-400 animate-spin" style="animation-duration: 3s">
                                                     <div class="absolute inset-2 lg:inset-4 rounded-full border-[3px] lg:border-4 border-white/5 border-b-cyan-400 animate-spin" style="animation-duration: 2s"></div>
                                                 </div>
                                             </div>
                                         </div>
                                    </div>
                                    <div class="h-40 lg:h-64 rounded-[1.5rem] lg:rounded-[2.5rem] bg-white/[0.02] border border-white/5 p-6 lg:p-8 flex flex-col justify-between group-hover:bg-white/[0.04] transition-colors overflow-hidden">
                                        <div class="text-[8px] lg:text-[9px] font-black text-gray-400 tracking-[0.4em] uppercase text-left leading-relaxed">Intelligence <br> Deep Sync</div>
                                        <div class="h-12 lg:h-20 w-full rounded-xl lg:rounded-2xl bg-emerald-400/10 border border-emerald-400/20 flex items-center justify-center">
                                            <span class="text-[10px] lg:text-xs font-black text-emerald-400 tracking-[0.3em] lg:tracking-[0.5em] animate-pulse">ACTIVE</span>
                                        </div>
                                    </div>
                                </div>
                             </div>
                        </div>
                    </div>
                </div>

                <!-- Trust Indicators -->
                <div class="reveal mt-24 text-center">
                    <p class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-500 mb-8">Trusted by the world's most innovative teams</p>
                    <div class="flex flex-wrap items-center justify-center gap-x-16 gap-y-8 opacity-40 grayscale transition-all hover:grayscale-0">
                        <!-- Placeholder Logos -->
                        <div class="text-xl font-black text-white italic">??</div>
                        <div class="text-xl font-black text-white italic tracking-tighter">??</div>
                        <div class="text-xl font-black text-white flex items-center gap-1"><span class="h-4 w-4 rounded-full border-2 border-white"/>QUANTUM</div>
                        <div class="text-xl font-black text-white italic">??</div>
                        <div class="text-xl font-black text-white">??</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- LUME Forensics: Bento Grid -->
        <section id="forensics" class="py-24 lg:py-32 relative overflow-hidden">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-16 reveal">
                    <h2 class="text-4xl font-black text-white sm:text-6xl">
                        Universal <br>
                        <span class="bg-gradient-to-r from-emerald-400 via-emerald-500 to-cyan-400 bg-clip-text text-transparent italic tracking-tight">Forensic Intelligence</span>
                    </h2>
                    <p class="mt-6 max-w-2xl text-lg text-gray-400 font-medium leading-relaxed">
                        Beyond simple audits—LUME CORE generates complex forensic vectors to verify the soul of your software.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                    <!-- Sovereign Vault (Large) -->
                    <div class="reveal md:col-span-8 rounded-[2.5rem] border border-white/5 bg-white/[0.02] p-10 relative overflow-hidden group transition-all hover:bg-white/[0.04]">
                        <div class="absolute -right-24 -top-24 h-64 w-64 bg-lume-primary/10 blur-[80px] group-hover:bg-lume-primary/20 transition-all shadow-2xl" />
                        
                        <div class="relative z-10 flex flex-col h-full justify-between">
                            <div>
                                <div class="h-14 w-14 rounded-2xl bg-lume-primary/10 border border-lume-primary/20 flex items-center justify-center text-lume-primary mb-8 shadow-inner shadow-lume-primary/20">
                                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                                <h3 class="text-3xl font-black text-white mb-4 italic tracking-tight">Sovereign Vault</h3>
                                <p class="text-gray-400 text-sm max-w-md font-medium leading-relaxed mb-8">
                                    Military-grade encrypted infrastructure for your codebase and high-value datasets. Your IP is protected by multi-node isolation and sovereign encryption protocols.
                                </p>
                            </div>
                            
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                <div class="p-4 rounded-2xl bg-white/5 border border-white/5 backdrop-blur-sm transition-transform hover:scale-105">
                                    <div class="text-[8px] font-black text-lume-primary uppercase mb-1 tracking-[0.2em]">Encrypted Nodes</div>
                                    <div class="text-lg font-black text-white tracking-tighter">12 Active</div>
                                </div>
                                <div class="p-4 rounded-2xl bg-white/5 border border-white/5 backdrop-blur-sm transition-transform hover:scale-105">
                                    <div class="text-[8px] font-black text-emerald-400 uppercase mb-1 tracking-[0.2em]">Uptime</div>
                                    <div class="text-lg font-black text-white tracking-tighter">99.99%</div>
                                </div>
                                <div class="hidden sm:block p-4 rounded-2xl bg-white/5 border border-white/5 backdrop-blur-sm transition-transform hover:scale-105">
                                    <div class="text-[8px] font-black text-sovereign-primary uppercase mb-1 tracking-[0.2em]">Isolation</div>
                                    <div class="text-lg font-black text-white tracking-tighter">Level 4</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lume Forensic Core (Tall) -->
                    <div class="reveal delay-100 md:col-span-4 rounded-[2.5rem] border border-white/5 bg-white/[0.02] p-10 relative overflow-hidden group transition-all hover:bg-white/[0.04]">
                        <div class="absolute -left-12 bottom-0 h-48 w-48 bg-sovereign-primary/5 blur-[60px]" />
                        <div class="h-14 w-14 rounded-2xl bg-cyan-400/10 border border-cyan-400/20 flex items-center justify-center text-cyan-400 mb-8 shadow-inner shadow-cyan-400/20">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <h3 class="text-2xl font-black text-white mb-4 italic tracking-tight">Forensic Core</h3>
                        <p class="text-gray-400 text-xs font-medium leading-relaxed mb-6">
                            Deep-dive analysis uncovering hidden security risks, performance bottlenecks, and scalability vectors across 6 major technical pillars.
                        </p>
                        <div class="space-y-3 pt-4">
                            <div class="flex items-center justify-between text-[8px] font-black text-gray-500 uppercase tracking-widest px-1">
                                <span>Scanning Progress</span>
                                <span class="text-emerald-400">84% Live</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-white/5 overflow-hidden ring-1 ring-white/5">
                                <div class="h-full w-4/5 bg-gradient-to-r from-lume-primary to-sovereign-primary animate-pulse shadow-glow shadow-lume-primary" />
                            </div>
                        </div>
                    </div>

                    <!-- Asset Ledger (Small) -->
                    <div class="reveal md:col-span-5 rounded-[2.5rem] border border-white/5 bg-white/[0.02] p-10 relative overflow-hidden group transition-all hover:bg-white/[0.04]">
                        <div class="absolute -left-8 -top-8 h-32 w-32 bg-emerald-500/5 blur-[40px]" />
                        <div class="h-14 w-14 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 mb-8 shadow-inner shadow-emerald-500/20">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <h3 class="text-2xl font-black text-white mb-4 italic tracking-tight">Asset Ledger</h3>
                        <div class="flex items-baseline gap-2 mb-2">
                            <span class="text-4xl font-black text-white tracking-tighter italic">482.5</span>
                            <span class="text-[8px] font-black text-emerald-400 uppercase tracking-[0.3em]">LUME CREDITS</span>
                        </div>
                        <p class="text-gray-500 text-[10px] font-bold uppercase tracking-[0.2em]">Global Settlement Protocol Active</p>
                    </div>

                    <!-- Web-to-Repo Sync (Large/Wide) -->
                    <div id="sync" class="reveal delay-100 md:col-span-7 rounded-[2.5rem] border border-white/5 bg-white/[0.02] p-10 relative overflow-hidden group transition-all hover:bg-white/[0.04]">
                        <div class="absolute -right-20 bottom-0 h-64 w-64 rounded-full bg-cyan-400/10 blur-[80px]" />
                        <div class="relative z-10 grid lg:grid-cols-2 gap-8 items-center h-full">
                            <div>
                                <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-cyan-400/10 px-3 py-1 text-[8px] font-black uppercase tracking-widest text-cyan-400 ring-1 ring-cyan-400/20">
                                    LUME EXCLUSIVE
                                </div>
                                <h3 class="text-3xl font-black text-white mb-4 italic tracking-tight">Repo Sync</h3>
                                <p class="text-gray-400 leading-relaxed font-medium text-sm">
                                    LUME CORE verifies that your live web presence exactly matches the repository source code. No ghosts in the machine.
                                </p>
                            </div>
                            <div class="rounded-3xl bg-black/40 border border-white/10 p-6 flex flex-col items-center gap-4 backdrop-blur-md shadow-2xl">
                                <div class="flex gap-4 items-center">
                                    <div class="h-10 w-10 rounded-xl bg-cyan-400/20 border border-cyan-400/30 flex items-center justify-center text-cyan-400">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </div>
                                    <div class="w-12 h-0.5 bg-gradient-to-r from-cyan-400/50 via-emerald-400/50 to-cyan-400/50 rounded-full" />
                                    <div class="h-10 w-10 rounded-xl bg-emerald-400/20 border border-emerald-400/30 flex items-center justify-center text-emerald-400">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </div>
                                </div>
                                <div class="text-[8px] font-black tracking-[0.4em] text-emerald-400 uppercase">SYNCHRONIZED</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="py-24 lg:py-32 relative">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16 reveal">
                    <h2 class="text-4xl font-black text-white sm:text-5xl">
                        Universal <span class="text-lume-primary italic tracking-tight">Access</span>
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-gray-400 font-medium">
                        Institutional-grade forensic data at your fingertips. Simple, transparent, and sovereign.
                    </p>
                </div>

                <div class="grid md:grid-cols-3 gap-8 items-start">
                    <!-- Tier 1: Single Load -->
                    <div class="reveal rounded-[2.5rem] border border-white/5 bg-white/[0.02] p-10 backdrop-blur-sm transition-all hover:bg-white/[0.04]">
                        <h3 class="text-xs font-black uppercase tracking-[0.3em] text-gray-500 mb-8">Single Load</h3>
                        <div class="flex items-baseline gap-2 mb-8">
                            <span class="text-5xl font-black text-white italic tracking-tighter">1</span>
                            <span class="text-sm font-bold text-gray-500 uppercase tracking-widest">LUME CREDIT</span>
                        </div>
                        <ul class="space-y-4 mb-10">
                            <li class="flex items-center gap-3 text-xs font-medium text-gray-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-lume-primary" />
                                1x Full Forensic Audit
                            </li>
                            <li class="flex items-center gap-3 text-xs font-medium text-gray-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-lume-primary" />
                                Asset Sync Certificate
                            </li>
                        </ul>
                        <Link :href="route('register')" class="block w-full rounded-full border border-white/10 bg-white/5 py-4 text-center text-xs font-black uppercase tracking-widest text-white transition-all hover:bg-white/10">
                            Initialize
                        </Link>
                    </div>

                    <!-- Tier 2: Developer Plan (Featured) -->
                    <div class="reveal delay-100 rounded-[2.5rem] border border-lume-primary/50 bg-lume-primary/5 p-10 backdrop-blur-xl relative shadow-2xl shadow-lume-primary/20 scale-105">
                        <div class="absolute -top-4 left-1/2 -translate-x-1/2 rounded-full bg-lume-primary px-4 py-1 text-[10px] font-black uppercase tracking-widest text-white">
                            Recommended
                        </div>
                        <h3 class="text-xs font-black uppercase tracking-[0.3em] text-lume-primary mb-8">DEVELOPER</h3>
                        <div class="flex items-baseline gap-2 mb-8 text-white">
                            <span class="text-5xl font-black italic tracking-tighter">$19</span>
                            <span class="text-sm font-bold text-lume-primary/60 uppercase tracking-widest">/ MONTH</span>
                        </div>
                        <ul class="space-y-4 mb-10">
                            <li class="flex items-center gap-3 text-xs font-bold text-white">
                                <svg class="h-4 w-4 text-lume-primary" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/></svg>
                                100 Document Scans daily
                            </li>
                            <li class="flex items-center gap-3 text-xs font-bold text-white">
                                <svg class="h-4 w-4 text-lume-primary" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/></svg>
                                20 Individual & 10 Sync Scans daily
                            </li>
                            <li class="flex items-center gap-3 text-xs font-bold text-white">
                                <svg class="h-4 w-4 text-lume-primary" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/></svg>
                                4 Surface PenTests / Month
                            </li>
                        </ul>
                        <Link :href="route('register')" class="block w-full rounded-full bg-lume-primary py-5 text-center text-xs font-black uppercase tracking-widest text-white shadow-xl transition-all hover:brightness-110 active:scale-95">
                            Get Access
                        </Link>
                    </div>

                    <!-- Tier 3: Sovereign Plan -->
                    <div class="reveal delay-200 rounded-[2.5rem] border border-white/5 bg-white/[0.02] p-10 backdrop-blur-sm transition-all hover:bg-white/[0.04]">
                        <h3 class="text-xs font-black uppercase tracking-[0.3em] text-sovereign-primary mb-8">SOVEREIGN</h3>
                        <div class="flex items-baseline gap-2 mb-8 text-white">
                            <span class="text-5xl font-black italic tracking-tighter">$59</span>
                            <span class="text-sm font-bold text-sovereign-primary/60 uppercase tracking-widest">/ MONTH</span>
                        </div>
                        <ul class="space-y-4 mb-10">
                            <li class="flex items-center gap-3 text-xs font-medium text-gray-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-sovereign-primary" />
                                Unlimited Daily Scans & Forensics
                            </li>
                            <li class="flex items-center gap-3 text-xs font-medium text-gray-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-sovereign-primary" />
                                Unlimited Daily Document Scans
                            </li>
                            <li class="flex items-center gap-3 text-xs font-medium text-gray-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-sovereign-primary" />
                                100 Surface PenTests / Month
                            </li>
                            <li class="flex items-center gap-3 text-xs font-medium text-gray-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-sovereign-primary" />
                                Unlimited Team Collaboration
                            </li>
                        </ul>
                        <Link :href="route('marketplace.index')" class="block w-full rounded-full border border-white/10 bg-white/5 py-4 text-center text-xs font-black uppercase tracking-widest text-white transition-all hover:bg-white/10">
                            Contact Sales
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Roadmap: The Future -->
        <section class="py-24 border-t border-white/5 relative overflow-hidden bg-gradient-to-b from-transparent to-lume-primary/[0.02]">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col lg:flex-row gap-16 items-center">
                    <div class="lg:w-1/3 text-left reveal">
                        <h2 class="text-4xl font-black text-white mb-6 italic tracking-tight underline decoration-lume-primary/30 underline-offset-8">The Future <br><span class="text-lume-primary">of LUME.</span></h2>
                        <p class="text-gray-400 font-medium leading-relaxed">We are building the definitive infrastructure for the digital age, merging AI forensics with programmable settlement.</p>
                    </div>
                    <div class="lg:w-2/3 grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="reveal delay-100 p-8 rounded-[2rem] bg-white/[0.02] border border-white/5 relative overflow-hidden group hover:bg-white/[0.04] transition-all">
                             <div class="absolute top-0 right-0 p-4 text-[10px] font-black text-lume-primary uppercase tracking-widest bg-lume-primary/10 rounded-bl-xl group-hover:bg-lume-primary/20">Q3 2026</div>
                             <h4 class="text-white font-black mb-3 text-lg italic tracking-tight">Autonomous Mitigation</h4>
                             <p class="text-xs text-gray-500 font-medium leading-relaxed">AI-driven security remediation that automatically patches identified forensic drifts in real-time without human lag.</p>
                        </div>
                        <div class="reveal delay-200 p-8 rounded-[2rem] bg-white/[0.02] border border-white/5 relative overflow-hidden group hover:bg-white/[0.04] transition-all">
                             <div class="absolute top-0 right-0 p-4 text-[10px] font-black text-sovereign-primary uppercase tracking-widest bg-sovereign-primary/10 rounded-bl-xl group-hover:bg-sovereign-primary/20">Q1 2027</div>
                             <h4 class="text-white font-black mb-3 text-lg italic tracking-tight">Ledger Gateway</h4>
                             <p class="text-xs text-gray-500 font-medium leading-relaxed">Direct integration with institutional settlement layers for instant, programmatic asset transfer and trust verification.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-white/5 pt-20 pb-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-12 lg:grid-cols-12 mb-20">
                    <div class="lg:col-span-6">
                        <div class="flex items-center gap-3 mb-8">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-lume-primary to-sovereign-primary">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <span class="text-xl font-black text-white tracking-tight uppercase">LUME <span class="text-lume-primary">CORE</span></span>
                        </div>
                        <p class="max-w-md text-sm text-gray-400 font-medium leading-relaxed text-left">
                            The Sovereign Operating System for High-Value Assets. <br>
                            Verify, protect, and monetize your digital intellectual property with LUME Forensic Engine.
                        </p>
                    </div>
                    <div class="lg:col-span-3 text-left">
                        <h4 class="text-[10px] font-black uppercase tracking-[0.3em] text-white mb-6">Platform</h4>
                        <ul class="space-y-4">
                            <li><Link :href="route('marketplace.index')" class="text-xs font-bold text-gray-500 hover:text-white transition-colors">Marketplace</Link></li>
                            <li><a href="#forensics" class="text-xs font-bold text-gray-500 hover:text-white transition-colors">Forensics</a></li>
                            <li><a href="#pricing" class="text-xs font-bold text-gray-500 hover:text-white transition-colors">Pricing</a></li>
                            <li><Link :href="route('documentation.index')" class="text-xs font-bold text-gray-500 hover:text-white transition-colors">Documentation</Link></li>
                        </ul>
                    </div>
                    <div class="lg:col-span-3">
                        <h4 class="text-[10px] font-black uppercase tracking-[0.3em] text-white mb-6">Protocol</h4>
                        <ul class="space-y-4">
                            <li><Link :href="route('privacy-policy')" class="text-xs font-bold text-gray-500 hover:text-white transition-colors">Privacy Policy</Link></li>
                            <li><Link :href="route('forensic-standards')" class="text-xs font-bold text-gray-500 hover:text-white transition-colors">Forensic Standards</Link></li>
                            <li><Link :href="route('terms-of-service')" class="text-xs font-bold text-gray-500 hover:text-white transition-colors">Terms of Service</Link></li>
                        </ul>
                    </div>
                </div>
                <div class="flex flex-col md:flex-row justify-between items-center border-t border-white/5 pt-12">
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-600 mb-4 md:mb-0">© 2026 LUME PROTOCOL. ALL RIGHTS RESERVED.</p>
                    <div class="flex gap-6">
                        <a href="#" class="h-6 w-6 text-gray-500 hover:text-white transition-colors" target="_blank" rel="noopener noreferrer">
                            <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                        </a>
                        <a href="#" class="h-6 w-6 text-gray-500 hover:text-white transition-colors" target="_blank" rel="noopener noreferrer">
                            <svg fill="currentColor" viewBox="0 0 24 24"><path d="M20.317 4.37a19.791 19.791 0 00-4.885-1.515.074.074 0 00-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 00-5.487 0 12.64 12.64 0 00-.617-1.25.077.077 0 00-.079-.037A19.736 19.736 0 003.677 4.37a.07.07 0 00-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 00.031.057 19.9 19.9 0 005.993 3.03.078.078 0 00.084-.028 14.09 14.09 0 001.226-1.994.076.076 0 00-.041-.106 13.107 13.107 0 01-1.872-.892.077.077 0 01-.008-.128 10.2 10.2 0 00.372-.292.074.074 0 01.077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 01.078.01c.12.098.246.198.373.292a.077.077 0 01-.006.127 12.299 12.299 0 01-1.873.892.077.077 0 00-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 00.084.028 19.839 19.839 0 006.002-3.03.077.077 0 00.032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 00-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.332.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.419 0 1.334-.956 2.419-2.157 2.419zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.332.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.419 0 1.334-.946 2.419-2.157 2.419z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Global AI Helper Bot -->
        <LumeAISupport mode="global" />
    </div>
</template>

<style scoped>
@keyframes float {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(30px, -50px) scale(1.1); }
    66% { transform: translate(-20px, 20px) scale(0.9); }
}

.animate-float {
    animation: float 20s infinite ease-in-out;
}

.animate-float-delayed {
    animation: float 25s infinite ease-in-out reverse;
    animation-delay: -5s;
}

/* Scroll Reveal Animations */
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
.delay-300 { transition-delay: 300ms; }
</style>
