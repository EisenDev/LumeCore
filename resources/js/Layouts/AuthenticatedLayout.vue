<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Sidebar from '@/Components/Sidebar.vue';
import LumeAISupport from '@/Components/LumeAISupport.vue';
import CreditPurchaseModal from '@/Components/CreditPurchaseModal.vue';
import axios from 'axios';

const isSidebarOpen = ref(true);
const showCreditModal = ref(false);
const showNotificationsDropdown = ref(false);

const page = usePage();
const unreadNotifications = computed(() => (page.props.auth as any)?.unread_notifications || []);
const unreadCount = computed(() => (page.props.auth as any)?.unread_notifications_count || 0);

// Auto-collapse on small screens
onMounted(() => {
    if (window.innerWidth < 1024) {
        isSidebarOpen.value = false;
    }
});

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};

const markAsRead = async (id: string, actionUrl?: string) => {
    try {
        await axios.patch(`/notifications/${id}/read`);
        router.reload({ only: ['auth'] });
        if (actionUrl) {
            router.visit(actionUrl);
            showNotificationsDropdown.value = false;
        }
    } catch (error) {
        console.error('Failed to mark notification as read:', error);
    }
};

const markAllAsRead = async () => {
    try {
        await axios.post('/notifications/read-all');
        router.reload({ only: ['auth'] });
    } catch (error) {
        console.error('Failed to mark all notifications as read:', error);
    }
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
            <header class="flex h-20 shrink-0 items-center justify-between border-b-0 bg-transparent px-8 transition-all duration-500 sticky top-0 z-40">
                
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
                    
                    <div v-if="$slots.header" class="leading-none">
                        <slot name="header" />
                    </div>
                </div>

                <!-- Right Side: User Dropdown -->
                <div class="flex items-center gap-4">
                    <!-- Search Bar -->
                    <div class="relative hidden md:block w-64 mr-2">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input 
                            type="text" 
                            readonly
                            class="block w-full rounded-xl border-white/5 bg-white/[0.02] pl-9 pr-12 py-1.5 text-xs text-white placeholder-slate-500 focus:border-[#CBB48A]/50 focus:ring-0 focus:outline-none transition-all font-medium" 
                            placeholder="Search anything..."
                        >
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                            <kbd class="text-[9px] font-mono text-slate-600 bg-white/5 border border-white/10 px-1.5 py-0.5 rounded">⌘K</kbd>
                        </div>
                    </div>

                    <!-- Credits Display -->
                    <button 
                        @click="showCreditModal = true"
                        class="hidden md:flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] px-4 py-1.5 text-[10px] font-bold tracking-wider text-[#CBB48A] hover:bg-white/[0.08] hover:border-[#CBB48A]/30 transition-all active:scale-95 shadow-lg shadow-[#CBB48A]/5"
                    >
                        <svg class="h-4 w-4 text-[#CBB48A]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.286L13 21l-2.286-6.857L5 12l5.714-2.286L13 3z" />
                        </svg>
                        <span>{{ Number($page.props.auth.user.credits || 0).toFixed(0) }} Credits</span>
                    </button>

                    <!-- Floating Notification Bell -->
                    <div class="relative">
                        <button 
                            @click="showNotificationsDropdown = !showNotificationsDropdown"
                            class="relative p-2 rounded-xl text-gray-400 hover:text-white hover:bg-white/5 transition-all duration-300"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span v-if="unreadCount > 0" class="absolute -top-0.5 -right-0.5 h-4 w-4 rounded-full bg-red-600 text-[9px] font-bold text-white flex items-center justify-center animate-pulse">
                                {{ unreadCount }}
                            </span>
                        </button>

                        <!-- Invisible Click-out Overlay -->
                        <div v-if="showNotificationsDropdown" class="fixed inset-0 z-40" @click="showNotificationsDropdown = false"></div>

                        <!-- Dropdown Menu -->
                        <div 
                            v-if="showNotificationsDropdown"
                            class="absolute right-0 mt-2 w-80 rounded-2xl border border-white/5 bg-[#09090B] p-4 shadow-2xl shadow-black/80 z-50 text-left font-sans"
                        >
                            <div class="flex items-center justify-between border-b border-white/5 pb-2 mb-3">
                                <h3 class="text-xs font-bold text-white uppercase tracking-wider">Notifications</h3>
                                <button 
                                    v-if="unreadCount > 0"
                                    @click="markAllAsRead"
                                    class="text-[9px] font-bold text-[#CBB48A] hover:underline uppercase tracking-wider"
                                >
                                    Mark all read
                                </button>
                            </div>

                            <div class="space-y-3 max-h-64 overflow-y-auto custom-scrollbar">
                                <div 
                                    v-for="notification in unreadNotifications" 
                                    :key="notification.id"
                                    @click="markAsRead(notification.id, notification.data.action_url)"
                                    class="p-2.5 rounded-xl border border-white/[0.02] bg-white/[0.01] hover:bg-white/[0.03] transition-all cursor-pointer flex gap-3 items-start group"
                                >
                                    <!-- Indicator Dot -->
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mt-1.5 shrink-0 group-hover:bg-[#CBB48A]"></span>
                                    <div class="space-y-1 min-w-0 flex-1">
                                        <h4 class="text-xs font-bold text-white group-hover:text-[#CBB48A] transition-colors truncate">
                                            {{ notification.data.title || 'Notification' }}
                                        </h4>
                                        <p class="text-[10px] text-slate-400 leading-normal line-clamp-2">
                                            {{ notification.data.message || '' }}
                                        </p>
                                        <span class="text-[9px] text-slate-500 font-mono block">
                                            {{ notification.time_ago }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Empty State -->
                                <div 
                                    v-if="unreadNotifications.length === 0"
                                    class="py-8 text-center space-y-2"
                                >
                                    <svg class="h-8 w-8 text-slate-600 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">No unread notifications</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Help Question Mark Icon -->
                    <button class="p-2 rounded-xl text-gray-400 hover:text-white hover:bg-white/5 transition-all duration-300">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </button>

                    <!-- User Menu -->
                    <div class="relative">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-xl border border-transparent p-1 focus:outline-none"
                                >
                                    <!-- Avatar -->
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#5b51d8] font-bold text-white text-xs border border-[#7d74eb]/20 shadow-lg shadow-[#5b51d8]/10 hover:bg-[#6c63e6] transition-all">
                                        AD
                                    </div>
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
                    <div class="absolute -top-[10%] left-[20%] h-[500px] w-[500px] rounded-full bg-[#CBB48A]/5 blur-[120px] animate-pulse"></div>
                    <div class="absolute bottom-[20%] right-[10%] h-[400px] w-[400px] rounded-full bg-[#F3E7C9]/5 blur-[100px] animate-pulse" style="animation-delay: 2s"></div>
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
