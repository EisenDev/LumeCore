<script setup lang="ts">
/**
 * LUME Welcome/Landing Page (Welcome.vue)
 * Custom Redesign matching the premium visual mockup exactly:
 * - 3D glowing gold cube image assets
 * - Warm charcoal background (#121315) and Soft Ivory (#F3E7C9) typography
 * - Floating glass verification card
 * - Four-column proof row
 */
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import LumeAISupport from '@/Components/LumeAISupport.vue';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
}>();

// Mobile menu state
const mobileMenuOpen = ref(false);

// FAQ accordion state
const faqs = ref([
    {
        question: "How does LUME verify digital asset ownership?",
        answer: "LUME utilizes automated repository validation, DNS checks, and cryptographic challenge-responses to bind ownership profiles and verify the integrity of your codebases.",
        active: false
    },
    {
        question: "What is the Titan Forensic Engine?",
        answer: "The Titan Engine is our proprietary static and dynamic code auditor that sniffs tech footprints, inspects SSL certificate health, and identifies toxic files with high git churn.",
        active: false
    },
    {
        question: "How do LUME Credits work?",
        answer: "LUME Credits are individual tokens used to trigger audits and scans. One credit buys a single full-pipeline codebase audit and sovereign sync certification.",
        active: false
    },
    {
        question: "Can I self-host LUME inside my own secure VPC?",
        answer: "Yes, our Sovereign Enterprise plan supports complete VPC self-hosting and multi-node isolated deployment for maximum asset privacy.",
        active: false
    }
]);

const toggleFaq = (index: number) => {
    faqs.value[index].active = !faqs.value[index].active;
};

// Scroll Reveal Observer
onMounted(() => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.05,
        rootMargin: '0px 0px -40px 0px'
    });

    document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));
});
</script>

