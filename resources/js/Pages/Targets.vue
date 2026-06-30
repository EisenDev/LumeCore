<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import WebURLandGitRepoSync from '@/Components/WebURLandGitRepoSync.vue';
import GithubRepositoryForensicModal from '@/Components/GithubRepositoryForensicModal.vue';

interface MappedAsset {
    id: string;
    hash: string;
    user_id: number;
    file_name: string;
    status: string;
    metadata: any;
    synced_metadata: any;
    website_metadata: any;
    repository_metadata: any;
    sync_score: number | null;
    score: number | null;
    batch_id: string | null;
    created_at: string;
    updated_at: string;
}

interface SyncedTarget {
    batch_id: string;
    web_asset: MappedAsset;
    repo_asset: MappedAsset;
    name: string;
    status: string;
    risk_score: number;
    correlation: number;
    last_scan: string;
}

interface OtherTarget {
    id: string;
    asset: MappedAsset;
    name: string;
    type: 'website' | 'repository' | 'api' | 'domain';
    status: string;
    risk_score: number;
    last_scan: string;
}

interface Props {
    syncedTargets: SyncedTarget[];
    otherTargets: OtherTarget[];
    allAssets: MappedAsset[];
    recentActivities: any[];
    stats: {
        total: number;
        synced: number;
        websites: number;
        repos: number;
        apis: number;
        domains: number;
        growth: {
            total: number;
            synced: number;
            websites: number;
            repos: number;
            apis: number;
            domains: number;
        };
    };
    wallet: {
        credits: number;
    } | null;
}

const props = defineProps<Props>();

// --- STATE ---
const viewLayout = ref<'grid' | 'list'>('grid');
const searchQueryGlobal = ref('');
const searchQueryFilters = ref('');
const selectedType = ref('All Types');
const selectedStatus = ref('All Status');
const selectedRisk = ref('All Risk Levels');
const selectedLabel = ref('All Labels');
const sortBy = ref('Sort by: Recently Added');

// Modal States
const showNewTargetModal = ref(false);
const isScanning = ref(false);
const scanError = ref<string | null>(null);

// Sync Scans Modal Details View States
const showSyncModal = ref(false);
const activeWebAsset = ref<any>(null);
const activeRepoAsset = ref<any>(null);
const activeComparisonData = ref<any>(null);

// Repo Details View States
const showRepoModal = ref(false);
const activeRepoDetailsAsset = ref<any>(null);

// Dropdown Toggles for custom styling UI
const activeDropdown = ref<string | null>(null);

// New Target Form State
const targetName = ref('');
const selectedWebAssetId = ref('');
const selectedRepoAssetId = ref('');
const githubToken = ref('');

const searchWebQuery = ref('');
const searchRepoQuery = ref('');

// Lists for New Target Selection
const websiteAssets = computed(() => {
    return props.allAssets.filter(a => {
        const type = a.metadata?.audit_type || '';
        return type === 'website_scan' || type === 'website' || type === 'project' || type === 'design' || 
               (!a.file_name.includes('github.com') && !a.file_name.includes('gitlab.com'));
    });
});

const repositoryAssets = computed(() => {
    return props.allAssets.filter(a => {
        const type = a.metadata?.audit_type || '';
        return type === 'repository_scan' || type === 'repository' || type === 'github' || 
               a.file_name.includes('github.com') || a.file_name.includes('gitlab.com');
    });
});

// Dropdown filtered selections
const filteredWebsites = computed(() => {
    if (!searchWebQuery.value) return websiteAssets.value;
    return websiteAssets.value.filter(w => w.file_name.toLowerCase().includes(searchWebQuery.value.toLowerCase()));
});

const filteredRepositories = computed(() => {
    if (!searchRepoQuery.value) return repositoryAssets.value;
    return repositoryAssets.value.filter(r => r.file_name.toLowerCase().includes(searchRepoQuery.value.toLowerCase()));
});

// Select item helper
const selectWebAsset = (asset: MappedAsset) => {
    selectedWebAssetId.value = asset.id;
    searchWebQuery.value = asset.file_name;
    // Auto-populate name if empty
    if (!targetName.value) {
        targetName.value = asset.file_name.replace(/https?:\/\/(www\.)?/, '').split('/')[0] + ' Sync';
    }
    activeDropdown.value = null;
};

const selectRepoAsset = (asset: MappedAsset) => {
    selectedRepoAssetId.value = asset.id;
    searchRepoQuery.value = asset.file_name;
    activeDropdown.value = null;
};

// Filter & Sort Synced Targets
const filteredSyncedTargets = computed(() => {
    let result = [...props.syncedTargets];

    // Search filter
    const query = searchQueryGlobal.value.trim().toLowerCase() || searchQueryFilters.value.trim().toLowerCase();
    if (query) {
        result = result.filter(t => t.name.toLowerCase().includes(query) || (t.repo_asset?.file_name || '').toLowerCase().includes(query));
    }

    // Risk level filter
    if (selectedRisk.value !== 'All Risk Levels') {
        result = result.filter(t => {
            const lvl = getRiskLevel(t.risk_score);
            return lvl.toUpperCase() === selectedRisk.value.replace(' Risk', '').toUpperCase();
        });
    }

    // Status filter
    if (selectedStatus.value !== 'All Status') {
        result = result.filter(t => t.status.toLowerCase() === selectedStatus.value.replace('Status: ', '').toLowerCase());
    }

    // Sort sorting
    if (sortBy.value.includes('Recently Added')) {
        result.sort((a, b) => new Date(b.last_scan).getTime() - new Date(a.last_scan).getTime());
    } else if (sortBy.value.includes('Highest Risk')) {
        result.sort((a, b) => b.risk_score - a.risk_score);
    } else if (sortBy.value.includes('Lowest Risk')) {
        result.sort((a, b) => a.risk_score - b.risk_score);
    }

    return result;
});

