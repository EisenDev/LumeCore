<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    subscription: any;
    organization_id: string;
    organization_name: string;
    members: any[];
}>();

const isAgency = computed(() => {
    return props.subscription && props.subscription.plan_type === 'agency';
});

const showInviteModal = ref(false);
const inviteEmails = ref('');

const openInviteModal = () => {
    showInviteModal.value = true;
};

const closeInviteModal = () => {
    showInviteModal.value = false;
    inviteEmails.value = '';
};

const confirmInvite = () => {
    // Logic for inviting operatives
    alert(`Invitations sent to: ${inviteEmails.value}`);
    closeInviteModal();
};

</script>

<template>
    <Head title="Team Management" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex flex-col">
                    <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.3em] text-gray-500 mb-1">
                        <Link :href="route('organizations.index')" class="hover:text-emerald-400 transition-colors">Organazitaions</Link>
                        <span>/</span>
                        <span class="text-gray-400 italic">{{ props.organization_name }}</span>
                    </div>
                    <span class="text-2xl font-black italic tracking-tighter text-white uppercase">Team Management</span>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                
                <!-- Advanced Stats Bar -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">
                    <div class="p-6 rounded-[2rem] bg-white/[0.02] border border-white/5 backdrop-blur-3xl relative overflow-hidden group">
                        <div class="absolute -right-8 -bottom-8 h-24 w-24 bg-emerald-500/10 blur-3xl group-hover:bg-emerald-500/20 transition-all"></div>
                        <div class="text-[9px] font-black text-emerald-400 uppercase tracking-widest mb-2">Active Operatives</div>
                        <div class="text-4xl font-black italic tracking-tighter text-white">{{ props.members.length }}</div>
                    </div>
                    <div class="p-6 rounded-[2rem] bg-white/[0.02] border border-white/5 backdrop-blur-3xl relative overflow-hidden group">
                        <div class="absolute -right-8 -bottom-8 h-24 w-24 bg-cyan-500/10 blur-3xl group-hover:bg-cyan-500/20 transition-all"></div>
                        <div class="text-[9px] font-black text-cyan-400 uppercase tracking-widest mb-2">Pending Clearances</div>
                        <div class="text-4xl font-black italic tracking-tighter text-white">0</div>
                    </div>
                    <div class="p-6 rounded-[2rem] bg-white/[0.02] border border-white/5 backdrop-blur-3xl relative overflow-hidden group">
                        <div class="absolute -right-8 -bottom-8 h-24 w-24 bg-purple-500/10 blur-3xl group-hover:bg-purple-500/20 transition-all"></div>
                        <div class="text-[9px] font-black text-purple-400 uppercase tracking-widest mb-2">Security Level</div>
                        <div class="text-4xl font-black italic tracking-tighter text-white uppercase">Omega</div>
                    </div>
                    <div class="p-6 rounded-[2rem] bg-emerald-500/[0.03] border border-emerald-500/10 backdrop-blur-3xl relative overflow-hidden group">
                        <div class="text-[9px] font-black text-emerald-500 uppercase tracking-widest mb-2">Plan Quota</div>
                        <div class="text-4xl font-black italic tracking-tighter text-white uppercase">Infinite</div>
                    </div>
                </div>

                <!-- Forensic Member Interface -->
                <div class="relative overflow-hidden bg-[#0A0A0B]/40 backdrop-blur-3xl rounded-[3rem] border border-white/5 p-12">
                    <!-- Dynamic Grid Background -->
                    <div class="absolute inset-0 opacity-[0.03] pointer-events-none" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 40px 40px;"></div>
                    
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-10">
                            <div>
                                <h3 class="text-sm font-black italic tracking-tighter text-white uppercase mb-1">Operational Roaster</h3>
                                <p class="text-[8px] font-bold text-gray-600 uppercase tracking-widest">Managing clearances for organization node {{ props.organization_id }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <a href="/docs#security-protocol" target="_blank" class="group flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-[9px] font-black uppercase tracking-widest text-gray-400 hover:text-white hover:bg-white/10 transition-all border-b-2 border-b-white/5 hover:border-b-emerald-500/50">
                                    <svg class="w-4 h-4 group-hover:text-emerald-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                                    Security Protocol
                                </a>
                                <button @click="openInviteModal" class="flex items-center gap-3 px-8 py-2.5 rounded-xl bg-emerald-500 text-slate-950 text-[9px] font-black uppercase tracking-[0.2em] shadow-[0_0_30px_rgba(16,185,129,0.3)] hover:bg-emerald-400 hover:shadow-[0_0_40px_rgba(16,185,129,0.4)] transition-all">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                                    Invite operative
                                </button>
                                <div class="w-px h-8 bg-white/5 mx-2"></div>
                                <div class="relative">
                                    <input type="text" placeholder="FILTER BY IDENTITY..." class="pl-10 pr-6 py-2.5 bg-white/5 border border-white/10 rounded-xl text-[9px] font-black tracking-widest text-white placeholder-gray-600 focus:ring-emerald-400/30 focus:border-emerald-400/50 uppercase transition-all">
                                    <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                </div>
                            </div>
                        </div>

                        <!-- Member List -->
                        <div class="space-y-4">
                            <div v-for="member in props.members" :key="member.id" class="group relative flex items-center justify-between p-6 rounded-[2rem] bg-white/[0.02] border border-white/5 hover:bg-white/[0.04] hover:border-emerald-500/20 transition-all duration-500">
                                <!-- Status Line -->
                                <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-emerald-500/40 rounded-r-full group-hover:h-12 transition-all"></div>
                                
                                <div class="flex items-center gap-6">
                                    <div class="relative">
                                        <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-emerald-400/20 to-cyan-400/20 p-0.5 shadow-xl shadow-emerald-400/5 overflow-hidden">
                                            <div class="w-full h-full bg-[#0A0A0B] rounded-[0.9rem] flex items-center justify-center text-xl font-black text-emerald-400 italic">
                                                <img v-if="member.avatar" :src="member.avatar" class="w-full h-full object-cover">
                                                <span v-else>{{ member.name.charAt(0).toUpperCase() }}</span>
                                            </div>
                                        </div>
                                        <div class="absolute -bottom-1 -right-1 h-4 w-4 bg-emerald-500 rounded-full border-4 border-[#0A0A0B] shadow-inner shadow-black"></div>
                                    </div>

                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-3 mb-1">
                                            <span class="text-xs font-black text-white uppercase tracking-tight">{{ member.name }}</span>
                                            <span v-if="member.is_you" class="px-2 py-0.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-[7px] font-black text-emerald-400 uppercase tracking-widest">Self</span>
                                        </div>
                                        <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest">{{ member.email }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-16">
                                    <div class="hidden lg:flex flex-col items-center">
                                        <span class="text-[8px] font-black text-gray-600 uppercase tracking-[0.2em] mb-2">Access Multiplier</span>
                                        <div class="flex gap-1">
                                            <div v-for="i in 5" :key="i" class="h-1 w-4 rounded-full" :class="i < 4 ? 'bg-emerald-500/40' : 'bg-white/5'"></div>
                                        </div>
                                    </div>

                                    <div class="flex flex-col text-right">
                                        <span class="text-[8px] font-black text-gray-600 uppercase tracking-[0.2em] mb-1">Protocol Role</span>
                                        <span class="text-[10px] font-black text-emerald-400 uppercase tracking-widest italic">{{ member.role }}</span>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <button class="h-10 w-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/10 transition-all">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        </button>
                                        <button class="h-10 w-10 rounded-xl bg-rose-500/5 border border-rose-500/10 flex items-center justify-center text-rose-500/40 hover:text-rose-500 hover:bg-rose-500/10 transition-all">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State if only one (Self) -->
                        <div v-if="props.members.length === 1" class="mt-8 p-12 rounded-[2.5rem] border border-dashed border-white/5 flex flex-col items-center justify-center group pointer-events-none">
                            <div class="h-20 w-20 rounded-full bg-white/[0.02] flex items-center justify-center mb-6">
                                <svg class="h-10 w-10 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                            </div>
                            <span class="text-sm font-black italic tracking-tighter text-gray-600 uppercase mb-2">Solitary Operation Detected</span>
                            <span class="text-[9px] font-bold text-gray-700 uppercase tracking-[0.3em]">Invite operatives to scale your forensic ecosystem</span>
                        </div>
                    </div>
                </div>

                <!-- Invite Operative Modal -->
                <div v-if="showInviteModal" class="fixed inset-0 z-[100] flex items-center justify-center p-6 sm:p-0">
                    <div class="absolute inset-0 bg-[#0A0A0B] backdrop-blur-xl" @click="closeInviteModal"></div>
                    
                    <div class="relative w-full max-w-lg bg-[#0A0A0B] rounded-[3rem] border border-white/10 p-12 shadow-[0_0_100px_rgba(0,0,0,0.5)] animate-in fade-in zoom-in duration-500">
                        <div class="mb-10 text-center">
                            <div class="h-20 w-20 rounded-full bg-emerald-500/10 flex items-center justify-center mx-auto mb-6">
                                <svg class="h-10 w-10 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                            </div>
                            <h2 class="text-3xl font-black italic tracking-tighter text-white uppercase mb-2">Invite Operatives</h2>
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest leading-relaxed">Expand your forensic network. Access will be granted upon protocol verification.</p>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <label class="block text-[10px] font-black text-emerald-400 uppercase tracking-widest mb-3 italic">Operative Identities (EMAILS)</label>
                                <textarea 
                                    v-model="inviteEmails"
                                    placeholder="operative1@lumecore.tech, operative2@example.com..."
                                    rows="4"
                                    class="w-full bg-white/5 border border-white/10 rounded-[1.5rem] p-6 text-sm font-bold text-white placeholder-gray-700 focus:ring-emerald-400/30 focus:border-emerald-400/50 outline-none transition-all uppercase resize-none"
                                ></textarea>
                                <p class="mt-3 text-[8px] font-bold text-gray-600 uppercase tracking-widest">Separate multiple identities with commas.</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mt-10">
                                <button @click="closeInviteModal" class="py-4 rounded-2xl bg-white/5 border border-white/10 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-white hover:bg-white/10 transition-all">
                                    Abort
                                </button>
                                <button @click="confirmInvite" class="py-4 rounded-2xl bg-emerald-500 text-slate-950 text-[10px] font-black uppercase tracking-widest shadow-[0_0_30px_rgba(16,185,129,0.3)] hover:bg-emerald-400 transition-all">
                                    Initiate Invite
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
    height: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(52, 211, 153, 0.2);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(52, 211, 153, 0.4);
}
</style>
