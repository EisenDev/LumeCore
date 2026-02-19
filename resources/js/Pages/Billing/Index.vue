<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';

const props = defineProps<{
    wallet: any;
    subscription: any;
    invoices: any[];
    usageLogs: any[];
}>();

const formatDate = (date: string) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString();
};

const formattedBalance = computed(() => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: props.wallet?.currency || 'USD'
    }).format(props.wallet?.balance || 0);
});

import CreditPurchaseModal from '@/Components/CreditPurchaseModal.vue';
import SubscriptionPaymentModal from '@/Components/SubscriptionPaymentModal.vue';
import ManageSubscriptionModal from '@/Components/ManageSubscriptionModal.vue';
import { ref } from 'vue';

const showPurchaseModal = ref(false);
const showSubscriptionModal = ref(false);
const showManageModal = ref(false);
const selectedPlan = ref<any>(null);
const activeHistoryTab = ref<'invoices' | 'usage'>('invoices');

// Search & Pagination for Usage Logs
const searchQuery = ref('');
const itemsPerPage = 15;
const usagePage = ref(1);

const filteredUsageLogs = computed(() => {
    let logs = props.usageLogs || [];
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        logs = logs.filter(log => 
            log.display_name?.toLowerCase().includes(query) || 
            log.user?.toLowerCase().includes(query) ||
            log.type?.toLowerCase().includes(query)
        );
    }
    return logs;
});

const paginatedUsageLogs = computed(() => {
    const start = (usagePage.value - 1) * itemsPerPage;
    return filteredUsageLogs.value.slice(start, start + itemsPerPage);
});

const totalUsagePages = computed(() => Math.ceil(filteredUsageLogs.value.length / itemsPerPage));

const openSubscriptionModal = (plan: any) => {
    selectedPlan.value = plan;
    showSubscriptionModal.value = true;
};
</script>