// Filter & Sort Other Targets
const filteredOtherTargets = computed(() => {
    let result = [...props.otherTargets];

    // Search filter
    const query = searchQueryGlobal.value.trim().toLowerCase() || searchQueryFilters.value.trim().toLowerCase();
    if (query) {
        result = result.filter(t => t.name.toLowerCase().includes(query));
    }

    // Type filter
    if (selectedType.value !== 'All Types') {
        const typeMapping: Record<string, string> = {
            'Websites': 'website',
            'Repositories': 'repository',
            'APIs': 'api',
            'Domains': 'domain'
        };
        const filterVal = typeMapping[selectedType.value] || selectedType.value.toLowerCase();
        result = result.filter(t => t.type === filterVal);
    }

    // Risk level filter
    if (selectedRisk.value !== 'All Risk Levels') {
        result = result.filter(t => {
            const lvl = getRiskLevel(t.risk_score);
            return lvl.toUpperCase() === selectedRisk.value.replace(' Risk', '').toUpperCase();
        });
    }

    // Status filter
    if (selectedStatus.value !== 'All Status') {
        result = result.filter(t => t.status.toLowerCase() === selectedStatus.value.replace('Status: ', '').toLowerCase());
    }

    // Sort sorting
    if (sortBy.value.includes('Recently Added')) {
        result.sort((a, b) => new Date(b.last_scan).getTime() - new Date(a.last_scan).getTime());
    } else if (sortBy.value.includes('Highest Risk')) {
        result.sort((a, b) => b.risk_score - a.risk_score);
    } else if (sortBy.value.includes('Lowest Risk')) {
        result.sort((a, b) => a.risk_score - b.risk_score);
    }

    return result;
});

// Helper for Risk Level Range (consistent with screenshot style and backend)
function getRiskLevel(score: number) {
    if (score < 21) return 'Low';
    if (score <= 35) return 'Medium';
    return 'High';
}

function getRiskColorClass(score: number) {
    const lvl = getRiskLevel(score);
    if (lvl === 'Low') return 'text-emerald-400 border-emerald-500/30 bg-emerald-500/5';
    if (lvl === 'Medium') return 'text-amber-400 border-amber-500/30 bg-amber-500/5';
    return 'text-rose-400 border-rose-500/30 bg-rose-500/5';
}

function getRiskTextClass(score: number) {
    const lvl = getRiskLevel(score);
    if (lvl === 'Low') return 'text-emerald-500';
    if (lvl === 'Medium') return 'text-amber-500';
    return 'text-rose-500';
}

function getStatusColorClass(status: string) {
    const s = status.toLowerCase();
    if (s === 'active' || s === 'completed' || s === 'ready' || s === 'verified') return 'bg-emerald-500';
    if (s === 'processing' || s === 'pending') return 'bg-amber-500 animate-pulse';
    return 'bg-slate-500';
}

function getGrowthClass(val: number | undefined) {
    if (val === undefined) return 'text-slate-500';
    return val >= 0 ? 'text-emerald-400' : 'text-rose-500';
}

// Format duration/relative time
function formatRelativeTime(dateStr: string) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / (1000 * 60));
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    return `${diffDays}d ago`;
}

// Action Trigger details modal
const openSyncTargetDetails = (target: SyncedTarget) => {
    activeWebAsset.value = target.web_asset;
    activeRepoAsset.value = target.repo_asset;
    activeComparisonData.value = target.web_asset.synced_metadata || {
        sync_score: target.correlation,
        drift_analysis: { summary: 'Architectural match' }
    };
    showSyncModal.value = true;
};

const openOtherTargetDetails = (target: OtherTarget) => {
    if (target.type === 'repository') {
        activeRepoDetailsAsset.value = target.asset;
        showRepoModal.value = true;
    } else {
        // Redirection to Website Results View
        router.visit(route('website.results', { hash: target.asset.hash }));
    }
};

// Dispatch a Sync Scan
const handleCreateSyncTarget = async () => {
    if (!targetName.value) {
        scanError.value = "Target designation name is required.";
        return;
    }
    if (!selectedWebAssetId.value || !selectedRepoAssetId.value) {
        scanError.value = "Please select both a website and source repository.";
        return;
    }

    // Locate selected asset details
    const webObj = websiteAssets.value.find(w => w.id === selectedWebAssetId.value);
    const repoObj = repositoryAssets.value.find(r => r.id === selectedRepoAssetId.value);

    if (!webObj || !repoObj) {
        scanError.value = "Failed to locate selected asset records.";
        return;
    }

    scanError.value = null;
    isScanning.value = true;

    try {
        const response = await axios.post('/projects', {
            name: targetName.value,
            website_url: webObj.file_name,
            github_repo_url: repoObj.file_name,
            github_token: githubToken.value || null,
            audit_type: 'project'
        });

        if (response.data.success) {
            // Close modal & reload page
            showNewTargetModal.value = false;
            targetName.value = '';
            selectedWebAssetId.value = '';
            selectedRepoAssetId.value = '';
            githubToken.value = '';
            searchWebQuery.value = '';
            searchRepoQuery.value = '';
            
            router.reload({ only: ['syncedTargets', 'otherTargets', 'stats'] });
        } else {
            scanError.value = response.data.message || "Failed to trigger sync scan.";
        }
    } catch (e: any) {
        console.error(e);
        scanError.value = e.response?.data?.message || "An error occurred while launching sync scan.";
    } finally {
        isScanning.value = false;
    }
};

const toggleDropdown = (name: string) => {
    if (activeDropdown.value === name) activeDropdown.value = null;
    else activeDropdown.value = name;
};

// Close all custom dropdowns when clicking outside
const closeDropdowns = () => {
    activeDropdown.value = null;
};
</script>

