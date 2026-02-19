<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import LumeAISupport from '@/Components/LumeAISupport.vue';
import PenTest_FutureInt from '@/Components/PenTest_FutureInt.vue';

const props = defineProps<{
    show: boolean;
    asset: any;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'confirm', prompt: string, isRescan: boolean): void;
    (e: 'open-credit-modal'): void;
    (e: 'completion'): void;
}>();

const page = usePage();
const customPrompt = ref('');
const isSubmitting = ref(false);
const showPentestAI = ref(false);
const showFutureModal = ref(false);

// Credit Info
const userCredits = computed(() => {
    const walletCredits = (page.props as any).wallet?.credits;
    if (walletCredits !== undefined && walletCredits !== null) {
        return Number(walletCredits);
    }
    const user = (page.props as any).auth?.user;
    return Number(user?.wallet?.credits ?? user?.credits ?? 0);
});

const activeSubscription = computed(() => (page.props as any).auth?.user?.active_subscription);

const pentestCost = 250;

// TITAN V8.7: Fix ReferenceError by lifting isRescan to computed scope
const isRescan = computed(() => props.asset?.metadata?.security_audit != null);

const hasPentestQuota = computed(() => {
    if (!activeSubscription.value) return false;
    const plan = activeSubscription.value.plan_type;
    const used = isRescan.value
        ? activeSubscription.value.daily_rescans_used || 0
        : activeSubscription.value.monthly_pentests_used || 0;
    const limit = plan === 'agency' ? 100 : (isRescan.value ? 20 : 4);
    return used < limit;
});

const remainingPentests = computed(() => {
    if (!activeSubscription.value) return 0;
    const plan = activeSubscription.value.plan_type || 'standard';
    const limit = plan === 'agency' ? 100 : (isRescan.value ? 20 : 4);
    const used = isRescan.value
       ? activeSubscription.value.daily_rescans_used || 0
       : activeSubscription.value.monthly_pentests_used || 0;
    return limit - used;
});

const hasEnoughCredits = computed(() => hasPentestQuota.value || userCredits.value >= pentestCost);

const handleConfirm = () => {
    if (!hasEnoughCredits.value) {
        handleBuyCredits();
        return;
    }
    
    isSubmitting.value = true;
    
    // Emit confirm to parent (Dashboard)
    emit('confirm', customPrompt.value, isRescan.value);
    
    // Modal will be closed by Dashboard or we can close it here
    setTimeout(() => {
        handleClose();
    }, 500);
};

const handleClose = () => {
    customPrompt.value = '';
    isSubmitting.value = false;
    emit('close');
};

const handleBuyCredits = () => {
    emit('close');
    emit('open-credit-modal');
};

const navigateToBilling = () => {
    emit('open-credit-modal');
};

</script>

