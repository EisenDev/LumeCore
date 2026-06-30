<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'toggle'): void;
}>();

const sidebarClass = computed(() => {
    return props.open ? 'w-56' : 'w-20 lg:w-20 w-0 overflow-hidden border-none';
});

const page = usePage();
const activeOrgId = computed(() => (page.props.auth as any).user?.active_organization_id);

// Grouped Navigation Sections (Image Sidebar styling)
// Grouped Navigation Sections (Image Sidebar styling)
const navSections = computed(() => [
    {
        title: 'OVERVIEW',
        items: [
            {
                name: 'Overview',
                route: 'overview',
                icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                active: 'overview'
            },
            {
                name: 'Scans',
                route: 'scans.index',
                icon: 'M12 21a9 9 0 110-18 9 9 0 010 18zm0-3a6 6 0 100-12 6 6 0 000 12zm0-3a3 3 0 110-6 3 3 0 010 6z', // Radar Target icon
                active: 'scans.index'
            },
            {
                name: 'Assets',
                route: 'overview',
                icon: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
                active: 'assets.*'
            },
            {
                name: 'Reports',
                route: 'reports.index',
                icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                active: 'reports.*'
            },
            {
                name: 'AI Assistant',
                route: 'ai-assistant.index',
                icon: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                active: 'ai-assistant.*'
            }
        ]
    },
    {
        title: 'DISCOVERY',
        items: [
            {
                name: 'Targets',
                route: 'targets.index',
                icon: 'M12 3v1.5M12 19.5V21M3 12h1.5M19.5 12H21M12 8.25a3.75 3.75 0 100 7.5 3.75 3.75 0 000-7.5z',
                active: 'targets.*'
            },
            {
                name: 'Schedules',
                route: 'schedules.index',
                icon: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
                active: 'schedules.*'
            }
        ]
    },
    {
        title: 'WORKSPACE',
        items: [
            {
                name: 'Integrations',
                route: 'integrations.index',
                icon: 'M14.25 9.75L16.5 12l-2.25 2.25m-4.5 0L7.5 12l2.25-2.25M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z',
                active: 'integrations.*'
            },
            {
                name: 'Teams',
                route: 'teams.index',
                icon: 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
                active: 'team.*'
            },
            {
                name: 'Settings',
                route: 'profile.edit',
                icon: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.43l-1.003.828c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.43l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128c.332-.183.582-.495.645-.869l.214-1.28z',
                active: 'profile.*'
            }
        ]
    }
]);
</script>

