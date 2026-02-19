<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { computed } from 'vue';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'toggle'): void;
}>();

const sidebarClass = computed(() => {
    return props.open ? 'w-52' : 'w-20 lg:w-20 w-0 overflow-hidden border-none';
});

// Navigation Items
const navItems = [
    {
        name: 'Dashboard',
        route: 'dashboard',
        icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        active: 'dashboard'
    },
    {
        name: 'Marketplace',
        route: 'marketplace.index',
        icon: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
        active: 'marketplace.*'
    },
    {
        name: 'Organazitaions',
        route: 'organizations.index',
        icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        active: 'organizations.*'
    },
    {
        name: 'Billing & Usage',
        route: 'billing.index',
        icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        active: 'billing.index'
    }
];

// Helper to check active route
const isActive = (routeName: string | null) => {
    if (!routeName) return false;
    // Simple check, can use route().current() in template
    return false; // Handled in template via $page or active prop
};

</script>

<template>
    <!-- Mobile Backdrop -->
    <div 
        v-if="open" 
        class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
        @click="$emit('toggle')"
    ></div>

    <aside
        class="flex flex-col border-r border-white/5 bg-[#0A0A0B] transition-all duration-300 fixed inset-y-0 left-0 z-50 lg:static"
        :class="sidebarClass"
    >
        <!-- Logo Area -->
        <div class="flex h-16 items-center border-b border-white/5 px-4 bg-gradient-to-r from-emerald-400/5 to-transparent">
            <Link :href="route('dashboard')" class="flex items-center gap-3 overflow-hidden group">
                <div class="relative flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-400 to-cyan-400 shadow-lg shadow-emerald-400/20 group-hover:scale-105 transition-transform">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <span
                    class="truncate font-black text-white uppercase italic tracking-tighter transition-opacity duration-300"
                    :class="open ? 'opacity-100' : 'opacity-0 w-0'"
                >
                    LUME<span class="text-emerald-400 not-italic">CORE</span>
                </span>
            </Link>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 space-y-1 overflow-y-auto p-2" aria-label="Sidebar">
            <template v-for="item in navItems" :key="item.name">
                <Link
                    v-if="item.route"
                    :href="route(item.route)"
                    class="group flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                    :class="route().current(item.active) 
                        ? 'bg-emerald-400/10 text-emerald-400 shadow-[0_0_15px_rgba(52,211,153,0.1)] border border-emerald-400/20' 
                        : 'text-gray-400 hover:bg-white/[0.05] hover:text-white'"
                    :title="!open ? item.name : ''"
                >
                    <!-- Icon -->
                    <svg
                        class="h-6 w-6 shrink-0 transition-colors"
                        :class="route().current(item.active) ? 'text-emerald-400' : 'text-gray-500 group-hover:text-gray-300'"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        <path v-if="item.icon2" stroke-linecap="round" stroke-linejoin="round" :d="item.icon2" />
                    </svg>

                    <!-- Text label -->
                    <span
                        class="ml-3 truncate transition-all duration-300 font-bold tracking-tight"
                        :class="open ? 'opacity-100 w-auto' : 'opacity-0 w-0 overflow-hidden'"
                    >
                        {{ item.name }}
                    </span>
                </Link>
                
                <!-- Standard Link Loop Covers Marketplace Now -->
            </template>
        </nav>

        <!-- Documentation Footer Links -->
        <div class="p-4 border-t border-white/5 space-y-3" v-if="open">
            <div class="h-px bg-white/10 w-full mb-2"></div>
            
            <a 
                :href="route('documentation.index')" 
                target="_blank"
                class="flex items-center gap-3 text-xs font-bold uppercase tracking-widest text-gray-500 hover:text-white transition-colors group"
            >
                <div class="h-6 w-6 rounded bg-white/5 flex items-center justify-center group-hover:bg-white/10">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                </div>
                Docs
            </a>

            <a 
                href="/docs#roadmap" 
                target="_blank"
                class="flex items-center gap-3 text-xs font-bold uppercase tracking-widest text-gray-500 hover:text-emerald-400 transition-colors group"
            >
                <div class="h-6 w-6 rounded bg-emerald-500/10 flex items-center justify-center group-hover:bg-emerald-500/20">
                    <svg class="h-3 w-3 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894l4.816 2.408a2 2 0 001.474 0l5.526-2.763a2 2 0 011.474 0l5.526 2.763a1 1 0 011.447.894v10.764a1 1 0 01-.553.894L15 20l-6-3-6 3z" /></svg>
                </div>
                Road Map
            </a>
        </div>

        <!-- Bottom Actions -->
        <div class="border-t border-gray-100 p-2 dark:border-gray-800">
            <!-- Collapse Toggle -->
            <button
                @click="$emit('toggle')"
                class="flex w-full items-center justify-center rounded-lg py-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                :title="open ? 'Collapse Sidebar' : 'Expand Sidebar'"
            >
                <svg
                    class="h-6 w-6 transition-transform duration-300"
                    :class="open ? 'rotate-180' : ''"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                </svg>
            </button>
        </div>
    </aside>
</template>