<template>
    <Teleport to="body">
        <transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-[110] overflow-y-auto px-4 py-8 sm:px-6">
                <!-- Backdrop -->
                <div 
                    class="fixed inset-0 bg-brand-dark/90 backdrop-blur-xl"
                    @click="handleClose()"
                >
                    <!-- Ambient Glow -->
                    <div class="absolute inset-0 overflow-hidden pointer-events-none">
                         <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-lume-primary/10 rounded-full blur-[120px]" />
                         <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-sovereign-primary/10 rounded-full blur-[120px]" />
                    </div>
                </div>

                <div class="flex min-h-full items-center justify-center relative z-10">
                    <!-- Modal -->
                    <div 
                        class="relative w-full max-w-6xl transform overflow-hidden rounded-[2rem] border border-white/10 bg-[#0A0A0B] shadow-2xl transition-all duration-300"
                    >
                        <!-- Header -->
                        <div class="border-b border-white/5 bg-white/[0.02] px-8 py-6">
                            <div class="flex items-center gap-6">
                                <div class="relative flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-lume-primary/20 to-sovereign-primary/20 ring-1 ring-white/10">
                                    <div class="absolute inset-0 bg-lume-primary/20 blur-xl"></div>
                                    <svg class="h-7 w-7 text-lume-primary relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-[1000] text-white tracking-tighter uppercase italic leading-none mb-2">
                                        Deep Security <span class="text-transparent bg-clip-text bg-gradient-to-r from-lume-primary to-sovereign-primary">Protocol.</span>
                                    </h3>
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-[0.2em]">
                                        Configure Forensic Parameters
                                    </p>
                                </div>
                                <button @click="handleClose" class="ml-auto p-2 rounded-full hover:bg-white/5 text-gray-500 hover:text-white transition-colors">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- FORM VIEW -->
                        <div class="grid grid-cols-1 lg:grid-cols-2">
                            <!-- Left Column: Strategy & Cost -->
                            <div class="p-8 lg:p-10 border-r border-white/5 bg-white/[0.01] space-y-8">
                                <div class="space-y-4">
                                    <h4 class="text-xs font-black text-white uppercase tracking-[0.2em]">Target Asset</h4>
                                    <div class="p-6 rounded-2xl bg-white/[0.03] border border-white/5 flex items-center gap-5 group transition-all hover:bg-white/[0.05]">
                                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-gray-800 to-black flex-shrink-0 flex items-center justify-center text-gray-400 border border-white/5">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9-9H3m9 9V3" /></svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-[10px] text-lume-primary font-black tracking-[0.2em] uppercase mb-1">Infrastructure Map</div>
                                            <div class="text-base font-medium text-white tracking-tight truncate">{{ asset?.file_name }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Credit Info Card -->
                                <div class="rounded-3xl border border-white/10 bg-black/60 p-8 space-y-6 shadow-2xl relative overflow-hidden">
                                    <div class="absolute top-0 right-0 w-32 h-32 bg-lume-primary/10 rounded-full blur-[60px]"></div>
                                    
                                    <div v-if="activeSubscription" class="flex items-center justify-between border-b border-white/5 pb-6 relative z-10">
                                        <div class="flex flex-col gap-1">
                                            <span class="text-lg text-white font-[1000] uppercase italic tracking-tighter">{{ activeSubscription.plan_type }} Plan</span>
                                            <span class="text-[10px] uppercase font-black tracking-[0.2em]" :class="asset?.metadata?.security_audit ? 'text-sovereign-primary' : 'text-lume-primary'">
                                                {{ asset?.metadata?.security_audit ? 'Re-Scan Quota' : 'Full Pentest Quota' }}
                                            </span>
                                        </div>
                                        <div class="flex flex-col items-end gap-1">
                                            <span class="text-3xl font-[1000] text-white italic tracking-tighter">{{ remainingPentests }}</span>
                                            <span class="text-[9px] text-gray-500 font-black uppercase tracking-[0.2em]">
                                                {{ asset?.metadata?.security_audit 
                                                    ? (activeSubscription.plan_type === 'agency' ? 'Daily Re-Scans' : 'Daily Re-Scans') 
                                                    : (activeSubscription.plan_type === 'agency' ? 'Monthly Scans' : 'Monthly Scans') 
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center justify-between py-2 relative z-10">
                                        <span class="text-xs font-bold text-gray-500 uppercase tracking-widest">Scan Cost</span>
                                        <div class="flex flex-col items-end">
                                            <span class="text-xl font-black text-lume-primary tracking-tight">{{ pentestCost }} Credits</span>
                                            <span v-if="hasPentestQuota" class="text-[9px] text-white font-bold uppercase tracking-widest bg-emerald-500/20 px-2 py-0.5 rounded-full mt-1">Covered by Plan</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between border-t border-white/5 pt-6 relative z-10">
                                        <span class="text-xs font-bold text-gray-500 uppercase tracking-widest">Available Balance</span>
                                        <span class="text-xl font-black text-white tracking-tight">
                                            {{ userCredits.toFixed(2) }}
                                        </span>
                                    </div>
                                    
                                    <div v-if="!hasEnoughCredits" class="relative z-10 flex items-center gap-3 rounded-xl bg-red-500/10 border border-red-500/20 px-4 py-3 text-xs text-red-400 font-bold uppercase tracking-wide">
                                        <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        Insufficient Funds
                                    </div>
                                </div>

                                <!-- Capabilities -->
                                <div class="space-y-4">
                                    <h4 class="text-xs font-black text-white uppercase tracking-[0.2em]">Coverage Vector</h4>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div v-for="tag in [
                                            'SQL Injection Testing',
                                            'XSS Detection',
                                            'Security Header Audit',
                                            'Sensitive Data Exposure',
                                            'Directory Discovery',
                                            'Auth & Session Analysis',
                                            'IDOR / Access Control',
                                        ]" :key="tag" class="flex items-center gap-2.5 text-[10px] text-gray-400 font-bold bg-white/[0.02] p-2.5 rounded-xl border border-white/5 hover:border-lume-primary/30 transition-colors group">
                                            <div class="h-1.5 w-1.5 rounded-full bg-lume-primary flex-shrink-0 group-hover:animate-pulse"></div>
                                            {{ tag }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Instructions & Action -->
                            <div class="p-8 lg:p-10 space-y-8 flex flex-col bg-black/20">
                                <div class="flex-1 space-y-6">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div class="flex flex-col">
                                            <h4 class="text-xs font-black text-sovereign-primary uppercase tracking-[0.2em] mb-2">Instruction Set</h4>
                                            <span class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Tailor Offensive Focus</span>
                                        </div>
                                        <button 
                                            @click="showPentestAI = true"
                                            class="flex items-center justify-center gap-3 text-[10px] font-black text-black bg-white hover:bg-gray-200 transition-all uppercase tracking-[0.2em] px-6 py-3 rounded-full shadow-lg hover:scale-105 active:scale-95 group"
                                        >
                                            <svg class="w-4 h-4 text-lume-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                            AI Oracle
                                        </button>
                                    </div>

                                    <div class="relative group h-[300px]">
                                        <div class="absolute -inset-0.5 bg-gradient-to-r from-lume-primary to-sovereign-primary rounded-[20px] blur opacity-10 group-focus-within:opacity-30 transition duration-500"></div>
                                        <textarea
                                            v-model="customPrompt"
                                            placeholder="Define specific attack vectors or focus areas..."
                                            class="relative w-full h-full rounded-[18px] border border-white/10 bg-[#050505] px-6 py-6 text-sm text-gray-300 placeholder-gray-700 focus:border-lume-primary/50 focus:outline-none focus:ring-0 transition-all font-medium leading-relaxed resize-none"
                                        ></textarea>
                                    </div>

                                    <div class="p-5 bg-lume-primary/5 rounded-2xl border border-lume-primary/10 space-y-3">
                                        <p class="text-[11px] text-lume-primary/80 leading-relaxed font-medium">
                                            <span class="font-black uppercase tracking-wider mr-2">Beta Notice:</span>
                                            This engine currently supports automated surface-level reconnaissance aligned with OWASP Top 10 standards. Deep manual exploit chains, SSRF, and business logic testing are not yet available.
                                        </p>
                                        
                                        <!-- Future Integration Link -->
                                        <button 
                                            @click="showFutureModal = true"
                                            class="flex items-center gap-2 text-[10px] font-black uppercase tracking-wider text-lume-primary hover:text-sovereign-primary transition-colors border-b border-lume-primary/30 hover:border-sovereign-primary pb-0.5 w-fit"
                                        >
                                            View Current Protocol & Future Integration
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                                        </button>

                                        <div class="flex flex-wrap gap-1.5 pt-2">
                                            <span v-for="cap in ['SQLi', 'XSS', 'Headers', 'Data Leaks', 'Dir Scan', 'Auth', 'IDOR']" :key="cap" class="text-[9px] font-black uppercase tracking-wider text-lume-primary/60 bg-lume-primary/10 px-2 py-0.5 rounded-full">
                                                {{ cap }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex gap-4 border-t border-white/5 bg-black/40 px-8 py-6 backdrop-blur-sm">
                            <button
                                @click="handleClose"
                                class="flex-1 rounded-full border border-white/10 bg-white/[0.02] px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-gray-400 hover:bg-white/[0.05] hover:text-white transition-all"
                            >
                                Cancel Operation
                            </button>
                            <button
                                @click="handleConfirm"
                                :disabled="isSubmitting"
                                class="flex-1 rounded-full px-6 py-4 text-xs font-black uppercase tracking-[0.2em] transition-all disabled:opacity-50 shadow-xl hover:scale-[1.02] active:scale-[0.98]"
                                :class="hasEnoughCredits 
                                    ? 'bg-gradient-to-r from-lume-primary to-sovereign-primary text-white shadow-lume-primary/20' 
                                    : 'bg-gradient-to-r from-orange-500 to-red-600 text-white'"
                            >
                                <span v-if="isSubmitting" class="flex items-center justify-center gap-3">
                                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Authenticating...
                                </span>
                                <span v-else>
                                    {{ hasPentestQuota ? 'Execute Sovereign Scan' : (hasEnoughCredits ? `Confirm & Deduct ${pentestCost}` : 'Insufficient Credits') }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>

    <!-- PenTest AI Support Modal -->
    <LumeAISupport
        v-if="showPentestAI"
        :show="true"
        mode="pentest"
        :asset="asset"
        @close="showPentestAI = false"
    />

    <!-- Future Integration Modal -->
    <PenTest_FutureInt
        v-if="showFutureModal"
        :show="true"
        @close="showFutureModal = false"
    />
</template>

<style scoped>
/* No Custom Scrollbar needed for textarea if resize-none and sufficient height, cleaner look */
</style>