<template>
    <!-- Mobile Backdrop -->
    <div 
        v-if="open" 
        class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
        @click="$emit('toggle')"
    ></div>

    <aside
        class="flex flex-col border-r border-white/5 bg-[#09090B] transition-all duration-300 fixed inset-y-0 left-0 z-50 lg:static"
        :class="sidebarClass"
    >
        <!-- Logo Area -->
        <div class="flex h-20 shrink-0 items-center border-b border-white/5 px-4 bg-gradient-to-r from-[#CBB48A]/5 to-transparent py-4">
            <Link :href="route('overview')" class="flex items-center gap-3 overflow-hidden group">
                <img src="/images/none-transparent-logo.png" alt="Lume Logo" class="h-9 w-9 object-contain transition-transform group-hover:scale-105 shrink-0" />
                <div class="flex flex-col min-w-0 transition-opacity duration-300" :class="open ? 'opacity-100' : 'opacity-0 w-0 overflow-hidden'">
                    <span class="truncate font-display text-[15px] font-black tracking-[0.12em] text-white">
                        LUME
                    </span>
                    <span class="text-[7.5px] font-bold tracking-[0.18em] text-[#CBB48A] uppercase whitespace-nowrap">
                        SOVEREIGN FORENSICS
                    </span>
                </div>
            </Link>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 space-y-6 overflow-y-auto p-3" aria-label="Sidebar">
            <div v-for="section in navSections" :key="section.title" class="space-y-2">
                <!-- Section Title (only if open) -->
                <h3 v-if="open" class="px-3 text-[10px] font-bold tracking-[0.15em] text-gray-500 uppercase">
                    {{ section.title }}
                </h3>
                <div v-else class="h-px bg-white/5 mx-2 my-4"></div>
                
                <!-- Section Items -->
                <div class="space-y-0.5">
                    <Link
                        v-for="item in section.items"
                        :key="item.name"
                        :href="item.routeParams ? route(item.route, item.routeParams) : route(item.route)"
                        class="group flex items-center rounded-lg px-3 py-2.5 text-xs font-semibold tracking-wide transition-all border border-transparent"
                        :class="route().current(item.active)
                            ? 'bg-[#CBB48A]/10 text-[#CBB48A] border-[#CBB48A]/20 shadow-[0_0_15px_rgba(203,180,138,0.05)]'
                            : 'text-gray-400 hover:bg-white/[0.03] hover:text-white'"
                        :title="!open ? item.name : ''"
                    >
                        <!-- Icon -->
                        <svg
                            class="h-5 w-5 shrink-0 transition-colors"
                            :class="route().current(item.active) ? 'text-[#CBB48A]' : 'text-gray-500 group-hover:text-gray-300'"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>

                        <!-- Text label -->
                        <span
                            class="ml-3 truncate transition-all duration-300"
                            :class="open ? 'opacity-100 w-auto' : 'opacity-0 w-0 overflow-hidden'"
                        >
                            {{ item.name }}
                        </span>
                    </Link>
                </div>
            </div>
        </nav>

        <!-- Bottom Status & Analyst section -->
        <div class="p-3 border-t border-white/5 space-y-3 bg-[#070708]/80 shrink-0">
            <!-- Scan Integrity Status Card -->
            <div 
                class="rounded-xl border border-emerald-500/10 bg-emerald-500/[0.02] p-3 transition-all duration-300"
                :class="open ? 'flex items-center gap-3' : 'flex justify-center'"
                title="Scan integrity status"
            >
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-400">
                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0110 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0114 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" />
                    </svg>
                </div>
                <div v-if="open" class="flex flex-col min-w-0">
                    <span class="text-[11px] font-bold text-white tracking-wide">Scan integrity</span>
                    <span class="text-[9px] font-semibold text-emerald-400/90 tracking-wider uppercase">VERIFIED (SHA-256)</span>
                </div>
            </div>

            <!-- Analyst Profile Block -->
            <div 
                class="flex items-center justify-between rounded-xl bg-white/[0.01] hover:bg-white/[0.03] p-2 transition-all duration-200 cursor-pointer border border-white/5"
                :class="open ? 'gap-3' : 'justify-center'"
            >
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-600/80 font-bold text-white text-xs shadow-lg shadow-indigo-600/10 border border-indigo-400/20">
                        AD
                    </div>
                    <div v-if="open" class="flex flex-col min-w-0">
                        <span class="text-xs font-bold text-white truncate">Analyst</span>
                        <span class="text-[10px] font-medium text-gray-500 truncate">Digital Forensics</span>
                    </div>
                </div>
                <svg v-if="open" class="h-4 w-4 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </div>

            <!-- Collapse Toggle Button -->
            <button
                @click="$emit('toggle')"
                class="flex items-center gap-3 text-[10px] font-bold uppercase tracking-widest text-gray-500 hover:text-white transition-colors group w-full text-left focus:outline-none py-2 px-2"
            >
                <div class="h-6 w-6 rounded bg-white/5 flex items-center justify-center group-hover:bg-white/10 transition-colors shrink-0">
                    <svg class="h-3 w-3 transition-transform" :class="!open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                </div>
                <span v-if="open" class="tracking-[0.1em]">Collapse Navigation</span>
            </button>
        </div>
    </aside>
</template>
