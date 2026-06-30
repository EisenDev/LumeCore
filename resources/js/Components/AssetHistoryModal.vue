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
    individual_score?: number; // TITAN V6.5
    sync_score?: number;       // TITAN V6.5
    linked_id?: string;        // TITAN V6.5
    scanned_type?: string;     // TITAN V6.6
    status: string;
    metadata: any;
    created_at: string;
    // Triad support
    is_triad?: boolean;
    batch_id?: string;
    entries?: {
        sync: AuditHistoryItem | null;
        website: AuditHistoryItem | null;
        repository: AuditHistoryItem | null;
    }
}

interface Props {
    show: boolean;
    asset: VaultAsset | null;
    filterType?: string; // e.g. 'sync', 'website', 'repository'
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'view-snapshot', payload: { item: AuditHistoryItem, list: AuditHistoryItem[] }): void;
}>();

const history = ref<AuditHistoryItem[]>([]);
const isLoading = ref(false);

async function fetchHistory() {
    if (!props.asset?.id) return;
    
    isLoading.value = true;


    try {
        // 1. Fetch Main History (Website/Sync)
        const mainUrl = props.filterType 
            ? `/api/vault/assets/${props.asset.id}/history?type=${props.filterType}`
            : `/api/vault/assets/${props.asset.id}/history`;
        
        const mainResponse = await axios.get(mainUrl);
        const mainHistory = mainResponse.data.history ?? [];

        // 2. Determine Linked Repository ID
        // TITAN V6.6: Check both Prop (fast path) and API Response (robust path)
        let repoAssetId = props.asset.synced_assets?.repo;
        
        if (!repoAssetId && mainResponse.data.synced_assets?.repo) {
            repoAssetId = mainResponse.data.synced_assets.repo;
        }

        // 3. Conditional Fetch Sibling (Repository)
        let repoHistory: AuditHistoryItem[] = [];
        
        if (repoAssetId && repoAssetId !== props.asset.id) {
             try {
                const repoResponse = await axios.get(`/api/vault/assets/${repoAssetId}/history`);
                repoHistory = repoResponse.data.history ?? [];
             } catch (err) {
                 console.warn("Failed to fetch sibling repository history", err);
             }
        }

        // 3. Merge Logic (Stitch Repository Entries into Main History)
        if (repoHistory.length > 0) {
            const historyMap = new Map();
            const legacyItems: AuditHistoryItem[] = []; // TITAN V6.6 FIX: Preserve legacy items

            // Populate Main Map
            mainHistory.forEach((item: AuditHistoryItem) => {
                if (item.batch_id) {
                    historyMap.set(item.batch_id.trim(), item); // TITAN V6.7: Trim
                } else {
                    legacyItems.push(item);
                }
            });

            // Merge Repo Items
            repoHistory.forEach((repoItem: AuditHistoryItem) => {
                const batchId = repoItem.batch_id ? repoItem.batch_id.trim() : null; // TITAN V6.7: Trim
                if (!batchId) return;

                let repoEntry: AuditHistoryItem | null = null;

                // CASE 1: Identifying the Repository Entry
                // TITAN V6.6: Robust check using scanned_type
                if (repoItem.scanned_type === 'repository') {
                     repoEntry = repoItem;
                }
                // Fallback: Check for wrapped entry
                else if (repoItem.entries?.repository) {
                    repoEntry = repoItem.entries.repository;
                }
                // Fallback: Unwrapped item without type (legacy guessing)
                // CRITICAL FIX: Explicitly ignore 'sync' or 'website' to prevent overwriting
                else if (!repoItem.entries && !['sync', 'website', 'sync_scan', 'website_scan'].includes(repoItem.scanned_type || '')) {
                     repoEntry = repoItem;
                }

                if (repoEntry) {
                    const existing = historyMap.get(batchId);
                    
                    if (existing) {
                        // HIT: We found a sibling (Website or Sync) in the main list.
                        // We must ensure 'existing' is a Triad Container.

                        if (existing.is_triad && existing.entries) {
                            // It's already a container (e.g., Sync wrapper). Just add Repo.
                            existing.entries.repository = repoEntry;
                        } else {
                            // It's a flat item (e.g., Unwrapped Website Scan).
                            // We must UPGRADE it to a Triad Container.
                            
                            const type = existing.scanned_type;
                            
                            // Construct valid entries object
                            const newEntries: any = {
                                sync: type === 'sync' ? existing : null,
                                website: type === 'website' ? existing : null,
                                repository: repoEntry
                            };

                            // Create the Container
                            const triadContainer: AuditHistoryItem = {
                                ...existing, // Inherit base props
                                is_triad: true,
                                entries: newEntries
                            };
                            
                            // Update the Map with the new Container
                            historyMap.set(batchId, triadContainer);
                        }
                    } else {
                        // MISS: No sibling found (unlikely if batch_id exists, but possible).
                        // Create a new Triad Container for this lone Repository item.
                        historyMap.set(batchId, {
                            ...repoItem,
                            is_triad: true,
                            entries: {
                                sync: null,
                                website: null,
                                repository: repoEntry
                            }
                        });
                    }
                }
            });

            const stitchedOptions = Array.from(historyMap.values());
            history.value = [...stitchedOptions, ...legacyItems].sort((a, b) => 
                new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
            );
        } else {
            history.value = mainHistory;
        }

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
    if (score >= 85) return 'text-[#CBB48A]';
    if (score >= 70) return 'text-amber-400';
    return 'text-rose-400';
}

function getStatusBadge(status: string): string {
    switch (status) {
        case 'verified': return 'bg-[#CBB48A]/20 text-[#CBB48A] border-[#CBB48A]/30';
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
                    <div class="overflow-y-auto max-h-[60vh] p-6 custom-scrollbar">
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
                        <div v-else class="space-y-6">
                            <div 
                                v-for="(item, idx) in history" 
                                :key="item.id"
                                class="relative pl-8 pb-4"
                                :class="{ 'border-l-2 border-slate-700': idx < history.length - 1 }"
                            >
                                <!-- Dot -->
                                <div 
                                    class="absolute left-0 -translate-x-[9px] w-4 h-4 rounded-full border-2 border-[#0f172a]"
                                    :class="idx === 0 ? 'bg-indigo-500' : 'bg-slate-600'"
                                ></div>
                                
                                <!-- Card (Triad View) -->
                                <div v-if="item.is_triad" class="flex flex-col gap-2">
                                     <div class="text-[10px] text-slate-500 font-mono mb-1 flex justify-between">
                                         <span>BATCH: {{ item.batch_id }}</span>
                                         <span>{{ formatDate(item.created_at) }}</span>
                                     </div>

                                     <div class="grid grid-cols-3 gap-2 bg-slate-800/30 p-2 rounded-lg border border-slate-700/50">
                                         
                                         <!-- 1. WEBSITE -->
                                         <div 
                                            @click="item.entries.website ? $emit('view-snapshot', { item: item.entries.website, list: history, type: 'website' }) : null"
                                            class="flex flex-col items-center justify-center p-2 rounded transition-colors border border-transparent"
                                            :class="item.entries.website ? 'hover:bg-slate-700/50 hover:border-indigo-500/30 cursor-pointer' : 'opacity-30 grayscale cursor-not-allowed'"
                                         >
                                             <span class="text-[9px] text-slate-500 font-bold uppercase tracking-wider mb-1">Website</span>
                                             <div v-if="item.entries.website" class="flex flex-col items-center">
                                                 <!-- TITAN V6.5: Prioritize individual_score -->
                                                 <span class="text-lg font-black" :class="getScoreColor(item.entries.website.individual_score ?? item.entries.website.score)">
                                                     {{ item.entries.website.individual_score ?? item.entries.website.score }}
                                                 </span>
                                                 <span class="text-[9px] px-1.5 py-0.5 rounded border border-white/10 bg-black/20" :class="getScoreColor(item.entries.website.individual_score ?? item.entries.website.score)">
                                                     {{ item.entries.website.status.substring(0,3).toUpperCase() }}
                                                 </span>
                                             </div>
                                             <span v-else class="text-[10px] text-slate-600 italic">N/A</span>
                                         </div>

                                         <!-- 2. SYNC (Middle) -->
                                         <div 
                                            @click="item.entries.sync ? $emit('view-snapshot', { item: item.entries.sync, list: history, type: 'sync' }) : null"
                                            class="flex flex-col items-center justify-center p-2 rounded transition-colors border-x border-slate-700/50"
                                            :class="item.entries.sync ? 'hover:bg-slate-700/50 cursor-pointer' : 'opacity-30 grayscale cursor-not-allowed'"
                                         >
                                             <span class="text-[9px] text-indigo-400 font-bold uppercase tracking-wider mb-1">Sync</span>
                                             <div v-if="item.entries.sync" class="flex flex-col items-center">
                                                 <div class="flex items-center gap-1">
                                                     
                                                     <!-- TITAN V6.5: Prioritize sync_score -->
                                                     <span class="text-lg font-black text-indigo-400">
                                                         {{ item.entries.sync.sync_score ?? item.entries.sync.score }}%
                                                     </span>
                                                 </div>
                                                 <span class="text-[9px] text-slate-400">Match</span>
                                             </div>
                                             <span v-else class="text-[10px] text-slate-600 italic">Pending</span>
                                         </div>

                                         <!-- 3. REPOSITORY -->
                                         <div 
                                            @click="item.entries.repository ? $emit('view-snapshot', { item: item.entries.repository, list: history, type: 'repository' }) : null"
                                            class="flex flex-col items-center justify-center p-2 rounded transition-colors border border-transparent"
                                            :class="item.entries.repository ? 'hover:bg-slate-700/50 hover:border-indigo-500/30 cursor-pointer' : 'opacity-30 grayscale cursor-not-allowed'"
                                         >
                                             <span class="text-[9px] text-slate-500 font-bold uppercase tracking-wider mb-1">Repository</span>
                                             <div v-if="item.entries.repository" class="flex flex-col items-center">
                                                 <!-- TITAN V6.5: Prioritize individual_score -->
                                                 <span class="text-lg font-black" :class="getScoreColor(item.entries.repository.individual_score ?? item.entries.repository.score)">
                                                     {{ item.entries.repository.individual_score ?? item.entries.repository.score }}
                                                 </span>
                                                 <span class="text-[9px] px-1.5 py-0.5 rounded border border-white/10 bg-black/20" :class="getScoreColor(item.entries.repository.individual_score ?? item.entries.repository.score)">
                                                     {{ item.entries.repository.status.substring(0,3).toUpperCase() }}
                                                 </span>
                                             </div>
                                             <span v-else class="text-[10px] text-slate-600 italic">N/A</span>
                                         </div>

                                     </div>
                                </div>

                                <!-- Card (Legacy/Individual View) -->
                                <div 
                                    v-else
                                    @click="$emit('view-snapshot', { item, list: history })"
                                    class="bg-slate-800/50 rounded-lg border border-slate-700/50 p-4 cursor-pointer hover:bg-slate-700/50 hover:border-indigo-500/30 transition-all group"
                                >
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex flex-col">
                                            <span class="text-xs text-slate-400">{{ formatDate(item.created_at) }}</span>
                                            <span class="text-[10px] text-slate-600 font-mono mt-0.5">Batch: {{ item.metadata?.batch_id || 'Legacy' }}</span>
                                        </div>
                                        <span 
                                            class="text-xs font-semibold px-2 py-0.5 rounded border"
                                            :class="getStatusBadge(item.status)"
                                        >
                                            {{ item.status.replace('_', ' ').toUpperCase() }}
                                        </span>
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-4">
                                            <div class="flex flex-col items-center">
                                                <span 
                                                    class="text-2xl font-black"
                                                    :class="getScoreColor(item.score)"
                                                >
                                                    {{ item.score }}
                                                </span>
                                                <span class="text-[10px] text-slate-500 uppercase tracking-wider">Score</span>
                                            </div>
                                            
                                            <!-- Sync Type Check -->
                                            <div v-if="item.metadata?.audit_type === 'sync_scan' || item.metadata?.topology" class="flex flex-col">
                                                 <span class="text-xs text-white font-medium">Synced Verified</span>
                                                 <span class="text-[10px] text-slate-500">Web + Repo</span>
                                            </div>
                                            <div v-else class="flex flex-col">
                                                 <span class="text-xs text-white font-medium">{{ item.metadata?.audit_type || 'Standard Audit' }}</span>
                                                 <span class="text-[10px] text-slate-500">Individual Scan</span>
                                            </div>
                                        </div>

                                        <div class="opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-2 text-indigo-400 text-xs font-medium">
                                            View Report
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
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

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #0a0f1a;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #1e293b;
    border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #334155;
}
</style>
