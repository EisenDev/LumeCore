<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue';
import axios from 'axios';

interface Props { show: boolean; asset: any; }
const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'close'): void }>();

// --- STATE ---
const activeTab = ref<'overview' | 'data_audit' | 'metadata' | 'chat' | 'ai_checker'>('overview');
const animatedScore = ref(0);
const highlightFilter = ref<string>('all');
const activeHighlightId = ref<string | null>(null);
const selectedFact = ref<any>(null); // For Source Drawer

// Chat state
const chatMessages = ref<{ role: 'user' | 'assistant'; content: string }[]>([]);
const chatInput = ref('');
const isChatLoading = ref(false);
const chatContainer = ref<HTMLElement | null>(null);

// AI Detection state
const aiDetectionState = ref<'idle' | 'loading' | 'done' | 'error'>('idle');
const aiDetectionResult = ref<any>(null);

// --- COMPUTED DATA ---
const report = computed(() => {
    if (!props.asset?.metadata) return null;
    const m = props.asset.metadata;
    return {
        type: m.audit_type || 'document', score: m.score || props.asset.score || 0,
        verdict: m.verdict || 'pending', document_type: m.document_type || 'Document',
        detected_citation_style: m.detected_citation_style || 'Unknown',
        summary: m.summary || 'Analysis pending...',
        primary_lens: m.primary_lens || 'General',
        audit_meta: m.audit_meta || { risk_score: 0 },
        audit_facts: m.audit_facts || [], // The new structured data
        eligibility: m.eligibility || { is_eligible: false, reason: 'Unknown' },
        value_pillars: m.value_pillars || {
            professional_polish: { score: 0, label: 'Unknown' },
            content_depth: { score: 0, label: 'Unknown' },
            utility_score: { score: 0, label: 'Unknown' },
        },
        safety_checks: m.safety_checks || { pii_status: 'UNKNOWN', pii_details: [], encryption_status: 'UNKNOWN', warnings: 'No data' },
        forensic_highlights: m.forensic_highlights || [], // Keeping for backward compat if needed, but primary is audit_facts
        content_sections: m.content_sections || [],
        key_insights: m.key_insights || [],
        suggested_prompts: m.suggested_prompts || ['Summarize this document', 'What are the key findings?', 'List any issues found', 'What is the document about?'],
        quality_audit: m.quality_audit || {},
        improvement_suggestions: m.improvement_suggestions || [],
        signals: m.doc_signals || {},
        full_text: m.full_text || '',
    };
});

// --- UI HELPERS ---
const scoreColor = computed(() => {
    const s = report.value?.score || 0;
    if (s >= 85) return '#CBB48A'; if (s >= 70) return '#3b82f6'; if (s >= 50) return '#f59e0b'; return '#ef4444';
});

const verdictBadge = computed(() => {
    const v = report.value?.verdict || '';
    if (v.includes('eligible')) return { text: 'VERIFIED ELIGIBLE', cls: 'bg-[#CBB48A]/20 text-[#CBB48A] border-[#CBB48A]/40' };
    if (v.includes('private')) return { text: 'PRIVATE RECORD', cls: 'bg-amber-500/20 text-amber-400 border-amber-500/40' };
    if (v.includes('rejected')) return { text: 'REJECTED', cls: 'bg-red-500/20 text-red-400 border-red-500/40' };
    return { text: 'ACTION REQUIRED', cls: 'bg-orange-500/20 text-orange-400 border-orange-500/40' };
});

// ── Highlight helpers ──
const filteredHighlights = computed(() => {
    const highlights = report.value?.forensic_highlights || [];
    if (highlightFilter.value === 'all') return highlights;
    return highlights.filter((h: any) => h.type === highlightFilter.value);
});

const highlightCategories = computed(() => {
    const highlights = report.value?.forensic_highlights || [];
    const counts: Record<string, number> = {};
    highlights.forEach((h: any) => { counts[h.type] = (counts[h.type] || 0) + 1; });
    return counts;
});

function hlColor(type: string) {
    const m: Record<string, { text: string; bg: string; border: string; dot: string }> = {
        spelling_error: { text: 'text-red-400', bg: 'bg-red-500/10', border: 'border-red-500/30', dot: 'bg-red-400' },
        grammar_error: { text: 'text-orange-400', bg: 'bg-orange-500/10', border: 'border-orange-500/30', dot: 'bg-orange-400' },
        citation: { text: 'text-[#CBB48A]', bg: 'bg-[#CBB48A]/10', border: 'border-[#CBB48A]/30', dot: 'bg-[#CBB48A]' },
        entity: { text: 'text-blue-400', bg: 'bg-blue-500/10', border: 'border-blue-500/30', dot: 'bg-blue-400' },
        formatting_issue: { text: 'text-yellow-400', bg: 'bg-yellow-500/10', border: 'border-yellow-500/30', dot: 'bg-yellow-400' },
        pii_risk: { text: 'text-rose-400', bg: 'bg-rose-500/10', border: 'border-rose-500/30', dot: 'bg-rose-400' },
        structure: { text: 'text-teal-400', bg: 'bg-teal-500/10', border: 'border-teal-500/30', dot: 'bg-teal-400' },
    };
    return m[type] || { text: 'text-gray-400', bg: 'bg-gray-500/10', border: 'border-gray-500/30', dot: 'bg-gray-400' };
}

function hlIcon(type: string): string {
    const m: Record<string, string> = { spelling_error: '✗', grammar_error: '✎', citation: '✓', entity: '◉', formatting_issue: '¶', pii_risk: '⚠', structure: '☰' };
    return m[type] || '●';
}

function hlLabel(type: string): string {
    const m: Record<string, string> = { spelling_error: 'Spelling', grammar_error: 'Grammar', citation: 'Citation', entity: 'Entity', formatting_issue: 'Formatting', pii_risk: 'PII Risk', structure: 'Structure' };
    return m[type] || type;
}

// ── Helper: Get Status Badge ──
function getStatusBadge(status: string) {
    switch (status) {
        case 'verified': return { icon: '✓', text: 'Verified', cls: 'bg-[#CBB48A]/20 text-[#CBB48A] border-[#CBB48A]/30' };
        case 'risk_flag': return { icon: '⚠', text: 'Risk Flag', cls: 'bg-red-500/20 text-red-400 border-red-500/30' };
        case 'missing': return { icon: '∅', text: 'Missing', cls: 'bg-amber-500/20 text-amber-400 border-amber-500/30' };
        case 'redacted': return { icon: '█', text: 'Redacted', cls: 'bg-gray-700 text-gray-400 border-gray-600' };
        default: return { icon: '?', text: 'Unknown', cls: 'bg-gray-500/20 text-gray-400 border-gray-500/30' };
    }
}

// ── Helper: Format Data Point ──
// Returns true if the data point seems to be a monetary value
function isMoney(text: string): boolean {
    return /^\$|€|£|¥|USD|EUR/.test(text);
}

function scrollToHighlight(idx: number) {
    activeHighlightId.value = `hl-${idx}`;
    nextTick(() => {
        const el = document.getElementById(`hl-${idx}`);
        if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.classList.add('hl-flash'); setTimeout(() => el.classList.remove('hl-flash'), 2000); }
    });
}

function gaugeArc(score: number): string {
    const pct = Math.min(score, 100) / 100; const angle = pct * 180; const rad = (angle * Math.PI) / 180;
    const r = 40, cx = 50, cy = 50; const x = cx + r * Math.cos(Math.PI - rad); const y = cy - r * Math.sin(Math.PI - rad);
    return `M ${cx - r} ${cy} A ${r} ${r} 0 ${angle > 180 ? 1 : 0} 1 ${x} ${y}`;
}
function gaugeColor(s: number): string { if (s >= 80) return '#3b82f6'; if (s >= 60) return '#f59e0b'; return '#ef4444'; }

