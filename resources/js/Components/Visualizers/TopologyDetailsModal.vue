<script setup lang="ts">
/**
 * TopologyDetailsModal Component
 * Shows detailed table of all topology nodes with explanations
 */
import { computed } from 'vue';

interface Node {
    id: string;
    status: number;
    type?: string;
}

interface Props {
    show: boolean;
    nodes: Node[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
}>();

// Categorize nodes by type
const categorizedNodes = computed(() => {
    const root = props.nodes.filter(n => n.type === 'root');
    const scripts = props.nodes.filter(n => n.type === 'script');
    const external = props.nodes.filter(n => n.type === 'external');
    
    return { root, scripts, external };
});

// Get node type explanation
function getNodeExplanation(type: string): string {
    const explanations: Record<string, string> = {
        'root': 'Your website\'s main domain. The central node from which all resources are loaded.',
        'script': 'JavaScript files loaded by your site. These can be framework code, libraries, or application logic.',
        'external': 'External domains your site communicates with (APIs, CDNs, third-party services).'
    };
    return explanations[type] || 'Unknown resource type';
}

// Get security implication
function getSecurityImplication(type: string): string {
    const implications: Record<string, string> = {
        'root': 'Primary target for attacks. Ensure proper security headers and HTTPS.',
        'script': 'Potential XSS vectors if compromised. Verify integrity with SRI hashes.',
        'external': 'Data exfiltration risk. Review third-party privacy policies and data flow.'
    };
    return implications[type] || 'Review for security implications';
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="$emit('close')">
        <div class="bg-slate-900 border border-cyan-500/30 rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="flex items-center justify-between p-6 border-b border-white/10 bg-slate-950/20">
                <div>
                    <h3 class="text-lg font-bold text-white">Site Topology Details</h3>
                    <p class="text-sm text-slate-400 mt-1">Forensic analysis of {{ nodes.length }} discovered nodes</p>
                </div>
                <button 
                    @click="$emit('close')"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-800 transition-colors text-slate-400 hover:text-white"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar">
                <!-- Root Nodes -->
                <div v-if="categorizedNodes.root.length">
                    <h4 class="text-sm font-bold text-emerald-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Root Domain
                    </h4>
                    <div class="bg-slate-900/50 rounded-lg p-4 border border-cyan-500/10">
                        <div v-for="node in categorizedNodes.root" :key="node.id" class="mb-4 last:mb-0">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-sm text-white">{{ node.id }}</span>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 font-bold">{{ node.status }}</span>
                                </div>
                            </div>
                            <p class="text-xs text-slate-400 mb-2">{{ getNodeExplanation('root') }}</p>
                            <div class="text-[10px] text-amber-400 bg-amber-500/10 border border-amber-500/20 rounded px-2 py-1">
                                🔒 {{ getSecurityImplication('root') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Script Nodes -->
                <div v-if="categorizedNodes.scripts.length">
                    <h4 class="text-sm font-bold text-blue-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                        </svg>
                        JavaScript Resources ({{ categorizedNodes.scripts.length }})
                    </h4>
                    <div class="bg-slate-900/50 rounded-lg p-4 border border-cyan-500/10 space-y-3">
                        <div v-for="node in categorizedNodes.scripts" :key="node.id" class="pb-3 border-b border-slate-800/50 last:border-0 last:pb-0">
                            <div class="flex items-start justify-between mb-2">
                                <span class="font-mono text-xs text-white truncate max-w-md" :title="node.id">{{ node.id }}</span>
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-cyan-500/20 text-cyan-400 font-bold whitespace-nowrap ml-2 border border-cyan-500/30">{{ node.status }}</span>
                            </div>
                            <p class="text-[11px] text-slate-500">{{ getNodeExplanation('script') }}</p>
                        </div>
                    </div>
                    <div class="text-[10px] text-amber-400 bg-amber-500/10 border border-amber-500/20 rounded px-3 py-2 mt-2">
                        🔒 {{ getSecurityImplication('script') }}
                    </div>
                </div>

                <!-- External Nodes -->
                <div v-if="categorizedNodes.external.length">
                    <h4 class="text-sm font-bold text-cyan-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                        </svg>
                        External Dependencies ({{ categorizedNodes.external.length }})
                    </h4>
                    <div class="bg-slate-900/50 rounded-lg p-4 border border-cyan-500/10 space-y-3">
                        <div v-for="node in categorizedNodes.external" :key="node.id" class="pb-3 border-b border-slate-800/50 last:border-0 last:pb-0">
                            <div class="flex items-start justify-between mb-2">
                                <span class="font-mono text-xs text-white truncate max-w-md" :title="node.id">{{ node.id }}</span>
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-cyan-500/20 text-cyan-300 font-bold whitespace-nowrap ml-2 border border-cyan-500/30">{{ node.status }}</span>
                            </div>
                            <p class="text-[11px] text-slate-500">{{ getNodeExplanation('external') }}</p>
                        </div>
                    </div>
                    <div class="text-[10px] text-amber-400 bg-amber-500/10 border border-amber-500/20 rounded px-3 py-2 mt-2">
                        🔒 {{ getSecurityImplication('external') }}
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!nodes.length" class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-slate-700 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="text-slate-500 text-sm">No topology data available</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-4 border-t border-white/10 bg-slate-950/50">
                <button 
                    @click="$emit('close')"
                    class="w-full px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-semibold rounded-lg transition-colors border border-cyan-500/30"
                >
                    Close Forensic Details
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #164e63; /* cyan-900 */
    border-radius: 20px;
    border: 2px solid transparent;
    background-clip: content-box;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #0891b2; /* cyan-600 */
    background-clip: content-box;
}
/* Firefox support */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #164e63 transparent;
}
</style>
