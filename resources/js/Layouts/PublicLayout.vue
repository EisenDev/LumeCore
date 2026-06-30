<script setup lang="ts">
/**
 * LUME Public Layout (PublicLayout.vue)
 * Aligns global layout navigation and footer style with the warm charcoal premium design system.
 */
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const mobileMenuOpen = ref(false);

defineProps<{
    title?: string;
}>();
</script>

<template>
    <!-- Core Layout Wrapper -->
    <div class="landing-theme bg-[#0a0a0b] min-h-screen font-sans antialiased selection:bg-[#fbe6af]/20 selection:text-white text-[#888888]">
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
                        <!-- <Link :href="route('marketplace.index')" class="text-sm font-medium text-[#9CA3AF] transition-colors hover:text-[#F3E7C9]">
                            Marketplace
                        </Link> -->
                        <a href="/#features" class="text-sm font-medium text-[#9CA3AF] transition-colors hover:text-[#F3E7C9]">
                            Features
                        </a>
                        <a href="/#pricing" class="text-sm font-medium text-[#9CA3AF] transition-colors hover:text-[#F3E7C9]">
                            Pricing
                        </a>
                        <a href="/#faq" class="text-sm font-medium text-[#9CA3AF] transition-colors hover:text-[#F3E7C9]">
                            FAQ
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
                            <Link :href="route('login')" class="text-sm font-medium text-[#9CA3AF] transition-colors hover:text-[#F3E7C9]">
                                Log in
                            </Link>
                            <Link :href="route('register')" class="btn-primary py-2 px-4.5 rounded-lg text-xs font-semibold">
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
                        <!-- <Link :href="route('marketplace.index')" class="text-sm font-medium text-[#9CA3AF] hover:text-[#F3E7C9]" @click="mobileMenuOpen = false">
                            Marketplace
                        </Link> -->
                        <a href="/#features" class="text-sm font-medium text-[#9CA3AF] hover:text-[#F3E7C9]" @click="mobileMenuOpen = false">
                            Features
                        </a>
                        <a href="/#pricing" class="text-sm font-medium text-[#9CA3AF] hover:text-[#F3E7C9]" @click="mobileMenuOpen = false">
                            Pricing
                        </a>
                        <a href="/#faq" class="text-sm font-medium text-[#9CA3AF] hover:text-[#F3E7C9]" @click="mobileMenuOpen = false">
                            FAQ
                        </a>
                        <Link :href="route('login')" class="text-sm font-medium text-[#9CA3AF] hover:text-[#F3E7C9] pt-3 border-t border-[#222428]" @click="mobileMenuOpen = false">
                            Log in
                        </Link>
                    </div>
                </div>
            </transition>
        </nav>

        <!-- Main Content Slot -->
        <main>
            <slot />
        </main>

        <!-- Footer Section -->
        <footer class="border-t border-[#222428] pt-20 pb-12 bg-[#121315] mt-20">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="grid gap-12 lg:grid-cols-12 mb-16">
                    <div class="lg:col-span-6 space-y-6">
                        <div class="flex items-center gap-3">
                            <img src="/images/none-transparent-logo.png" alt="Lume Logo" class="h-7 w-7 object-contain" />
                            <span class="font-display text-[15px] font-bold tracking-[0.1em] text-[#F3E7C9]">Lume</span>
                        </div>
                        <p class="max-w-md text-sm text-[#9CA3AF] leading-relaxed text-left">
                            The Sovereign Operating System for High-Value Assets. Verify, sync, and protect codebase intellectual property securely.
                        </p>
                    </div>
                    
                    <div class="lg:col-span-3 text-left space-y-4">
                        <h4 class="text-[10px] font-bold uppercase tracking-[0.25em] text-[#F3E7C9]">Platform</h4>
                        <ul class="space-y-3 text-xs">
                            <!-- <li><Link :href="route('marketplace.index')" class="text-[#9CA3AF] hover:text-[#F3E7C9] transition-colors">Marketplace</Link></li> -->
                            <li><a href="/#features" class="text-[#9CA3AF] hover:text-[#F3E7C9] transition-colors">Features</a></li>
                            <li><a href="/#pricing" class="text-[#9CA3AF] hover:text-[#F3E7C9] transition-colors">Pricing</a></li>
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
                    <p>© 2026 zeraynce. All rights reserved. — EisenDev | Arjay Escabas</p>
                    <div class="flex gap-6 mt-4 md:mt-0">
                        <a href="https://github.com/EisenDev" target="_blank" class="hover:text-[#F3E7C9] transition-colors">GitHub</a>
                        <a href="https://www.linkedin.com/in/arjay-escabas-8a30413a0/" target="_blank" class="hover:text-[#F3E7C9] transition-colors">LinkedIn</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
