<script setup lang="ts">
import { computed, ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import type { VaultAsset } from '@/types/vault';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps<{
    show: boolean;
    assets: VaultAsset[];
}>();

const emit = defineEmits(['close', 'sync-now', 'scan-and-sync']);

const searchQuery = ref('');
const selectedWebId = ref<string | null>(null);
const selectedRepoId = ref<string | null>(null);

// Filter assets by type and search query
const websites = computed(() => {
    return props.assets
        .filter(a => a.metadata?.audit_type === 'project')
        .filter(a => a.file_name.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

const repositories = computed(() => {
    return props.assets
        .filter(a => (a.metadata?.audit_type as any) === 'repository_scan')
        .filter(a => a.file_name.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

const canSync = computed(() => selectedWebId.value && selectedRepoId.value);

const showConfirmation = ref(false);
const confirmAction = ref<'scan-and-sync' | null>(null);
const confirmMessage = ref('');

function initiateAction(action: 'scan-and-sync') {
    if (!canSync.value) return;

    confirmAction.value = action;
    confirmMessage.value = "This will Perform a Dual Scan on both assets systematically to generate fresh results, then Sync & Compare. usage: 10 Credits. Continue?";
    
    showConfirmation.value = true;
}

function executeConfirmedAction() {
    if (!selectedWebId.value || !selectedRepoId.value || !confirmAction.value) return;

    const webAsset = props.assets.find(a => a.id === selectedWebId.value);
    const repoAsset = props.assets.find(a => a.id === selectedRepoId.value);

    if (webAsset && repoAsset) {
        emit(confirmAction.value, { web: webAsset, repo: repoAsset });
        close();
    }
    
    showConfirmation.value = false;
}

function close() {
    selectedWebId.value = null;
    selectedRepoId.value = null;
    searchQuery.value = '';
    emit('close');
}
</script>

<template>
    <Modal :show="show" @close="close" :maxWidth="'4xl' as any">
        <div class="p-6 bg-slate-900 text-white">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-white">Sync Project & Repository</h2>
                <div class="relative">
                    <input 
                        v-model="searchQuery"
                        type="text" 
                        placeholder="Search assets..." 
                        class="bg-slate-800 border-slate-700 rounded-lg text-sm px-3 py-1 text-slate-300 focus:ring-[#CBB48A] focus:border-[#CBB48A]"
                    >
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6 h-[400px]">
                <!-- Websites Column -->
                <div class="bg-slate-800/50 rounded-xl border border-slate-700 flex flex-col overflow-hidden">
                    <div class="p-3 bg-slate-800 border-b border-slate-700 font-semibold text-[#CBB48A] text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
                        Select Website
                    </div>
                    <div class="overflow-y-auto flex-1 p-2 space-y-2">
                        <div v-if="websites.length === 0" class="text-center text-slate-500 text-xs py-10">
                            No websites found.
                        </div>
                        <label 
                            v-for="asset in websites" 
                            :key="asset.id"
                            class="flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors border"
                            :class="selectedWebId === asset.id ? 'bg-[#CBB48A]/10 border-[#CBB48A]' : 'bg-slate-800 border-slate-700 hover:border-slate-600'"
                        >
                            <input type="radio" :value="asset.id" v-model="selectedWebId" class="text-[#CBB48A] focus:ring-[#CBB48A] bg-slate-900 border-slate-600">
                            <div class="overflow-hidden">
                                <div class="font-medium text-sm truncate" :title="asset.file_name">{{ asset.file_name }}</div>
                                <div class="text-[10px] text-slate-400">{{ new Date(asset.created_at).toLocaleDateString() }}</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Repositories Column -->
                <div class="bg-slate-800/50 rounded-xl border border-slate-700 flex flex-col overflow-hidden">
                    <div class="p-3 bg-slate-800 border-b border-slate-700 font-semibold text-blue-400 text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                        Select Repository
                    </div>
                    <div class="overflow-y-auto flex-1 p-2 space-y-2">
                        <div v-if="repositories.length === 0" class="text-center text-slate-500 text-xs py-10">
                            No repositories found.
                        </div>
                        <label 
                            v-for="asset in repositories" 
                            :key="asset.id"
                            class="flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors border"
                            :class="selectedRepoId === asset.id ? 'bg-blue-500/10 border-blue-500' : 'bg-slate-800 border-slate-700 hover:border-slate-600'"
                        >
                            <input type="radio" :value="asset.id" v-model="selectedRepoId" class="text-blue-500 focus:ring-blue-500 bg-slate-900 border-slate-600">
                            <div class="overflow-hidden">
                                <div class="font-medium text-sm truncate" :title="asset.file_name">{{ asset.file_name }}</div>
                                <div class="text-[10px] text-slate-400">{{ new Date(asset.created_at).toLocaleDateString() }}</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <SecondaryButton @click="close">Cancel</SecondaryButton>
                
                <PrimaryButton 
                    @click="initiateAction('scan-and-sync')" 
                    :disabled="!canSync"
                    class="bg-[#DCC8A5] hover:bg-[#CBB48A] border-[#CBB48A]"
                    :class="{ 'opacity-50 cursor-not-allowed': !canSync }"
                >
                    Scan again and Sync
                </PrimaryButton>
            </div>
        </div>

        <!-- Custom Confirmation Modal (Nested) -->
        <Modal :show="showConfirmation" @close="showConfirmation = false" maxWidth="lg">
            <div class="p-6 bg-slate-900 border border-slate-700">
                <h3 class="text-lg font-bold text-white mb-3">
                    Confirm Dual Scan
                </h3>
                <p class="text-slate-300 text-sm mb-6 leading-relaxed">
                    {{ confirmMessage }}
                </p>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="showConfirmation = false">Cancel</SecondaryButton>
                    <PrimaryButton @click="executeConfirmedAction" class="bg-indigo-600 hover:bg-indigo-500">
                        Proceed
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </Modal>
</template>
