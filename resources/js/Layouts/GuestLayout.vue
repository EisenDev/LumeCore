<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-brand-dark px-6 py-12">
        <!-- IMMERSIVE AI CANVAS -->
        <!-- Animated Background Orbs -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <!-- Core Glow -->
            <div class="absolute -left-[10%] top-[-10%] h-[600px] w-[600px] rounded-full bg-brand-primary/20 blur-[120px] animate-pulse" />
            <div class="absolute -right-[10%] bottom-[-10%] h-[600px] w-[600px] rounded-full bg-brand-secondary/20 blur-[120px] animate-pulse" />
            
            <!-- Floating Secondary Blobs -->
            <div class="absolute left-1/4 top-1/3 h-96 w-96 rounded-full bg-indigo-500/10 blur-[80px] animate-float" />
            <div class="absolute right-1/4 bottom-1/3 h-96 w-96 rounded-full bg-[#CBB48A]/10 blur-[80px] animate-float-delayed" />
            
            <!-- Mesh Texture / Noise Overlay -->
            <div class="absolute inset-0 bg-[url('https://grainy-gradients.vercel.app/noise.svg')] opacity-[0.15] brightness-50 mix-blend-overlay" />
        </div>

        <!-- CONTENT LAYER -->
        <div class="relative z-20 w-full max-w-2xl flex flex-col items-center">
            <!-- Branding Header (Floating above card) -->
            <!-- Branding Header Removed as per user request -->
            <div class="mb-6"></div>

            <!-- THE GLASS CARD -->
            <div class="w-full max-w-[500px]">
                <div class="overflow-hidden rounded-[2.5rem] border border-white/10 bg-white/[0.03] p-8 lg:p-12 shadow-[0_0_50px_-12px_rgba(0,0,0,0.5)] backdrop-blur-3xl relative">
                    <!-- Internal Card Accents -->
                    <div class="absolute -top-12 -left-12 h-40 w-40 bg-brand-primary/10 blur-3xl rounded-full" />
                    <div class="absolute -bottom-12 -right-12 h-40 w-40 bg-brand-secondary/10 blur-3xl rounded-full" />
                    
                    <slot />
                </div>

                <!-- Footer / Legal -->
                <div class="mt-10 text-center">
                    <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">
                        Encrypted Node: <span class="text-gray-400">Vault-OS-Genesis-v2</span>
                    </p>
                </div>
            </div>
        </div>
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
</style>
