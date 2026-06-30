<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

// ─── Props from IntegrationsController ────────────────────────────────────────

interface ConnectedIntegration {
    id: string;
    name: string;
    status: string;
    desc: string;
    addedBy: string;
    addedDate: string;
    lastSync: string;
    icon: string;
}

interface AvailableIntegration {
    id: string;
    name: string;
    desc: string;
    icon: string;
}

interface HealthStatus {
    name: string;
    status: string;
    color: string;
}

interface ActivityLog {
    title: string;
    desc: string;
    time: string;
    error: boolean;
}

interface Stats {
    connected: number;
    available: number;
    systemHealth: string;
    allHealthy: boolean;
    eventsSynced: number;
}

const props = defineProps<{
    connectedIntegrations: ConnectedIntegration[];
    availableIntegrations: AvailableIntegration[];
    healthStatuses: HealthStatus[];
    activityLog: ActivityLog[];
    stats: Stats;
}>();

// ─── Local UI State ───────────────────────────────────────────────────────────

const activeTab = ref('All'); // All, Connected, Available
const searchQuery = ref('');
const showAddModal = ref(false);
const togglingPlatform = ref<string | null>(null);

// ─── Computed: Filtered lists ─────────────────────────────────────────────────

const filteredConnected = computed(() => {
    if (activeTab.value === 'Available') return [];
    let list = props.connectedIntegrations;
    if (searchQuery.value) {
        list = list.filter(i => i.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
    }
    return list;
});

const filteredAvailable = computed(() => {
    if (activeTab.value === 'Connected') return [];
    let list = props.availableIntegrations;
    if (searchQuery.value) {
        list = list.filter(i => i.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
    }
    return list;
});

// ─── Actions ──────────────────────────────────────────────────────────────────

/**
 * Sends a POST request to toggle the connection state of a platform.
 * Uses Inertia router to preserve state without a full page reload.
 */
function toggleIntegration(platform: string): void {
    togglingPlatform.value = platform;
    router.post(
        route('integrations.toggle', { platform }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                togglingPlatform.value = null;
                showAddModal.value = false;
            },
        }
    );
}
</script>

<template>
    <Head title="Integrations" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col">
                <h2 class="text-xl font-bold tracking-tight text-white/90">Integrations</h2>
                <span class="text-xs text-gray-500 mt-1 font-medium">Connect LUME with your favorite tools and platforms</span>
            </div>
        </template>

        <div class="space-y-8 pb-16">
            <!-- Controls Toolbar -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-white/5 pb-4">
                <!-- Tab Switcher -->
                <div class="flex items-center gap-6">
                    <button
                        v-for="tab in ['All', 'Connected', 'Available']"
                        :key="tab"
                        @click="activeTab = tab"
                        class="pb-2 text-xs font-bold uppercase tracking-wider relative transition-colors"
                        :class="activeTab === tab ? 'text-[#CBB48A]' : 'text-slate-500 hover:text-slate-300'"
                    >
                        {{ tab === 'All' ? 'All Integrations' : tab }}
                        <span v-if="activeTab === tab" class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#CBB48A]"></span>
                    </button>
                </div>

                <!-- Right Side Actions -->
                <div class="flex items-center gap-3">
                    <div class="relative w-64">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="block w-full rounded-xl border-white/5 bg-[#050811] pl-9 pr-4 py-2 text-xs text-white placeholder-slate-500 focus:border-[#CBB48A]/50 focus:ring-0 focus:outline-none transition-all"
                            placeholder="Search integrations..."
                        >
                    </div>

                    <button
                        @click="showAddModal = true"
                        class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 text-xs font-bold text-black hover:from-amber-400 hover:to-amber-500 shadow shadow-amber-500/10 active:scale-95 transition-all"
                    >
                        <span>+ Add Integration</span>
                    </button>
                </div>
            </div>

            <!-- Main Layout: 2 Columns (Content and Sidebar) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

                <!-- Left Side: Integrations List -->
                <div class="lg:col-span-9 space-y-8">
                    <!-- Stat Metric counters driven by DB props -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between">
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Connected</span>
                                <span class="text-2xl font-bold font-mono text-white">{{ stats.connected }}</span>
                                <span class="text-[9px] text-emerald-400 font-bold block">&uarr; active integrations</span>
                            </div>
                            <div class="h-10 w-10 rounded-lg bg-cyan-500/10 flex items-center justify-center text-cyan-400">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101" /></svg>
                            </div>
                        </div>

                        <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between">
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Available</span>
                                <span class="text-2xl font-bold font-mono text-white">{{ stats.available }}</span>
                                <span class="text-[9px] text-slate-500 font-medium block">View all integrations</span>
                            </div>
                            <div class="h-10 w-10 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                            </div>
                        </div>

                        <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between">
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">System Health</span>
                                <span class="text-2xl font-bold font-mono" :class="stats.allHealthy ? 'text-emerald-400' : 'text-amber-400'">{{ stats.systemHealth }}</span>
                                <span class="text-[9px] font-medium block" :class="stats.allHealthy ? 'text-emerald-400/90' : 'text-amber-400/90'">
                                    {{ stats.allHealthy ? 'All integrations operational' : 'Some integrations need attention' }}
                                </span>
                            </div>
                            <div class="h-10 w-10 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9" /></svg>
                            </div>
                        </div>

                        <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between">
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Events Synced</span>
                                <span class="text-2xl font-bold font-mono text-white">{{ stats.eventsSynced }}</span>
                                <span class="text-[9px] text-slate-500 font-medium block">Last 7 days</span>
                            </div>
                            <div class="h-10 w-10 rounded-lg bg-violet-500/10 flex items-center justify-center text-violet-400">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2" /></svg>
                            </div>
                        </div>
                    </div>

                    <!-- Connected Integrations -->
                    <div v-if="filteredConnected.length > 0" class="space-y-4">
                        <div class="flex items-center justify-between border-b border-white/5 pb-2">
                            <h2 class="text-xs font-bold tracking-widest text-slate-500 uppercase">Connected Integrations</h2>
                            <a href="#" class="text-xs font-bold text-[#CBB48A] hover:underline">View all</a>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                            <div
                                v-for="item in filteredConnected"
                                :key="item.id"
                                class="bg-[#050811]/60 border border-white/5 hover:border-slate-800 rounded-2xl p-5 flex flex-col justify-between h-56 hover:bg-[#070b17] transition-all group relative"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="h-11 w-11 rounded-xl bg-slate-900 border border-white/5 flex items-center justify-center text-white font-black">
                                        <!-- Platform icon initials -->
                                        <span v-if="item.id === 'github'" class="text-xl">G</span>
                                        <span v-else-if="item.id === 'gitlab'" class="text-xl text-orange-500">G</span>
                                        <span v-else-if="item.id === 'slack'" class="text-xl text-cyan-400">S</span>
                                        <span v-else-if="item.id === 'jira'" class="text-xl text-blue-500">J</span>
                                        <span v-else-if="item.id === 'google'" class="text-xl text-rose-400">G</span>
                                        <span v-else class="text-xl">{{ item.name.charAt(0) }}</span>
                                    </div>
                                    <!-- Disconnect button -->
                                    <button
                                        @click="toggleIntegration(item.id)"
                                        :disabled="togglingPlatform === item.id"
                                        class="text-slate-500 hover:text-rose-400 transition-colors disabled:opacity-50"
                                        title="Disconnect"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    </button>
                                </div>

                                <div class="text-left my-2 space-y-1">
                                    <h4 class="text-sm font-bold text-white">{{ item.name }}</h4>
                                    <div class="flex items-center gap-1">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        <span class="text-[9px] font-bold text-emerald-400 uppercase tracking-wide">Connected</span>
                                    </div>
                                    <p class="text-[11px] text-slate-400 line-clamp-2 leading-relaxed">{{ item.desc }}</p>
                                </div>

                                <div class="border-t border-white/5 pt-3 flex items-center justify-between text-[10px] font-mono text-slate-500">
                                    <div class="flex items-center gap-1.5">
                                        <div class="h-5 w-5 rounded-md bg-indigo-600 font-bold text-white text-[8px] flex items-center justify-center">
                                            {{ (item.addedBy ?? 'U').substring(0, 2).toUpperCase() }}
                                        </div>
                                        <span>Added by {{ item.addedBy }}</span>
                                    </div>
                                    <span>Last sync: {{ item.lastSync }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state for connected -->
                    <div v-else-if="activeTab !== 'Available'" class="flex flex-col items-center justify-center py-16 border border-dashed border-white/5 rounded-2xl text-center">
                        <svg class="w-10 h-10 text-slate-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101" /></svg>
                        <p class="text-sm font-bold text-slate-500">No connected integrations yet</p>
                        <p class="text-xs text-slate-600 mt-1">Connect an integration from the available list below</p>
                    </div>

                    <!-- Available Integrations -->
                    <div v-if="filteredAvailable.length > 0" class="space-y-4">
                        <div class="flex items-center justify-between border-b border-white/5 pb-2">
                            <h2 class="text-xs font-bold tracking-widest text-slate-500 uppercase">Available Integrations</h2>
                            <a href="#" class="text-xs font-bold text-[#CBB48A] hover:underline">View all</a>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                            <div
                                v-for="item in filteredAvailable"
                                :key="item.id"
                                class="bg-[#050811]/40 border border-white/5 hover:border-slate-800 rounded-2xl p-5 flex flex-col justify-between h-56 hover:bg-[#070b17] transition-all group"
                            >
                                <div class="h-11 w-11 rounded-xl bg-slate-900 border border-white/5 flex items-center justify-center text-slate-400 font-black group-hover:scale-105 transition-transform duration-300">
                                    <span v-if="item.id === 'azure'" class="text-xl text-blue-500">A</span>
                                    <span v-else-if="item.id === 'bitbucket'" class="text-xl text-blue-400">B</span>
                                    <span v-else-if="item.id === 'discord'" class="text-xl text-indigo-400">D</span>
                                    <span v-else-if="item.id === 'pagerduty'" class="text-xl text-green-500">P</span>
                                    <span v-else-if="item.id === 'snyk'" class="text-xl text-purple-400">S</span>
                                    <span v-else>{{ item.name.charAt(0) }}</span>
                                </div>

                                <div class="text-left my-2 space-y-1">
                                    <h4 class="text-sm font-bold text-white">{{ item.name }}</h4>
                                    <p class="text-[11px] text-slate-400 line-clamp-2 leading-relaxed">{{ item.desc }}</p>
                                </div>

                                <button
                                    @click="toggleIntegration(item.id)"
                                    :disabled="togglingPlatform === item.id"
                                    class="w-full py-2.5 rounded-xl border border-white/5 bg-slate-950/60 hover:bg-white/5 text-slate-300 text-xs font-bold tracking-wider uppercase transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {{ togglingPlatform === item.id ? 'Connecting…' : 'Connect' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Health & Activity Sidebar -->
                <div class="lg:col-span-3 space-y-6">
                    <!-- Integration Health (DB-driven) -->
                    <div class="bg-[#050811]/60 border border-white/5 rounded-2xl p-5 text-left space-y-4">
                        <div class="flex items-center justify-between border-b border-white/5 pb-2">
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Integration Health</h3>
                            <a href="#" class="text-[10px] font-bold text-[#CBB48A] hover:underline">View all</a>
                        </div>
                        <div class="space-y-3">
                            <!-- Real health statuses from DB -->
                            <div
                                v-for="h in healthStatuses"
                                :key="h.name"
                                class="flex items-center justify-between text-xs font-semibold"
                            >
                                <span class="text-slate-300">{{ h.name }}</span>
                                <span :class="h.color">{{ h.status }}</span>
                            </div>
                            <!-- Fallback if no integrations exist -->
                            <p v-if="healthStatuses.length === 0" class="text-xs text-slate-600 text-center py-2">No integrations to monitor</p>
                        </div>
                        <button class="w-full py-2 rounded-xl bg-slate-900 border border-white/5 hover:bg-slate-800 text-[10px] font-bold uppercase tracking-wider text-[#CBB48A] transition-all">
                            View Detailed Health
                        </button>
                    </div>

                    <!-- Activity Log (DB-driven) -->
                    <div class="bg-[#050811]/60 border border-white/5 rounded-2xl p-5 text-left space-y-4">
                        <div class="flex items-center justify-between border-b border-white/5 pb-2">
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Activity Log</h3>
                            <a href="#" class="text-[10px] font-bold text-[#CBB48A] hover:underline">View all</a>
                        </div>
                        <div class="space-y-4">
                            <!-- Real logs from DB -->
                            <div
                                v-for="act in activityLog"
                                :key="act.desc"
                                class="flex gap-2.5 text-xs"
                            >
                                <div class="h-5 w-5 rounded bg-slate-900 flex items-center justify-center shrink-0 border border-white/5">
                                    <span v-if="act.error" class="text-rose-500 font-bold">!</span>
                                    <span v-else class="text-emerald-400">&bull;</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <span class="font-bold text-white truncate block">{{ act.title }}</span>
                                    <span class="text-[10px] text-slate-400 leading-normal block">{{ act.desc }}</span>
                                </div>
                                <span class="text-[9px] text-slate-500 whitespace-nowrap shrink-0">{{ act.time }}</span>
                            </div>
                            <!-- Empty state -->
                            <p v-if="activityLog.length === 0" class="text-xs text-slate-600 text-center py-2">No recent activity</p>
                        </div>
                        <button class="w-full py-2 rounded-xl bg-slate-900 border border-white/5 hover:bg-slate-800 text-[10px] font-bold uppercase tracking-wider text-[#CBB48A] transition-all">
                            View Full Activity Log
                        </button>
                    </div>
                </div>

            </div>

            <!-- Footer banner -->
            <div class="bg-gradient-to-r from-[#111625] via-[#0b101d] to-[#050811] rounded-2xl border border-white/5 p-6 flex flex-col lg:flex-row items-center justify-between gap-6 shadow-2xl relative overflow-hidden">
                <div class="absolute -right-20 -bottom-20 h-64 w-64 rounded-full bg-[#CBB48A]/5 blur-3xl pointer-events-none"></div>

                <div class="flex items-start gap-5 max-w-2xl text-left">
                    <div class="p-3 bg-amber-500/10 border border-amber-500/20 text-[#CBB48A] rounded-xl flex-shrink-0">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Build powerful workflows with integrations</h3>
                        <p class="mt-1 text-xs text-slate-400 leading-relaxed">
                            Automate your security processes, get real-time alerts, and keep your team in sync with the tools you use every day.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap lg:flex-nowrap items-center gap-6 text-left shrink-0">
                    <div>
                        <span class="text-[10px] font-bold text-white block">Real-time Sync</span>
                        <span class="text-[8.5px] text-slate-500 block">Keep data updated</span>
                    </div>
                    <div class="h-6 w-px bg-white/5 hidden lg:block"></div>
                    <div>
                        <span class="text-[10px] font-bold text-white block">Automated Alerts</span>
                        <span class="text-[8.5px] text-slate-500 block">Instant notifications</span>
                    </div>
                    <div class="h-6 w-px bg-white/5 hidden lg:block"></div>
                    <div>
                        <span class="text-[10px] font-bold text-white block">Seamless Workflows</span>
                        <span class="text-[8.5px] text-slate-500 block">Connect tools directly</span>
                    </div>
                    <button class="px-5 py-2.5 rounded-xl border border-slate-700 bg-slate-900 text-xs font-bold text-[#CBB48A] hover:bg-slate-800 transition-all">
                        Explore Workflows
                    </button>
                </div>
            </div>

            <!-- Footer identity -->
            <div class="text-center pt-8 border-t border-white/5">
                <p class="text-[10px] text-slate-600 tracking-wider font-mono">EisenDev|Arjay @ 2026</p>
            </div>
        </div>

        <!-- Add Integration Modal (wired to real available list) -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#020408]/90 backdrop-blur-sm" @click="showAddModal = false"></div>
            <div class="relative w-full max-w-md bg-[#050811] border border-slate-800 rounded-2xl shadow-2xl p-6 text-slate-300 text-left">
                <h2 class="text-lg font-bold text-white mb-2">Connect New Integration</h2>
                <p class="text-xs text-slate-400 mb-4">Choose an integration to link with LumeCore.</p>
                <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
                    <button
                        v-for="p in availableIntegrations"
                        :key="p.id"
                        @click="toggleIntegration(p.id)"
                        :disabled="togglingPlatform === p.id"
                        class="w-full p-3 hover:bg-white/5 rounded-xl border border-white/5 text-xs text-white font-bold text-left transition-all flex items-center justify-between disabled:opacity-50"
                    >
                        <span>{{ p.name }}</span>
                        <span v-if="togglingPlatform === p.id" class="text-[#CBB48A]">Connecting…</span>
                    </button>
                    <p v-if="availableIntegrations.length === 0" class="text-xs text-slate-600 text-center py-4">
                        All available integrations are already connected!
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
