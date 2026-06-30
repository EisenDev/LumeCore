<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface Member {
    id: number;
    name: string;
    email: string;
    role: string;
    is_you?: boolean;
    status: 'Active' | 'Invited' | 'Deactivated';
    lastActive: string;
    joined: string;
    enabled_mfa: boolean;
    avatar: string | null;
}

interface Props {
    organization_id?: string;
    organization_name?: string;
    members?: any[];
}

const props = withDefaults(defineProps<Props>(), {
    members: () => []
});

// State
const searchQuery = ref('');
const activeTab = ref('Members'); // Members, Roles & Permissions, Activity, Settings
const selectedRoleFilter = ref('All Roles');
const selectedStatusFilter = ref('All Status');
const showInviteModal = ref(false);

// Combine backend-provided members (if any) with dummy data matching mockup
const teamMembers = computed<Member[]>(() => {
    // Standard mockup list
    const mockList: Member[] = [
        { id: 991, name: 'Taylor Smith', email: 'taylor@lume.ai', role: 'Administrator', status: 'Active', lastActive: '15 minutes ago', joined: 'Jan 10, 2026', enabled_mfa: true, avatar: null },
        { id: 992, name: 'Jordan Rivera', email: 'jordan@lume.ai', role: 'Security Analyst', status: 'Active', lastActive: '1 hour ago', joined: 'Jan 12, 2026', enabled_mfa: true, avatar: null },
        { id: 993, name: 'Morgan Lee', email: 'morgan@lume.ai', role: 'Developer', status: 'Active', lastActive: '3 hours ago', joined: 'Jan 14, 2026', enabled_mfa: false, avatar: null },
        { id: 994, name: 'Casey Wong', email: 'casey@lume.ai', role: 'Viewer', status: 'Active', lastActive: '5 hours ago', joined: 'Jan 16, 2026', enabled_mfa: false, avatar: null },
        { id: 995, name: 'Blake Pierce', email: 'blake@lume.ai', role: 'Security Analyst', status: 'Invited', lastActive: 'Invited', joined: 'May 20, 2026', enabled_mfa: false, avatar: null },
        { id: 996, name: 'Noah Harris', email: 'noah@lume.ai', role: 'Developer', status: 'Invited', lastActive: 'Invited', joined: 'May 20, 2026', enabled_mfa: false, avatar: null },
        { id: 997, name: 'Emery White', email: 'emery@lume.ai', role: 'Viewer', status: 'Deactivated', lastActive: '2 weeks ago', joined: 'Apr 28, 2026', enabled_mfa: false, avatar: null }
    ];

    // Read current user details from props or default to mockup Owner
    const currentMember = props.members && props.members.length > 0 ? props.members[0] : null;
    const ownerMember: Member = {
        id: currentMember?.id || 1,
        name: currentMember?.name || 'Analyst Doe',
        email: currentMember?.email || 'analyst@lume.ai',
        role: 'Owner',
        is_you: true,
        status: 'Active',
        lastActive: '2 minutes ago',
        joined: 'Jan 8, 2026',
        enabled_mfa: currentMember?.enabled_mfa || true,
        avatar: null
    };

    return [ownerMember, ...mockList];
});

// Filters
const filteredMembers = computed(() => {
    return teamMembers.value.filter(m => {
        // Search filter
        if (searchQuery.value && !m.name.toLowerCase().includes(searchQuery.value.toLowerCase()) && !m.email.toLowerCase().includes(searchQuery.value.toLowerCase())) return false;
        
        // Role filter
        if (selectedRoleFilter.value !== 'All Roles' && m.role !== selectedRoleFilter.value) return false;

        // Status filter
        if (selectedStatusFilter.value !== 'All Status' && m.status !== selectedStatusFilter.value) return false;

        return true;
    });
});

// Invite inputs
const inviteEmail = ref('');
const inviteRole = ref('Developer');