<template>
    <Head title="Billing & Usage" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <span class="text-2xl font-black italic tracking-tighter text-white uppercase mt-1">Billing & Usage</span>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                
                <!-- 1. Subscription Plans (Primary CTA) -->
                <div>
                    <div class="mb-10 text-center sm:text-left">
                        <h3 class="text-3xl font-black italic tracking-tighter text-white uppercase leading-none">Compare Deployment Plans</h3>
                        <p class="mt-2 text-[10px] font-bold text-gray-500 uppercase tracking-widest leading-none">Select the forensic capacity that fits your operation.</p>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Plan 1: CI/CD (Developer) -->
                        <div 
                            class="group relative overflow-hidden rounded-[2rem] border border-white/5 bg-[#0A0A0B]/60 backdrop-blur-3xl p-8 transition-all duration-300 hover:border-emerald-400/30"
                            :class="{ 'ring-2 ring-emerald-400/50 shadow-2xl shadow-emerald-400/10': props.subscription?.plan_type === 'developer' }"
                        >
                            <div v-if="props.subscription?.plan_type === 'developer'" class="absolute -right-12 top-6 rotate-45 bg-emerald-400 px-12 py-1 text-[8px] font-black uppercase italic tracking-tighter text-slate-950 shadow-lg">CURRENT</div>
                            
                            <h4 class="text-xl font-black italic tracking-tighter text-white uppercase flex items-center gap-2">
                                SENTINEL Plan 
                                <span class="rounded-lg bg-emerald-400/10 border border-emerald-400/20 px-2 py-0.5 text-[8px] font-black uppercase tracking-tighter text-emerald-400">Developer</span>
                            </h4>
                            <div class="mt-6 flex items-baseline gap-1">
                                <span class="text-4xl font-black italic tracking-tighter bg-gradient-to-r from-emerald-400 to-cyan-400 bg-clip-text text-transparent">$19</span>
                                <span class="text-xs font-bold text-gray-500 uppercase tracking-widest">/mo</span>
                            </div>
                            
                            <ul class="mt-8 space-y-4">
                                <li class="flex items-center gap-3 text-gray-400">
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400/10 text-emerald-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4"><path d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider">100 Document Scans daily</span>
                                </li>
                                <li class="flex items-center gap-3 text-gray-400">
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400/10 text-emerald-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4"><path d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider">20 Individual Scans daily</span>
                                </li>
                                <li class="flex items-center gap-3 text-gray-400">
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400/10 text-emerald-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4"><path d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider">10 Sync Scans daily</span>
                                </li>
                                <li class="flex items-center gap-3 text-gray-400">
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400/10 text-emerald-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4"><path d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider">4 Surface PenTests/Month</span>
                                </li>
                                <li class="flex items-center gap-3 text-gray-400 opacity-20">
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-white/5 text-gray-500">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12" /></svg>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider">Team Collaboration</span>
                                </li>
                            </ul>

                            <button 
                                @click="openSubscriptionModal({ name: 'SENTINEL Plan', price: '$19', badge: 'Developer', type: 'developer' })"
                                :disabled="props.subscription && (props.subscription.plan_type === 'developer' || props.subscription.status === 'active')"
                                class="mt-8 w-full rounded-2xl border border-white/10 bg-white shadow-xl px-4 py-4 text-center text-[10px] font-black italic tracking-tighter uppercase text-slate-950 transition-all hover:bg-emerald-400 active:scale-[0.98] disabled:opacity-50"
                            >
                                {{ props.subscription?.plan_type === 'developer' ? 'Active Plan' : (props.subscription ? 'Manage Plan' : 'Select Developer') }}
                            </button>
                        </div>

                        <!-- Plan 2: Agency (Sovereign) -->
                        <div 
                            class="group relative overflow-hidden rounded-[2rem] border-2 border-transparent bg-gradient-to-br from-emerald-400/20 to-cyan-400/5 p-8 transition-all duration-300"
                            style="border-image: linear-gradient(to bottom, #34d399, #22d3ee) 1; border-radius: 2rem !important;"
                            :class="{ 'ring-4 ring-emerald-400/30 shadow-2xl shadow-emerald-400/20': props.subscription?.plan_type === 'agency' }"
                        >
                            <div v-if="props.subscription?.plan_type === 'agency'" class="absolute -right-12 top-6 rotate-45 bg-emerald-400 px-12 py-1 text-[8px] font-black uppercase italic tracking-tighter text-slate-950 shadow-lg">CURRENT</div>

                            <h4 class="text-xl font-black italic tracking-tighter text-white uppercase flex items-center gap-2">
                                Agency Plan 
                                <span class="rounded-lg bg-emerald-400/10 border border-emerald-400/20 px-2 py-0.5 text-[8px] font-black uppercase tracking-tighter text-emerald-400">Sovereign</span>
                            </h4>
                            <div class="mt-6 flex items-baseline gap-1">
                                <span class="text-4xl font-black italic tracking-tighter text-emerald-400">$59</span>
                                <span class="text-xs font-bold text-gray-500 uppercase tracking-widest">/mo</span>
                            </div>

                            <ul class="mt-8 space-y-4">
                                <li class="flex items-center gap-3 text-emerald-400">
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400/20 text-emerald-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4"><path d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <span class="text-[10px] font-black uppercase tracking-widest">Unlimited Daily Scans & Forensics</span>
                                </li>
                                <li class="flex items-center gap-3 text-emerald-400">
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400/20 text-emerald-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4"><path d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <span class="text-[10px] font-black uppercase tracking-widest">Unlimited Daily Document Scans</span>
                                </li>
                                <li class="flex items-center gap-3 text-gray-400">
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400/10 text-emerald-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4"><path d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider">100 Surface PenTests / Month</span>
                                </li>
                                <li class="flex items-center gap-3 text-gray-400">
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400/10 text-emerald-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4"><path d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider">Unlimited Team Collaboration</span>
                                </li>
                                <li class="flex items-center gap-3 text-gray-400">
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400/10 text-emerald-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4"><path d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider">Sovereign Agency Badge</span>
                                </li>
                            </ul>

                            <button 
                                @click="openSubscriptionModal({ name: 'Agency Plan', price: '$59', badge: 'Sovereign', type: 'agency' })"
                                :disabled="props.subscription && (props.subscription.plan_type === 'agency' || props.subscription.status === 'active')"
                                class="mt-8 w-full rounded-2xl bg-gradient-to-r from-emerald-400 to-cyan-400 px-4 py-4 text-center text-[10px] font-black italic tracking-tighter uppercase text-slate-950 shadow-xl transition-all hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50"
                            >
                                {{ props.subscription?.plan_type === 'agency' ? 'Active Plan' : (props.subscription ? 'Manage Plan' : 'Upgrade to Agency') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 2. Subscription Status & Usage (Secondary) -->
                <div class="overflow-hidden bg-[#0A0A0B]/60 backdrop-blur-3xl rounded-[2rem] border border-white/5">
                    <div class="p-8">
                        <template v-if="props.subscription">
                            <div class="flex items-center justify-between mb-8">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-xs font-black italic tracking-tighter text-white uppercase mt-1">Subscription Status</h3>
                                </div>
                                <button 
                                    @click="showManageModal = true"
                                    class="text-[10px] font-black uppercase tracking-widest text-emerald-400 hover:text-white transition-colors"
                                >
                                    Manage Plan
                                </button>
                            </div>
                            
                            <!-- Active Plan Info -->
                            <div class="mb-8 p-6 rounded-3xl bg-white/[0.02] border border-white/5 relative overflow-hidden group">
                                <div class="absolute -right-20 -top-20 h-40 w-40 rounded-full bg-emerald-400/5 blur-[60px] group-hover:bg-emerald-400/10 transition-all duration-700"></div>
                                <div class="flex flex-wrap items-start justify-between gap-6 relative z-10">
                                    <!-- Plan Identity -->
                                    <div class="flex items-center gap-5">
                                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400/10 to-transparent border border-emerald-400/20 shadow-lg shadow-emerald-400/5">
                                            <svg class="h-7 w-7 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-3">
                                                <span class="text-2xl font-black italic tracking-tighter text-white uppercase mt-1">{{ props.subscription.plan_type }} Plan</span>
                                                <span class="inline-flex items-center rounded-full bg-emerald-400/10 px-2.5 py-0.5 text-[8px] font-black uppercase tracking-widest text-emerald-400 border border-emerald-400/20">
                                                    Active
                                                </span>
                                            </div>
                                            <div class="mt-1 text-[10px] font-bold text-gray-500 uppercase tracking-widest">
                                                Billing cycle: Monthly • 
                                                <span v-if="props.subscription.ends_at">Renews {{ formatDate(props.subscription.ends_at) }}</span>
                                                <span v-else>Indefinite</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Mini Wallet (Standardized) -->
                                    <div class="flex flex-col items-end gap-2 pr-2 border-l border-white/5 pl-6">
                                        <span class="text-[8px] font-black uppercase tracking-widest text-gray-500">Wallet Balance</span>
                                        <div class="text-xl font-black italic tracking-tighter text-white uppercase">{{ formattedBalance }}</div>
                                        <button class="text-[8px] font-black uppercase tracking-widest text-emerald-400 hover:text-white transition-colors">
                                            Withdraw Funds
                                        </button>
                                    </div>
                                </div>

                                <!-- Usage Tracking Progress Bars -->
                                <div class="mt-12 grid gap-8 md:grid-cols-3 relative z-10">
                                    <!-- Individual Scans -->
                                    <div>
                                        <div class="flex items-center justify-between mb-3">
                                            <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">Individual Scans</span>
                                            <span class="text-[9px] font-bold text-gray-400">
                                                {{ props.subscription.plan_type === 'agency' ? 'Unlimited' : `${props.subscription.daily_individual_scans_used || 0} / 20` }}
                                            </span>
                                        </div>
                                        <div class="h-1 w-full bg-white/5 rounded-full overflow-hidden">
                                            <div 
                                                class="h-full transition-all duration-500 shadow-[0_0_10px_rgba(52,211,153,0.3)]"
                                                :class="props.subscription.plan_type === 'agency' ? 'bg-gradient-to-r from-emerald-400 to-cyan-400' : 'bg-emerald-400'"
                                                :style="{ width: props.subscription.plan_type === 'agency' ? '100%' : `${Math.min(((props.subscription.daily_individual_scans_used || 0) / 20) * 100, 100)}%` }"
                                            ></div>
                                        </div>
                                    </div>

                                    <!-- Sync Scans -->
                                    <div>
                                        <div class="flex items-center justify-between mb-3">
                                            <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">Sync Scans</span>
                                            <span class="text-[9px] font-bold text-gray-400">
                                                {{ props.subscription.plan_type === 'agency' ? 'Unlimited' : `${props.subscription.daily_sync_scans_used || 0} / 10` }}
                                            </span>
                                        </div>
                                        <div class="h-1 w-full bg-white/5 rounded-full overflow-hidden">
                                            <div 
                                                class="h-full transition-all duration-500 shadow-[0_0_10px_rgba(34,211,238,0.3)]"
                                                :class="props.subscription.plan_type === 'agency' ? 'bg-gradient-to-r from-cyan-400 to-emerald-400' : 'bg-cyan-400'"
                                                :style="{ width: props.subscription.plan_type === 'agency' ? '100%' : `${Math.min(((props.subscription.daily_sync_scans_used || 0) / 10) * 100, 100)}%` }"
                                            ></div>
                                        </div>
                                    </div>

                                    <!-- Pentests -->
                                    <div>
                                        <div class="flex items-center justify-between mb-3">
                                            <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">PenTests & Scoring</span>
                                            <span class="text-[9px] font-bold text-gray-400">
                                                {{ props.subscription.monthly_pentests_used || 0 }} / {{ props.subscription.plan_type === 'agency' ? '100' : '4' }}
                                            </span>
                                        </div>
                                        <div class="h-1 w-full bg-white/5 rounded-full overflow-hidden">
                                            <div 
                                                class="h-full bg-emerald-500 transition-all duration-500 shadow-[0_0_10px_rgba(16,185,129,0.3)]"
                                                :style="{ width: `${Math.min(((props.subscription.monthly_pentests_used || 0) / (props.subscription.plan_type === 'agency' ? 100 : 4)) * 100, 100)}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- No Subscription State -->
                        <div v-else class="mb-8 rounded-[2rem] border border-dashed border-white/10 p-12 text-center bg-white/[0.02]">
                            <div class="flex flex-col items-center">
                                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/5 text-gray-500 mb-6 border border-white/5">
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                                    </svg>
                                </div>
                                <h4 class="text-xl font-black italic tracking-tighter text-white uppercase">No active subscription</h4>
                                <p class="mt-3 text-[10px] font-bold text-gray-500 uppercase tracking-widest max-w-sm">
                                    Unlock daily scan quotas and advanced forensic features by subscribing to a plan above.
                                </p>
                            </div>
                        </div>

                        <!-- History Tab Switcher -->
                        <div class="mt-12">
                            <div class="flex items-center gap-10 border-b border-white/5 mb-8">
                                <button 
                                    @click="activeHistoryTab = 'invoices'"
                                    :class="[
                                        'pb-4 text-[10px] font-black uppercase tracking-widest transition-all relative',
                                        activeHistoryTab === 'invoices' ? 'text-emerald-400' : 'text-gray-500 hover:text-gray-300'
                                    ]"
                                >
                                    Payment History
                                    <div v-if="activeHistoryTab === 'invoices'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-emerald-400 shadow-[0_0_10px_rgba(52,211,153,0.5)]"></div>
                                </button>
                                <button 
                                    @click="activeHistoryTab = 'usage'"
                                    :class="[
                                        'pb-4 text-[10px] font-black uppercase tracking-widest transition-all relative',
                                        activeHistoryTab === 'usage' ? 'text-emerald-400' : 'text-gray-500 hover:text-gray-300'
                                    ]"
                                >
                                    Usage Logs
                                    <div v-if="activeHistoryTab === 'usage'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-emerald-400 shadow-[0_0_10px_rgba(52,211,153,0.5)]"></div>
                                </button>
                            </div>

                            <!-- Invoices Table -->
                            <div v-if="activeHistoryTab === 'invoices'" class="overflow-x-auto custom-scrollbar pb-4">
                                <table class="min-w-full border-separate border-spacing-y-2">
                                    <thead>
                                        <tr>
                                            <th class="px-6 py-4 text-left text-[9px] font-black text-gray-500 uppercase tracking-widest">Invoice ID</th>
                                            <th class="px-6 py-4 text-left text-[9px] font-black text-gray-500 uppercase tracking-widest">Date</th>
                                            <th class="px-6 py-4 text-left text-[9px] font-black text-gray-500 uppercase tracking-widest">Plan</th>
                                            <th class="px-6 py-4 text-left text-[9px] font-black text-gray-500 uppercase tracking-widest">Amount</th>
                                            <th class="px-6 py-4 text-left text-[9px] font-black text-gray-500 uppercase tracking-widest">Status</th>
                                            <th class="px-6 py-4"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="invoice in props.invoices" :key="invoice.id" class="bg-white/[0.02] hover:bg-white/[0.04] transition-colors rounded-xl">
                                            <td class="px-6 py-5 whitespace-nowrap text-[10px] font-bold text-white rounded-l-xl">{{ invoice.id }}</td>
                                            <td class="px-6 py-5 whitespace-nowrap text-[10px] font-bold text-gray-400">{{ formatDate(invoice.date) }}</td>
                                            <td class="px-6 py-5 whitespace-nowrap text-[10px] font-black uppercase tracking-tighter text-emerald-400/80">{{ invoice.plan }}</td>
                                            <td class="px-6 py-5 whitespace-nowrap text-[10px] font-black text-white">${{ Number(invoice.amount).toFixed(2) }}</td>
                                            <td class="px-6 py-5 whitespace-nowrap">
                                                <span class="inline-flex items-center rounded-lg bg-emerald-400/10 px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-emerald-400 border border-emerald-400/20">{{ invoice.status }}</span>
                                            </td>
                                            <td class="px-6 py-5 whitespace-nowrap text-right rounded-r-xl">
                                                <a :href="route('billing.invoice.show', invoice.id)" target="_blank" class="text-[10px] font-black uppercase tracking-widest text-emerald-400 hover:text-white transition-colors">PDF</a>
                                            </td>
                                        </tr>
                                        <tr v-if="props.invoices.length === 0">
                                            <td colspan="6" class="px-6 py-12 text-center text-[10px] font-bold text-gray-500 uppercase tracking-widest italic">No invoices found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Usage Logs Table -->
                            <div v-else>
                                <!-- Search Bar -->
                                <div class="mb-6 relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input 
                                        v-model="searchQuery"
                                        type="text" 
                                        placeholder="SEARCH BY TARGET OR USER..." 
                                        class="block w-full pl-12 pr-4 py-3 bg-white/5 border border-white/10 rounded-2xl text-[10px] font-bold tracking-widest text-white placeholder-gray-600 focus:ring-emerald-400/30 focus:border-emerald-400/50 transition-all uppercase"
                                        @input="usagePage = 1"
                                    >
                                </div>

                                <div class="overflow-x-auto custom-scrollbar pb-4">
                                    <table class="min-w-full border-separate border-spacing-y-2">
                                        <thead>
                                            <tr>
                                                <th class="px-6 py-4 text-left text-[9px] font-black text-gray-500 uppercase tracking-widest">User</th>
                                                <th class="px-6 py-4 text-left text-[9px] font-black text-gray-500 uppercase tracking-widest">Action</th>
                                                <th class="px-6 py-4 text-left text-[9px] font-black text-gray-500 uppercase tracking-widest">Target</th>
                                                <th class="px-6 py-4 text-left text-[9px] font-black text-gray-500 uppercase tracking-widest">Date</th>
                                                <th class="px-6 py-4 text-left text-[9px] font-black text-gray-500 uppercase tracking-widest">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="log in paginatedUsageLogs" :key="log.id" class="bg-white/[0.02] hover:bg-white/[0.04] transition-colors rounded-xl">
                                                <td class="px-6 py-5 whitespace-nowrap rounded-l-xl">
                                                    <div class="flex items-center gap-3">
                                                        <div class="h-8 w-8 rounded-xl bg-gradient-to-br from-emerald-400/20 to-cyan-400/20 flex items-center justify-center text-[10px] font-black text-emerald-400 border border-emerald-400/20 shadow-lg shadow-emerald-400/5">{{ log.user.charAt(0).toUpperCase() }}</div>
                                                        <span class="text-[10px] font-bold text-white uppercase">{{ log.user }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-5 whitespace-nowrap text-[10px] font-black uppercase text-gray-400">
                                                    {{ log.type }} SCAN
                                                </td>
                                                <td class="px-6 py-5 whitespace-nowrap text-[10px] font-medium text-gray-300">{{ log.display_name }}</td>
                                                <td class="px-6 py-5 whitespace-nowrap text-[9px] font-bold text-gray-500">{{ log.date }}</td>
                                                <td class="px-6 py-5 whitespace-nowrap rounded-r-xl">
                                                    <span class="px-2.5 py-1 rounded-lg bg-white/5 border border-white/5 text-[8px] font-black uppercase tracking-widest text-gray-400">{{ log.status }}</span>
                                                </td>
                                            </tr>
                                            <tr v-if="paginatedUsageLogs.length === 0">
                                                <td colspan="5" class="px-6 py-12 text-center text-[10px] font-bold text-gray-500 uppercase tracking-widest italic">No usage records found.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <div v-if="totalUsagePages > 1" class="mt-8 flex items-center justify-between">
                                    <div class="text-[9px] font-bold text-gray-500 uppercase tracking-widest">
                                        Showing {{ ((usagePage - 1) * itemsPerPage) + 1 }} to {{ Math.min(usagePage * itemsPerPage, filteredUsageLogs.length) }} of {{ filteredUsageLogs.length }}
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button 
                                            @click="usagePage--" 
                                            :disabled="usagePage === 1"
                                            class="px-5 py-2 text-[9px] font-black uppercase tracking-widest rounded-xl bg-white/5 border border-white/5 text-gray-400 hover:text-white hover:bg-white/10 disabled:opacity-20 transition-all active:scale-95"
                                        >
                                            Prev
                                        </button>
                                        <span class="text-[10px] font-black text-gray-400">{{ usagePage }} / {{ totalUsagePages }}</span>
                                        <button 
                                            @click="usagePage++" 
                                            :disabled="usagePage === totalUsagePages"
                                            class="px-5 py-2 text-[9px] font-black uppercase tracking-widest rounded-xl bg-white/5 border border-white/5 text-gray-400 hover:text-white hover:bg-white/10 disabled:opacity-20 transition-all active:scale-95"
                                        >
                                            Next
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modals -->
                <CreditPurchaseModal
                    :show="showPurchaseModal"
                    @close="showPurchaseModal = false"
                />

                <SubscriptionPaymentModal
                    :show="showSubscriptionModal"
                    :plan="selectedPlan"
                    @close="showSubscriptionModal = false"
                />

                <ManageSubscriptionModal
                    :show="showManageModal"
                    :subscription="props.subscription"
                    @close="showManageModal = false"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
