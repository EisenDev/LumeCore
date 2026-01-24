<script setup lang="ts">
/**
 * AssetHistoryModal Component
 * Displays audit history for a VaultAsset.
 */
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';
import type { VaultAsset } from '@/types/vault';

interface AuditHistoryItem {
    id: string;
    score: number;
    status: string;
    metadata: any;
    created_at: string;
}

interface Props {
    show: boolean;
    asset: VaultAsset | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
}>();

const history = ref<AuditHistoryItem[]>([]);
const isLoading = ref(false);

async function fetchHistory() {
    if (!props.asset?.id) return;
    
    isLoading.value = true;
    try {
        const { data } = await axios.get(`/api/vault/assets/${props.asset.id}/history`);
        history.value = data.history ?? [];
    } catch (error) {
        console.error('Failed to fetch audit history:', error);
        history.value = [];
    } finally {
        isLoading.value = false;
    }
}

watch(() => props.show, (newVal) => {
    if (newVal && props.asset) {
        fetchHistory();
    }
});

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getScoreColor(score: number): string {
    if (score >= 85) return 'text-emerald-400';
    if (score >= 70) return 'text-amber-400';
    return 'text-rose-400';
}

function getStatusBadge(status: string): string {
    switch (status) {
        case 'verified': return 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30';
        case 'verified_private': return 'bg-purple-500/20 text-purple-400 border-purple-500/30';
        case 'flagged': return 'bg-rose-500/20 text-rose-400 border-rose-500/30';
        case 'action_required': return 'bg-amber-500/20 text-amber-400 border-amber-500/30';
        default: return 'bg-slate-500/20 text-slate-400 border-slate-500/30';
    }
}

const closeModal = () => emit('close');
</script>

<template>
    <Teleport to="body">
        <transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200"
            leave-to-class="opacity-0"
        >
            <div v-if="show && asset" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="closeModal"></div>
                
                <!-- Modal -->
                <div class="relative w-full max-w-lg max-h-[80vh] overflow-hidden rounded-2xl border border-slate-700 bg-[#0f172a] shadow-2xl">
                    
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-slate-700 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-indigo-500/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-white">Audit History</h2>
                                <p class="text-xs text-slate-400 truncate max-w-xs">{{ asset.file_name }}</p>
                            </div>
                        </div>
                        <button @click="closeModal" class="p-2 rounded-lg text-gray-400 hover:bg-white/10 hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Body -->
                    <div class="overflow-y-auto max-h-[60vh] p-6">
                        <!-- Loading -->
                        <div v-if="isLoading" class="flex items-center justify-center py-12">
                            <svg class="w-8 h-8 text-indigo-500 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        
                        <!-- Empty State -->
                        <div v-else-if="history.length === 0" class="text-center py-12">
                            <svg class="w-12 h-12 mx-auto text-slate-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <p class="text-slate-400 text-sm">No audit history available.</p>
                            <p class="text-slate-500 text-xs mt-1">This asset hasn't been re-scanned yet.</p>
                        </div>
                        
                        <!-- Timeline -->
                        <div v-else class="space-y-4">
                            <div 
                                v-for="(item, idx) in history" 
                                :key="item.id"
                                class="relative pl-6 pb-4"
                                :class="{ 'border-l-2 border-slate-700': idx < history.length - 1 }"
                            >
                                <!-- Dot -->
                                <div 
                                    class="absolute left-0 -translate-x-1/2 w-3 h-3 rounded-full border-2 border-[#0f172a]"
                                    :class="idx === 0 ? 'bg-indigo-500' : 'bg-slate-600'"
                                ></div>
                                
                                <!-- Card -->
                                <div class="bg-slate-800/50 rounded-lg border border-slate-700/50 p-4 ml-2">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs text-slate-500">{{ formatDate(item.created_at) }}</span>
                                        <span 
                                            class="text-xs font-semibold px-2 py-0.5 rounded border"
                                            :class="getStatusBadge(item.status)"
                                        >
                                            {{ item.status.replace('_', ' ').toUpperCase() }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span 
                                            class="text-2xl font-black"
                                            :class="getScoreColor(item.score)"
                                        >
                                            {{ item.score }}
                                        </span>
                                        <span class="text-xs text-slate-400">Trust Score</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="border-t border-slate-700 px-6 py-4 flex justify-end">
                        <button 
                            @click="closeModal"
                            class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white text-sm font-medium rounded-lg transition-colors"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