<template>
    <Head title="Lume — Verify, sync, and protect digital assets" />

    <!-- Core Landing Wrapper -->
    <div class="landing-theme bg-[#0a0a0b] min-h-screen font-sans antialiased overflow-x-hidden selection:bg-[#fbe6af]/20 selection:text-white text-[#888888]">
        <!-- Subtle Noise Texture Overlay -->
        <div class="fixed inset-0 z-[100] pointer-events-none opacity-[0.02] bg-[url('https://grainy-gradients.vercel.app/noise.svg')] brightness-125 contrast-125" />

        <!-- Navigation Bar (Floating/Fixed & Transparent) -->
        <nav class="fixed top-0 left-0 right-0 z-[60] w-full nav-premium py-4">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="flex h-20 items-center justify-between">
                    <!-- Left: Logo -->
                    <Link href="/" class="flex items-center gap-3 group">
                        <img src="/images/none-transparent-logo.png" alt="Lume Logo" class="h-9 w-9 object-contain transition-transform group-hover:rotate-12 duration-300" />
                        <span class="font-display text-[17px] font-bold tracking-[0.1em] text-white">Lume</span>
                    </Link>

                    <!-- Middle: Navigation Links -->
                    <div class="hidden items-center gap-8 md:flex">
                        <a href="#" class="text-sm font-medium text-[#9CA3AF] transition-colors hover:text-[#F3E7C9]">
                            Product
                        </a>
                        <a href="#" class="text-sm font-medium text-[#9CA3AF] transition-colors hover:text-[#F3E7C9]">
                            Solutions
                        </a>
                        <a href="#" class="text-sm font-medium text-[#9CA3AF] transition-colors hover:text-[#F3E7C9]">
                            Resources
                        </a>
                        <a href="#pricing" class="text-sm font-medium text-[#9CA3AF] transition-colors hover:text-[#F3E7C9]">
                            Pricing
                        </a>
                        <a href="#" class="text-sm font-medium text-[#9CA3AF] transition-colors hover:text-[#F3E7C9]">
                            Developers
                        </a>
                    </div>

                    <!-- Right: CTAs -->
                    <div class="flex items-center gap-6">
                        <template v-if="$page.props.auth.user">
                            <Link :href="route('overview')" class="btn-primary py-2 px-5 text-xs font-semibold rounded-lg">
                                Overview
                            </Link>
                        </template>
                        <template v-else>
                            <Link v-if="canLogin" :href="route('login')" class="text-sm font-medium text-[#9CA3AF] transition-colors hover:text-[#F3E7C9]">
                                Log in
                            </Link>
                            <Link v-if="canRegister" :href="route('register')" class="btn-primary py-2 px-4.5 rounded-lg text-xs font-semibold">
                                Get Access
                            </Link>
                        </template>

                        <!-- Mobile Toggle -->
                        <button @click="mobileMenuOpen = !mobileMenuOpen" class="rounded-xl p-2 text-[#9CA3AF] hover:text-[#F3E7C9] md:hidden">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 6h16M4 12h16M4 18h16" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Links -->
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="transform -translate-y-4 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="transform translate-y-0 opacity-100"
                leave-to-class="transform -translate-y-4 opacity-0"
            >
                <div v-if="mobileMenuOpen" class="border-t border-[#222428] bg-[#121315]/95 backdrop-blur-2xl py-6 px-6 md:hidden absolute left-0 right-0 top-20 shadow-2xl">
                    <div class="flex flex-col gap-5">
                        <a href="#" class="text-sm font-medium text-[#9CA3AF] hover:text-[#F3E7C9]" @click="mobileMenuOpen = false">
                            Product
                        </a>
                        <a href="#" class="text-sm font-medium text-[#9CA3AF] hover:text-[#F3E7C9]" @click="mobileMenuOpen = false">
                            Solutions
                        </a>
                        <a href="#" class="text-sm font-medium text-[#9CA3AF] hover:text-[#F3E7C9]" @click="mobileMenuOpen = false">
                            Resources
                        </a>
                        <a href="#pricing" class="text-sm font-medium text-[#9CA3AF] hover:text-[#F3E7C9]" @click="mobileMenuOpen = false">
                            Pricing
                        </a>
                        <a href="#" class="text-sm font-medium text-[#9CA3AF] hover:text-[#F3E7C9]" @click="mobileMenuOpen = false">
                            Developers
                        </a>
                        <Link v-if="!$page.props.auth.user" :href="route('login')" class="text-sm font-medium text-[#9CA3AF] hover:text-[#F3E7C9] pt-3 border-t border-[#222428]" @click="mobileMenuOpen = false">
                            Log in
                        </Link>
                    </div>
                </div>
            </transition>
        </nav>

        <!-- 1. Hero Section (Full-bleed, transparent nav overlays) -->
        <header class="relative min-h-[90vh] lg:min-h-screen hero-bg flex items-center overflow-hidden">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 w-full relative z-10 pt-28 pb-20 lg:pt-32 lg:pb-24">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    
                    <!-- Left Side: Content -->
                    <div class="lg:col-span-7 text-left space-y-8">
                        <div class="reveal">
                            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-white/10 bg-white/5 text-[10px] font-semibold tracking-wider text-white uppercase">
                                <svg class="h-3 w-3 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                Trusted by forward-thinking teams
                            </div>
                        </div>
                        
                        <h1 class="reveal hero-headline text-white">
                            Trust every<br>
                            <span class="text-[#fbe6af]">digital asset.</span>
                        </h1>
                        
                        <p class="reveal text-[16px] text-[#888888] max-w-[340px] leading-relaxed">
                            LUME verifies the integrity, ownership, and security of digital assets so you can move forward with confidence.
                        </p>
                        
                        <div class="reveal flex items-center gap-6 pt-2">
                            <Link :href="route('register')" class="btn-primary py-3.5 px-7 rounded-lg flex items-center gap-2 group text-sm font-semibold">
                                Start for free
                                <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                            
                            <a href="#features" class="inline-flex items-center gap-3 text-sm font-semibold text-white hover:text-[#fbe6af] transition-colors group">
                                <svg class="h-6 w-6 text-white group-hover:text-[#fbe6af] transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <circle cx="12" cy="12" r="10" />
                                    <path d="M10 8l6 4-6 4V8z" fill="currentColor" />
                                </svg>
                                Watch overview
                            </a>
                        </div>
                    </div>

                    <!-- Right Side: Content / Cube & Card -->
                    <div class="lg:col-span-5 relative flex justify-center lg:justify-start items-center min-h-[450px] z-20">
                        <!-- Pedestal & Cube Image -->
                        <img src="/images/lp-hero-sec.png" class="absolute right-[-15%] bottom-[-15%] min-w-[550px] lg:min-w-[700px] h-auto object-contain pointer-events-none z-10" />

                        <!-- Floating Glass Card -->
                        <div class="w-[320px] glass-card-premium p-6 space-y-4 animate-float-rotated z-20 relative mr-0 lg:mr-16">
                            <div class="flex items-center gap-2.5">
                                <svg class="h-4 w-4 text-[#fbe6af]" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-[11px] font-semibold text-[#888888] uppercase tracking-wider">Asset Verified</span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-xs text-white/90">finance-dashboard-v2</span>
                                <span class="text-[9px] text-[#fbe6af] bg-[#fbe6af]/10 border border-[#fbe6af]/25 px-2 py-0.5 rounded font-mono">v2.4.1</span>
                            </div>
                            
                            <div class="space-y-3 font-sans text-xs border-t border-white/5 pt-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-[#888888]">Integrity Score</span>
                                    <span class="text-white font-medium">98%</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[#888888]">Security Status</span>
                                    <span class="text-white font-medium">Secure</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[#888888]">Ownership</span>
                                    <span class="text-white font-medium">Secure</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[#888888]">Last Scan</span>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-white font-medium">Verified</span>
                                        <span class="text-[#888888]/60 text-[10px]">2h ago</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pt-3 border-t border-white/5 flex items-start gap-2.5 bg-white/[0.01] p-3 rounded-xl border border-white/5">
                                <div class="h-4.5 w-4.5 rounded-full bg-[#CBB48A]/10 flex items-center justify-center text-[#CBB48A] mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <div>
                                    <span class="font-bold text-white text-[10px] block">Verification Complete</span>
                                    <span class="text-[#888888] text-[9px] block mt-0.5">This asset is trusted and ready.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- 1.2 Features summary row (Feature strip, dark background continues, no divider) -->
        <section class="bg-[#0a0a0b] pb-24">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 w-full">
                <div class="reveal grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-8">
                    <!-- Feature 1 -->
                    <div class="flex gap-4 items-start">
                        <div class="p-2.5 rounded-xl border border-white/10 text-white flex-shrink-0">
                            <!-- Outlined Shield icon -->
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-display font-semibold text-sm text-white mb-1">Proven Security</h4>
                            <p class="text-xs text-[#888888] leading-relaxed">Rigorous checks that catch what matters.</p>
                        </div>
                    </div>
                    
                    <!-- Feature 2 -->
                    <div class="flex gap-4 items-start">
                        <div class="p-2.5 rounded-xl border border-white/10 text-white flex-shrink-0">
                            <!-- Outlined Person icon -->
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 2.944c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-display font-semibold text-sm text-white mb-1">Proven Ownership</h4>
                            <p class="text-xs text-[#888888] leading-relaxed">Cryptographic proof you can rely on.</p>
                        </div>
                    </div>
                    
                    <!-- Feature 3 -->
                    <div class="flex gap-4 items-start">
                        <div class="p-2.5 rounded-xl border border-white/10 text-white flex-shrink-0">
                            <!-- Outlined Document icon -->
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-display font-semibold text-sm text-white mb-1">Proven Compliance</h4>
                            <p class="text-xs text-[#888888] leading-relaxed">Built-in standards for global scale.</p>
                        </div>
                    </div>
                    
                    <!-- Feature 4 -->
                    <div class="flex gap-4 items-start">
                        <div class="p-2.5 rounded-xl border border-white/10 text-white flex-shrink-0">
                            <!-- Outlined Swap/Transfer icon -->
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-display font-semibold text-sm text-white mb-1">Proven Transfer</h4>
                            <p class="text-xs text-[#888888] leading-relaxed">Secure settlements with full transparency.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. Trusted Companies Logo Bar -->
        <section class="border-t border-white/5 bg-[#0a0a0b] py-14">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <p class="text-left text-[11px] font-semibold uppercase tracking-[0.25em] text-[#555555] mb-8">
                    TRUSTED BY TEAMS AROUND THE WORLD
                </p>
                <div class="flex flex-wrap items-center justify-between gap-8 opacity-45 hover:opacity-75 transition-opacity text-[#888888]">
                    <!-- Vertex -->
                    <div class="flex items-center gap-2.5 hover:text-white transition-colors">
                        <svg class="h-4.5 w-4.5 text-[#888888]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4.5L12 20L20 4.5" />
                            <path d="M12 11.5L16 4.5" />
                        </svg>
                        <span class="font-display font-bold text-[14px] tracking-wider lowercase">vertex</span>
                    </div>

                    <!-- Northpeak -->
                    <div class="flex items-center gap-2.5 hover:text-white transition-colors">
                        <svg class="h-4.5 w-4.5 text-[#888888]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2L2 22h20L12 2z" />
                            <path d="M12 2v20" />
                            <path d="M12 7l5 5" />
                        </svg>
                        <span class="font-display font-semibold text-[14px] tracking-wider lowercase">northpeak</span>
                    </div>

                    <!-- Spheron -->
                    <div class="flex items-center gap-2.5 hover:text-white transition-colors">
                        <svg class="h-4.5 w-4.5 text-[#888888]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <ellipse cx="12" cy="12" rx="4" ry="10" />
                            <path d="M2 12h20" />
                        </svg>
                        <span class="font-display font-extrabold text-[14px] tracking-wider lowercase">spheron</span>
                    </div>

                    <!-- Summit -->
                    <div class="flex items-center gap-2.5 hover:text-white transition-colors">
                        <svg class="h-4.5 w-4.5 text-[#888888]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 20l7-10 3 4 5-7 3 13H3z" />
                        </svg>
                        <span class="font-display font-medium text-[14px] tracking-wider lowercase">summit</span>
                    </div>

                    <!-- Canyon -->
                    <div class="flex items-center gap-2.5 hover:text-white transition-colors">
                        <svg class="h-4.5 w-4.5 text-[#888888]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2L2 7v10l10 5 10-5V7L12 2z" />
                            <path d="M12 2v20" />
                            <path d="M2 7l10 5 10-5" />
                        </svg>
                        <span class="font-display font-semibold text-[14px] tracking-wider lowercase">canyon</span>
                    </div>

                    <!-- Cloudline -->
                    <div class="flex items-center gap-2.5 hover:text-white transition-colors">
                        <svg class="h-4.5 w-4.5 text-[#888888]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17.5 19A5.5 5.5 0 0 0 18 8h-1.26A8 8 0 1 0 3 16.25" />
                            <path d="M3 19h15" />
                        </svg>
                        <span class="font-display font-bold text-[14px] tracking-wider lowercase">cloudline</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. Features Section (Bento Grid) -->
        <section id="features" class="section-spacing relative">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="mb-16 text-left max-w-2xl reveal">
                    <span class="text-xs font-semibold text-[#CBB48A] uppercase tracking-[0.2em] block mb-3">Architectural Pillars</span>
                    <h2 class="font-display text-3xl sm:text-4xl font-bold text-[#F3E7C9] tracking-tight">
                        Deep forensic verification for modern software teams.
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
                    <!-- Column 1: Codebase Auditing (Large Card) -->
                    <div class="card-premium md:col-span-8 flex flex-col justify-between min-h-[340px] reveal">
                        <div class="space-y-6">
                            <div class="h-10 w-10 rounded-xl bg-[#F3E7C9]/5 border border-[#F3E7C9]/10 flex items-center justify-center text-[#DCC8A5]">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                </svg>
                            </div>
                            <h3 class="font-display text-xl font-bold text-[#F3E7C9]">Codebase Auditing (Titan Engine)</h3>
                            <p class="text-[#9CA3AF] text-sm leading-relaxed max-w-xl">
                                Automatically audits your frameworks, databases, and dependencies. Titan identifies toxic commits (high churn combined with code volume), DNS profiles, and active ownership structures.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-3 pt-6 border-t border-white/5">
                            <span class="px-3 py-1 rounded-md bg-white/[0.02] border border-white/5 text-[10px] font-semibold text-[#9CA3AF] uppercase tracking-wider">Tech Footprints</span>
                            <span class="px-3 py-1 rounded-md bg-white/[0.02] border border-white/5 text-[10px] font-semibold text-[#9CA3AF] uppercase tracking-wider">DNS & SSL Health</span>
                            <span class="px-3 py-1 rounded-md bg-white/[0.02] border border-white/5 text-[10px] font-semibold text-[#9CA3AF] uppercase tracking-wider">Toxicity Reports</span>
                        </div>
                    </div>

                    <!-- Column 2: Reverb Real-time Sync (Small Card) -->
                    <div class="card-premium md:col-span-4 flex flex-col justify-between min-h-[340px] reveal">
                        <div class="space-y-6">
                            <div class="h-10 w-10 rounded-xl bg-[#F3E7C9]/5 border border-[#F3E7C9]/10 flex items-center justify-center text-[#DCC8A5]">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <h3 class="font-display text-xl font-bold text-[#F3E7C9]">Real-time Sync</h3>
                            <p class="text-[#9CA3AF] text-sm leading-relaxed">
                                Tracks deployment modifications live, matching production code states with core repositories to identify security drift automatically.
                            </p>
                        </div>
                        <span class="text-xs font-semibold text-[#CBB48A] uppercase tracking-wider block pt-4">Powered by Reverb</span>
                    </div>

                    <!-- Column 3: CloudVault Storage (Small Card) -->
                    <div class="card-premium md:col-span-4 flex flex-col justify-between min-h-[340px] reveal">
                        <div class="space-y-6">
                            <div class="h-10 w-10 rounded-xl bg-[#F3E7C9]/5 border border-[#F3E7C9]/10 flex items-center justify-center text-[#DCC8A5]">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <h3 class="font-display text-xl font-bold text-[#F3E7C9]">Sovereign Vault</h3>
                            <p class="text-[#9CA3AF] text-sm leading-relaxed">
                                Upload, index, and query your asset document repositories with semantic vector searches powered by pgvector.
                            </p>
                        </div>
                        <span class="text-xs font-semibold text-[#CBB48A] uppercase tracking-wider block pt-4">Encrypted Isolation</span>
                    </div>

                    <!-- Column 4: AI Architect (Large Card) -->
                    <div class="card-premium md:col-span-8 flex flex-col justify-between min-h-[340px] reveal">
                        <div class="space-y-6">
                            <div class="h-10 w-10 rounded-xl bg-[#F3E7C9]/5 border border-[#F3E7C9]/10 flex items-center justify-center text-[#DCC8A5]">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                </svg>
                            </div>
                            <h3 class="font-display text-xl font-bold text-[#F3E7C9]">AI Architect Integration</h3>
                            <p class="text-[#9CA3AF] text-sm leading-relaxed max-w-xl">
                                Driven by Google Gemini 2.0 Flash, our AI engine automatically parses requirements, config files, and logs to draft deployment guidelines and summarize security audits dynamically.
                            </p>
                        </div>
                        <div class="pt-6 border-t border-white/5">
                            <span class="text-xs text-[#9CA3AF]/60 italic font-mono">Automated Intelligence Protocol</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 4. Product Showcase Section -->
        <section class="section-spacing bg-[#15171A] border-y border-[#222428]">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-16 reveal">
                    <span class="text-xs font-semibold text-[#CBB48A] uppercase tracking-[0.2em] block mb-3">Minimal Interface</span>
                    <h2 class="font-display text-3xl sm:text-4xl font-bold text-[#F3E7C9] tracking-tight">
                        Elegance meets asset management.
                    </h2>
                    <p class="text-[#9CA3AF] text-sm leading-relaxed mt-4">
                        Everything you need to view repository audits, verify credentials, and buy or sell software components in a unified developer cockpit.
                    </p>
                </div>

                <!-- Showcase Frame -->
                <div class="reveal rounded-2xl border border-white/5 bg-[#121315] p-6 lg:p-8 shadow-2xl max-w-5xl mx-auto overflow-hidden">
                    <div class="flex items-center gap-2 mb-6 border-b border-white/5 pb-4">
                        <div class="w-3 h-3 rounded-full bg-white/10"></div>
                        <div class="w-3 h-3 rounded-full bg-white/10"></div>
                        <div class="w-3 h-3 rounded-full bg-white/10"></div>
                        <span class="text-[10px] text-[#9CA3AF]/40 font-mono ml-4 uppercase tracking-widest">verify-panel</span>
                    </div>

                    <!-- Inner Mockup Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 font-mono text-xs">
                        <div class="p-5 bg-[#1B1D21] border border-white/5 rounded-xl space-y-4">
                            <span class="text-[#CBB48A] uppercase text-[10px] tracking-wider block">01 / Repository</span>
                            <div class="space-y-1.5">
                                <div class="text-[#9CA3AF]/40 text-[10px]">TARGET URL</div>
                                <div class="text-[#F3E7C9] truncate">github.com/lumecore/engine</div>
                            </div>
                            <div class="space-y-1.5 pt-3 border-t border-white/5">
                                <div class="text-[#9CA3AF]/40 text-[10px]">INTEGRITY HASH</div>
                                <div class="text-[#DCC8A5] truncate font-mono">sha256-f94a32ef...</div>
                            </div>
                        </div>

                        <div class="p-5 bg-[#1B1D21] border border-white/5 rounded-xl space-y-4">
                            <span class="text-[#CBB48A] uppercase text-[10px] tracking-wider block">02 / Forensic Result</span>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-[#9CA3AF]/60">Bus Factor</span>
                                    <span class="text-[#CBB48A]">Low Risk</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[#9CA3AF]/60">Code Churn</span>
                                    <span class="text-[#DCC8A5]">Stable</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[#9CA3AF]/60">Toxicity</span>
                                    <span class="text-[#F3E7C9]">0% Detected</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-5 bg-[#1B1D21] border border-white/5 rounded-xl space-y-4">
                            <span class="text-[#CBB48A] uppercase text-[10px] tracking-wider block">03 / Escrow Wallet</span>
                            <div class="space-y-1.5">
                                <div class="text-[#9CA3AF]/40 text-[10px]">ACTIVE BALANCE</div>
                                <div class="text-white text-lg font-bold">1,240.00 LUME</div>
                            </div>
                            <div class="space-y-1.5 pt-2 border-t border-white/5">
                                <div class="text-[#9CA3AF]/40 text-[10px]">STATUS</div>
                                <div class="text-[#CBB48A] font-semibold tracking-widest">SETTLED</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 5. Benefits Section -->
        <section class="section-spacing">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="text-left max-w-xl mb-16 reveal">
                    <span class="text-xs font-semibold text-[#CBB48A] uppercase tracking-[0.2em] block mb-3">Enterprise Ready</span>
                    <h2 class="font-display text-3xl font-bold text-[#F3E7C9] tracking-tight">
                        Built for confidence and compliance.
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="space-y-4 reveal">
                        <span class="text-xs font-semibold text-[#CBB48A] font-mono block">01 / TRUSTWORTHY</span>
                        <h4 class="font-display text-lg font-bold text-[#F3E7C9]">Provable Integrity</h4>
                        <p class="text-sm text-[#9CA3AF] leading-relaxed">
                            Generate unique codebase fingerprints that guarantee structural authenticity. Perfect for transferring assets or licensing codebases without compliance doubts.
                        </p>
                    </div>

                    <div class="space-y-4 reveal delay-100">
                        <span class="text-xs font-semibold text-[#CBB48A] font-mono block">02 / ELEGANT CONTROL</span>
                        <h4 class="font-display text-lg font-bold text-[#F3E7C9]">Zero Noise Dashboards</h4>
                        <p class="text-sm text-[#9CA3AF] leading-relaxed">
                            Clean layouts, beautiful typography, and essential analytics. No neon maps, no excessive charts—only calm, readable intelligence reports that get straight to the point.
                        </p>
                    </div>

                    <div class="space-y-4 reveal delay-200">
                        <span class="text-xs font-semibold text-[#CBB48A] font-mono block">03 / SECURE SETTLEMENTS</span>
                        <h4 class="font-display text-lg font-bold text-[#F3E7C9]">Integrated Escrow</h4>
                        <p class="text-sm text-[#9CA3AF] leading-relaxed">
                            Safely list verified assets, manage wallets, and exchange software bundles in an isolated and compliant sandbox environment.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 6. Testimonials Section -->
        <section class="section-spacing bg-[#15171A] border-y border-[#222428]">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-16 reveal">
                    <span class="text-xs font-semibold text-[#CBB48A] uppercase tracking-[0.2em] block mb-3">Testimonials</span>
                    <h2 class="font-display text-3xl font-bold text-[#F3E7C9] tracking-tight">
                        Endorsed by developers and designers.
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                    <div class="card-premium space-y-6 reveal">
                        <p class="text-sm text-[#F3E7C9]/90 italic leading-relaxed">
                            "LUME completely changed how we handle codebase transfers during our M&A transactions. The cleanliness of the interface and the precision of the Titan Engine gave us total confidence."
                        </p>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-[#F3E7C9]/10 border border-[#F3E7C9]/20 flex items-center justify-center font-bold text-[10px] text-[#F3E7C9]">
                                MD
                            </div>
                            <div>
                                <h5 class="text-xs font-bold text-[#F3E7C9]">Marcus Drake</h5>
                                <span class="text-[10px] text-[#9CA3AF]">Head of M&A, Cloudline Corp</span>
                            </div>
                        </div>
                    </div>

                    <div class="card-premium space-y-6 reveal delay-100">
                        <p class="text-sm text-[#F3E7C9]/90 italic leading-relaxed">
                            "The design speaks for itself. It feels calm, confident, and professional—like Stripe or Vercel, but custom-tailored for code and asset verification."
                        </p>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-[#F3E7C9]/10 border border-[#F3E7C9]/20 flex items-center justify-center font-bold text-[10px] text-[#F3E7C9]">
                                EH
                            </div>
                            <div>
                                <h5 class="text-xs font-bold text-[#F3E7C9]">Elena Vance</h5>
                                <span class="text-[10px] text-[#9CA3AF]">Lead Architect, Northpeak</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 7. Pricing Section -->
        <section id="pricing" class="section-spacing">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-16 reveal">
                    <span class="text-xs font-semibold text-[#CBB48A] uppercase tracking-[0.2em] block mb-3">Transparent Access</span>
                    <h2 class="font-display text-3xl font-bold text-[#F3E7C9] tracking-tight">
                        Elegance at any scale.
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-start max-w-6xl mx-auto">
                    <!-- Tier 1: Single Load -->
                    <div class="card-premium flex flex-col justify-between min-h-[420px] reveal">
                        <div class="space-y-6">
                            <h4 class="text-xs font-bold uppercase tracking-widest text-[#9CA3AF]/60">Single Load</h4>
                            <div class="flex items-baseline gap-2">
                                <span class="font-display text-4xl font-bold text-[#F3E7C9]">1</span>
                                <span class="text-[10px] font-bold text-[#9CA3AF] uppercase tracking-wider">LUME Credit</span>
                            </div>
                            <p class="text-xs text-[#9CA3AF] leading-relaxed">
                                Perfect for one-time codebase audit validation or generating an asset sync verification certificate.
                            </p>
                            <ul class="space-y-3 pt-4 text-xs">
                                <li class="flex items-center gap-2 text-[#9CA3AF]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#CBB48A]"></span>
                                    1x Complete Codebase Audit
                                </li>
                                <li class="flex items-center gap-2 text-[#9CA3AF]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#CBB48A]"></span>
                                    Asset Sync Certificate
                                </li>
                            </ul>
                        </div>
                        <Link :href="route('register')" class="btn-secondary w-full text-center py-2.5 mt-8">
                            Initialize Scan
                        </Link>
                    </div>

                    <!-- Tier 2: Developer Plan (Featured) -->
                    <div class="card-premium flex flex-col justify-between min-h-[420px] border-[#CBB48A]/50 bg-[#1e2025] scale-[1.03] shadow-2xl reveal delay-100">
                        <div class="space-y-6">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-bold uppercase tracking-widest text-[#CBB48A]">Developer</h4>
                                <span class="px-2 py-0.5 rounded bg-[#CBB48A]/10 text-[8px] font-bold text-[#CBB48A] uppercase tracking-widest">Recommended</span>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="font-display text-4xl font-bold text-[#F3E7C9]">$19</span>
                                <span class="text-[10px] font-bold text-[#9CA3AF]/60 uppercase tracking-wider">/ Month</span>
                            </div>
                            <p class="text-xs text-[#9CA3AF] leading-relaxed">
                                Tailored for independent builders and small engineering teams auditing assets actively.
                            </p>
                            <ul class="space-y-3 pt-4 text-xs">
                                <li class="flex items-center gap-2 text-[#F3E7C9] font-medium">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#CBB48A]"></span>
                                    100 daily Document Scans
                                </li>
                                <li class="flex items-center gap-2 text-[#F3E7C9] font-medium">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#CBB48A]"></span>
                                    20 daily Repository Scans
                                </li>
                                <li class="flex items-center gap-2 text-[#F3E7C9] font-medium">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#CBB48A]"></span>
                                    4 security PenTests / Month
                                </li>
                            </ul>
                        </div>
                        <Link :href="route('register')" class="btn-primary w-full text-center py-3 mt-8">
                            Select Plan
                        </Link>
                    </div>

                    <!-- Tier 3: Team Plan -->
                    <div class="card-premium flex flex-col justify-between min-h-[420px] reveal delay-200">
                        <div class="space-y-6">
                            <h4 class="text-xs font-bold uppercase tracking-widest text-[#9CA3AF]/60">Sovereign</h4>
                            <div class="flex items-baseline gap-2">
                                <span class="font-display text-4xl font-bold text-[#F3E7C9]">$59</span>
                                <span class="text-[10px] font-bold text-[#9CA3AF]/60 uppercase tracking-wider">/ Month</span>
                            </div>
                            <p class="text-xs text-[#9CA3AF] leading-relaxed">
                                Enterprise-ready security pipelines, complete team seats, and advanced automation options.
                            </p>
                            <ul class="space-y-3 pt-4 text-xs">
                                <li class="flex items-center gap-2 text-[#9CA3AF]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#CBB48A]"></span>
                                    Unlimited Daily Scans & Sync
                                </li>
                                <li class="flex items-center gap-2 text-[#9CA3AF]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#CBB48A]"></span>
                                    100 security PenTests / Month
                                </li>
                                <li class="flex items-center gap-2 text-[#9CA3AF]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#CBB48A]"></span>
                                    Dedicated Multi-seat Dashboard
                                </li>
                            </ul>
                        </div>
                        <Link :href="route('register')" class="btn-secondary w-full text-center py-2.5 mt-8">
                            Contact Sales
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- 8. FAQ Section -->
        <section id="faq" class="section-spacing bg-[#15171A] border-t border-[#222428]">
            <div class="mx-auto max-w-4xl px-6 lg:px-8">
                <div class="text-left mb-12 reveal">
                    <span class="text-xs font-semibold text-[#CBB48A] uppercase tracking-[0.2em] block mb-3">FAQ</span>
                    <h2 class="font-display text-3xl font-bold text-[#F3E7C9] tracking-tight">
                        Common Questions
                    </h2>
                </div>

                <div class="reveal border-t border-[#222428] divide-y divide-[#222428]">
                    <div 
                        v-for="(faq, index) in faqs" 
                        :key="index"
                        class="faq-item"
                        :class="{ active: faq.active }"
                    >
                        <button class="faq-trigger" @click="toggleFaq(index)">
                            <span>{{ faq.question }}</span>
                            <svg class="faq-icon h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div class="faq-content">
                            <p>{{ faq.answer }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 9. Footer Section -->
        <footer class="border-t border-[#222428] pt-20 pb-12 bg-[#121315]">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="grid gap-12 lg:grid-cols-12 mb-16">
                    <div class="lg:col-span-6 space-y-6">
                        <div class="flex items-center gap-3">
                            <img src="/images/none-transparent-logo.png" alt="Lume Logo" class="h-7 w-7 object-contain" />
                            <span class="font-display text-[16px] font-bold tracking-[0.1em] text-[#F3E7C9]">Lume</span>
                        </div>
                        <p class="max-w-md text-sm text-[#9CA3AF] leading-relaxed text-left">
                            The Sovereign Operating System for High-Value Assets. Verify, sync, and protect codebase intellectual property securely.
                        </p>
                    </div>
                    
                    <div class="lg:col-span-3 text-left space-y-4">
                        <h4 class="text-[10px] font-bold uppercase tracking-[0.25em] text-[#F3E7C9]">Platform</h4>
                        <ul class="space-y-3 text-xs">
                            <!-- <li><Link :href="route('marketplace.index')" class="text-[#9CA3AF] hover:text-[#F3E7C9] transition-colors">Marketplace</Link></li> -->
                            <li><a href="#features" class="text-[#9CA3AF] hover:text-[#F3E7C9] transition-colors">Features</a></li>
                            <li><a href="#pricing" class="text-[#9CA3AF] hover:text-[#F3E7C9] transition-colors">Pricing</a></li>
                        </ul>
                    </div>

                    <div class="lg:col-span-3 text-left space-y-4">
                        <h4 class="text-[10px] font-bold uppercase tracking-[0.25em] text-[#F3E7C9]">Legal</h4>
                        <ul class="space-y-3 text-xs">
                            <li><Link :href="route('privacy-policy')" class="text-[#9CA3AF] hover:text-[#F3E7C9] transition-colors">Privacy Policy</Link></li>
                            <li><Link :href="route('forensic-standards')" class="text-[#9CA3AF] hover:text-[#F3E7C9] transition-colors">Standards</Link></li>
                            <li><Link :href="route('terms-of-service')" class="text-[#9CA3AF] hover:text-[#F3E7C9] transition-colors">Terms of Service</Link></li>
                        </ul>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row justify-between items-center border-t border-[#222428] pt-12 text-[10px] font-mono text-[#9CA3AF]/40">
                    <p>© 2026 zeraynce. All rights reserved. — EisenDev | Arjay Escabas (Laravel {{ laravelVersion }} / PHP {{ phpVersion }})</p>
                    <div class="flex gap-6 mt-4 md:mt-0">
                        <a href="https://github.com/EisenDev" target="_blank" class="hover:text-[#F3E7C9] transition-colors">GitHub</a>
                        <a href="https://www.linkedin.com/in/arjay-escabas-8a30413a0/" target="_blank" class="hover:text-[#F3E7C9] transition-colors">LinkedIn</a>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Global AI Helper Bot -->
        <LumeAISupport mode="global" />
    </div>
</template>