<template>
    <AuthenticatedLayout @click="closeDropdowns">
        <Head title="Targets Dashboard" />

        <div class="space-y-8 pb-16">
            <!-- Header section matching screenshot -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-white">Targets</h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Manage websites, repositories and synced digital assets monitored by LUME.
                    </p>
                </div>
                
                <!-- Action bar on top right matching layout -->
                <div class="flex items-center gap-3">
                    <div class="relative w-64">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input 
                            v-model="searchQueryGlobal"
                            type="text" 
                            class="block w-full rounded-xl border-white/5 bg-[#050811] pl-9 pr-10 py-2 text-xs text-white placeholder-slate-500 focus:border-[#CBB48A]/50 focus:ring-0 focus:outline-none transition-all font-medium" 
                            placeholder="Search targets..."
                        >
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                            <kbd class="text-[9px] font-mono text-slate-600 bg-white/5 border border-white/10 px-1 py-0.5 rounded">⌘K</kbd>
                        </div>
                    </div>

                    <!-- Filter buttons and bell -->
                    <button class="p-2.5 rounded-xl border border-white/5 bg-[#050811] text-slate-400 hover:text-white hover:bg-white/[0.03] transition-all">
                        <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 8.293A1 1 0 013 7.586V4z" />
                        </svg>
                    </button>

                    <button class="relative p-2.5 rounded-xl border border-white/5 bg-[#050811] text-slate-400 hover:text-white hover:bg-white/[0.03] transition-all">
                        <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span class="absolute top-1 right-1 h-2 w-2 rounded-full bg-red-600"></span>
                    </button>

                    <button 
                        @click="showNewTargetModal = true" 
                        class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 text-xs font-bold text-black hover:from-amber-400 hover:to-amber-500 shadow-lg shadow-amber-500/10 active:scale-95 transition-all"
                    >
                        <span>+ New Target</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Stats Metric row matching image -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                <!-- Stat 1 -->
                <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between group hover:border-white/10 transition-all">
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">All Targets</span>
                        <span class="text-2xl font-bold font-mono text-white">{{ stats.total }}</span>
                        <span class="text-[10px] font-bold flex items-center gap-0.5" :class="getGrowthClass(stats.growth?.total)">
                            <svg v-if="(stats.growth?.total ?? 0) >= 0" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                            <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>
                            {{ Math.abs(stats.growth?.total ?? 0) }}% <span class="text-slate-500 font-normal text-[9px] lowercase">last 30d</span>
                        </span>
                    </div>
                    <div class="h-10 w-10 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                    </div>
                </div>

                <!-- Stat 2 -->
                <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between group hover:border-white/10 transition-all">
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Synced Targets</span>
                        <span class="text-2xl font-bold font-mono text-white">{{ stats.synced }}</span>
                        <span class="text-[10px] font-bold flex items-center gap-0.5" :class="getGrowthClass(stats.growth?.synced)">
                            <svg v-if="(stats.growth?.synced ?? 0) >= 0" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                            <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>
                            {{ Math.abs(stats.growth?.synced ?? 0) }}% <span class="text-slate-500 font-normal text-[9px] lowercase">last 30d</span>
                        </span>
                    </div>
                    <div class="h-10 w-10 rounded-lg bg-cyan-500/10 flex items-center justify-center text-cyan-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                    </div>
                </div>

                <!-- Stat 3 -->
                <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between group hover:border-white/10 transition-all">
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Websites</span>
                        <span class="text-2xl font-bold font-mono text-white">{{ stats.websites }}</span>
                        <span class="text-[10px] font-bold flex items-center gap-0.5" :class="getGrowthClass(stats.growth?.websites)">
                            <svg v-if="(stats.growth?.websites ?? 0) >= 0" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                            <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>
                            {{ Math.abs(stats.growth?.websites ?? 0) }}% <span class="text-slate-500 font-normal text-[9px] lowercase">last 30d</span>
                        </span>
                    </div>
                    <div class="h-10 w-10 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
                    </div>
                </div>

                <!-- Stat 4 -->
                <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between group hover:border-white/10 transition-all">
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Repositories</span>
                        <span class="text-2xl font-bold font-mono text-white">{{ stats.repos }}</span>
                        <span class="text-[10px] font-bold flex items-center gap-0.5" :class="getGrowthClass(stats.growth?.repos)">
                            <svg v-if="(stats.growth?.repos ?? 0) >= 0" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                            <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>
                            {{ Math.abs(stats.growth?.repos ?? 0) }}% <span class="text-slate-500 font-normal text-[9px] lowercase">last 30d</span>
                        </span>
                    </div>
                    <div class="h-10 w-10 rounded-lg bg-violet-500/10 flex items-center justify-center text-violet-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>
                    </div>
                </div>

                <!-- Stat 5 -->
                <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between group hover:border-white/10 transition-all">
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">APIs</span>
                        <span class="text-2xl font-bold font-mono text-white">{{ stats.apis }}</span>
                        <span class="text-[10px] font-bold flex items-center gap-0.5" :class="getGrowthClass(stats.growth?.apis)">
                            <svg v-if="(stats.growth?.apis ?? 0) >= 0" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                            <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>
                            {{ Math.abs(stats.growth?.apis ?? 0) }}% <span class="text-slate-500 font-normal text-[9px] lowercase">last 30d</span>
                        </span>
                    </div>
                    <div class="h-10 w-10 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                    </div>
                </div>

                <!-- Stat 6 -->
                <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between group hover:border-white/10 transition-all">
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Domains</span>
                        <span class="text-2xl font-bold font-mono text-white">{{ stats.domains }}</span>
                        <span class="text-[10px] font-bold flex items-center gap-0.5" :class="getGrowthClass(stats.growth?.domains)">
                            <svg v-if="(stats.growth?.domains ?? 0) >= 0" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                            <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>
                            {{ Math.abs(stats.growth?.domains ?? 0) }}% <span class="text-slate-500 font-normal text-[9px] lowercase">last 30d</span>
                        </span>
                    </div>
                    <div class="h-10 w-10 rounded-lg bg-yellow-500/10 flex items-center justify-center text-yellow-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9" /></svg>
                    </div>
                </div>
            </div>

            <!-- Filter Controls Bar matching screenshot -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border border-white/5 bg-[#050811]/60 backdrop-blur-md rounded-xl p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative w-48">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input 
                            v-model="searchQueryFilters"
                            type="text" 
                            class="block w-full rounded-lg border-white/5 bg-[#080c17] pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:border-[#CBB48A]/50 focus:ring-0 focus:outline-none transition-all" 
                            placeholder="Search targets..."
                        >
                    </div>

                    <!-- Custom Custom Select Dropdowns to avoid default browser inputs -->
                    <!-- Type Selector -->
                    <div class="relative">
                        <button @click.stop="toggleDropdown('type')" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-white/5 bg-[#080c17] text-xs font-semibold text-slate-300 hover:text-white transition-all">
                            <span>{{ selectedType }}</span>
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div v-if="activeDropdown === 'type'" class="absolute left-0 mt-1 z-30 w-40 bg-[#080c17] border border-slate-800 rounded-lg shadow-xl py-1 text-xs">
                            <button v-for="t in ['All Types', 'Websites', 'Repositories', 'APIs', 'Domains']" :key="t" @click="selectedType = t; activeDropdown = null" class="w-full text-left px-3 py-2 hover:bg-white/5 text-slate-300 hover:text-white transition-all">{{ t }}</button>
                        </div>
                    </div>

                    <!-- Status Selector -->
                    <div class="relative">
                        <button @click.stop="toggleDropdown('status')" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-white/5 bg-[#080c17] text-xs font-semibold text-slate-300 hover:text-white transition-all">
                            <span>{{ selectedStatus }}</span>
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div v-if="activeDropdown === 'status'" class="absolute left-0 mt-1 z-30 w-40 bg-[#080c17] border border-slate-800 rounded-lg shadow-xl py-1 text-xs">
                            <button v-for="s in ['All Status', 'Status: Active', 'Status: Paused', 'Status: Processing']" :key="s" @click="selectedStatus = s; activeDropdown = null" class="w-full text-left px-3 py-2 hover:bg-white/5 text-slate-300 hover:text-white transition-all">{{ s }}</button>
                        </div>
                    </div>

                    <!-- Risk Selector -->
                    <div class="relative">
                        <button @click.stop="toggleDropdown('risk')" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-white/5 bg-[#080c17] text-xs font-semibold text-slate-300 hover:text-white transition-all">
                            <span>{{ selectedRisk }}</span>
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div v-if="activeDropdown === 'risk'" class="absolute left-0 mt-1 z-30 w-40 bg-[#080c17] border border-slate-800 rounded-lg shadow-xl py-1 text-xs">
                            <button v-for="r in ['All Risk Levels', 'High Risk', 'Medium Risk', 'Low Risk']" :key="r" @click="selectedRisk = r; activeDropdown = null" class="w-full text-left px-3 py-2 hover:bg-white/5 text-slate-300 hover:text-white transition-all">{{ r }}</button>
                        </div>
                    </div>

                    <!-- Label Selector -->
                    <div class="relative">
                        <button @click.stop="toggleDropdown('label')" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-white/5 bg-[#080c17] text-xs font-semibold text-slate-300 hover:text-white transition-all">
                            <span>{{ selectedLabel }}</span>
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div v-if="activeDropdown === 'label'" class="absolute left-0 mt-1 z-30 w-40 bg-[#080c17] border border-slate-800 rounded-lg shadow-xl py-1 text-xs">
                            <button v-for="l in ['All Labels', 'Production', 'Staging', 'Corporate']" :key="l" @click="selectedLabel = l; activeDropdown = null" class="w-full text-left px-3 py-2 hover:bg-white/5 text-slate-300 hover:text-white transition-all">{{ l }}</button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 self-end md:self-auto">
                    <!-- Sort -->
                    <div class="relative">
                        <button @click.stop="toggleDropdown('sort')" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-white/5 bg-[#080c17] text-xs font-semibold text-slate-300 hover:text-white transition-all">
                            <span>{{ sortBy }}</span>
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div v-if="activeDropdown === 'sort'" class="absolute right-0 mt-1 z-30 w-48 bg-[#080c17] border border-slate-800 rounded-lg shadow-xl py-1 text-xs">
                            <button v-for="s in ['Sort by: Recently Added', 'Sort by: Highest Risk', 'Sort by: Lowest Risk']" :key="s" @click="sortBy = s; activeDropdown = null" class="w-full text-left px-3 py-2 hover:bg-white/5 text-slate-300 hover:text-white transition-all">{{ s }}</button>
                        </div>
                    </div>

                    <!-- Layout switchers -->
                    <div class="h-6 w-px bg-white/5"></div>
                    
                    <div class="flex items-center gap-1 bg-[#080c17] p-0.5 rounded-lg border border-white/5">
                        <button 
                            @click="viewLayout = 'grid'" 
                            class="p-1 rounded-md transition-all"
                            :class="viewLayout === 'grid' ? 'bg-[#CBB48A]/10 text-[#CBB48A]' : 'text-slate-500 hover:text-slate-300'"
                        >
                            <!-- Grid icon -->
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M4 4h4v4H4V4zm6 0h4v4h-4V4zm6 0h4v4h-4V4zM4 10h4v4H4v-4zm6 0h4v4h-4v-4zm6 0h4v4h-4v-4zM4 16h4v4H4v-4zm6 0h4v4h-4v-4zm6 0h4v4h-4v-4z" /></svg>
                        </button>
                        <button 
                            @click="viewLayout = 'list'" 
                            class="p-1 rounded-md transition-all"
                            :class="viewLayout === 'list' ? 'bg-[#CBB48A]/10 text-[#CBB48A]' : 'text-slate-500 hover:text-slate-300'"
                        >
                            <!-- List icon -->
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- SYNCED TARGETS SECTION matching screenshot -->
            <div class="space-y-4">
                <div class="flex items-center justify-between border-b border-white/5 pb-2">
                    <h2 class="text-xs font-bold tracking-widest text-slate-500 uppercase">
                        Synced Targets ({{ filteredSyncedTargets.length }})
                    </h2>
                    <a href="#" class="text-xs font-bold text-[#CBB48A] hover:text-[#CBB48A]/85 transition-all">View all</a>
                </div>

                <div v-if="filteredSyncedTargets.length === 0" class="flex flex-col items-center justify-center p-12 border border-dashed border-white/5 rounded-2xl bg-[#050811]/30">
                    <svg class="w-8 h-8 text-slate-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                    <p class="text-xs text-slate-500">No synced targets found matching your query.</p>
                </div>

                <!-- Grid view -->
                <div v-else-if="viewLayout === 'grid'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                    <div 
                        v-for="target in filteredSyncedTargets" 
                        :key="target.batch_id"
                        @click="openSyncTargetDetails(target)"
                        class="bg-[#050811]/70 border border-white/5 hover:border-[#CBB48A]/20 rounded-2xl p-5 flex flex-col justify-between h-64 hover:bg-[#080d1a] cursor-pointer shadow-lg transition-all duration-300 relative group"
                    >
                        <!-- Top status bar -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full" :class="getStatusColorClass(target.status)"></span>
                                <span class="text-[10px] font-bold text-slate-400 capitalize">{{ target.status }}</span>
                            </div>
                            <button @click.stop class="text-slate-500 hover:text-white p-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                            </button>
                        </div>

                        <!-- Central Connection graphic exactly as screenshot -->
                        <div class="flex items-center justify-center my-4 relative">
                            <!-- Sibling line connection -->
                            <div class="absolute left-1/4 right-1/4 h-0.5 border-t border-dashed border-slate-700 top-1/2 -translate-y-1/2 z-0"></div>
                            
                            <!-- Glowing Correlation Circle badge -->
                            <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-10 bg-slate-900 rounded-full p-1 border border-emerald-500/30 shadow-[0_0_10px_rgba(16,185,129,0.2)]">
                                <div class="bg-emerald-500/10 text-emerald-400 rounded-full p-0.5 flex items-center justify-center">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                </div>
                            </div>

                            <!-- Left Web Globe -->
                            <div class="z-10 h-12 w-12 rounded-full border border-blue-500/30 bg-[#0c192d] flex items-center justify-center shadow-[0_0_15px_rgba(59,130,246,0.1)] group-hover:scale-105 transition-transform duration-300">
                                <svg class="w-6 h-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
                            </div>

                            <!-- Spacer -->
                            <div class="w-20"></div>

                            <!-- Right Repository Git -->
                            <div class="z-10 h-12 w-12 rounded-full border border-slate-700 bg-slate-950 flex items-center justify-center shadow-[0_0_15px_rgba(255,255,255,0.05)] group-hover:scale-105 transition-transform duration-300">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                            </div>
                        </div>

                        <!-- Target Labels -->
                        <div class="grid grid-cols-2 gap-4 text-left mb-3">
                            <div class="min-w-0">
                                <div class="text-[11px] font-bold text-white truncate">{{ target.name.replace(/https?:\/\/(www\.)?/, '') }}</div>
                                <div class="text-[9px] text-slate-500 font-mono tracking-tight">Website</div>
                            </div>
                            <div class="min-w-0 text-right md:text-left">
                                <div class="text-[11px] font-bold text-white truncate">{{ target.repo_asset?.file_name.replace('github.com/', '').replace('gitlab.com/', '') || 'No Repo' }}</div>
                                <div class="text-[9px] text-slate-500 font-mono tracking-tight text-right md:text-left">Repository</div>
                            </div>
                        </div>

                        <!-- Footer matching screenshot details -->
                        <div class="border-t border-white/5 pt-3 flex items-center justify-between">
                            <div>
                                <span class="text-[8px] font-bold text-slate-500 block uppercase tracking-wider">Risk Score</span>
                                <div class="flex items-center gap-1 mt-0.5">
                                    <div class="relative flex items-center justify-center w-7 h-7">
                                        <svg class="absolute inset-0 w-full h-full text-current" :class="getRiskTextClass(target.risk_score)" viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="10">
                                            <polygon points="50,5 90,28 90,72 50,95 10,72 10,28" />
                                        </svg>
                                        <span class="z-10 font-mono font-bold text-[9px] text-white">{{ target.risk_score }}</span>
                                    </div>
                                    <span class="text-[9px] font-black uppercase text-slate-300" :class="getRiskTextClass(target.risk_score)">
                                        {{ getRiskLevel(target.risk_score) }}
                                    </span>
                                </div>
                            </div>

                            <div class="text-center">
                                <span class="text-[8px] font-bold text-slate-500 block uppercase tracking-wider">Correlation</span>
                                <span class="text-xs font-black text-emerald-400 font-mono block mt-1">
                                    {{ target.correlation }}%
                                </span>
                            </div>

                            <div class="text-right">
                                <span class="text-[8px] font-bold text-slate-500 block uppercase tracking-wider">Last Scan</span>
                                <span class="text-[10px] text-slate-400 block mt-1 font-mono">
                                    {{ formatRelativeTime(target.last_scan) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- List view -->
                <div v-else class="border border-white/5 bg-[#050811]/40 rounded-2xl overflow-hidden text-xs">
                    <div class="grid grid-cols-12 gap-4 px-6 py-3 border-b border-white/5 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                        <div class="col-span-4">Target (Website & Codebase)</div>
                        <div class="col-span-2 text-center">Status</div>
                        <div class="col-span-2 text-center">Risk Score</div>
                        <div class="col-span-2 text-center">Correlation</div>
                        <div class="col-span-2 text-right">Last Scan</div>
                    </div>
                    <div 
                        v-for="target in filteredSyncedTargets" 
                        :key="target.batch_id"
                        @click="openSyncTargetDetails(target)"
                        class="grid grid-cols-12 gap-4 px-6 py-4 items-center border-b border-white/5 hover:bg-white/[0.02] cursor-pointer transition-all"
                    >
                        <div class="col-span-4 flex items-center gap-3">
                            <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                            <div class="min-w-0">
                                <div class="font-bold text-white truncate">{{ target.name.replace(/https?:\/\/(www\.)?/, '') }}</div>
                                <div class="text-[9.5px] text-slate-500 font-mono truncate">{{ target.repo_asset?.file_name.replace('github.com/', '') || 'No Repo' }}</div>
                            </div>
                        </div>
                        <div class="col-span-2 text-center">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold tracking-wider uppercase" :class="target.status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-500/10 text-slate-400'">
                                <span class="h-1 w-1 rounded-full" :class="getStatusColorClass(target.status)"></span>
                                {{ target.status }}
                            </span>
                        </div>
                        <div class="col-span-2 flex justify-center">
                            <div class="flex items-center gap-1.5">
                                <span class="px-2 py-0.5 rounded-lg border text-[9px] font-bold font-mono tracking-tight" :class="getRiskColorClass(target.risk_score)">
                                    {{ target.risk_score }}
                                </span>
                                <span class="text-[9px] font-black uppercase" :class="getRiskTextClass(target.risk_score)">
                                    {{ getRiskLevel(target.risk_score) }}
                                </span>
                            </div>
                        </div>
                        <div class="col-span-2 text-center text-emerald-400 font-bold font-mono">{{ target.correlation }}%</div>
                        <div class="col-span-2 text-right font-mono text-slate-400">{{ formatRelativeTime(target.last_scan) }}</div>
                    </div>
                </div>
            </div>

            <!-- OTHER TARGETS SECTION matching screenshot -->
            <div class="space-y-4">
                <div class="flex items-center justify-between border-b border-white/5 pb-2">
                    <h2 class="text-xs font-bold tracking-widest text-slate-500 uppercase">
                        Other Targets ({{ filteredOtherTargets.length }})
                    </h2>
                    <a href="#" class="text-xs font-bold text-[#CBB48A] hover:text-[#CBB48A]/85 transition-all">View all</a>
                </div>

                <div v-if="filteredOtherTargets.length === 0" class="flex flex-col items-center justify-center p-12 border border-dashed border-white/5 rounded-2xl bg-[#050811]/30">
                    <svg class="w-8 h-8 text-slate-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    <p class="text-xs text-slate-500">No other individual targets found matching your query.</p>
                </div>

                <!-- Grid view -->
                <div v-else-if="viewLayout === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div 
                        v-for="target in filteredOtherTargets" 
                        :key="target.id"
                        @click="openOtherTargetDetails(target)"
                        class="bg-[#050811]/60 border border-white/5 hover:border-slate-800 rounded-xl p-4 flex flex-col justify-between h-48 hover:bg-[#070b17] cursor-pointer shadow transition-all group"
                    >
                        <!-- Top status bar -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full" :class="getStatusColorClass(target.status)"></span>
                                <span class="text-[9px] font-bold text-slate-500 capitalize">{{ target.status }}</span>
                            </div>
                            <button @click.stop class="text-slate-600 hover:text-white p-0.5">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                            </button>
                        </div>

                        <!-- Target Icon & Title -->
                        <div class="my-3 space-y-2.5 text-left">
                            <div class="h-9 w-9 rounded-lg bg-slate-900 border border-white/5 flex items-center justify-center text-slate-400 group-hover:scale-105 transition-transform duration-300">
                                <svg v-if="target.type === 'repository'" class="w-5 h-5 text-violet-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                                <svg v-else-if="target.type === 'api'" class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                <svg v-else class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9" /></svg>
                            </div>

                            <div class="min-w-0">
                                <div class="text-[11px] font-bold text-white truncate max-w-full">
                                    {{ target.name.replace(/https?:\/\/(www\.)?/, '') }}
                                </div>
                                <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[8px] font-extrabold uppercase font-mono tracking-wider bg-slate-900 border border-slate-800 text-slate-400">
                                    {{ target.type === 'api' ? 'API Endpoint' : (target.type === 'repository' ? 'Repository' : 'Website') }}
                                </span>
                            </div>
                        </div>

                        <!-- Footer matching screenshot details -->
                        <div class="border-t border-white/5 pt-2 flex items-center justify-between">
                            <div>
                                <span class="text-[7.5px] font-bold text-slate-500 block uppercase">Risk Score</span>
                                <div class="flex items-center gap-1 mt-0.5">
                                    <div class="relative flex items-center justify-center w-6 h-6">
                                        <svg class="absolute inset-0 w-full h-full text-current" :class="getRiskTextClass(target.risk_score)" viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="12">
                                            <polygon points="50,5 90,28 90,72 50,95 10,72 10,28" />
                                        </svg>
                                        <span class="z-10 font-mono font-bold text-[8.5px] text-white">{{ target.risk_score }}</span>
                                    </div>
                                    <span class="text-[8.5px] font-black uppercase" :class="getRiskTextClass(target.risk_score)">
                                        {{ getRiskLevel(target.risk_score) }}
                                    </span>
                                </div>
                            </div>

                            <div class="text-right">
                                <span class="text-[7.5px] font-bold text-slate-500 block uppercase">Last Scan</span>
                                <span class="text-[9.5px] text-slate-400 block mt-0.5 font-mono">
                                    {{ formatRelativeTime(target.last_scan) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- List view -->
                <div v-else class="border border-white/5 bg-[#050811]/40 rounded-2xl overflow-hidden text-xs">
                    <div class="grid grid-cols-12 gap-4 px-6 py-3 border-b border-white/5 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                        <div class="col-span-5">Target (Asset Endpoint)</div>
                        <div class="col-span-2 text-center">Type</div>
                        <div class="col-span-2 text-center">Status</div>
                        <div class="col-span-1 text-center">Risk Score</div>
                        <div class="col-span-2 text-right">Last Scan</div>
                    </div>
                    <div 
                        v-for="target in filteredOtherTargets" 
                        :key="target.id"
                        @click="openOtherTargetDetails(target)"
                        class="grid grid-cols-12 gap-4 px-6 py-4 items-center border-b border-white/5 hover:bg-white/[0.02] cursor-pointer transition-all"
                    >
                        <div class="col-span-5 flex items-center gap-3">
                            <svg v-if="target.type === 'repository'" class="w-4 h-4 text-violet-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                            <svg v-else class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9" /></svg>
                            <span class="font-bold text-white truncate">{{ target.name.replace(/https?:\/\/(www\.)?/, '') }}</span>
                        </div>
                        <div class="col-span-2 text-center capitalize text-slate-400 font-semibold">{{ target.type }}</div>
                        <div class="col-span-2 text-center">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold uppercase" :class="target.status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-500/10 text-slate-400'">
                                <span class="h-1 w-1 rounded-full" :class="getStatusColorClass(target.status)"></span>
                                {{ target.status }}
                            </span>
                        </div>
                        <div class="col-span-1 text-center">
                            <span class="px-2 py-0.5 rounded border font-bold font-mono text-[9px]" :class="getRiskColorClass(target.risk_score)">
                                {{ target.risk_score }}
                            </span>
                        </div>
                        <div class="col-span-2 text-right font-mono text-slate-400">{{ formatRelativeTime(target.last_scan) }}</div>
                    </div>
                </div>
            </div>

            <!-- FOOTER PROMO BANNER matching screenshot -->
            <div class="bg-gradient-to-r from-[#111625] via-[#0b101d] to-[#050811] rounded-2xl border border-white/5 p-6 flex flex-col lg:flex-row items-center justify-between gap-6 shadow-2xl relative overflow-hidden">
                <div class="absolute -right-20 -bottom-20 h-64 w-64 rounded-full bg-[#CBB48A]/5 blur-3xl pointer-events-none"></div>

                <div class="flex items-start gap-5 max-w-2xl text-left">
                    <div class="p-3 bg-amber-500/10 border border-amber-500/20 text-[#CBB48A] rounded-xl flex-shrink-0">
                        <!-- Connected dots network graphic icon -->
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Sync your website with its source repository</h3>
                        <p class="mt-1 text-xs text-slate-400 leading-relaxed">
                            LUME correlates your live website with its codebase to uncover hidden exposures, misconfigurations, and deployment risks.
                        </p>
                        <button class="mt-4 px-4 py-1.5 rounded-lg border border-slate-700 bg-slate-900 text-xs font-bold text-[#CBB48A] hover:bg-slate-800 transition-all">
                            Learn more
                        </button>
                    </div>
                </div>

                <!-- Feature Icons on Right matching screenshot -->
                <div class="grid grid-cols-2 gap-x-8 gap-y-4 text-left">
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded-lg bg-violet-500/10 border border-violet-500/20 flex items-center justify-center text-violet-400">
                            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" /></svg>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-white block">Deep Correlation</span>
                            <span class="text-[8.5px] text-slate-500 block">Code &harr; Live Match</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded-lg bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400">
                            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-white block">Enhanced Findings</span>
                            <span class="text-[8.5px] text-slate-500 block">Deeper Context</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" /></svg>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-white block">Risk Accuracy</span>
                            <span class="text-[8.5px] text-slate-500 block">Up to 3x Better</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400">
                            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-white block">Continuous Monitoring</span>
                            <span class="text-[8.5px] text-slate-500 block">Changes Detection</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EISEN FOOTPRINTS FOOTER -->
            <div class="text-center pt-8 border-t border-white/5">
                <p class="text-[10px] text-slate-600 tracking-wider font-mono">EisenDev|Arjay @ 2026</p>
            </div>
        </div>

        <!-- NEW SYNC TARGET MODAL -->
        <Teleport to="body">
            <div v-if="showNewTargetModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-[#020408]/90 backdrop-blur-sm" @click="showNewTargetModal = false"></div>

                <!-- Modal Content -->
                <div class="relative w-full max-w-lg bg-[#050811] border border-slate-800 rounded-2xl shadow-2xl p-6 text-slate-300 overflow-hidden flex flex-col">
                    
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h2 class="text-lg font-bold text-white">Create New Sync Target</h2>
                            <p class="text-xs text-slate-400 mt-1">Correlate a previously scanned repository and live website to run a sync audit.</p>
                        </div>
                        <button @click="showNewTargetModal = false" class="text-slate-500 hover:text-white p-1">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- Inner Loading / Scanning state -->
                    <div v-if="isScanning" class="py-12 flex flex-col items-center justify-center space-y-4">
                        <div class="relative flex items-center justify-center">
                            <div class="h-16 w-16 rounded-full border-t-2 border-b-2 border-[#CBB48A] animate-spin"></div>
                            <div class="absolute h-10 w-10 rounded-full bg-slate-900 flex items-center justify-center">
                                <svg class="w-5 h-5 text-[#CBB48A]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 110-18 9 9 0 010 18zm0-3a6 6 0 100-12 6 6 0 000 12zm0-3a3 3 0 110-6 3 3 0 010 6z" /></svg>
                            </div>
                        </div>
                        <div class="text-center">
                            <p class="text-sm font-bold text-white">Deploying LUME Sync Scanner...</p>
                            <p class="text-[10px] text-slate-500 font-mono mt-1">LOCKING DUAL-MODE CREDITS &bull; INITIATING DRIFT CORRELATION</p>
                        </div>
                    </div>

                    <!-- Form Inputs -->
                    <div v-else class="space-y-4">
                        <div v-if="scanError" class="p-3 rounded-lg bg-rose-500/10 border border-rose-500/20 text-xs text-rose-400">
                            {{ scanError }}
                        </div>

                        <!-- Target Designation Name -->
                        <div class="space-y-1.5 text-left">
                            <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Target Designation Name</label>
                            <input 
                                v-model="targetName" 
                                type="text"
                                class="block w-full rounded-xl border-white/5 bg-slate-950 px-4 py-2.5 text-xs text-white placeholder-slate-600 focus:border-[#CBB48A]/50 focus:ring-0 focus:outline-none transition-all"
                                placeholder="e.g. poolreno.com Core Sync"
                            />
                        </div>

                        <!-- Website Selector -->
                        <div class="space-y-1.5 text-left relative">
                            <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Select Scanned Live Website</label>
                            <div class="relative">
                                <input 
                                    v-model="searchWebQuery"
                                    @focus="activeDropdown = 'webSelect'"
                                    @click.stop
                                    type="text"
                                    class="block w-full rounded-xl border-white/5 bg-slate-950 pl-4 pr-10 py-2.5 text-xs text-white placeholder-slate-600 focus:border-[#CBB48A]/50 focus:ring-0 focus:outline-none transition-all"
                                    placeholder="Search or select a website URL..."
                                />
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                            <!-- Searchable Dropdown List -->
                            <div v-if="activeDropdown === 'webSelect'" class="absolute left-0 right-0 mt-1 z-50 max-h-48 overflow-y-auto bg-slate-950 border border-slate-800 rounded-xl shadow-2xl py-1 text-xs custom-scrollbar">
                                <button 
                                    v-for="web in filteredWebsites" 
                                    :key="web.id"
                                    @click.stop="selectWebAsset(web)"
                                    class="w-full text-left px-4 py-2.5 hover:bg-white/5 flex items-center justify-between border-b border-white/[0.02]"
                                >
                                    <span class="font-bold text-white truncate max-w-[70%]">{{ web.file_name }}</span>
                                    <span class="text-[9px] font-mono px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        Score: {{ web.score ?? 'N/A' }}
                                    </span>
                                </button>
                                <div v-if="filteredWebsites.length === 0" class="px-4 py-3 text-slate-500 text-center font-mono">No scanned websites found</div>
                            </div>
                        </div>

                        <!-- Repository Selector -->
                        <div class="space-y-1.5 text-left relative">
                            <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Select Scanned Source Code Repository</label>
                            <div class="relative">
                                <input 
                                    v-model="searchRepoQuery"
                                    @focus="activeDropdown = 'repoSelect'"
                                    @click.stop
                                    type="text"
                                    class="block w-full rounded-xl border-white/5 bg-slate-950 pl-4 pr-10 py-2.5 text-xs text-white placeholder-slate-600 focus:border-[#CBB48A]/50 focus:ring-0 focus:outline-none transition-all"
                                    placeholder="Search or select a repository URL..."
                                />
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                            <!-- Searchable Dropdown List -->
                            <div v-if="activeDropdown === 'repoSelect'" class="absolute left-0 right-0 mt-1 z-50 max-h-48 overflow-y-auto bg-slate-950 border border-slate-800 rounded-xl shadow-2xl py-1 text-xs custom-scrollbar">
                                <button 
                                    v-for="repo in filteredRepositories" 
                                    :key="repo.id"
                                    @click.stop="selectRepoAsset(repo)"
                                    class="w-full text-left px-4 py-2.5 hover:bg-white/5 flex items-center justify-between border-b border-white/[0.02]"
                                >
                                    <span class="font-bold text-white truncate max-w-[70%]">{{ repo.file_name.replace('https://', '') }}</span>
                                    <span class="text-[9px] font-mono px-2 py-0.5 rounded bg-violet-500/10 text-violet-400 border border-violet-500/20">
                                        Score: {{ repo.score ?? 'N/A' }}
                                    </span>
                                </button>
                                <div v-if="filteredRepositories.length === 0" class="px-4 py-3 text-slate-500 text-center font-mono">No scanned repositories found</div>
                            </div>
                        </div>

                        <!-- GitHub Access Token (Optional) -->
                        <div class="space-y-1.5 text-left">
                            <div class="flex justify-between items-center">
                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">GitHub Access Token (Optional)</label>
                                <span class="text-[9px] text-amber-400/80 font-bold uppercase font-mono">Required for private repos</span>
                            </div>
                            <input 
                                v-model="githubToken" 
                                type="password"
                                class="block w-full rounded-xl border-white/5 bg-slate-950 px-4 py-2.5 text-xs text-white placeholder-slate-600 focus:border-[#CBB48A]/50 focus:ring-0 focus:outline-none transition-all"
                                placeholder="github_pat_..."
                            />
                        </div>

                        <!-- Submit button -->
                        <div class="pt-4 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1.5 text-[10px] text-slate-500 font-bold tracking-tight">
                                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                <span>REQUIRES 10 CREDITS</span>
                            </div>

                            <div class="flex items-center gap-3">
                                <button 
                                    @click="showNewTargetModal = false" 
                                    class="px-4 py-2 rounded-xl border border-slate-800 bg-slate-900 text-xs font-semibold text-slate-400 hover:text-white transition-all"
                                >
                                    Cancel
                                </button>
                                <button 
                                    @click="handleCreateSyncTarget"
                                    class="px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 text-xs font-bold text-black hover:from-amber-400 hover:to-amber-500 shadow shadow-amber-500/10 active:scale-95 transition-all"
                                >
                                    Start Sync Scan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- SYNC FORENSIC DETAILS VIEW MODAL -->
        <WebURLandGitRepoSync
            :show="showSyncModal"
            :webAsset="activeWebAsset"
            :repoAsset="activeRepoAsset"
            :comparisonData="activeComparisonData"
            @close="showSyncModal = false"
        />

        <!-- REPO DETAILS VIEW MODAL -->
        <GithubRepositoryForensicModal
            :show="showRepoModal"
            :asset="activeRepoDetailsAsset"
            @close="showRepoModal = false"
        />

    </AuthenticatedLayout>
</template>

<style>
/* Custom styled slim scrollbars */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(2, 4, 8, 0.5);
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(203, 180, 138, 0.2);
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(203, 180, 138, 0.4);
}
</style>
