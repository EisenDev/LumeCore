<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

// Define props from Inertia
const props = defineProps<{
    reports: Array<{
        id: string | number;
        name: string;
        target: string;
        type: string;
        generatedBy: string;
        created: string;
        status: string;
        asset_id?: string | number | null;
    }>;
    stats: {
        total: number;
        generated: number;
        shared: number;
        scheduled: number;
        downloads: number;
    };
    breakdown: {
        executive: number;
        technical: number;
        compliance: number;
        client: number;
    };
    recentActivities: Array<{
        author: string;
        action: string;
        target: string;
        time: string;
    }>;
}>();

// State
const searchQuery = ref('');
const selectedTargetFilter = ref('All Targets');
const selectedTypeFilter = ref('All Types');
const selectedStatusFilter = ref('All Status');
const activeDropdown = ref<string | null>(null);

const reports = ref(props.reports || []);

// Unique targets for the filter dropdown
const uniqueTargets = computed(() => {
    const targets = reports.value.map(r => r.target);
    return [...new Set(targets)];
});

// Filters
const filteredReports = computed(() => {
    return reports.value.filter(r => {
        // Search Filter
        if (searchQuery.value && !r.name.toLowerCase().includes(searchQuery.value.toLowerCase()) && !r.target.toLowerCase().includes(searchQuery.value.toLowerCase())) return false;
        
        // Target Filter
        if (selectedTargetFilter.value !== 'All Targets' && r.target !== selectedTargetFilter.value) return false;

        // Type Filter
        if (selectedTypeFilter.value !== 'All Types' && r.type !== selectedTypeFilter.value) return false;

        // Status Filter
        if (selectedStatusFilter.value !== 'All Status' && r.status !== selectedStatusFilter.value) return false;

        return true;
    });
});

const toggleDropdown = (name: string) => {
    if (activeDropdown.value === name) activeDropdown.value = null;
    else activeDropdown.value = name;
};

// Help helpers for badge styles
function getTypeBadgeClass(type: string) {
    const t = type.toLowerCase();
    if (t === 'executive') return 'bg-purple-500/10 text-purple-400 border border-purple-500/20';
    if (t === 'technical') return 'bg-blue-500/10 text-blue-400 border border-blue-500/20';
    if (t === 'compliance') return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
    return 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
}

function getStatusColorClass(status: string) {
    const s = status.toLowerCase();
    if (s === 'completed') return 'bg-emerald-500';
    if (s === 'shared') return 'bg-blue-500';
    return 'bg-amber-500';
}

function getStatusTextColorClass(status: string) {
    const s = status.toLowerCase();
    if (s === 'completed') return 'text-emerald-400';
    if (s === 'shared') return 'text-blue-400';
    return 'text-amber-400';
}

// Doughnut calculations
const totalCount = computed(() => {
    return props.breakdown.executive + props.breakdown.technical + props.breakdown.compliance + props.breakdown.client || 1;
});

const execDash = computed(() => {
    const pct = props.breakdown.executive / totalCount.value;
    const len = pct * 251.2;
    return `${len} 251.2`;
});
const execOffset = computed(() => 0);

const techDash = computed(() => {
    const pct = props.breakdown.technical / totalCount.value;
    const len = pct * 251.2;
    return `${len} 251.2`;
});
const techOffset = computed(() => {
    const prevPct = props.breakdown.executive / totalCount.value;
    return -prevPct * 251.2;
});

const compDash = computed(() => {
    const pct = props.breakdown.compliance / totalCount.value;
    const len = pct * 251.2;
    return `${len} 251.2`;
});
const compOffset = computed(() => {
    const prevPct = (props.breakdown.executive + props.breakdown.technical) / totalCount.value;
    return -prevPct * 251.2;
});

const clientDash = computed(() => {
    const pct = props.breakdown.client / totalCount.value;
    const len = pct * 251.2;
    return `${len} 251.2`;
});
const clientOffset = computed(() => {
    const prevPct = (props.breakdown.executive + props.breakdown.technical + props.breakdown.compliance) / totalCount.value;
    return -prevPct * 251.2;
});
</script>