const handleInviteSubmit = () => {
    if (!inviteEmail.value) return;
    // Add to list or trigger reload
    showInviteModal.value = false;
    inviteEmail.value = '';
};

// Help helper for role colors
function getRoleColorClass(role: string) {
    const r = role.toLowerCase();
    if (r === 'owner') return 'text-amber-400 bg-amber-500/10 border-amber-500/20';
    if (r === 'administrator') return 'text-purple-400 bg-purple-500/10 border-purple-500/20';
    if (r === 'security analyst') return 'text-blue-400 bg-blue-500/10 border-blue-500/20';
    if (r === 'developer') return 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20';
    return 'text-slate-400 bg-slate-500/10 border-slate-500/20';
}

function getStatusColorClass(status: string) {
    if (status === 'Active') return 'text-emerald-400 border-emerald-500/20 bg-emerald-500/5';
    if (status === 'Invited') return 'text-amber-400 border-amber-500/20 bg-amber-500/5';
    return 'text-rose-500 border-rose-500/20 bg-rose-500/5';
}

function getStatusDotClass(status: string) {
    if (status === 'Active') return 'bg-emerald-500';
    if (status === 'Invited') return 'bg-amber-500 animate-pulse';
    return 'bg-rose-500';
}
</script>

<template>
    <Head title="Teams Management" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col">
                <h2 class="text-xl font-bold tracking-tight text-white/90">Teams</h2>
                <span class="text-xs text-gray-500 mt-1 font-medium">Manage your team members, roles, and permissions</span>
            </div>
        </template>

        <div class="space-y-8 pb-16">
            <!-- Controls Toolbar -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-white/5 pb-4">
                <!-- Tab Switcher -->
                <div class="flex items-center gap-6">
                    <button 
                        v-for="tab in ['Members', 'Roles & Permissions', 'Activity', 'Settings']" 
                        :key="tab"
                        @click="activeTab = tab"
                        class="pb-2 text-xs font-bold uppercase tracking-wider relative transition-colors"
                        :class="activeTab === tab ? 'text-[#CBB48A]' : 'text-slate-500 hover:text-slate-300'"
                    >
                        {{ tab }}
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
                            placeholder="Search members..."
                        >
                    </div>

                    <button 
                        @click="showInviteModal = true"
                        class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 text-xs font-bold text-black hover:from-amber-400 hover:to-amber-500 shadow shadow-amber-500/10 active:scale-95 transition-all"
                    >
                        <span>+ Invite Member</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                </div>
            </div>

            <!-- Main Layout Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left Side: Members Table -->
                <div class="lg:col-span-9 space-y-6">
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                        <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between">
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Total Members</span>
                                <span class="text-2xl font-bold font-mono text-white">12</span>
                                <span class="text-[9px] text-emerald-400 font-bold block">&uarr; 18% last 30d</span>
                            </div>
                        </div>
                        <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between">
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Active Members</span>
                                <span class="text-2xl font-bold font-mono text-white">10</span>
                                <span class="text-[9px] text-emerald-400 font-bold block">&uarr; 12% last 30d</span>
                            </div>
                        </div>
                        <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between">
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Pending Invites</span>
                                <span class="text-2xl font-bold font-mono text-white">2</span>
                                <span class="text-[9px] text-rose-400 font-bold block">&darr; 2% last 30d</span>
                            </div>
                        </div>
                        <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between">
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Administrators</span>
                                <span class="text-2xl font-bold font-mono text-white">3</span>
                                <span class="text-[9px] text-slate-500 font-medium block">No change</span>
                            </div>
                        </div>
                        <div class="rounded-xl border border-white/5 bg-[#050811] p-4 flex items-center justify-between">
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Total Roles</span>
                                <span class="text-2xl font-bold font-mono text-white">5</span>
                                <span class="text-[9px] text-slate-500 font-medium block">No change</span>
                            </div>
                        </div>
                    </div>

                    <!-- Members List Table area -->
                    <div class="border border-white/5 bg-[#050811]/40 rounded-2xl overflow-hidden p-4 space-y-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-2">
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
                                        placeholder="Search members..."
                                    >
                                </div>
                                <button class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-white/5 bg-[#080c17] text-xs font-semibold text-slate-300 hover:text-white">
                                    <span>All Roles</span>
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                </button>
                                <button class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-white/5 bg-[#080c17] text-xs font-semibold text-slate-300 hover:text-white">
                                    <span>All Status</span>
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                </button>
                            </div>

                            <button class="flex items-center gap-1 px-3 py-1.5 rounded-lg border border-white/5 bg-[#080c17] text-xs font-semibold text-slate-300 hover:text-white">
                                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 8.293A1 1 0 013 7.586V4z" /></svg>
                                <span>Filters</span>
                            </button>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-white/5 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                                        <th class="py-3 px-4">Member</th>
                                        <th class="py-3 px-4">Role</th>
                                        <th class="py-3 px-4">Status</th>
                                        <th class="py-3 px-4">Last Active</th>
                                        <th class="py-3 px-4">Joined</th>
                                        <th class="py-3 px-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr 
                                        v-for="m in filteredMembers" 
                                        :key="m.id"
                                        class="border-b border-white/[0.02] hover:bg-white/[0.01] transition-all"
                                    >
                                        <td class="py-3.5 px-4 font-bold text-white flex items-center gap-3">
                                            <div class="h-8 w-8 rounded-lg bg-indigo-600 font-bold text-white text-[10px] flex items-center justify-center border border-indigo-400/20 shadow-md">
                                                {{ m.name.substring(0, 2).toUpperCase() }}
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-1.5">
                                                    <span>{{ m.name }}</span>
                                                    <span v-if="m.is_you" class="px-1 py-0.5 text-[8px] font-bold text-slate-400 bg-slate-900 border border-slate-800 rounded font-mono uppercase">You</span>
                                                </div>
                                                <span class="text-[10px] text-slate-500 font-mono tracking-tight font-medium">{{ m.email }}</span>
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <span 
                                                class="px-2 py-0.5 rounded-lg border text-[9px] font-bold font-mono tracking-tight"
                                                :class="getRoleColorClass(m.role)"
                                            >
                                                {{ m.role }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <span 
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold tracking-wider uppercase border"
                                                :class="getStatusColorClass(m.status)"
                                            >
                                                <span class="h-1 w-1 rounded-full" :class="getStatusDotClass(m.status)"></span>
                                                {{ m.status }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-300 font-mono font-medium">{{ m.lastActive }}</td>
                                        <td class="py-3.5 px-4 text-slate-400 font-medium">{{ m.joined }}</td>
                                        <td class="py-3.5 px-4 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <button class="p-1.5 text-slate-500 hover:text-white rounded bg-slate-900 border border-white/5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg></button>
                                                <button class="p-1.5 text-slate-500 hover:text-white rounded bg-slate-900 border border-white/5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg></button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <div class="flex items-center justify-between border-t border-white/5 pt-4 text-[10px] font-bold text-slate-500 font-mono tracking-tight uppercase">
                            <span>Showing 1 to 8 of 12 members</span>
                            <div class="flex items-center gap-1">
                                <button class="p-1 rounded bg-[#080c17] text-slate-600">&lt;</button>
                                <button class="px-2 py-0.5 rounded bg-amber-500 text-black">1</button>
                                <button class="px-2 py-0.5 rounded bg-[#080c17] hover:text-white">2</button>
                                <button class="p-1 rounded bg-[#080c17] text-slate-300 hover:text-white">&gt;</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side Widget Column -->
                <div class="lg:col-span-3 space-y-6">
                    <!-- Team Overview Doughnut -->
                    <div class="bg-[#050811]/60 border border-white/5 rounded-2xl p-5 text-left space-y-4">
                        <h3 class="text-xs font-bold text-white uppercase tracking-wider border-b border-white/5 pb-2">Team Overview</h3>
                        <div class="flex items-center justify-center py-4 relative">
                            <!-- Doughnut Circle SVG mock -->
                            <div class="relative w-32 h-32 flex items-center justify-center">
                                <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="40" stroke="rgba(255,255,255,0.03)" stroke-width="12" fill="transparent"/>
                                    <!-- 10 Active (green) -> 83% of circle -->
                                    <circle cx="50" cy="50" r="40" stroke="#10b981" stroke-width="12" fill="transparent" stroke-dasharray="251.2" stroke-dashoffset="42"/>
                                    <!-- 2 Invited (orange) -> 17% of circle -->
                                    <circle cx="50" cy="50" r="40" stroke="#f59e0b" stroke-width="12" fill="transparent" stroke-dasharray="251.2" stroke-dashoffset="210"/>
                                </svg>
                                <div class="absolute flex flex-col items-center justify-center">
                                    <span class="text-xl font-bold font-mono text-white">12</span>
                                    <span class="text-[8px] text-slate-500 uppercase tracking-widest font-bold">Total</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2 text-xs font-semibold">
                            <div class="flex items-center justify-between"><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> 10 Active</span> <span class="text-slate-500 font-mono">83%</span></div>
                            <div class="flex items-center justify-between"><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> 2 Invited</span> <span class="text-slate-500 font-mono">17%</span></div>
                            <div class="flex items-center justify-between"><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span> 0 Deactivated</span> <span class="text-slate-500 font-mono">0%</span></div>
                        </div>
                    </div>

                    <!-- Role Distribution -->
                    <div class="bg-[#050811]/60 border border-white/5 rounded-2xl p-5 text-left space-y-3">
                        <div class="flex items-center justify-between border-b border-white/5 pb-2">
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Role Distribution</h3>
                            <a href="#" class="text-[10px] font-bold text-[#CBB48A] hover:underline">View all</a>
                        </div>
                        <div class="space-y-2.5 font-semibold text-xs text-slate-300">
                            <div class="flex items-center justify-between"><span>Owner</span> <span class="font-mono text-white">1</span></div>
                            <div class="flex items-center justify-between"><span>Administrators</span> <span class="font-mono text-white">2</span></div>
                            <div class="flex items-center justify-between"><span>Security Analysts</span> <span class="font-mono text-white">4</span></div>
                            <div class="flex items-center justify-between"><span>Developers</span> <span class="font-mono text-white">2</span></div>
                            <div class="flex items-center justify-between"><span>Viewers</span> <span class="font-mono text-white">3</span></div>
                        </div>
                    </div>

                    <!-- Pending Invites -->
                    <div class="bg-[#050811]/60 border border-white/5 rounded-2xl p-5 text-left space-y-4">
                        <div class="flex items-center justify-between border-b border-white/5 pb-2">
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Pending Invites</h3>
                            <a href="#" class="text-[10px] font-bold text-[#CBB48A] hover:underline">View all</a>
                        </div>
                        <div class="space-y-4">
                            <div v-for="inv in [
                                { email: 'alex@lume.ai', role: 'Security Analyst', time: 'Invited 1h ago' },
                                { email: 'sam@lume.ai', role: 'Developer', time: 'Invited 3h ago' }
                            ]" :key="inv.email" class="flex items-start justify-between gap-2 text-xs">
                                <div class="min-w-0">
                                    <span class="font-bold text-white truncate block">{{ inv.email }}</span>
                                    <span class="text-[9.5px] text-slate-500 block font-mono">{{ inv.time }}</span>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider font-mono bg-slate-900 border border-slate-800 text-slate-400">
                                    {{ inv.role }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Security Summary -->
                    <div class="bg-[#050811]/60 border border-white/5 rounded-2xl p-5 text-left space-y-3.5">
                        <h3 class="text-xs font-bold text-white uppercase tracking-wider border-b border-white/5 pb-2">Security Summary</h3>
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-3 text-xs">
                                <div class="min-w-0">
                                    <span class="font-bold text-white block">2FA Enforced</span>
                                    <span class="text-[9px] text-slate-500 block">All admins & owners</span>
                                </div>
                                <span class="h-4.5 w-4.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                </span>
                            </div>
                            <div class="flex items-start justify-between gap-3 text-xs">
                                <div class="min-w-0">
                                    <span class="font-bold text-white block">SSO Authentication</span>
                                    <span class="text-[9px] text-slate-500 block">Enabled</span>
                                </div>
                                <span class="h-4.5 w-4.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                </span>
                            </div>
                            <div class="flex items-start justify-between gap-3 text-xs">
                                <div class="min-w-0">
                                    <span class="font-bold text-white block">Session Timeout</span>
                                    <span class="text-[9px] text-slate-500 block">30 minutes</span>
                                </div>
                                <span class="h-4.5 w-4.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Bottom Invite Member Promo matching mockup -->
            <div class="bg-gradient-to-r from-[#111625] via-[#0b101d] to-[#050811] rounded-2xl border border-white/5 p-6 flex flex-col lg:flex-row items-center justify-between gap-6 shadow-2xl relative overflow-hidden">
                <div class="absolute -right-20 -bottom-20 h-64 w-64 rounded-full bg-[#CBB48A]/5 blur-3xl pointer-events-none"></div>

                <div class="flex items-start gap-5 max-w-2xl text-left">
                    <div class="p-3 bg-amber-500/10 border border-amber-500/20 text-[#CBB48A] rounded-xl flex-shrink-0">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Add your team and collaborate securely</h3>
                        <p class="mt-1 text-xs text-slate-400 leading-relaxed">
                            Invite your team members to start collaborating on scans, findings, and reports with role-based access controls.
                        </p>
                    </div>
                </div>

                <button @click="showInviteModal = true" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 text-xs font-bold text-black hover:from-amber-400 hover:to-amber-500 shadow active:scale-95 transition-all">
                    + Invite Member
                </button>
            </div>

            <!-- Footer identity -->
            <div class="text-center pt-8 border-t border-white/5">
                <p class="text-[10px] text-slate-600 tracking-wider font-mono">EisenDev|Arjay @ 2026</p>
            </div>
        </div>

        <!-- INVITE MEMBER MODAL -->
        <div v-if="showInviteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#020408]/90 backdrop-blur-sm" @click="showInviteModal = false"></div>
            <div class="relative w-full max-w-md bg-[#050811] border border-slate-800 rounded-2xl shadow-2xl p-6 text-slate-300 text-left space-y-4">
                <div>
                    <h2 class="text-lg font-bold text-white">Invite Team Member</h2>
                    <p class="text-xs text-slate-400 mt-1">Send an invitation to join your Lume workspace.</p>
                </div>
                <div class="space-y-3">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Email Address</label>
                        <input v-model="inviteEmail" type="email" placeholder="member@company.com" class="block w-full rounded-xl border-white/5 bg-slate-950 px-4 py-2.5 text-xs text-white placeholder-slate-600 focus:border-[#CBB48A]/50 focus:outline-none focus:ring-0" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Role</label>
                        <select v-model="inviteRole" class="block w-full rounded-xl border-white/5 bg-slate-950 px-4 py-2.5 text-xs text-white focus:border-[#CBB48A]/50 focus:outline-none focus:ring-0">
                            <option>Administrator</option>
                            <option>Security Analyst</option>
                            <option>Developer</option>
                            <option>Viewer</option>
                        </select>
                    </div>
                </div>
                <div class="pt-4 flex items-center justify-end gap-3">
                    <button @click="showInviteModal = false" class="px-4 py-2 rounded-xl border border-slate-800 bg-slate-900 text-xs font-semibold text-slate-400 hover:text-white transition-all">Cancel</button>
                    <button @click="handleInviteSubmit" class="px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 text-xs font-bold text-black hover:from-amber-400 hover:to-amber-500 shadow transition-all">Send Invite</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
