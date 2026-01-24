<script setup lang="ts">
/**
 * LUME Welcome/Landing Page
 * Professional landing page with hero section, features, and pricing
 */
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import GlobalHelper from '@/Components/GlobalHelper.vue';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
}>();

// Mobile menu state
const mobileMenuOpen = ref(false);
</script>

<template>
    <Head title="LUME - The Sovereign Operating System for High-Value Assets" />

    <div class="min-h-screen bg-brand-dark">
        <!-- Navigation Bar -->
        <nav class="sticky top-0 z-50 border-b border-white/10 bg-brand-dark/80 backdrop-blur-xl">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <!-- Left: Logo -->
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand-primary to-brand-secondary shadow-lg shadow-brand-primary/20">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <span class="text-xl font-bold text-white">LUME</span>
                    </div>

                    <!-- Middle: Links (Desktop) -->
                    <div class="hidden items-center gap-8 md:flex">
                        <Link :href="route('marketplace.index')" class="text-sm font-medium text-gray-300 transition-colors hover:text-white">
                            Marketplace
                        </Link>
                        <a href="#features" class="text-sm font-medium text-gray-300 transition-colors hover:text-white">
                            Features
                        </a>
                        <a href="#pricing" class="text-sm font-medium text-gray-300 transition-colors hover:text-white">
                            Pricing
                        </a>
                    </div>

                    <!-- Right: Auth Buttons -->
                    <div class="flex items-center gap-3">
                        <template v-if="$page.props.auth.user">
                            <Link
                                :href="route('dashboard')"
                                class="rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110"
                            >
                                Dashboard
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                v-if="canLogin"
                                :href="route('login')"
                                class="hidden rounded-lg px-4 py-2 text-sm font-medium text-gray-300 transition-colors hover:text-white sm:block"
                            >
                                Log In
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110"
                            >
                                Get Started
                            </Link>
                        </template>

                        <!-- Mobile Menu Button -->
                        <button
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            class="rounded-lg p-2 text-gray-400 hover:bg-gray-800 hover:text-white md:hidden"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Mobile Menu -->
                <div v-if="mobileMenuOpen" class="border-t border-white/10 py-4 md:hidden">
                    <div class="flex flex-col gap-4">
                        <Link :href="route('marketplace.index')" class="text-sm font-medium text-gray-300 transition-colors hover:text-white">
                            Marketplace
                        </Link>
                        <a href="#features" class="text-sm font-medium text-gray-300 transition-colors hover:text-white">
                            Features
                        </a>
                        <a href="#pricing" class="text-sm font-medium text-gray-300 transition-colors hover:text-white">
                            Pricing
                        </a>
                        <Link v-if="canLogin && !$page.props.auth.user" :href="route('login')" class="text-sm font-medium text-gray-300 transition-colors hover:text-white">
                            Log In
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="relative overflow-hidden py-20 lg:py-32">
            <!-- Gradient Orbs -->
            <div class="absolute left-1/4 top-1/4 h-96 w-96 rounded-full bg-brand-primary/20 blur-3xl" />
            <div class="absolute bottom-1/4 right-1/4 h-96 w-96 rounded-full bg-brand-secondary/20 blur-3xl" />
            
            <div class="relative mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                <!-- Badge -->
                <div class="mb-8 inline-flex items-center gap-2 rounded-full border border-brand-primary/30 bg-brand-primary/10 px-4 py-2">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-brand-primary" />
                    <span class="text-sm font-medium text-brand-primary">Powered by AI</span>
                </div>

                <!-- Headline -->
                <h1 class="mx-auto max-w-4xl text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl">
                    The
                    <span class="bg-gradient-to-r from-brand-primary to-brand-secondary bg-clip-text text-transparent">
                        Sovereign Operating System
                    </span>
                    for High-Value Assets
                </h1>

                <!-- Subtext -->
                <p class="mx-auto mt-6 max-w-2xl text-lg text-gray-400 lg:text-xl">
                    Verify your intellectual property with AI-driven audits and list them on our public marketplace. 
                    LUME protects your data while maximizing its value.
                </p>

                <!-- CTA Buttons -->
                <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <Link
                        :href="route('marketplace.index')"
                        class="group flex w-full items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/5 px-8 py-4 text-sm font-semibold text-white backdrop-blur-sm transition-all hover:bg-white/10 sm:w-auto"
                    >
                        <svg class="h-5 w-5 text-gray-400 transition-colors group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Explore Marketplace
                    </Link>
                    <Link
                        :href="canLogin && $page.props.auth.user ? route('dashboard') : route('register')"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-8 py-4 text-sm font-semibold text-white shadow-lg shadow-brand-primary/30 transition-all hover:brightness-110 sm:w-auto"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Audit My Assets
                    </Link>
                </div>

                <!-- Trust Indicators -->
                <div class="mt-16 flex flex-wrap items-center justify-center gap-8 border-t border-white/10 pt-8">
                    <div class="text-center">
                        <p class="text-3xl font-bold text-white">100%</p>
                        <p class="text-sm text-gray-400">AI Verified</p>
                    </div>
                    <div class="hidden h-8 w-px bg-white/20 sm:block" />
                    <div class="text-center">
                        <p class="text-3xl font-bold text-white">Privacy</p>
                        <p class="text-sm text-gray-400">Compliant</p>
                    </div>
                    <div class="hidden h-8 w-px bg-white/20 sm:block" />
                    <div class="text-center">
                        <p class="text-3xl font-bold text-brand-secondary">Instant</p>
                        <p class="text-sm text-gray-400">Payouts</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="border-t border-white/10 bg-gradient-to-b from-transparent to-gray-900/50 py-20 lg:py-32">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-white sm:text-4xl">
                        Three Pillars of 
                        <span class="bg-gradient-to-r from-brand-primary to-brand-secondary bg-clip-text text-transparent">LUME</span>
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-gray-400">
                        A complete ecosystem for managing, verifying, and monetizing your digital assets.
                    </p>
                </div>

                <div class="mt-16 grid gap-8 md:grid-cols-3">
                    <!-- Pillar 1: CloudVault -->
                    <div class="group rounded-2xl border border-white/10 bg-gradient-to-br from-gray-800/50 to-gray-900/50 p-8 transition-all hover:border-brand-primary/50">
                        <div class="mb-6 flex h-14 w-14 items-center justify-center rounded-xl bg-brand-primary/20 ring-1 ring-brand-primary/30">
                            <svg class="h-7 w-7 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                            </svg>
                        </div>
                        <h3 class="mb-3 text-xl font-bold text-white">CloudVault</h3>
                        <p class="text-gray-400">
                            Secure, encrypted storage for your high-value digital assets. Upload documents, templates, datasets, and creative works with military-grade security.
                        </p>
                    </div>

                    <!-- Pillar 2: Ledger -->
                    <div class="group rounded-2xl border border-white/10 bg-gradient-to-br from-gray-800/50 to-gray-900/50 p-8 transition-all hover:border-brand-secondary/50">
                        <div class="mb-6 flex h-14 w-14 items-center justify-center rounded-xl bg-brand-secondary/20 ring-1 ring-brand-secondary/30">
                            <svg class="h-7 w-7 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 class="mb-3 text-xl font-bold text-white">Ledger</h3>
                        <p class="text-gray-400">
                            Credit-based transaction system with transparent fee structures. Buy credits, pay for audits, and receive payouts seamlessly.
                        </p>
                    </div>

                    <!-- Pillar 3: AI Auditor -->
                    <div class="group rounded-2xl border border-white/10 bg-gradient-to-br from-gray-800/50 to-gray-900/50 p-8 transition-all hover:border-purple-500/50">
                        <div class="mb-6 flex h-14 w-14 items-center justify-center rounded-xl bg-purple-500/20 ring-1 ring-purple-500/30">
                            <svg class="h-7 w-7 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                        </div>
                        <h3 class="mb-3 text-xl font-bold text-white">Sovereign AI Auditor</h3>
                        <p class="text-gray-400">
                            Advanced AI-powered document analysis. Get instant verification scores, privacy compliance checks, and marketplace eligibility assessments.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="border-t border-white/10 py-20 lg:py-32">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-white sm:text-4xl">
                        Simple, Transparent 
                        <span class="bg-gradient-to-r from-brand-primary to-brand-secondary bg-clip-text text-transparent">Pricing</span>
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-gray-400">
                        Pay only for what you use. No subscriptions, no hidden fees.
                    </p>
                </div>

                <div class="mx-auto mt-16 max-w-lg">
                    <div class="rounded-2xl border border-brand-primary/30 bg-gradient-to-br from-brand-primary/10 to-brand-secondary/10 p-8">
                        <div class="text-center">
                            <h3 class="text-xl font-bold text-white">Credit-Based</h3>
                            <div class="mt-4 flex items-baseline justify-center gap-1">
                                <span class="text-5xl font-bold text-white">1</span>
                                <span class="text-2xl text-gray-400">credit</span>
                            </div>
                            <p class="mt-2 text-gray-400">per AI audit</p>
                        </div>

                        <ul class="mt-8 space-y-4">
                            <li class="flex items-center gap-3">
                                <svg class="h-5 w-5 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-gray-300">Unlimited file uploads</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="h-5 w-5 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-gray-300">AI-powered document analysis</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="h-5 w-5 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-gray-300">Privacy compliance check</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="h-5 w-5 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-gray-300">Marketplace listing eligibility</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="h-5 w-5 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-gray-300">Suggested market value</span>
                            </li>
                        </ul>

                        <Link
                            :href="route('register')"
                            class="mt-8 block w-full rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary py-4 text-center font-semibold text-white shadow-lg transition-all hover:brightness-110"
                        >
                            Get Started Free
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-white/10 bg-gray-900/50">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="grid gap-8 md:grid-cols-4">
                    <!-- Brand -->
                    <div class="md:col-span-2">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand-primary to-brand-secondary">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <span class="text-xl font-bold text-white">LUME</span>
                        </div>
                        <p class="mt-4 max-w-xs text-sm text-gray-400">
                            The Sovereign Operating System for High-Value Assets. Verify, protect, and monetize your digital intellectual property.
                        </p>
                    </div>

                    <!-- Platform Links -->
                    <div>
                        <h4 class="font-semibold text-white">Platform</h4>
                        <ul class="mt-4 space-y-2">
                            <li>
                                <Link :href="route('marketplace.index')" class="text-sm text-gray-400 transition-colors hover:text-white">
                                    Marketplace
                                </Link>
                            </li>
                            <li>
                                <a href="#features" class="text-sm text-gray-400 transition-colors hover:text-white">
                                    Features
                                </a>
                            </li>
                            <li>
                                <a href="#pricing" class="text-sm text-gray-400 transition-colors hover:text-white">
                                    Pricing
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Legal Links -->
                    <div>
                        <h4 class="font-semibold text-white">Legal</h4>
                        <ul class="mt-4 space-y-2">
                            <li>
                                <a href="#" class="text-sm text-gray-400 transition-colors hover:text-white">
                                    Privacy Policy
                                </a>
                            </li>
                            <li>
                                <a href="#" class="text-sm text-gray-400 transition-colors hover:text-white">
                                    Data Standards
                                </a>
                            </li>
                            <li>
                                <a href="#" class="text-sm text-gray-400 transition-colors hover:text-white">
                                    Terms of Service
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mt-12 border-t border-white/10 pt-8 text-center">
                    <p class="text-sm text-gray-500">
                        © 2026 LUME. All rights reserved. Powered by AI.
                    </p>
                </div>
            </div>
        </footer>

        <!-- Global AI Helper Bot -->
        <GlobalHelper />
    </div>
</template>