// --- SCORE ANIMATION ---
watch(() => props.show, (val) => {
    if (val) {
        activeTab.value = 'overview'; animatedScore.value = 0; 
        
        // Hydrate or Reset AI State
        if (props.asset?.metadata?.ai_analysis) {
            aiDetectionResult.value = props.asset.metadata.ai_analysis;
            aiDetectionState.value = 'done';
        } else {
            aiDetectionState.value = 'idle'; aiDetectionResult.value = null;
        }

        const target = report.value?.score || 0; const startTime = performance.now();
        const animate = (time: number) => { const p = Math.min((time - startTime) / 1200, 1); animatedScore.value = Math.floor(target * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(animate); };
        requestAnimationFrame(animate); initChat();
    }
});

watch(activeTab, () => {
    selectedFact.value = null;
});

// --- CHAT ---
function initChat() {
    chatMessages.value = []; const dt = report.value?.document_type || 'Document';
    chatMessages.value.push({ role: 'assistant', content: `Hello! I've analyzed this ${dt}. ${report.value?.summary || ''}\n\nHow can I assist you?` });
}
function scrollToBottom() { nextTick(() => { if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight; }); }
async function sendChatMessage(content?: string) {
    const msg = content || chatInput.value.trim(); if (!msg || isChatLoading.value) return;
    chatMessages.value.push({ role: 'user', content: msg }); chatInput.value = ''; isChatLoading.value = true; scrollToBottom();
    try { const r = await axios.post('/ai/chat', { message: msg, mode: 'document', asset_id: props.asset?.id }); chatMessages.value.push({ role: 'assistant', content: r.data.reply }); }
    catch { chatMessages.value.push({ role: 'assistant', content: 'Error processing request. Please try again.' }); }
    finally { isChatLoading.value = false; scrollToBottom(); }
}

// --- AI DETECTION ---
async function runAiDetection() {
    if (aiDetectionState.value === 'loading') return;
    aiDetectionState.value = 'loading'; aiDetectionResult.value = null;
    try { 
        const r = await axios.post('/ai/detect', { asset_id: props.asset?.id }); 
        aiDetectionResult.value = r.data; 
        aiDetectionState.value = 'done';
        // Optimize: Update local prop for immediate re-open persistence without refetch
        if (props.asset && props.asset.metadata) {
            props.asset.metadata.ai_analysis = r.data;
        }
    }
    catch { aiDetectionState.value = 'error'; aiDetectionResult.value = { ai_probability: 50, human_probability: 50, verdict: 'Analysis Failed', confidence: 'Low', overall_assessment: 'Could not complete analysis.', reasoning: [], flagged_passages: [] }; }
}

function aiIndicatorColor(indicator: string) { if (indicator === 'human') return 'text-[#CBB48A]'; if (indicator === 'ai') return 'text-red-400'; return 'text-gray-400'; }
function aiIndicatorBg(indicator: string) { if (indicator === 'human') return 'bg-[#CBB48A]/10 border-[#CBB48A]/30'; if (indicator === 'ai') return 'bg-red-500/10 border-red-500/30'; return 'bg-gray-500/10 border-gray-500/30'; }

function downloadReport() {
    if (!props.asset?.id) return;
    window.location.href = `/api/vault/assets/${props.asset.id}/export`;
}

const tabs = [
    { id: 'overview', icon: 'home', label: 'Overview' },
    { id: 'data_audit', icon: 'table', label: 'Data Audit' },
    { id: 'metadata', icon: 'info', label: 'Metadata' },
    { id: 'chat', icon: 'chat', label: 'Chat' },
    { id: 'ai_checker', icon: 'shield', label: 'AI Check' },
] as const;
</script>

<template>
    <Teleport to="body">
        <transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show && asset" class="fixed inset-0 z-[9999] overflow-hidden flex items-center justify-center p-4 sm:p-6">
                <!-- Backdrop -->
                <div 
                    class="fixed inset-0 bg-[#050505]/95 backdrop-blur-xl"
                    @click="$emit('close')"
                >
                    <!-- Ambient Glow -->
                    <div class="absolute inset-0 overflow-hidden pointer-events-none">
                         <div class="absolute top-0 left-0 w-[500px] h-[500px] bg-[#CBB48A]/10 rounded-full blur-[120px]" />
                         <div class="absolute bottom-0 right-0 w-[500px] h-[500px] bg-lume-primary/5 rounded-full blur-[120px]" />
                    </div>
                </div>

                <!-- Modal Container -->
                <div class="relative w-full max-w-[95rem] bg-[#050505] border border-white/10 rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col h-[92vh] transition-all duration-300 ring-1 ring-white/5">
                    
                    <!-- Grid Background Overlay -->
                    <div class="absolute inset-0 bg-grid-white/[0.02] bg-[length:30px_30px] pointer-events-none"></div>

                    <!-- HEADER -->
                    <div class="px-10 py-8 border-b border-white/5 bg-[#0A0A0B]/80 backdrop-blur-xl flex justify-between items-center flex-shrink-0 relative z-20">
                        <div class="flex items-center gap-8 min-w-0">
                            <!-- Icon -->
                            <div class="relative flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-lume-primary/10 to-transparent border border-white/10 flex-shrink-0 shadow-inner group overflow-hidden">
                                <div class="absolute inset-0 bg-gradient-to-br from-lume-primary/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                <svg class="h-8 w-8 text-lume-primary relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            
                            <!-- Title Text -->
                            <div class="min-w-0 flex flex-col gap-1">
                                <div class="flex items-center gap-3">
                                    <div class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-[0.15em] bg-white/5 text-gray-400 border border-white/10">
                                        Asset Protocol
                                    </div>
                                    <div class="h-px w-8 bg-white/10"></div>
                                    <span v-if="tabs.find(t => t.id === activeTab)" class="text-[9px] font-black uppercase tracking-[0.15em] text-lume-primary">
                                        {{ tabs.find(t => t.id === activeTab)?.label }}
                                    </span>
                                </div>
                                <h3 class="text-4xl font-black text-white tracking-tight uppercase italic leading-none">
                                    Document <span class="bg-gradient-to-r from-[#CBB48A] to-teal-400 bg-clip-text text-transparent">Analysis</span>
                                </h3>
                                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-[0.2em] truncate mt-1">
                                    Target: {{ asset.file_name }}
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-4">
                            <button @click="downloadReport" class="hidden sm:flex items-center gap-3 px-6 py-3 text-[10px] font-black uppercase tracking-[0.2em] text-black bg-white rounded-full hover:bg-lume-primary transition-all hover:scale-105 active:scale-95 shadow-[0_0_20px_rgba(255,255,255,0.1)] hover:shadow-[0_0_30px_rgba(203, 180, 138, 0.4)] group">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                Export Data
                            </button>
                            <button @click="$emit('close')" class="h-12 w-12 rounded-full border border-white/10 bg-white/5 flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/10 hover:border-white/20 transition-all active:scale-95">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

            <!-- BODY -->
            <div class="flex-1 flex overflow-hidden">
                <!-- SIDEBAR NAV -->
                <div class="w-24 bg-[#0A0A0B] border-r border-white/5 flex flex-col items-center py-8 gap-6 flex-shrink-0 relative z-20">
                    <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id as any"
                        class="relative w-14 h-14 rounded-2xl flex flex-col items-center justify-center gap-1 transition-all duration-300 group"
                        :class="activeTab === tab.id ? 'bg-[#CBB48A]/10 text-[#CBB48A] ring-1 ring-[#CBB48A]/40 shadow-[0_0_30px_rgba(203, 180, 138, 0.15)]' : 'text-gray-600 hover:text-gray-200 hover:bg-white/5'">
                        
                        <!-- Active Indicator -->
                        <div v-if="activeTab === tab.id" class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-full w-1 h-8 bg-[#CBB48A] rounded-r-full shadow-[0_0_15px_#CBB48A]"></div>

                        <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path v-if="tab.icon === 'home'" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            <path v-if="tab.icon === 'table'" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />

                            <path v-if="tab.icon === 'info'" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            <path v-if="tab.icon === 'chat'" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            <path v-if="tab.icon === 'shield'" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <span class="text-[8px] font-black tracking-widest uppercase truncate max-w-full px-1">{{ tab.label }}</span>
                    </button>
                </div>

                <!-- MAIN CONTENT -->
                <div class="flex-1 overflow-y-auto custom-scrollbar">

                    <!-- ══ TAB: OVERVIEW ══ -->
                    <div v-if="activeTab === 'overview'" class="p-8 space-y-8">
                        <!-- Score Card (Sovereign Vault Style) -->
                        <div class="relative rounded-[2.5rem] border border-white/5 bg-[#0A0A0B] p-10 overflow-hidden group hover:border-white/10 transition-all">
                            <div class="absolute -right-24 -top-24 h-64 w-64 bg-lume-primary/10 blur-[80px] group-hover:bg-lume-primary/20 transition-all shadow-2xl" />
                            
                            <div class="relative z-10 flex flex-col md:flex-row gap-10 items-center">
                                <!-- Score Gauge -->
                                <div class="relative w-48 h-48 flex-shrink-0">
                                    <svg class="w-full h-full -rotate-90" viewBox="0 0 36 36">
                                        <!-- Background Circle -->
                                        <path class="text-white/5" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="1.5" />
                                        <!-- Progress Circle -->
                                        <path :stroke="scoreColor" :stroke-dasharray="`${animatedScore}, 100`" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke-linecap="round" stroke-width="2.5" class="drop-shadow-[0_0_15px_currentColor]" />
                                    </svg>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <div class="text-6xl font-black text-white tracking-tight">{{ animatedScore }}</div>
                                        <div class="text-[9px] font-black text-gray-500 uppercase tracking-[0.15em] mt-2">Trust Score</div>
                                    </div>
                                </div>
                                
                                <div class="flex-1 text-center md:text-left space-y-6">
                                    <div>
                                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-[0.2em] mb-4 border" :class="verdictBadge.cls">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span>
                                            {{ verdictBadge.text }}
                                        </div>
                                        <h4 class="text-3xl font-black text-white tracking-tight mb-2">
                                            {{ report?.document_type }}
                                            <span v-if="report?.detected_citation_style !== 'Unknown'" class="ml-2 text-lg not-italic font-bold text-gray-500 tracking-normal">
                                                // {{ report?.detected_citation_style }}
                                            </span>
                                        </h4>
                                        <p class="text-gray-400 font-medium leading-relaxed max-w-2xl text-sm uppercase tracking-wide">
                                            {{ report?.eligibility?.reason }}
                                        </p>
                                    </div>
                                    
                                    <div class="grid grid-cols-3 gap-8 pt-6 border-t border-white/5">
                                        <div class="space-y-1">
                                            <div class="text-[9px] text-gray-500 font-black uppercase tracking-[0.2em]">Length</div>
                                            <div class="text-lg font-black text-white tracking-tight flex items-center gap-2">
                                                <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                {{ report?.signals?.page_count || '?' }} Pages
                                            </div>
                                        </div>
                                        <div class="space-y-1">
                                            <div class="text-[9px] text-gray-500 font-black uppercase tracking-[0.2em]">Volume</div>
                                            <div class="text-lg font-black text-white tracking-tight flex items-center gap-2">
                                                <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" /></svg>
                                                {{ (report?.signals?.word_count || 0).toLocaleString() }}
                                            </div>
                                        </div>
                                        <div class="space-y-1">
                                            <div class="text-[9px] text-gray-500 font-black uppercase tracking-[0.2em]">Est. Read</div>
                                            <div class="text-lg font-black text-white tracking-tight flex items-center gap-2">
                                                <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                {{ report?.signals?.read_time || '?' }} min
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary -->
                        <div class="bg-white/[0.02] border border-white/5 rounded-[2rem] p-8 relative overflow-hidden">
                            <div class="absolute inset-0 bg-grid-white/[0.02]"></div>
                            <h4 class="text-xs font-black text-white uppercase tracking-[0.2em] mb-6 flex items-center gap-3 relative z-10">
                                <span class="w-1.5 h-1.5 rounded-full bg-lume-primary shadow-[0_0_10px_#CBB48A]"></span>
                                Executive Summary
                            </h4>
                            <p class="text-gray-400 text-sm font-medium leading-loose text-justify relative z-10">{{ report?.summary }}</p>
                        </div>

                        <!-- Value Pillars Grid -->
                        <div>
                            <h4 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em] mb-6 pl-2">Assessment Vectors</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div v-for="(pillar, key) in { 'Professional Polish': report?.value_pillars?.professional_polish, 'Content Depth': report?.value_pillars?.content_depth, 'Utility Score': report?.value_pillars?.utility_score }" :key="key" 
                                    class="bg-[#0A0A0B] border border-white/5 rounded-[2rem] p-8 text-center hover:border-white/20 transition-all relative overflow-hidden group">
                                    <div class="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                    
                                    <div class="relative z-10 mb-6 mx-auto w-full aspect-[2/1] flex items-end justify-center pb-2">
                                        <!-- Half Gauge SVG -->
                                        <svg viewBox="0 0 100 55" class="w-full h-full overflow-visible">
                                            <defs>
                                                <linearGradient id="gaugeGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                                    <stop offset="0%" stop-color="#374151" stop-opacity="0.2" />
                                                    <stop offset="100%" stop-color="#374151" stop-opacity="0.1" />
                                                </linearGradient>
                                            </defs>
                                            <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="url(#gaugeGrad)" stroke-width="6" stroke-linecap="round" />
                                            <path :d="gaugeArc(pillar?.score||0)" fill="none" :stroke="gaugeColor(pillar?.score||0)" stroke-width="6" stroke-linecap="round" class="drop-shadow-[0_0_10px_currentColor] transition-all duration-1000 ease-out" />
                                        </svg>
                                        <div class="absolute inset-0 flex items-end justify-center">
                                            <span class="text-3xl font-black text-white tracking-tight">{{ pillar?.score||0 }}<span class="text-sm not-italic text-gray-500 ml-1">%</span></span>
                                        </div>
                                    </div>
                                    
                                    <h5 class="relative z-10 text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] group-hover:text-white transition-colors">{{ key }}</h5>
                                    <p class="relative z-10 text-[9px] font-bold text-gray-600 uppercase tracking-wider mt-2">{{ pillar?.label || 'Unknown' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Insights & Safety -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Safety Protocols -->
                            <div class="bg-gradient-to-br from-white/[0.02] to-transparent border border-white/5 rounded-[2rem] p-8">
                                <h4 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em] mb-6">Compliance Grid</h4>
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between p-4 bg-[#0A0A0B] rounded-2xl border border-white/5 group hover:border-white/10 transition-colors">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-white/5 text-lg border border-white/10 group-hover:border-[#CBB48A]/30 group-hover:text-[#CBB48A] transition-all">
                                                <svg v-if="report?.safety_checks?.pii_status==='CLEAN'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                            </div>
                                            <div>
                                                <div class="text-[9px] font-black uppercase tracking-[0.2em] text-white">PII Status</div>
                                                <div class="text-[9px] font-bold uppercase tracking-wider text-gray-500">Sensitive Data Check</div>
                                            </div>
                                        </div>
                                        <span class="px-3 py-1 rounded-md text-[9px] font-black uppercase tracking-[0.2em] border"
                                            :class="report?.safety_checks?.pii_status==='CLEAN'?'bg-[#CBB48A]/10 text-[#CBB48A] border-[#CBB48A]/20':'bg-red-500/10 text-red-400 border-red-500/20'">
                                            {{ report?.safety_checks?.pii_status }}
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between p-4 bg-[#0A0A0B] rounded-2xl border border-white/5 group hover:border-white/10 transition-colors">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-white/5 text-lg border border-white/10 group-hover:border-[#F3E7C9]/30 group-hover:text-[#F3E7C9] transition-all">
                                                <svg v-if="report?.safety_checks?.encryption_status==='NONE'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" /></svg>
                                                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                            </div>
                                            <div>
                                                <div class="text-[9px] font-black uppercase tracking-[0.2em] text-white">Encryption</div>
                                                <div class="text-[9px] font-bold uppercase tracking-wider text-gray-500">Security Protocol</div>
                                            </div>
                                        </div>
                                        <span class="px-3 py-1 rounded-md text-[9px] font-black uppercase tracking-[0.2em] border"
                                            :class="report?.safety_checks?.encryption_status==='NONE'?'bg-[#CBB48A]/10 text-[#CBB48A] border-[#CBB48A]/20':'bg-amber-500/10 text-amber-400 border-amber-500/20'">
                                            {{ report?.safety_checks?.encryption_status }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Key Insights -->
                            <div class="bg-gradient-to-br from-white/[0.02] to-transparent border border-white/5 rounded-[2rem] p-8">
                                <h4 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em] mb-6">Key Directives</h4>
                                <ul class="space-y-4">
                                    <li v-for="(ins,i) in report?.key_insights?.slice(0,5)" :key="i" class="flex gap-4 group">
                                        <div class="w-1.5 h-1.5 rounded-full bg-lume-primary mt-2 flex-shrink-0 group-hover:shadow-[0_0_8px_currentColor] transition-all"></div>
                                        <span class="text-sm text-gray-400 font-medium leading-relaxed group-hover:text-gray-200 transition-colors">{{ ins }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- ══ TAB: DATA AUDIT GRID ══ -->
                    <div v-if="activeTab === 'data_audit'" class="flex h-full relative">
                        <div class="flex-1 p-8 overflow-y-auto custom-scrollbar">
                            <div class="flex items-center justify-between mb-8">
                                <div class="flex items-center gap-4">
                                    <h4 class="text-xs font-black text-white uppercase tracking-[0.2em] border-b-2 border-lume-primary pb-1">Verified Audit Facts</h4>
                                    
                                    <!-- Lens Badge -->
                                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-[0.2em] border bg-[#0A0A0B]" 
                                        :class="report?.primary_lens === 'Financial' ? 'bg-[#CBB48A]/10 text-[#CBB48A] border-[#CBB48A]/20' : 
                                                report?.primary_lens === 'Legal' ? 'bg-rose-500/10 text-rose-400 border-rose-500/20' : 
                                                'bg-white/5 text-gray-300 border-white/10'">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                        {{ report?.primary_lens }} Lens
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-4">
                                    <div class="flex bg-[#0A0A0B] border border-white/10 rounded-lg p-0.5">
                                        <button @click="highlightFilter = 'all'" :class="highlightFilter === 'all' ? 'bg-white/10 text-white shadow-sm' : 'text-gray-500 hover:text-gray-300'" class="px-3 py-1.5 rounded-md text-[9px] font-black uppercase tracking-[0.2em] transition-all">All</button>
                                        <button @click="highlightFilter = 'risk_flag'" :class="highlightFilter === 'risk_flag' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'text-gray-500 hover:text-rose-400'" class="px-3 py-1.5 rounded-md text-[9px] font-black uppercase tracking-[0.2em] transition-all border border-transparent">Risks</button>
                                    </div>
                                </div>
                            </div>

                            <!-- DATAGRID -->
                            <div class="bg-[#0A0A0B] border border-white/5 rounded-[2rem] overflow-hidden shadow-2xl relative">
                                <div class="absolute inset-0 bg-grid-white/[0.02] pointer-events-none"></div>
                                <table class="w-full text-left border-collapse relative z-10">
                                    <thead>
                                        <tr class="border-b border-white/5 bg-white/[0.02]">
                                            <th class="px-6 py-5 w-24 text-[9px] font-black uppercase tracking-[0.2em] text-gray-500">Status</th>
                                            <th class="px-6 py-5 text-[9px] font-black uppercase tracking-[0.2em] text-gray-500">Data Point</th>
                                            <th class="px-6 py-5 text-[9px] font-black uppercase tracking-[0.2em] text-gray-500">Context / Label</th>
                                            <th class="px-6 py-5 w-40 text-[9px] font-black uppercase tracking-[0.2em] text-gray-500">Location</th>
                                            <th class="px-6 py-5 text-right w-24 text-[9px] font-black uppercase tracking-[0.2em] text-gray-500">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5">
                                        <tr v-for="(fact, idx) in report?.audit_facts" :key="idx" 
                                            class="group hover:bg-white/[0.02] transition-colors"
                                            :class="{'hidden': highlightFilter !== 'all' && fact.status !== highlightFilter}">
                                            
                                            <!-- Status -->
                                            <td class="px-6 py-4">
                                                <div class="inline-flex items-center gap-2 px-2 py-1 rounded text-[9px] font-black uppercase tracking-[0.2em] border w-fit" :class="getStatusBadge(fact.status).cls">
                                                    <span>{{ getStatusBadge(fact.status).icon }}</span>
                                                    <span>{{ getStatusBadge(fact.status).text }}</span>
                                                </div>
                                            </td>

                                            <!-- Data Point -->
                                            <td class="px-6 py-4">
                                                <span :class="isMoney(fact.data_point) ? 'font-mono text-[#CBB48A] font-bold tracking-tight' : 'text-gray-200 font-medium'">
                                                    {{ fact.data_point }}
                                                </span>
                                                <div v-if="fact.judgment" class="text-[10px] uppercase tracking-wide text-gray-500 mt-1 line-clamp-1 group-hover:text-gray-400">
                                                    {{ fact.judgment }}
                                                </div>
                                            </td>

                                            <!-- Context -->
                                            <td class="px-6 py-4 text-xs font-medium text-gray-400 leading-relaxed">
                                                {{ fact.context || fact.category }}
                                            </td>

                                            <!-- Location -->
                                            <td class="px-6 py-4 text-[10px] font-mono text-gray-500 uppercase">
                                                {{ fact.location }}
                                            </td>

                                            <!-- Action -->
                                            <td class="px-6 py-4 text-right">
                                                <button @click="selectedFact = fact" class="text-lume-primary hover:text-white text-[9px] font-black uppercase tracking-[0.2em] px-3 py-1.5 rounded-lg hover:bg-lume-primary/10 transition-colors border border-transparent hover:border-lume-primary/20">
                                                    SOURCE
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="(!report?.audit_facts || report.audit_facts.length === 0)" class="text-center py-12">
                                            <td colspan="5" class="py-12">
                                                <div class="flex flex-col items-center justify-center gap-4">
                                                    <div class="h-12 w-12 rounded-full bg-white/5 flex items-center justify-center text-gray-600">
                                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                    </div>
                                                    <div class="text-xs font-black uppercase tracking-[0.2em] text-gray-500">No verified facts found</div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ══ SOURCE DRAWER (Slide Over) ══ -->
                        <div v-if="selectedFact" class="absolute inset-y-0 right-0 w-[450px] bg-[#050505] border-l border-white/10 shadow-2xl flex flex-col z-20 animate-in slide-in-from-right duration-300">
                            <!-- Drawer Header -->
                            <div class="px-8 py-6 border-b border-white/5 flex justify-between items-center bg-[#0A0A0B]">
                                <h5 class="text-xs font-black text-white uppercase tracking-[0.2em] flex items-center gap-3">
                                    <span class="w-1.5 h-1.5 rounded-full bg-lume-primary shadow-[0_0_10px_#CBB48A]"></span>
                                    Source Verification
                                </h5>
                                <button @click="selectedFact = null" class="h-8 w-8 rounded-full flex items-center justify-center bg-white/5 hover:bg-white/10 text-gray-500 hover:text-white transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            
                            <!-- Drawer Content -->
                            <div class="flex-1 p-8 overflow-y-auto custom-scrollbar space-y-8">
                                <!-- Original Excerpt -->
                                <div class="bg-lume-primary/5 p-6 rounded-[1.5rem] border border-lume-primary/10 relative overflow-hidden group">
                                    <div class="absolute top-0 right-0 p-16 bg-lume-primary/5 rounded-full blur-[60px] pointer-events-none group-hover:bg-lume-primary/10 transition-colors"></div>
                                    <div class="text-[9px] text-lume-primary uppercase tracking-[0.2em] font-black mb-4">Original Excerpt</div>
                                    <div class="font-serif text-gray-200 leading-relaxed italic relative z-10 pl-4 border-l-2 border-lume-primary/30">
                                        "{{ selectedFact.source_text || 'No source text provided by AI.' }}"
                                    </div>
                                    <div class="mt-4 flex items-center justify-end">
                                        <div class="inline-flex items-center gap-2 px-2 py-1 rounded bg-black/20 border border-white/5 text-[9px] text-lume-primary/70 font-mono uppercase tracking-wider">
                                            LINE: {{ selectedFact.location }}
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-6">
                                    <div class="bg-[#0A0A0B] border border-white/5 rounded-2xl p-6">
                                        <span class="text-[9px] text-gray-500 uppercase tracking-[0.2em] font-black block mb-2">Extracted Fact</span>
                                        <div class="font-black text-white text-xl leading-snug tracking-tight">{{ selectedFact.data_point }}</div>
                                    </div>
                                    
                                    <div class="bg-[#0A0A0B] border border-white/5 rounded-2xl p-6">
                                        <span class="text-[9px] text-gray-500 uppercase tracking-[0.2em] font-black block mb-2">AI Judgment</span>
                                        <div class="text-sm text-gray-400 leading-loose">{{ selectedFact.judgment }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- ══ TAB: METADATA ══ -->
                    <div v-if="activeTab === 'metadata'" class="p-8 space-y-8">
                        <div>
                            <h4 class="text-xs font-black text-white uppercase tracking-[0.2em] mb-6 flex items-center gap-3">
                                <span class="w-1.5 h-1.5 rounded-full bg-lume-primary shadow-[0_0_10px_#CBB48A]"></span>
                                Document Metadata
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-[#0A0A0B] border border-white/5 rounded-[1.5rem] p-6 hover:border-white/10 transition-all group relative overflow-hidden" v-for="(item, idx) in [{ label: 'File Name', value: asset?.file_name }, { label: 'MIME Type', value: asset?.mime_type }, { label: 'File Size', value: asset?.file_size ? ((asset.file_size/1024/1024).toFixed(2)+' MB') : 'Unknown' }, { label: 'Document Type', value: report?.document_type }, { label: 'Citation Style', value: report?.detected_citation_style || 'Unknown' }, { label: 'Pages', value: report?.signals?.page_count || '?' }, { label: 'Word Count', value: (report?.signals?.word_count||0).toLocaleString() }, { label: 'Read Time', value: (report?.signals?.read_time||'?')+' min' }]" :key="idx">
                                    <div class="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                    <div class="relative z-10 flex flex-col">
                                        <div class="text-[9px] text-gray-500 uppercase tracking-[0.2em] font-black mb-2">{{ item.label }}</div>
                                        <div class="text-sm text-white font-medium truncate font-mono tracking-tight">{{ item.value }}</div>
                                    </div>
                                    <!-- Decorative Corner -->
                                    <div class="absolute top-0 right-0 p-2">
                                        <svg class="w-3 h-3 text-white/5 group-hover:text-white/10 transition-colors" viewBox="0 0 10 10"><path d="M0 0 L10 0 L10 10" fill="none" stroke="currentColor" /></svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="report?.quality_audit">
                            <h4 class="text-xs font-black text-white uppercase tracking-[0.2em] mb-6 flex items-center gap-3">
                                <span class="w-1.5 h-1.5 rounded-full bg-lume-secondary shadow-[0_0_10px_#F3E7C9]"></span>
                                Quality Audit
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="bg-[#0A0A0B] border border-white/5 rounded-[2rem] p-8 text-center relative group overflow-hidden">
                                    <div class="absolute inset-0 bg-grid-white/[0.02] opacity-50"></div>
                                    <div class="relative z-10">
                                        <div class="text-[9px] text-gray-500 uppercase tracking-[0.2em] font-black mb-4">Formatting</div>
                                        <div class="text-4xl font-black text-white tracking-tight">{{ report.quality_audit.formatting||0 }}<span class="text-lg text-gray-600 not-italic ml-1">/100</span></div>
                                    </div>
                                </div>
                                <div class="bg-[#0A0A0B] border border-white/5 rounded-[2rem] p-8 text-center relative group overflow-hidden">
                                    <div class="absolute inset-0 bg-grid-white/[0.02] opacity-50"></div>
                                    <div class="relative z-10">
                                        <div class="text-[9px] text-gray-500 uppercase tracking-[0.2em] font-black mb-4">Expertise</div>
                                        <div class="text-xl font-black text-white uppercase tracking-wide">{{ report.quality_audit.expertise_level||'N/A' }}</div>
                                    </div>
                                </div>
                                <div class="bg-[#0A0A0B] border border-white/5 rounded-[2rem] p-8 text-center relative group overflow-hidden">
                                    <div class="absolute inset-0 bg-grid-white/[0.02] opacity-50"></div>
                                    <div class="relative z-10">
                                        <div class="text-[9px] text-gray-500 uppercase tracking-[0.2em] font-black mb-4">Rigor</div>
                                        <div class="text-xl font-black text-white uppercase tracking-wide">{{ report.quality_audit.academic_rigor||'N/A' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="report?.improvement_suggestions?.length">
                            <h4 class="text-xs font-black text-white uppercase tracking-[0.2em] mb-6 flex items-center gap-3">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shadow-[0_0_10px_#f59e0b]"></span>
                                Optimization Vector
                            </h4>
                            <div class="space-y-4">
                                <div v-for="(sug, i) in report.improvement_suggestions" :key="i" class="p-6 bg-[#0A0A0B] border border-white/5 rounded-[1.5rem] flex gap-5 leading-relaxed hover:border-amber-500/20 transition-all group relative overflow-hidden">
                                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-amber-500/20 group-hover:bg-amber-500/50 transition-colors"></div>
                                    <span class="text-amber-400 flex-shrink-0 text-lg pt-1">💡</span>
                                    <span class="text-sm text-gray-400 font-medium group-hover:text-gray-300 transition-colors">{{ sug }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ TAB: CHAT ══ -->
                    <div v-if="activeTab === 'chat'" class="flex h-full">
                        <div class="flex-1 flex flex-col relative">
                            <!-- Header overlay -->
                            <div class="absolute top-0 left-0 right-0 z-10 p-6 bg-gradient-to-b from-[#050505] via-[#050505]/90 to-transparent">
                                <div class="px-5 py-3 bg-[#0A0A0B]/80 backdrop-blur-md border border-white/10 rounded-2xl flex items-center gap-4 shadow-lg">
                                    <div class="w-8 h-8 rounded-full bg-lume-primary/10 flex items-center justify-center flex-shrink-0 border border-lume-primary/20 shadow-[0_0_10px_rgba(203, 180, 138, 0.2)]">
                                        <svg class="w-4 h-4 text-lume-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Context Active</span>
                                        <span class="text-sm font-bold text-white tracking-tight">{{ report?.document_type || 'File verification' }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div ref="chatContainer" class="flex-1 overflow-y-auto px-8 pt-28 pb-6 space-y-8 custom-scrollbar">
                                <div v-for="(msg, idx) in chatMessages" :key="idx" class="flex gap-4 group" :class="msg.role==='user'?'flex-row-reverse':''">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-[10px] font-black shadow-lg transition-transform group-hover:scale-110" 
                                        :class="msg.role==='user'?'bg-[#DCC8A5] text-white border border-[#CBB48A] shadow-[0_0_15px_rgba(203, 180, 138, 0.3)]':'bg-[#0A0A0B] border border-white/10 text-lume-primary shadow-[0_0_15px_rgba(255,255,255,0.05)]'">
                                        {{ msg.role==='user'?'YOU':'LUME' }}
                                    </div>
                                    <div class="max-w-[75%] px-6 py-5 rounded-[1.5rem] text-sm leading-loose shadow-sm relative overflow-hidden" 
                                        :class="msg.role==='user'?'bg-[#DCC8A5] text-white rounded-tr-sm':'bg-[#0A0A0B] border border-white/5 text-gray-300 rounded-tl-sm'">
                                        <div v-if="msg.role!=='user'" class="absolute inset-0 bg-grid-white/[0.02] pointer-events-none"></div>
                                        <div class="relative z-10 whitespace-pre-wrap">{{ msg.content }}</div>
                                    </div>
                                </div>
                                <div v-if="isChatLoading" class="flex gap-4">
                                    <div class="w-9 h-9 rounded-xl bg-[#0A0A0B] border border-white/10 flex items-center justify-center flex-shrink-0 text-[10px] font-black text-lume-primary shadow-lg">LUME</div>
                                    <div class="px-6 py-5 bg-[#0A0A0B] border border-white/5 rounded-[1.5rem] rounded-tl-sm flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 bg-lume-primary/50 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                                        <span class="w-1.5 h-1.5 bg-lume-primary/50 rounded-full animate-bounce" style="animation-delay:150ms"></span>
                                        <span class="w-1.5 h-1.5 bg-lume-primary/50 rounded-full animate-bounce" style="animation-delay:300ms"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-6 border-t border-white/5 bg-[#050505]/95 backdrop-blur-xl relative z-20">
                                <div class="relative group">
                                    <div class="absolute -inset-0.5 bg-gradient-to-r from-[#CBB48A] to-teal-500 rounded-2xl opacity-0 group-focus-within:opacity-20 transition duration-500 blur-md"></div>
                                    <input v-model="chatInput" @keyup.enter="sendChatMessage()" type="text" placeholder="Inquire about this document..." class="relative w-full bg-[#0A0A0B] border border-white/10 rounded-xl px-6 py-4 pr-16 text-sm text-gray-200 placeholder-gray-600 focus:outline-none focus:border-lume-primary/50 focus:ring-0 transition-all font-medium tracking-wide" />
                                    <button @click="sendChatMessage()" :disabled="!chatInput.trim()||isChatLoading" class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 rounded-lg bg-[#CBB48A]/10 hover:bg-[#CBB48A] text-[#CBB48A] hover:text-white disabled:opacity-0 flex items-center justify-center transition-all duration-300 transform hover:scale-105 active:scale-95 group/send border border-transparent hover:border-[#CBB48A]/50">
                                        <svg class="w-4 h-4 transform group-hover/send:translate-x-0.5 group-hover/send:-translate-y-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sidebar Prompts -->
                        <div class="w-80 border-l border-white/5 bg-[#0A0A0B]/30 p-8 overflow-y-auto flex-shrink-0 hidden lg:flex flex-col gap-8">
                            <div>
                                <h4 class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-4">Intelligence Feed</h4>
                                <div class="space-y-3">
                                    <button v-for="(prompt, idx) in report?.suggested_prompts" :key="idx" @click="sendChatMessage(prompt)" class="w-full text-left p-5 rounded-2xl bg-[#0A0A0B] border border-white/5 text-xs font-medium text-gray-400 hover:text-white hover:border-lume-primary/30 hover:bg-lume-primary/5 transition-all leading-relaxed group shadow-sm hover:shadow-md relative overflow-hidden">
                                        <div class="absolute left-0 top-0 bottom-0 w-0.5 bg-lume-primary/0 group-hover:bg-lume-primary transition-colors"></div>
                                        <span class="group-hover:translate-x-1 block transition-transform">{{ prompt }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ TAB: AI CHECKER (V2 HUNTER UI) ══ -->
                    <div v-if="activeTab === 'ai_checker'" class="p-6 h-full flex flex-col items-center justify-center">

                        <!-- State A: Idle -->
                        <div v-if="aiDetectionState === 'idle'" class="text-center max-w-lg">
                            <div class="w-24 h-24 mx-auto mb-8 rounded-3xl bg-gradient-to-br from-[#CBB48A]/20 to-teal-500/20 border border-white/10 flex items-center justify-center relative group">
                                <div class="absolute inset-0 bg-[#CBB48A]/20 blur-xl group-hover:bg-[#CBB48A]/30 transition-all duration-500"></div>
                                <svg class="w-10 h-10 text-[#CBB48A] relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                            </div>
                            <h3 class="text-3xl font-bold text-white mb-4 tracking-tight uppercase italic">Lume Forensic <span class="text-[#CBB48A]">Engine</span></h3>
                            <p class="text-gray-400 text-sm leading-relaxed mb-10">Initiate a deep forensic scan for "Dirty Dozen" artifacts, structural monotony, and linguistic patterns commonly present in LLM-generated text.</p>
                            <button @click="runAiDetection" class="inline-flex items-center gap-3 px-8 py-4 bg-white text-black font-bold tracking-wider rounded-full hover:scale-105 active:scale-95 transition-all shadow-[0_0_20px_rgba(255,255,255,0.3)] hover:shadow-[0_0_30px_rgba(255,255,255,0.4)]">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                Run Forensic Analysis
                            </button>
                        </div>

                        <!-- State B: Loading (Cyberpunk Scanner) -->
                        <div v-if="aiDetectionState === 'loading'" class="flex flex-col items-center justify-center w-full max-w-2xl">
                            <!-- Scanner Visual -->
                            <div class="relative w-64 h-64 mb-12">
                                <div class="absolute inset-0 rounded-full border border-[#CBB48A]/30 animate-[spin_4s_linear_infinite]"></div>
                                <div class="absolute inset-4 rounded-full border border-teal-500/30 animate-[spin_3s_linear_infinite_reverse]"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="relative w-48 h-48 bg-[#050505] rounded-full flex items-center justify-center border border-[#CBB48A]/50 overflow-hidden shadow-[0_0_30px_rgba(203, 180, 138, 0.1)]">
                                        <!-- Scanning Grid -->
                                        <div class="absolute inset-0 bg-[linear-gradient(rgba(203, 180, 138, 0.1)_1px,transparent_1px),linear-gradient(90deg,rgba(203, 180, 138, 0.1)_1px,transparent_1px)] bg-[size:20px_20px]"></div>
                                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-[#CBB48A]/10 to-transparent w-full h-full animate-[spin_2s_linear_infinite] origin-bottom-right"></div>
                                        
                                        <div class="z-10 text-center">
                                            <span class="text-4xl animate-pulse filter drop-shadow-[0_0_10px_rgba(203, 180, 138, 0.5)]">🧬</span>
                                            <div class="text-[10px] font-black tracking-[0.2em] text-[#CBB48A] mt-4">ANALYZING</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Terminal Log -->
                            <div class="w-full bg-[#050505] rounded-xl border border-white/10 p-5 font-mono text-[10px] shadow-2xl relative overflow-hidden">
                                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-[#CBB48A] to-transparent"></div>
                                <div class="space-y-2 text-gray-400">
                                    <p class="flex justify-between border-b border-white/5 pb-2 mb-2"><span class="text-[#CBB48A]">> Initiating connection...</span> <span class="text-[#CBB48A] font-bold">OK</span></p>
                                    <p class="flex justify-between animation-delay-100"><span class="text-gray-300">> Extracting text sample...</span> <span class="text-[#CBB48A] font-bold">OK</span></p>
                                    <p class="flex justify-between animation-delay-300"><span class="text-gray-300">> Measuring Burstiness Variance...</span> <span class="animate-pulse text-amber-500 font-bold">CALCULATING</span></p>
                                    <p class="flex justify-between animation-delay-500"><span class="text-gray-300">> Scanning for 'Dirty Dozen'...</span> <span class="animate-pulse text-amber-500 font-bold">SEARCHING</span></p>
                                    <p class="mt-3 text-[#CBB48A]/60 italic">>> Sending forensic vector map to Neural Engine...</p>
                                </div>
                            </div>
                        </div>

                        <!-- State C: Results -->
                        <div v-if="aiDetectionState === 'done' || aiDetectionState === 'error'" class="w-full space-y-8 overflow-y-auto pr-2 custom-scrollbar">
                            
                            <!-- 1. HEAD OVERVIEW -->
                            <div class="bg-[#0A0A0B] border border-white/10 rounded-[2rem] p-8 shadow-2xl relative overflow-hidden group">
                                <div class="absolute inset-0 bg-grid-white/[0.02] opacity-50"></div>
                                <div class="absolute top-0 right-0 p-40 bg-lume-primary/5 rounded-full blur-[80px] pointer-events-none group-hover:bg-lume-primary/10 transition-colors duration-700"></div>
                                <div class="flex flex-col md:flex-row gap-10 items-center relative z-10">
                                    <!-- Gauge -->
                                    <div class="relative w-48 h-48 flex-shrink-0">
                                        <svg class="w-full h-full -rotate-90" viewBox="0 0 36 36">
                                            <path class="text-white/5" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="2.5" />
                                            <path :stroke="aiDetectionResult?.ai_probability > 50 ? '#ef4444' : '#CBB48A'" :stroke-dasharray="`${aiDetectionResult?.ai_probability || 0}, 100`" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke-linecap="round" stroke-width="2.5" class="drop-shadow-[0_0_15px_rgba(203, 180, 138, 0.4)] transition-all duration-1000 ease-out" />
                                        </svg>
                                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                                            <span class="text-4xl font-bold text-white tracking-tight drop-shadow-lg">{{ aiDetectionResult?.ai_probability || 0 }}%</span>
                                            <span class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 mt-2">AI Probability</span>
                                        </div>
                                    </div>

                                    <!-- Verdict Text -->
                                    <div class="flex-1 text-center md:text-left">
                                        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border text-[10px] font-bold tracking-wider mb-6 shadow-sm"
                                            :class="aiDetectionResult?.verdict?.includes('Human') ? 'bg-[#CBB48A]/10 text-[#CBB48A] border-[#CBB48A]/20' : aiDetectionResult?.verdict?.includes('AI') ? 'bg-red-500/10 text-red-400 border-red-500/20' : 'bg-amber-500/10 text-amber-400 border-amber-500/20'">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="aiDetectionResult?.verdict?.includes('Human') ? 'bg-[#CBB48A] animate-pulse' : aiDetectionResult?.verdict?.includes('AI') ? 'bg-red-400 animate-pulse' : 'bg-amber-400 animate-pulse'"></span>
                                            {{ aiDetectionResult?.verdict || 'Unknown' }}
                                        </div>
                                        <h3 class="text-3xl font-bold text-white tracking-tight mb-3 uppercase">Confidence: <span class="text-lume-primary text-transparent bg-clip-text bg-gradient-to-r from-[#CBB48A] to-teal-400">{{ aiDetectionResult?.confidence }}</span></h3>
                                        <p class="text-gray-400 text-sm leading-relaxed max-w-2xl font-medium tracking-wide">
                                            {{ aiDetectionResult?.overall_assessment }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. VISUALIZATION GRID -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                
                                <!-- WIDGET: Linguistic Artifacts Radar -->
                                <div class="bg-[#0A0A0B] border border-white/5 rounded-[1.5rem] p-8 flex flex-col hover:border-white/10 transition-all relative overflow-hidden group">
                                    <div class="absolute top-0 right-0 p-20 bg-red-500/5 rounded-full blur-[60px] pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                    <div class="flex items-center justify-between mb-8 relative z-10">
                                        <h4 class="text-[10px] font-black text-white uppercase tracking-[0.2em] flex items-center gap-3">
                                            <div class="w-1.5 h-1.5 rounded-full bg-red-500 shadow-[0_0_10px_#ef4444]"></div>
                                            Dirty Dozen Radar
                                        </h4>
                                    </div>
                                    
                                    <div class="space-y-5 flex-1 relative z-10">
                                        <template v-if="aiDetectionResult?.forensic_metrics?.dirty_dozen_breakdown && Object.keys(aiDetectionResult.forensic_metrics.dirty_dozen_breakdown).length > 0">
                                            <div v-for="(count, word) in aiDetectionResult.forensic_metrics.dirty_dozen_breakdown" :key="word" class="group/bar">
                                                <div class="flex justify-between items-end mb-2">
                                                    <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest group-hover/bar:text-white transition-colors">{{ word }}</span>
                                                    <span class="text-[10px] font-black text-red-400">{{ count }}</span>
                                                </div>
                                                <div class="w-full h-1.5 bg-white/5 rounded-full overflow-hidden">
                                                    <div class="h-full bg-gradient-to-r from-red-600 to-red-400 rounded-full shadow-[0_0_10px_rgba(239,68,68,0.4)]" 
                                                        :style="{ width: Math.min((count / 10) * 100, 100) + '%' }"></div>
                                                </div>
                                            </div>
                                        </template>
                                        <div v-else class="h-40 flex items-center justify-center text-gray-500 text-xs font-medium italic border border-dashed border-white/5 rounded-xl bg-white/[0.01]">
                                            No "Dirty Dozen" artifacts detected.
                                        </div>
                                    </div>
                                </div>

                                <!-- WIDGET: Sentence Structure Map -->
                                <div class="bg-[#0A0A0B] border border-white/5 rounded-[1.5rem] p-8 flex flex-col hover:border-white/10 transition-all relative overflow-hidden group">
                                    <div class="absolute top-0 right-0 p-20 bg-[#CBB48A]/5 rounded-full blur-[60px] pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                    <div class="flex items-center justify-between mb-8 relative z-10">
                                        <h4 class="text-[10px] font-black text-white uppercase tracking-[0.2em] flex items-center gap-3">
                                            <div class="w-1.5 h-1.5 rounded-full bg-[#CBB48A] shadow-[0_0_10px_#CBB48A]"></div>
                                            Burstiness Map
                                        </h4>
                                    </div>

                                    <div class="flex-1 flex items-end gap-[2px] h-48 mb-6 border-b border-white/5 pb-1 px-1 relative z-10">
                                        <template v-if="aiDetectionResult?.forensic_metrics?.sentence_lengths">
                                            <div v-for="(len, idx) in aiDetectionResult.forensic_metrics.sentence_lengths" :key="idx" 
                                                class="flex-1 bg-[#CBB48A]/30 hover:bg-lume-primary transition-all rounded-t-[1px] min-w-[2px] hover:shadow-[0_0_10px_rgba(203, 180, 138, 0.5)]"
                                                :style="{ height: Math.min(Math.max((len / 50) * 100, 5), 100) + '%' }"
                                                :title="len + ' words'">
                                            </div>
                                        </template>
                                        <div v-else class="w-full h-full flex items-center justify-center text-gray-500 text-xs font-medium italic">
                                            No sentence data available.
                                        </div>
                                    </div>

                                    <div class="flex justify-between items-center mt-2 relative z-10">
                                        <div class="text-[9px] text-gray-500 font-bold uppercase tracking-[0.2em]">Sequence Variance</div>
                                        <div class="text-xs font-black text-lume-primary font-mono tracking-wider">
                                            SCORE: {{ aiDetectionResult?.forensic_metrics?.burstiness_score || 0 }}<span class="text-gray-600">/100</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. FLAGGED EVIDENCE -->
                            <div v-if="aiDetectionResult?.flagged_passages?.length">
                                <h4 class="text-[10px] font-black text-white uppercase tracking-[0.2em] mb-6 flex items-center gap-3 px-2">
                                    <div class="w-1.5 h-1.5 rounded-full bg-amber-500 shadow-[0_0_10px_#f59e0b]"></div>
                                    Flagged Findings
                                </h4>
                                <div class="space-y-4">
                                    <div v-for="(fp, i) in aiDetectionResult.flagged_passages" :key="i" 
                                        @click="selectedFact = { data_point: 'AI Pattern Match', judgment: fp.reason, source_text: fp.context || fp.text, location: 'Approx. Loc: ' + (fp.location_index || 'Unknown') }"
                                        class="bg-[#0A0A0B] border border-white/5 hover:border-red-500/30 rounded-[1.5rem] p-6 group transition-all cursor-pointer relative overflow-hidden">
                                        
                                        <div class="absolute left-0 top-0 bottom-0 w-1 bg-red-500/20 group-hover:bg-red-500/50 transition-colors"></div>
                                        
                                        <p class="text-sm text-gray-400 italic mb-6 leading-loose pl-2 group-hover:text-gray-200 transition-colors font-medium">
                                            "{{ fp.text || fp }}"
                                        </p>
                                        <div class="flex items-center justify-between pl-2">
                                            <div class="flex items-center gap-4">
                                                <span class="text-[9px] font-black bg-red-500/10 text-red-400 px-3 py-1.5 rounded-lg border border-red-500/20 uppercase tracking-widest shadow-sm">Trigger</span>
                                                <span class="text-xs text-gray-500 group-hover:text-red-300 transition-colors font-bold">{{ fp.reason || 'AI Pattern Detected' }}</span>
                                            </div>
                                            <span class="text-[10px] text-lume-primary opacity-0 group-hover:opacity-100 transition-all transform translate-x-4 group-hover:translate-x-0 font-bold tracking-wider flex items-center gap-2">
                                                View Context <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center pt-12 pb-8">
                                <button @click="runAiDetection" class="text-gray-500 hover:text-white text-[10px] font-black uppercase tracking-[0.25em] px-6 py-3 hover:bg-white/5 rounded-xl transition-colors border border-transparent hover:border-white/10">↻ Re-run Analysis</button>
                            </div>

                        </div>
                    </div>
                    
                    <!-- REUSED SOURCE DRAWER for AI Evidence -->
                    <!-- This sits outside the tabs so it can overlay any tab if needed, but we keep it inside the main content area for scrolling context -->
                    <div v-if="selectedFact && activeTab === 'ai_checker'" class="absolute inset-y-0 right-0 w-[450px] bg-[#0A0A0B] border-l border-white/10 shadow-2xl flex flex-col z-20 animate-in slide-in-from-right duration-300">
                        <div class="px-6 py-5 border-b border-white/5 flex justify-between items-center bg-white/[0.02]">
                            <h5 class="text-xs font-black text-white uppercase tracking-[0.2em] flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                Forensic Evidence
                            </h5>
                            <button @click="selectedFact = null" class="text-gray-500 hover:text-white transition-colors"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </div>
                        <div class="flex-1 p-6 overflow-y-auto">
                            <div class="bg-[#CBB48A]/5 p-5 rounded-xl border border-[#CBB48A]/10 mb-6 relative overflow-hidden">
                                <div class="absolute top-0 right-0 p-2 opacity-10"><svg class="w-16 h-16 text-[#CBB48A]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg></div>
                                
                                <div class="text-[10px] text-[#CBB48A] uppercase tracking-widest font-black mb-3 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg> 
                                    Contextual Analysis
                                </div>
                                
                                <div class="font-serif text-gray-200 leading-relaxed text-sm pl-4 border-l-2 border-[#CBB48A]">
                                    <span v-if="!selectedFact.source_text?.includes('Context not found')">
                                        "...{{ selectedFact.source_text }}..."
                                    </span>
                                    <span v-else class="text-gray-500 italic">
                                        {{ selectedFact.source_text }}
                                    </span>
                                </div>

                                <div class="mt-4 pt-4 border-t border-white/5 flex justify-between items-center">
                                    <div class="text-[10px] text-gray-500 font-mono uppercase tracking-wider">{{ selectedFact.location }}</div>
                                    <button class="text-[10px] text-[#CBB48A] hover:text-white font-black uppercase tracking-wider transition-colors" title="Copy to clipboard">
                                        Copy Text
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-6">
                                <div>
                                    <span class="text-[10px] text-gray-500 block mb-2 uppercase tracking-widest font-bold">Pattern Type</span>
                                    <div class="font-bold text-white text-lg leading-snug border-l-2 border-red-500 pl-4">
                                        {{ selectedFact.data_point }}
                                    </div>
                                </div>
                                <div>
                                    <span class="text-[10px] text-gray-500 block mb-2 uppercase tracking-widest font-bold">AI Reasoning</span>
                                    <div class="text-sm text-gray-300 bg-white/[0.03] p-4 rounded-xl border border-white/5 leading-relaxed">
                                        {{ selectedFact.judgment }}
                                    </div>
                                </div>
                                <div class="bg-teal-500/10 border border-teal-500/20 rounded-xl p-4">
                                    <div class="flex gap-3">
                                        <svg class="w-5 h-5 text-teal-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <div class="text-xs text-teal-300 leading-relaxed">
                                            <span class="font-black uppercase tracking-wide block mb-1 text-[10px]">Significance</span>
                                            This pattern strongly correlates with specific training data artifacts found in GPT-4 and Claude 3. Human writing typically exhibits higher "burstiness" and irregularity in this context.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
            </div>
        </div>
    </div>
        </transition>
    </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 5px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(203, 180, 138, 0.3); border-radius: 4px; }

/* V3 Highlight Styles */
.document-text :deep(.hl-mark) { padding: 1px 4px; border-radius: 3px; cursor: pointer; transition: all 0.2s ease; position: relative; }

/* SPELLING: Red squiggly underline */
/* ─── Premium Forensic Highlights ─── */
.document-text :deep(.hl-mark) { transition: all 0.2s ease; cursor: help; border-radius: 2px; }
.document-text :deep(.hl-mark:hover) { filter: brightness(1.05); transform: scale(1.01); box-shadow: 0 2px 4px rgba(0,0,0,0.1); z-index: 10; position: relative; }

/* SPELLING: Critical Red Wavy Underline + Background */
.document-text :deep(.hl-spelling_error) { 
    text-decoration: underline wavy #ef4444 2px; 
    text-underline-offset: 3px; 
    background: rgba(239, 68, 68, 0.15); 
    color: #fca5a5; 
}

/* GRAMMAR: Heavy Orange Dashed Underline */
.document-text :deep(.hl-grammar_error) { 
    border-bottom: 2px solid #f97316; 
    background: rgba(249, 115, 22, 0.15); 
    color: #fdba74; 
}

/* CITATION: Clear Green Background check */
.document-text :deep(.hl-citation) { 
    background: rgba(203, 180, 138, 0.18); 
    border-bottom: 2px solid rgba(203, 180, 138, 0.5); 
    color: #6ee7b7; 
}

/* ENTITY: Blue Boxed Highlight */
.document-text :deep(.hl-entity) { 
    background: rgba(59, 130, 246, 0.15); 
    border: 1px solid rgba(59, 130, 246, 0.3); 
    border-radius: 3px; 
    color: #93c5fd; 
    padding: 0 2px; 
}

/* FORMATTING: High Visibility Yellow */
.document-text :deep(.hl-formatting_issue) { 
    background: rgba(234, 179, 8, 0.15); 
    border-bottom: 2px dashed #eab308; 
    color: #fde68a; 
}

/* PII: Danger Red Box */
.document-text :deep(.hl-pii_risk) { 
    background: rgba(239, 68, 68, 0.2); 
    border: 1px solid rgba(239, 68, 68, 0.5); 
    color: #fca5a5; 
    font-weight: 600; 
}

/* STRUCTURE: Teal Left Border */
.document-text :deep(.hl-structure) { 
    background: linear-gradient(90deg, rgba(20, 184, 166, 0.15) 0%, transparent 100%); 
    border-left: 3px solid #14b8a6; 
    padding-left: 4px; 
    color: #5eead4; 
}

/* Badge */
.document-text :deep(.hl-badge) { display: inline-block; font-size: 10px; font-weight: 800; padding: 1px 4px; border-radius: 4px; margin-left: 4px; vertical-align: top; transform: translateY(-2px); box-shadow: 0 1px 2px rgba(0,0,0,0.2); }
.document-text :deep(.hl-badge-verified) { background: #CBB48A; color: #fff; border: none; }
.document-text :deep(.hl-badge-unverified) { background: #ef4444; color: #fff; border: none; }

/* Flash Animation */
.document-text :deep(.hl-flash) { animation: highlightPulse 2s cubic-bezier(0.4, 0, 0.2, 1); }
@keyframes highlightPulse { 
    0%, 100% { box-shadow: none; background-color: transparent; } 
    10% { background-color: rgba(203, 180, 138, 0.3); box-shadow: 0 0 0 6px rgba(203, 180, 138, 0.4); } 
    50% { background-color: rgba(203, 180, 138, 0.1); box-shadow: 0 0 0 2px rgba(203, 180, 138, 0.1); } 
}

/* Document formatting */
.document-text :deep(.doc-paragraph) { text-align: justify; text-justify: inter-word; margin-bottom: 1rem; line-height: 1.85; color: #d1d5db; font-size: 0.95rem; text-indent: 2em; }
.document-text :deep(.doc-paragraph:first-child) { text-indent: 0; }
.document-text :deep(.doc-heading) { color: #e5e7eb; margin-top: 2rem; margin-bottom: 0.75rem; font-weight: 700; border-left: 3px solid rgba(203, 180, 138, 0.5); padding-left: 0.75rem; }
.document-text :deep(.doc-heading-h3) { font-size: 1.2rem; color: #f3f4f6; border-left-color: rgba(203, 180, 138, 0.7); }
.document-text :deep(.doc-heading-h4) { font-size: 1.05rem; color: #e5e7eb; border-left-color: rgba(203, 180, 138, 0.4); }
.document-text :deep(.doc-heading-h5) { font-size: 0.95rem; border-left-color: rgba(203, 180, 138, 0.3); }
.document-text :deep(.doc-heading-caps) { font-size: 1.15rem; text-transform: uppercase; letter-spacing: 0.08em; color: #f3f4f6; border-left-color: rgba(20,184,166,0.6); }
.document-text :deep(.doc-heading-num) { color: rgba(203, 180, 138, 0.8); font-weight: 800; margin-right: 0.25rem; }
.document-text :deep(.doc-table-wrap) { margin: 1.5rem 0; overflow-x: auto; border-radius: 8px; border: 1px solid rgba(55,65,81,0.5); }
.document-text :deep(.doc-table) { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.document-text :deep(.doc-table-header th) { background: rgba(203, 180, 138, 0.12); color: #6ee7b7; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.06em; padding: 10px 14px; text-align: left; border-bottom: 2px solid rgba(203, 180, 138, 0.25); }
.document-text :deep(.doc-table td) { padding: 8px 14px; border-bottom: 1px solid rgba(55,65,81,0.35); color: #d1d5db; }
.document-text :deep(.doc-table tr:hover td) { background: rgba(203, 180, 138, 0.05); }
.document-text :deep(.doc-list) { margin: 0.75rem 0 1rem 1.5rem; list-style: none; padding: 0; }
.document-text :deep(.doc-list li) { padding: 4px 0 4px 1.25rem; position: relative; color: #d1d5db; font-size: 0.93rem; line-height: 1.7; text-align: justify; }
.document-text :deep(.doc-list li::before) { content: '•'; position: absolute; left: 0; color: #CBB48A; font-weight: 700; }
.document-text :deep(.doc-list-numbered li::before) { content: none; }
.document-text :deep(.doc-list-num) { color: #CBB48A; font-weight: 700; margin-right: 0.35rem; }
.document-text :deep(.doc-caption) { font-size: 0.85rem; color: #6ee7b7; font-style: italic; text-align: center; margin: 1.25rem 0; padding: 0.6rem 1rem; background: rgba(203, 180, 138, 0.06); border-radius: 6px; border: 1px dashed rgba(203, 180, 138, 0.2); }
.document-text :deep(.doc-list-item) { padding: 4px 0 4px 1.75rem; position: relative; color: #d1d5db; font-size: 0.93rem; line-height: 1.7; text-align: justify; }
.document-text :deep(.doc-list-item::before) { content: '•'; position: absolute; left: 0.5rem; color: #CBB48A; font-weight: 700; }

/* Schema / DB Tables */
.document-text :deep(.doc-schema-table td:first-child) { font-family: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', monospace; font-size: 0.82rem; color: #6ee7b7; font-weight: 600; }
.document-text :deep(.doc-schema-table td:nth-child(2)) { font-family: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', monospace; font-size: 0.8rem; color: #f59e0b; }
.document-text :deep(.doc-schema-table tr:nth-child(even) td) { background: rgba(203, 180, 138, 0.04); }

/* Comparison Tables with check/cross */
.document-text :deep(.doc-compare-table td) { text-align: center; }
.document-text :deep(.doc-compare-table td:first-child) { text-align: left; font-weight: 600; color: #e5e7eb; }
.document-text :deep(.doc-cell-check) { color: #CBB48A !important; font-size: 1.1rem; font-weight: 700; background: rgba(203, 180, 138, 0.08) !important; }
.document-text :deep(.doc-cell-cross) { color: #ef4444 !important; font-size: 1.1rem; font-weight: 700; background: rgba(239,68,68,0.06) !important; }

/* Figure / Image Placeholder */
.document-text :deep(.doc-figure-placeholder) { margin: 2rem auto; padding: 2.5rem 2rem; background: linear-gradient(135deg, rgba(203, 180, 138, 0.06) 0%, rgba(20,184,166,0.06) 100%); border: 1px dashed rgba(203, 180, 138, 0.3); border-radius: 12px; text-align: center; max-width: 500px; }
.document-text :deep(.doc-figure-icon) { color: rgba(203, 180, 138, 0.5); margin-bottom: 0.75rem; }
.document-text :deep(.doc-figure-icon svg) { margin: 0 auto; }
.document-text :deep(.doc-figure-label) { font-size: 0.85rem; color: #9ca3af; font-style: italic; margin: 0; }

/* IEEE / Academic Style Overrides */
.document-text :deep(.doc-paragraph) { 
    text-align: justify; 
    text-justify: inter-word; 
    margin-bottom: 1rem; 
    line-height: 1.8; 
    color: #d1d5db; 
    font-size: 0.95rem; 
}
.document-text :deep(.doc-paragraph:first-child) { text-indent: 0; }

/* Inline Header (e.g. "Snappr.") */
.document-text :deep(.doc-inline-header) {
    font-weight: 800;
    color: #f3f4f6; /* High contrast against dark bg */
    display: inline;
    margin-right: 4px;
}

/* Abstract & Keywords */
.document-text :deep(.doc-abstract), 
.document-text :deep(.doc-keywords) {
    font-style: italic;
    background: rgba(255, 255, 255, 0.03);
    padding: 12px 16px;
    border-left: 3px solid #CBB48A;
    border-radius: 0 4px 4px 0;
    margin-bottom: 1.5rem;
}

.document-text :deep(.doc-abstract strong),
.document-text :deep(.doc-keywords strong) {
    font-style: normal;
    font-weight: 800;
    font-variant: small-caps;
    margin-right: 6px;
    color: #CBB48A;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
</style>

