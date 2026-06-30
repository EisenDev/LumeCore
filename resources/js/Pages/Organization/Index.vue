<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    subscription: any;
    organizations: any[];
}>();

const isAgency = computed(() => {
    return props.subscription && props.subscription.plan_type === 'agency';
});

const getAvatarColor = (name: string) => {
    const colors = [
        'from-[#CBB48A] to-[#F3E7C9]',
        'from-purple-400 to-pink-400',
        'from-amber-400 to-orange-400',
        'from-blue-400 to-indigo-400',
    ];
    const index = name.length % colors.length;
    return colors[index];
};

const upgradeSection = ref<HTMLElement | null>(null);
const showCreateModal = ref(false);

const form = useForm({
    name: '',
});

const scrollToUpgrade = () => {
    upgradeSection.value?.scrollIntoView({ behavior: 'smooth' });
};

const openCreateModal = () => {
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    form.post(route('organizations.store'), {
        onSuccess: () => closeCreateModal(),
    });
};

</script>

<template>
    <Head title="Organazitaions" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-3">
                    <span class="text-2xl font-bold tracking-tight text-white uppercase mt-1">Organazitaions</span>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                
                <!-- Organizations Grid -->
                <div v-if="props.organizations.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Feature Preview Card -->
                    <div class="md:col-span-2 lg:col-span-3 relative overflow-hidden rounded-[2.5rem] border border-white/5 bg-[#0A0A0B] p-10 group">
                        <div class="absolute inset-0 bg-grid-white/[0.02] bg-[length:30px_30px]"></div>
                        <div class="absolute -right-20 -bottom-20 h-64 w-64 bg-[#CBB48A]/5 blur-[80px] rounded-full group-hover:bg-[#CBB48A]/10 transition-all duration-1000"></div>

                        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
                            <div class="flex-1 text-center md:text-left">
                                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#CBB48A]/10 border border-[#CBB48A]/20 text-[10px] font-bold tracking-wider text-[#CBB48A] mb-4">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#CBB48A] animate-pulse"></span>
                                    Implementation Roadmap
                                </div>
                                <h3 class="text-2xl font-bold tracking-tight text-white uppercase mb-2">
                                    Strategic Feature Preview
                                </h3>
                                <p class="text-sm font-bold text-gray-500 uppercase tracking-widest max-w-xl leading-relaxed">
                                    This Organization Feature is not yet finished and have many considerations and implementation. View the architectural blueprint.
                                </p>
                            </div>
                            
                            <Link :href="route('organizations.roadmap')" class="group/btn flex items-center gap-3 px-8 py-4 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 text-xs font-black uppercase tracking-[0.2em] text-white transition-all whitespace-nowrap">
                                View Details
                                <svg class="w-4 h-4 text-gray-500 group-hover/btn:text-[#CBB48A] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                            </Link>
                        </div>
                    </div>

                    <Link 
                        v-for="org in props.organizations" 
                        :key="org.id"
                        :href="route('team.index', { organization: org.id })"
                        class="group relative overflow-hidden bg-[#0A0A0B]/60 backdrop-blur-3xl rounded-[2.5rem] border border-white/5 p-8 hover:border-[#CBB48A]/30 transition-all duration-500"
                    >
                        <!-- Hover Glow -->
                        <div class="absolute inset-0 bg-gradient-to-br from-[#CBB48A]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        
                        <div class="relative z-10">
                            <div class="flex items-start justify-between mb-6">
                                <div :class="`h-16 w-16 rounded-[1.5rem] bg-gradient-to-br ${getAvatarColor(org.name)} flex items-center justify-center text-xl font-black text-slate-950 shadow-lg shadow-[#CBB48A]/10 text-white`">
                                    {{ org.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-[8px] font-black text-gray-500 uppercase tracking-[0.2em]">
                                    {{ org.team_count }} Teams
                                </div>
                            </div>

                            <h3 class="text-lg font-bold tracking-tight text-white uppercase group-hover:text-[#CBB48A] transition-colors">
                                {{ org.name }}
                            </h3>
                            <p class="mt-2 text-[10px] font-bold text-gray-500 uppercase tracking-widest leading-relaxed line-clamp-2">
                                {{ org.description }}
                            </p>

                            <div class="mt-8 pt-6 border-t border-white/5 flex items-center justify-between">
                                <span class="text-[8px] font-black text-gray-600 uppercase tracking-widest italic">Created {{ org.created_at }}</span>
                                <svg class="h-4 w-4 text-gray-600 group-hover:text-[#CBB48A] group-hover:translate-x-1 transition-all" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </div>
                        </div>
                    </Link>

                    <!-- Add New Card -->
                    <button 
                        @click="isAgency ? openCreateModal() : scrollToUpgrade()"
                        class="group relative overflow-hidden bg-white/[0.02] border border-dashed border-white/10 rounded-[2.5rem] p-8 flex flex-col items-center justify-center hover:bg-white/[0.04] hover:border-[#CBB48A]/30 transition-all"
                    >
                        <div class="h-12 w-12 rounded-full bg-white/5 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                            <svg class="h-6 w-6 text-gray-500 group-hover:text-[#CBB48A]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] group-hover:text-white transition-colors">Add New Organization</span>
                    </button>

                    <!-- Add New Card -->
                    <button 
                        @click="isAgency ? openCreateModal() : scrollToUpgrade()"
                        class="group relative overflow-hidden bg-white/[0.02] border border-dashed border-white/10 rounded-[2.5rem] p-8 flex flex-col items-center justify-center hover:bg-white/[0.04] hover:border-[#CBB48A]/30 transition-all"
                    >
                        <div class="h-12 w-12 rounded-full bg-white/5 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                            <svg class="h-6 w-6 text-gray-500 group-hover:text-[#CBB48A]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] group-hover:text-white transition-colors">Add New Organization</span>
                    </button>
                </div>

                <!-- Empty State / Upgrade CTA -->
                <div v-if="props.organizations.length === 0 || !isAgency" ref="upgradeSection" class="mt-12">
                    <div class="relative overflow-hidden group">
                        <!-- Cinematic Backdrop -->
                        <div class="absolute inset-0 bg-gradient-to-br from-[#CBB48A]/10 via-[#F3E7C9]/5 to-transparent rounded-[3rem]"></div>
                        <div class="absolute -right-48 -top-48 h-96 w-96 rounded-full bg-[#CBB48A]/10 blur-[120px] group-hover:bg-[#CBB48A]/20 transition-all duration-1000"></div>
                        
                        <div class="relative z-10 p-12 md:p-20 flex flex-col items-center text-center">
                            <div class="mb-10 relative">
                                <div class="h-24 w-24 rounded-[2rem] bg-gradient-to-br from-[#CBB48A] to-[#F3E7C9] flex items-center justify-center p-0.5 shadow-[0_0_50px_rgba(203, 180, 138, 0.3)]">
                                    <div class="w-full h-full bg-[#0A0A0B] rounded-[1.9rem] flex items-center justify-center">
                                        <svg class="h-10 w-10 text-[#CBB48A]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </div>
                                </div>
                                <!-- Floating Particles -->
                                <div class="absolute -top-4 -right-4 h-4 w-4 bg-[#F3E7C9] rounded-full blur-md animate-bounce"></div>
                                <div class="absolute -bottom-2 -left-6 h-3 w-3 bg-[#CBB48A] rounded-full blur-sm animate-pulse"></div>
                            </div>

                            <h2 class="text-4xl md:text-6xl font-bold tracking-tight text-white uppercase leading-none max-w-2xl">
                                Unlock <span class="bg-gradient-to-r from-[#CBB48A] to-[#F3E7C9] bg-clip-text text-transparent">Enterprise Structure</span>
                            </h2>
                            
                            <p class="mt-8 text-sm md:text-lg font-bold text-gray-400 uppercase tracking-widest max-w-xl leading-relaxed">
                                Organization management is a <span class="text-[#CBB48A]">Sovereign-Tier</span> capability. Elevate your operation to create multiple organizational units and isolated teams.
                            </p>

                            <div v-if="!isAgency" class="mt-12 flex flex-col sm:flex-row items-center gap-6">
                                <Link 
                                    :href="route('billing.index')"
                                    class="px-10 py-5 rounded-2xl bg-gradient-to-r from-[#CBB48A] to-[#F3E7C9] text-slate-950 text-xs font-black uppercase tracking-[0.2em] shadow-[0_0_40px_rgba(203, 180, 138, 0.4)] hover:scale-105 active:scale-95 transition-all"
                                >
                                    Upgrade to Team Plan
                                </Link>
                                <Link 
                                    :href="route('documentation.index')"
                                    class="px-10 py-5 rounded-2xl bg-white/5 border border-white/10 text-white text-xs font-black uppercase tracking-[0.2em] hover:bg-white/10 transition-all text-center"
                                >
                                    View Documentation
                                </Link>
                            </div>
                             <div v-else-if="props.organizations.length === 0" class="mt-12">
                                <button 
                                    @click="openCreateModal"
                                    class="px-10 py-5 rounded-2xl bg-[#CBB48A] text-slate-950 text-xs font-black uppercase tracking-[0.2em] shadow-[0_0_40px_rgba(203, 180, 138, 0.3)] hover:scale-105 active:scale-95 transition-all"
                                >
                                    Create Your First Organazitaion
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Create Organization Modal -->
                <div v-if="showCreateModal" class="fixed inset-0 z-[100] flex items-center justify-center p-6 sm:p-0">
                    <div class="absolute inset-0 bg-[#0A0A0B] backdrop-blur-xl" @click="closeCreateModal"></div>
                    
                    <div class="relative w-full max-w-lg bg-[#0A0A0B] rounded-[3rem] border border-white/10 p-12 shadow-[0_0_100px_rgba(0,0,0,0.5)] animate-in fade-in zoom-in duration-500">
                        <div class="mb-10 text-center">
                            <div class="h-20 w-20 rounded-full bg-[#CBB48A]/10 flex items-center justify-center mx-auto mb-6">
                                <svg class="h-10 w-10 text-[#CBB48A]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <h2 class="text-3xl font-bold tracking-tight text-white uppercase mb-2">Form New Organization</h2>
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest leading-relaxed">Establish your sovereign operational unit. All nodes will be cryptographically isolated.</p>
                        </div>

                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label class="block text-[10px] font-black text-[#CBB48A] uppercase tracking-widest mb-3 italic">Organization Identity (NAME)</label>
                                <input 
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Enter organization name..."
                                    class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-sm font-bold text-white placeholder-gray-700 focus:ring-[#CBB48A]/30 focus:border-[#CBB48A]/50 outline-none transition-all uppercase"
                                    required
                                    autofocus
                                />
                                <div v-if="form.errors.name" class="mt-2 text-[10px] font-black text-rose-500 uppercase tracking-widest">{{ form.errors.name }}</div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mt-10">
                                <button type="button" @click="closeCreateModal" class="py-4 rounded-2xl bg-white/5 border border-white/10 text-[10px] font-bold tracking-wider text-gray-400 hover:text-white hover:bg-white/10 transition-all">
                                    Abort
                                </button>
                                <button 
                                    type="submit"
                                    :disabled="form.processing"
                                    class="py-4 rounded-2xl bg-[#CBB48A] text-slate-950 text-[10px] font-bold tracking-wider shadow-[0_0_30px_rgba(203, 180, 138, 0.3)] hover:bg-[#CBB48A] transition-all disabled:opacity-50"
                                >
                                    {{ form.processing ? 'Establishing...' : 'Confirm Formation' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;  
  overflow: hidden;
}
</style>
