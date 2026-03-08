<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Sidebar from '@/Components/Sidebar.vue';
import LumeAISupport from '@/Components/LumeAISupport.vue';
import CreditPurchaseModal from '@/Components/CreditPurchaseModal.vue';

const isSidebarOpen = ref(true);
const showCreditModal = ref(false);

// Auto-collapse on small screens
onMounted(() => {
    if (window.innerWidth < 1024) {
        isSidebarOpen.value = false;
    }
});

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-[#0A0A0B]">
        <!-- Sidebar -->
        <Sidebar 
            :open="isSidebarOpen" 
            @toggle="toggleSidebar"
            @visit-marketplace="() => {}" 
        />

        <!-- Main Content Area -->
        <div class="flex flex-1 flex-col overflow-hidden transition-all duration-300">
            
            <!-- Top Header (User Menu + Page Title Slot) -->
            <header class="flex h-16 shrink-0 items-center justify-between border-b border-white/5 bg-[#0A0A0B]/60 backdrop-blur-3xl px-6 transition-all duration-500 sticky top-0 z-40">
                
                <!-- Page Title / Header Slot -->
                <div class="flex items-center gap-4">
                    <!-- Mobile Hamburger -->
                    <button 
                        @click="toggleSidebar"
                        class="lg:hidden flex items-center justify-center p-2 rounded-xl bg-white/5 text-gray-400 hover:text-white"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <!-- Mobile Toggle (Visible only on small screens if needed, or if sidebar acts as drawer) -->
                    <!-- For now, we rely on sidebar collapse button, but on mobile we might want a hamburger here if sidebar is hidden -->
                    
                    <div v-if="$slots.header" class="text-2xl font-black italic tracking-tighter text-white uppercase leading-none mt-1">
                        <slot name="header" />
                    </div>
                </div>

                <!-- Right Side: User Dropdown -->
                <div class="flex items-center gap-4">
                    <!-- Credits Display (Mini) - Optional, mimicking dashboard card -->
                    <!-- Credits Display (Mini) -->
                    <button 
                        @click="showCreditModal = true"
                        class="hidden md:flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-4 py-1.5 text-[10px] font-black uppercase tracking-widest text-emerald-400 hover:bg-emerald-400/20 transition-all active:scale-95 shadow-lg shadow-emerald-400/10"
                    >
                       <div class="relative flex h-2 w-2">
                           <div class="absolute inset-0 animate-ping rounded-full bg-emerald-400/40 opacity-75"></div>
                           <div class="relative h-2 w-2 rounded-full bg-emerald-400"></div>
                       </div>
                       <span>{{ Number($page.props.auth.user.credits || 0).toFixed(0) }} Credits</span>
                    </button>

                    <!-- User Menu -->
                    <div class="relative">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-3 rounded-xl border border-transparent px-3 py-2 text-sm font-bold leading-4 text-slate-300 transition-all hover:bg-white/[0.07] hover:text-white focus:outline-none"
                                >
                                    <!-- Avatar -->
                                    <div class="relative">
                                        <div class="absolute -inset-1 rounded-full bg-gradient-to-r from-emerald-400 to-cyan-400 opacity-20 blur-sm"></div>
                                        <div class="relative h-9 w-9 overflow-hidden rounded-full border border-emerald-400/30 bg-gradient-to-br from-emerald-400 to-cyan-400 flex items-center justify-center text-white font-black text-sm shadow-lg shadow-emerald-400/20">
                                            {{ $page.props.auth.user.name.charAt(0) }}
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-start">
                                        <span class="hidden md:inline-block leading-none">{{ $page.props.auth.user.name }}</span>
                                        <span v-if="$page.props.auth.user.active_subscription?.plan_type === 'agency'" class="mt-0.5 text-[8px] font-bold bg-purple-500/20 text-purple-400 border border-purple-500/30 px-1 rounded uppercase tracking-tighter">
                                            Agency Badge
                                        </span>
                                        <span v-else-if="$page.props.auth.user.active_subscription?.plan_type === 'developer'" class="mt-0.5 text-[8px] font-bold bg-blue-500/20 text-blue-400 border border-blue-500/30 px-1 rounded uppercase tracking-tighter">
                                            Developer Badge
                                        </span>
                                    </div>

                                    <svg
                                        class="-me-0.5 ms-2 h-4 w-4"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </template>

                            <template #content>
                                <div class="px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest text-slate-500">
                                    Account: <span class="text-slate-300 font-mono lower-case tracking-normal">{{ $page.props.auth.user.email }}</span>
                                </div>
                                <div class="border-t border-white/5"></div>

                                <DropdownLink :href="route('profile.edit')">
                                    Profile
                                </DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">
                                    Log Out
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </header>

            <!-- Page Content Scroll Area -->
            <main class="flex-1 overflow-y-auto relative custom-scrollbar">
                <!-- Grainy Overlay -->
                <div class="pointer-events-none fixed inset-0 z-50 opacity-[0.03] mix-blend-overlay" style="background-image: url('https://grainy-gradients.vercel.app/noise.svg')"></div>
                
                <!-- Mesh Gradients -->
                <div class="pointer-events-none absolute inset-0 overflow-hidden">
                    <div class="absolute -top-[10%] left-[20%] h-[500px] w-[500px] rounded-full bg-emerald-400/5 blur-[120px] animate-pulse"></div>
                    <div class="absolute bottom-[20%] right-[10%] h-[400px] w-[400px] rounded-full bg-cyan-400/5 blur-[100px] animate-pulse" style="animation-delay: 2s"></div>
                </div>

                <div class="relative z-10 p-4 sm:p-6 lg:p-10">
                    <slot />
                </div>
            </main>
        </div>

        <!-- Global AI Helper Bot -->
        <LumeAISupport mode="global" />

        <!-- Global Credit Purchase Modal -->
        <CreditPurchaseModal
            :show="showCreditModal"
            @close="showCreditModal = false"
        />
    </div>
</template>
