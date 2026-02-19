<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const mobileMenuOpen = ref(false);

defineProps<{
    title?: string;
}>();
</script>

<template>
    <div class="min-h-screen bg-brand-dark selection:bg-lume-primary/30 selection:text-white font-sans antialiased overflow-x-hidden text-gray-300">
        <!-- Grain Overlay -->
        <div class="fixed inset-0 z-[100] pointer-events-none opacity-[0.03] bg-[url('https://grainy-gradients.vercel.app/noise.svg')] brightness-100 contrast-150" />

        <!-- Navigation Bar -->
        <nav class="sticky top-0 z-[60] border-b border-white/5 bg-brand-dark/40 backdrop-blur-2xl transition-all duration-500">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-20 items-center justify-between">
                    <!-- Left: Logo -->
                    <Link href="/" class="flex items-center gap-4 group cursor-pointer">
                        <div class="relative">
                            <div class="absolute -inset-2 bg-gradient-to-r from-lume-primary to-sovereign-primary rounded-xl blur-lg opacity-20 group-hover:opacity-40 transition-opacity" />
                            <div class="relative flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-lume-primary to-sovereign-primary shadow-xl shadow-lume-primary/20 transition-transform group-hover:scale-105">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                        </div>
                        <span class="text-2xl font-black tracking-tighter text-white uppercase italic">LUME<span class="text-lume-primary not-italic">CORE</span></span>
                    </Link>

                    <!-- Middle: Links (Desktop) -->
                    <div class="hidden items-center gap-10 md:flex">
                        <Link :href="route('marketplace.index')" class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-400 transition-all hover:text-white hover:tracking-[0.4em]">
                            Marketplace
                        </Link>
                        <a href="/#forensics" class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-400 transition-all hover:text-white hover:tracking-[0.4em]">
                            Forensics
                        </a>
                        <a href="/#sync" class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-400 transition-all hover:text-white hover:tracking-[0.4em]">
                            Asset Sync
                        </a>
                        <a href="/#pricing" class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-400 transition-all hover:text-white hover:tracking-[0.4em]">
                            Pricing
                        </a>
                    </div>

                    <!-- Right: Auth Buttons -->
                    <div class="flex items-center gap-6">
                        <Link
                            :href="route('login')"
                            class="hidden px-4 py-2 text-xs font-black uppercase tracking-widest text-gray-400 transition-all hover:text-white hover:tracking-[0.2em] sm:block border-r border-white/5 pr-8"
                        >
                            Log In
                        </Link>
                        <Link
                            :href="route('register')"
                            class="relative group rounded-full bg-gradient-to-r from-lume-primary to-lume-secondary px-8 py-3 text-xs font-black uppercase tracking-[0.2em] text-white shadow-xl shadow-lume-primary/30 transition-all hover:brightness-110 active:scale-95"
                        >
                            <span class="relative z-10">Get Access</span>
                        </Link>

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
                            <a href="/#forensics" class="text-xs font-black uppercase tracking-[0.3em] text-gray-400 transition-colors hover:text-white">
                                Forensic Core
                            </a>
                            <a href="/#pricing" class="text-xs font-black uppercase tracking-[0.3em] text-gray-400 transition-colors hover:text-white">
                                Pricing
                            </a>
                            <Link :href="route('login')" class="text-xs font-black uppercase tracking-[0.3em] text-gray-400 transition-colors hover:text-white mt-4 pt-4 border-t border-white/5">
                                Log In
                            </Link>
                        </div>
                    </div>
                </transition>
            </div>
        </nav>

        <!-- Main Content -->
        <main>
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t border-white/5 pt-20 pb-12 mt-20">
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
                            <li><a href="/#forensics" class="text-xs font-bold text-gray-500 hover:text-white transition-colors">Forensics</a></li>
                            <li><a href="/#pricing" class="text-xs font-bold text-gray-500 hover:text-white transition-colors">Pricing</a></li>
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
                        <a href="#" class="h-6 w-6 text-gray-500 hover:text-white transition-colors">
                            <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                        </a>
                        <a href="#" class="h-6 w-6 text-gray-500 hover:text-white transition-colors">
                            <svg fill="currentColor" viewBox="0 0 24 24"><path d="M20.317 4.37a19.791 19.791 0 00-4.885-1.515.074.074 0 00-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 00-5.487 0 12.64 12.64 0 00-.617-1.25.077.077 0 00-.079-.037A19.736 19.736 0 003.677 4.37a.07.07 0 00-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 00.031.057 19.9 19.9 0 005.993 3.03.078.078 0 00.084-.028 14.09 14.09 0 001.226-1.994.076.076 0 00-.041-.106 13.107 13.107 0 01-1.872-.892.077.077 0 01-.008-.128 10.2 10.2 0 00.372-.292.074.074 0 01.077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 01.078.01c.12.098.246.198.373.292a.077.077 0 01-.006.127 12.299 12.299 0 01-1.873.892.077.077 0 00-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 00.084.028 19.839 19.839 0 006.002-3.03.077.077 0 00.032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 00-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.332.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.419 0 1.334-.956 2.419-2.157 2.419zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.332.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.419 0 1.334-.946 2.419-2.157 2.419z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