<template>
    <Head title="Reports" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col">
                <h2 class="text-xl font-bold tracking-tight text-white/90">Reports</h2>
                <span class="text-xs text-gray-500 mt-1 font-medium">Generate, manage, and share security reports with your team and clients</span>
            </div>
        </template>

        <div class="space-y-8 pb-16">
            <!-- Controls Toolbar -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-end border-b border-white/5 pb-4">
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
                            placeholder="Search reports..."
                        >
                    </div>

                    <button 
                        class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 text-xs font-bold text-black hover:from-amber-400 hover:to-amber-500 shadow shadow-amber-500/10 active:scale-95 transition-all"
                    >
                        <span>+ Generate Report</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                </div>
            </div>

            <!-- Stats Metric Row -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between">
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Total Reports</span>
                        <span class="text-2xl font-bold font-mono text-white">{{ props.stats.total }}</span>
                        <span class="text-[9px] text-emerald-400 font-bold block">&uarr; 18% last 30d</span>
                    </div>
                </div>
                <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between">
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Generated</span>
                        <span class="text-2xl font-bold font-mono text-white">{{ props.stats.generated }}</span>
                        <span class="text-[9px] text-emerald-400 font-bold block">&uarr; 22% last 30d</span>
                    </div>
                </div>
                <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between">
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Shared</span>
                        <span class="text-2xl font-bold font-mono text-white">{{ props.stats.shared }}</span>
                        <span class="text-[9px] text-emerald-400 font-bold block">&uarr; 15% last 30d</span>
                    </div>
                </div>
                <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between">
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Scheduled</span>
                        <span class="text-2xl font-bold font-mono text-white">{{ props.stats.scheduled }}</span>
                        <span class="text-[9px] text-emerald-400 font-bold block">&uarr; 33% last 30d</span>
                    </div>
                </div>
                <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between">
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Downloads</span>
                        <span class="text-2xl font-bold font-mono text-white">{{ props.stats.downloads }}</span>
                        <span class="text-[9px] text-emerald-400 font-bold block">&uarr; 19% last 30d</span>
                    </div>
                </div>
            </div>

            <!-- Main Layout Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left Side Columns -->
                <div class="lg:col-span-9 space-y-8">
                    <!-- Create a new report Section -->
                    <div class="border border-white/5 bg-[#050811]/30 rounded-2xl p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-white/5 pb-2 text-left">
                            <div>
                                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Create a new report</h3>
                                <p class="text-[11px] text-slate-400">Choose a report type to get started.</p>
                            </div>
                            <button class="text-[10px] font-bold text-[#CBB48A] hover:underline font-mono">Custom Template</button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-5 gap-4">
                            <!-- Template Card 1 -->
                            <div class="bg-[#050811] border border-white/5 rounded-xl p-4 flex flex-col justify-between h-44 text-left">
                                <div class="space-y-1">
                                    <div class="h-8 w-8 rounded-lg bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center">
                                        <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    </div>
                                    <h4 class="text-xs font-bold text-white pt-1">Executive Summary</h4>
                                    <p class="text-[9.5px] text-slate-400 leading-normal">High level overview for decision makers.</p>
                                </div>
                                <button class="w-full py-1.5 rounded-lg border border-white/5 bg-slate-900 hover:bg-white/5 text-[10px] font-bold text-[#CBB48A] uppercase tracking-wider transition-all">Generate</button>
                            </div>

                            <!-- Template Card 2 -->
                            <div class="bg-[#050811] border border-white/5 rounded-xl p-4 flex flex-col justify-between h-44 text-left">
                                <div class="space-y-1">
                                    <div class="h-8 w-8 rounded-lg bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center">
                                        <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>
                                    </div>
                                    <h4 class="text-xs font-bold text-white pt-1">Technical Report</h4>
                                    <p class="text-[9.5px] text-slate-400 leading-normal">Detailed technical findings and evidence.</p>
                                </div>
                                <button class="w-full py-1.5 rounded-lg border border-white/5 bg-slate-900 hover:bg-white/5 text-[10px] font-bold text-[#CBB48A] uppercase tracking-wider transition-all">Generate</button>
                            </div>

                            <!-- Template Card 3 -->
                            <div class="bg-[#050811] border border-white/5 rounded-xl p-4 flex flex-col justify-between h-44 text-left">
                                <div class="space-y-1">
                                    <div class="h-8 w-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center">
                                        <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0" /></svg>
                                    </div>
                                    <h4 class="text-xs font-bold text-white pt-1">Compliance Report</h4>
                                    <p class="text-[9.5px] text-slate-400 leading-normal">Map findings to compliance frameworks.</p>
                                </div>
                                <button class="w-full py-1.5 rounded-lg border border-white/5 bg-slate-900 hover:bg-white/5 text-[10px] font-bold text-[#CBB48A] uppercase tracking-wider transition-all">Generate</button>
                            </div>

                            <!-- Template Card 4 -->
                            <div class="bg-[#050811] border border-white/5 rounded-xl p-4 flex flex-col justify-between h-44 text-left">
                                <div class="space-y-1">
                                    <div class="h-8 w-8 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center">
                                        <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                    </div>
                                    <h4 class="text-xs font-bold text-white pt-1">Client Report</h4>
                                    <p class="text-[9.5px] text-slate-400 leading-normal">Clean summary for clients and stakeholders.</p>
                                </div>
                                <button class="w-full py-1.5 rounded-lg border border-white/5 bg-slate-900 hover:bg-white/5 text-[10px] font-bold text-[#CBB48A] uppercase tracking-wider transition-all">Generate</button>
                            </div>

                            <!-- Template Card 5 (Custom) -->
                            <div class="bg-[#050811] border border-white/5 rounded-xl p-4 flex flex-col justify-between h-44 text-left">
                                <div class="space-y-1">
                                    <div class="h-8 w-8 rounded-lg bg-white/5 border border-white/10 text-white flex items-center justify-center">
                                        <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
                                    </div>
                                    <h4 class="text-xs font-bold text-white pt-1">Custom Report</h4>
                                    <p class="text-[9.5px] text-slate-400 leading-normal">Build a report with custom sections.</p>
                                </div>
                                <button class="w-full py-1.5 rounded-lg border border-[#CBB48A]/50 bg-[#CBB48A]/5 hover:bg-[#CBB48A]/10 text-[10px] font-bold text-[#CBB48A] uppercase tracking-wider transition-all">Create</button>
                            </div>
                        </div>
                    </div>

                    <!-- Reports List Container -->
                    <div class="border border-white/5 bg-[#050811]/40 rounded-2xl overflow-hidden p-4 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="relative w-48">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input 
                                        v-model="searchQuery"
                                        type="text" 
                                        class="block w-full rounded-lg border-white/5 bg-[#080c17] pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:border-[#CBB48A]/50 focus:ring-0 focus:outline-none transition-all" 
                                        placeholder="Search reports..."
                                    >
                                </div>

                                <select 
                                    v-model="selectedTargetFilter"
                                    class="block rounded-lg border border-white/5 bg-[#080c17] text-xs font-semibold text-slate-300 px-3 py-1.5 focus:border-[#CBB48A]/50 focus:ring-0 focus:outline-none transition-all cursor-pointer"
                                >
                                    <option value="All Targets">All Targets</option>
                                    <option v-for="t in uniqueTargets" :key="t" :value="t">{{ t }}</option>
                                </select>

                                <select 
                                    v-model="selectedTypeFilter"
                                    class="block rounded-lg border border-white/5 bg-[#080c17] text-xs font-semibold text-slate-300 px-3 py-1.5 focus:border-[#CBB48A]/50 focus:ring-0 focus:outline-none transition-all cursor-pointer"
                                >
                                    <option value="All Types">All Types</option>
                                    <option value="Executive">Executive</option>
                                    <option value="Technical">Technical</option>
                                    <option value="Compliance">Compliance</option>
                                    <option value="Client">Client</option>
                                </select>

                                <select 
                                    v-model="selectedStatusFilter"
                                    class="block rounded-lg border border-white/5 bg-[#080c17] text-xs font-semibold text-slate-300 px-3 py-1.5 focus:border-[#CBB48A]/50 focus:ring-0 focus:outline-none transition-all cursor-pointer"
                                >
                                    <option value="All Status">All Status</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Shared">Shared</option>
                                    <option value="Scheduled">Scheduled</option>
                                </select>
                            </div>

                            <button class="flex items-center gap-1 px-3 py-1.5 rounded-lg border border-white/5 bg-[#080c17] text-xs font-semibold text-slate-300 hover:text-white">
                                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 8.293A1 1 0 013 7.586V4z" /></svg>
                                <span>Filters</span>
                            </button>
                        </div>

                        <!-- Table list of reports -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-white/5 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                                        <th class="py-3 px-4">Report Name</th>
                                        <th class="py-3 px-4">Target</th>
                                        <th class="py-3 px-4">Type</th>
                                        <th class="py-3 px-4">Generated By</th>
                                        <th class="py-3 px-4">Created</th>
                                        <th class="py-3 px-4">Status</th>
                                        <th class="py-3 px-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr 
                                        v-for="r in filteredReports" 
                                        :key="r.id"
                                        class="border-b border-white/[0.02] hover:bg-white/[0.01] transition-all"
                                    >
                                        <td class="py-4 px-4 font-bold text-white flex items-center gap-3">
                                            <div class="h-6 w-6 rounded bg-slate-900 border border-white/5 flex items-center justify-center text-slate-400">
                                                <svg class="w-3.5 h-3.5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414" /></svg>
                                            </div>
                                            <div>
                                                <span class="block">{{ r.name }}</span>
                                                <span class="text-[9px] text-slate-500 font-sans tracking-tight font-medium">{{ r.type }} Report</span>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4 font-semibold text-slate-300">{{ r.target }}</td>
                                        <td class="py-4 px-4">
                                            <span 
                                                class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider font-mono"
                                                :class="getTypeBadgeClass(r.type)"
                                            >
                                                {{ r.type }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 font-bold text-slate-300 flex items-center gap-1.5">
                                            <div class="h-5 w-5 rounded bg-indigo-600 font-bold text-white text-[8px] flex items-center justify-center">
                                                {{ r.generatedBy.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() }}
                                            </div>
                                            <span>{{ r.generatedBy }}</span>
                                        </td>
                                        <td class="py-4 px-4 text-slate-400 font-medium">{{ r.created }}</td>
                                        <td class="py-4 px-4">
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider border border-white/5 bg-slate-950">
                                                <span class="h-1.5 w-1.5 rounded-full" :class="getStatusColorClass(r.status)"></span>
                                                {{ r.status }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <a 
                                                    v-if="r.asset_id"
                                                    :href="`/api/vault/assets/${r.asset_id}/export`"
                                                    class="p-1 text-slate-500 hover:text-white rounded bg-slate-900 border border-white/5 inline-block"
                                                    title="Download Report"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                                </a>
                                                <button class="p-1 text-slate-500 hover:text-white rounded bg-slate-900 border border-white/5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 10.742l5.084-2.542m0 0a3 3 0 10-5.367-2.684 3 3 0 005.367 2.684zm0 9.316a3 3 0 105.368-2.684 3 3 0 00-5.368 2.684z" /></svg></button>
                                                <button class="p-1 text-slate-500 hover:text-white rounded bg-slate-900 border border-white/5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg></button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <div class="flex items-center justify-between border-t border-white/5 pt-4 text-[10px] font-bold text-slate-500 font-mono tracking-tight uppercase">
                            <span>Showing {{ filteredReports.length }} of {{ props.stats.total }} reports</span>
                            <div class="flex items-center gap-1">
                                <button class="p-1 rounded bg-[#080c17] text-slate-600">&lt;</button>
                                <button class="px-2 py-0.5 rounded bg-amber-500 text-black">1</button>
                                <button class="p-1 rounded bg-[#080c17] text-slate-600">&gt;</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side Widgets -->
                <div class="lg:col-span-3 space-y-6 shrink-0">
                    <!-- Reports Overview doughnut -->
                    <div class="bg-[#050811]/60 border border-white/5 rounded-2xl p-5 text-left space-y-4">
                        <h3 class="text-xs font-bold text-white uppercase tracking-wider border-b border-white/5 pb-2">Reports Overview</h3>
                        <div class="flex items-center justify-center py-4 relative">
                            <!-- Doughnut Circle SVG -->
                            <div class="relative w-32 h-32 flex items-center justify-center">
                                <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="40" stroke="rgba(255,255,255,0.03)" stroke-width="12" fill="transparent"/>
                                    <!-- Executive (purple) -->
                                    <circle cx="50" cy="50" r="40" stroke="#a78bfa" stroke-width="12" fill="transparent" :stroke-dasharray="execDash" :stroke-dashoffset="execOffset"/>
                                    <!-- Technical (blue) -->
                                    <circle cx="50" cy="50" r="40" stroke="#3b82f6" stroke-width="12" fill="transparent" :stroke-dasharray="techDash" :stroke-dashoffset="techOffset"/>
                                    <!-- Compliance (green) -->
                                    <circle cx="50" cy="50" r="40" stroke="#10b981" stroke-width="12" fill="transparent" :stroke-dasharray="compDash" :stroke-dashoffset="compOffset"/>
                                    <!-- Client (gold) -->
                                    <circle cx="50" cy="50" r="40" stroke="#f59e0b" stroke-width="12" fill="transparent" :stroke-dasharray="clientDash" :stroke-dashoffset="clientOffset"/>
                                </svg>
                                <div class="absolute flex flex-col items-center justify-center">
                                    <span class="text-xl font-bold font-mono text-white">{{ props.stats.total }}</span>
                                    <span class="text-[8px] text-slate-500 uppercase tracking-widest font-bold">Total</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2 text-xs font-semibold">
                            <div class="flex items-center justify-between"><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-purple-400"></span> Executive</span> <span class="text-slate-500 font-mono">{{ props.breakdown.executive }}</span></div>
                            <div class="flex items-center justify-between"><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span> Technical</span> <span class="text-slate-500 font-mono">{{ props.breakdown.technical }}</span></div>
                            <div class="flex items-center justify-between"><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Compliance</span> <span class="text-slate-500 font-mono">{{ props.breakdown.compliance }}</span></div>
                            <div class="flex items-center justify-between"><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Client</span> <span class="text-slate-500 font-mono">{{ props.breakdown.client }}</span></div>
                        </div>
                    </div>

                    <!-- Recent Reports -->
                    <div class="bg-[#050811]/60 border border-white/5 rounded-2xl p-5 text-left space-y-4">
                        <div class="flex items-center justify-between border-b border-white/5 pb-2">
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Recent Reports</h3>
                            <a href="#" class="text-[10px] font-bold text-[#CBB48A] hover:underline">View all</a>
                        </div>
                        <div class="space-y-4">
                            <div v-for="rep in reports.slice(0, 5)" :key="rep.id" class="flex gap-2.5 text-xs items-start">
                                <div class="h-8 w-8 rounded-lg bg-slate-900 border border-white/5 flex items-center justify-center shrink-0 text-slate-400">
                                    <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586" /></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <span class="font-bold text-white truncate block">{{ rep.name }}</span>
                                    <span class="text-[9.5px] text-slate-500 block font-mono">{{ rep.created }}</span>
                                </div>
                                <span class="text-[9px] font-bold font-mono whitespace-nowrap shrink-0" :class="getStatusTextColorClass(rep.status)">{{ rep.status }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Report Activity -->
                    <div class="bg-[#050811]/60 border border-white/5 rounded-2xl p-5 text-left space-y-4">
                        <div class="flex items-center justify-between border-b border-white/5 pb-2">
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Report Activity</h3>
                            <a href="#" class="text-[10px] font-bold text-[#CBB48A] hover:underline">View all</a>
                        </div>
                        <div class="space-y-4">
                            <div v-for="act in props.recentActivities" :key="act.target" class="flex gap-2.5 text-xs">
                                <div class="h-5 w-5 rounded bg-slate-900 border border-white/5 flex items-center justify-center shrink-0 text-slate-400">
                                    <span>&bull;</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <span class="font-bold text-white block">{{ act.author }} <span class="text-slate-500 font-normal">{{ act.action }}</span></span>
                                    <span class="text-[10px] text-slate-400 block leading-normal">{{ act.target }}</span>
                                </div>
                                <span class="text-[9px] text-slate-500 font-mono whitespace-nowrap shrink-0">{{ act.time }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Automate reporting widget -->
                    <div class="bg-gradient-to-b from-[#111625] to-[#050811] rounded-2xl border border-white/5 p-4 text-center space-y-3 shadow-lg">
                        <div class="h-10 w-10 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center mx-auto">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806" /></svg>
                        </div>
                        <h4 class="text-xs font-bold text-white">Automate Your Reporting</h4>
                        <p class="text-[10px] text-slate-400 leading-normal">Schedule reports to run automatically and deliver to your clients.</p>
                        <button class="w-full py-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 rounded-lg text-[10px] font-bold text-black uppercase tracking-wider transition-all">
                            Create Schedule
                        </button>
                    </div>
                </div>

            </div>

            <!-- Footer identity -->
            <div class="text-center pt-8 border-t border-white/5">
                <p class="text-[10px] text-slate-600 tracking-wider font-mono">EisenDev|Arjay @ 2026</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
