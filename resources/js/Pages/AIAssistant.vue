<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed, nextTick, onMounted } from 'vue';
import axios from 'axios';

interface ChatMessage {
    id: string;
    role: 'user' | 'ai';
    content: string;
    timestamp: string;
    specialCard?: {
        riskScore: number;
        riskLevel: string;
        critical: number;
        high: number;
        medium: number;
        low: number;
        risks: { name: string; severity: string }[];
    } | null;
}

const page = usePage();
const currentUser = page.props.auth.user;
const userInitials = computed(() => {
    if (!currentUser?.name) return 'AD';
    return currentUser.name.split(' ').map((n: string) => n[0]).join('').toUpperCase().substring(0, 2);
});

// State
const chatMessages = ref<ChatMessage[]>([]);
const messageInput = ref('');
const isResponding = ref(false);
const showModelDropdown = ref(false);

const recentConversations = ref<any[]>([]);
const activeConversationId = ref<string | null>(null);
const showHistoryDrawer = ref(false);

const fetchConversations = async () => {
    try {
        const res = await axios.get('/ai/history');
        recentConversations.value = res.data.conversations || [];
    } catch (e) {
        console.error('Error fetching conversations:', e);
    }
};

const loadConversation = async (conversationId: string) => {
    try {
        const res = await axios.get('/ai/chat/history', {
            params: { conversation_id: conversationId }
        });
        chatMessages.value = (res.data.messages || []).map((msg: any) => {
            const time = msg.timestamp 
                ? new Date(msg.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) 
                : 'Today';
            return {
                id: msg.id || Math.random().toString(36).substring(7),
                role: msg.role,
                content: msg.content,
                timestamp: time
            };
        });
        activeConversationId.value = conversationId;
        showHistoryDrawer.value = false;
        await nextTick();
        scrollChatToBottom();
    } catch (e) {
        console.error('Error loading conversation:', e);
    }
};

const startNewChat = () => {
    chatMessages.value = [];
    activeConversationId.value = null;
};

const handlePromptClick = (prompt: string) => {
    messageInput.value = prompt;
    handleSendMessage();
};

const handleSendMessage = async () => {
    if (!messageInput.value.trim() || isResponding.value) return;

    const userMessageText = messageInput.value.trim();
    messageInput.value = '';

    const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    // Append User Message
    chatMessages.value.push({
        id: Math.random().toString(36).substring(7),
        role: 'user',
        content: userMessageText,
        timestamp: timestamp
    });

    isResponding.value = true;
    
    await nextTick();
    scrollChatToBottom();

    try {
        let aiResponseContent = '';
        let specialCardData = null;

        // Custom UI match for mockup trigger: "Analyze poolreno.com"
        if (userMessageText.toLowerCase().includes('poolreno.com')) {
            aiResponseContent = "Here's the security overview for poolreno.com based on the latest scan completed on Jun 23, 2026 10:15 PM.";
            specialCardData = {
                riskScore: 32,
                riskLevel: 'HIGH',
                critical: 2,
                high: 6,
                medium: 8,
                low: 12,
                risks: [
                    { name: 'Outdated framework detected (Laravel 11.x)', severity: 'CRITICAL' },
                    { name: 'Exposed administrative endpoint (/admin)', severity: 'HIGH' },
                    { name: 'Missing security headers (CSP, HSTS, X-Frame-Options)', severity: 'MEDIUM' },
                    { name: 'Git repository leaks detected', severity: 'MEDIUM' },
                    { name: 'Content discovery: sensitive files exposed', severity: 'LOW' }
                ]
            };

            // Still save to backend to persist history
            const res = await axios.post('/ai/chat', { 
                message: userMessageText,
                conversation_id: activeConversationId.value
            });
            if (res.data.conversation_id) {
                activeConversationId.value = res.data.conversation_id;
            }
        } else {
            // Live API request to AI Chat endpoint
            const res = await axios.post('/ai/chat', { 
                message: userMessageText,
                conversation_id: activeConversationId.value
            });
            aiResponseContent = res.data.reply || "No reply from AI service.";
            if (res.data.conversation_id) {
                activeConversationId.value = res.data.conversation_id;
            }
        }

        chatMessages.value.push({
            id: Math.random().toString(36).substring(7),
            role: 'ai',
            content: aiResponseContent,
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            specialCard: specialCardData
        });

        fetchConversations();

    } catch (e: any) {
        console.error(e);
        chatMessages.value.push({
            id: Math.random().toString(36).substring(7),
            role: 'ai',
            content: "Sorry, I encountered an error communicating with the LUME AI intelligence agent. Please check your credentials or connection.",
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        });
    } finally {
        isResponding.value = false;
        await nextTick();
        scrollChatToBottom();
    }
};

function scrollChatToBottom() {
    const chatContainer = document.getElementById('chat-scroll-container');
    if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
}

onMounted(() => {
    fetchConversations();
});
</script>

<template>
    <Head title="AI Assistant" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-bold tracking-tight text-white/90">AI Assistant</h2>
                    <span class="px-1.5 py-0.5 rounded text-[8px] font-bold text-violet-400 bg-violet-500/10 border border-violet-500/20 font-mono uppercase leading-none">Beta</span>
                </div>
                <span class="text-xs text-gray-500 mt-1 font-medium">Your intelligent security analyst. Ask questions, get insights, and automate your investigations.</span>
            </div>
        </template>

        <div class="space-y-6 pb-16 flex flex-col h-[82vh]">
            <!-- Controls Toolbar -->
            <div class="flex items-center justify-end border-b border-white/5 pb-4 shrink-0">
                <div class="flex items-center gap-3">
                    <!-- Intelligence Model Selector -->
                    <div class="relative">
                        <button @click="showModelDropdown = !showModelDropdown" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-white/5 bg-[#050811] text-xs font-semibold text-slate-300 hover:text-white transition-all">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#CBB48A] animate-pulse"></span>
                            <span>LUME Intelligence v1.0</span>
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                    </div>

                    <!-- New Chat Button -->
                    <button @click="startNewChat" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-white/5 bg-[#050811] text-xs font-semibold text-slate-300 hover:text-white transition-all">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        <span>New Chat</span>
                    </button>

                    <!-- History Button -->
                    <button @click="showHistoryDrawer = true" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-white/5 bg-[#050811] text-xs font-semibold text-slate-300 hover:text-white transition-all">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>History</span>
                    </button>
                </div>
            </div>

            <!-- Layout Area: Two Columns (Main Chat window and Sidebar panels) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 flex-1 min-h-0 items-stretch">
                
                <!-- Left Side: Chat Panel Container -->
                <div class="lg:col-span-9 bg-[#050811]/40 border border-white/5 rounded-2xl flex flex-col min-h-0 relative">
                    
                    <!-- Chat Scroll Area -->
                    <div id="chat-scroll-container" class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar">
                        <!-- Welcome / Prompt Suggestions Banner (Only visible if no messages exist) -->
                        <div v-if="chatMessages.length === 0" class="py-12 flex flex-col items-center justify-center text-center space-y-8 max-w-xl mx-auto my-auto h-full">
                            <div class="h-16 w-16 rounded-full bg-slate-900 border border-white/5 flex items-center justify-center text-[#CBB48A]">
                                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                            </div>
                            
                            <div class="space-y-2">
                                <h2 class="text-xl font-bold text-white">Hello, Analyst.</h2>
                                <h3 class="text-lg font-bold text-[#CBB48A]">How can I help you today?</h3>
                                <p class="text-xs text-slate-400 leading-relaxed">
                                    I can analyze your assets, findings, scans, and external data to help you uncover risks and make smarter decisions.
                                </p>
                            </div>

                            <!-- Prompt suggestion cards row -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full text-left">
                                <button 
                                    @click="handlePromptClick('Summarize Risk')"
                                    class="p-4 rounded-xl border border-white/5 bg-[#050811] hover:border-slate-800 text-slate-300 hover:text-white transition-all space-y-1.5 block group"
                                >
                                    <div class="flex items-center justify-between text-[#CBB48A]">
                                        <span class="text-[10px] font-extrabold uppercase font-mono tracking-wider">Summarize Risk</span>
                                        <span class="text-xs group-hover:translate-x-1 transition-transform">&rarr;</span>
                                    </div>
                                    <p class="text-[9.5px] text-slate-500 leading-normal">Give me a summary of the highest risks across all my assets.</p>
                                </button>

                                <button 
                                    @click="handlePromptClick('Analyze poolreno.com and show me the top security risks')"
                                    class="p-4 rounded-xl border border-white/5 bg-[#050811] hover:border-slate-800 text-slate-300 hover:text-white transition-all space-y-1.5 block group"
                                >
                                    <div class="flex items-center justify-between text-[#CBB48A]">
                                        <span class="text-[10px] font-extrabold uppercase font-mono tracking-wider">Investigate Domain</span>
                                        <span class="text-xs group-hover:translate-x-1 transition-transform">&rarr;</span>
                                    </div>
                                    <p class="text-[9.5px] text-slate-500 leading-normal">Analyze poolreno.com and show me potential exposure.</p>
                                </button>

                                <button 
                                    @click="handlePromptClick('Scan repository github.com/poolreno/web and summarize risks')"
                                    class="p-4 rounded-xl border border-white/5 bg-[#050811] hover:border-slate-800 text-slate-300 hover:text-white transition-all space-y-1.5 block group"
                                >
                                    <div class="flex items-center justify-between text-[#CBB48A]">
                                        <span class="text-[10px] font-extrabold uppercase font-mono tracking-wider">Repo Analysis</span>
                                        <span class="text-xs group-hover:translate-x-1 transition-transform">&rarr;</span>
                                    </div>
                                    <p class="text-[9.5px] text-slate-500 leading-normal">Scan codebase repository to identify dependency depth risks.</p>
                                </button>

                                <button 
                                    @click="handlePromptClick('What security trends have changed in my environment?')"
                                    class="p-4 rounded-xl border border-white/5 bg-[#050811] hover:border-slate-800 text-slate-300 hover:text-white transition-all space-y-1.5 block group"
                                >
                                    <div class="flex items-center justify-between text-[#CBB48A]">
                                        <span class="text-[10px] font-extrabold uppercase font-mono tracking-wider">Trend & Insights</span>
                                        <span class="text-xs group-hover:translate-x-1 transition-transform">&rarr;</span>
                                    </div>
                                    <p class="text-[9.5px] text-slate-500 leading-normal">What security trends have changed in my environment?</p>
                                </button>
                            </div>
                        </div>

                        <!-- Render messages dynamically -->
                        <div v-else class="space-y-6">
                            <div 
                                v-for="message in chatMessages" 
                                :key="message.id"
                                class="flex gap-4 items-start"
                                :class="message.role === 'user' ? 'justify-end text-right' : 'justify-start text-left'"
                            >
                                <!-- AI Avatar -->
                                <div v-if="message.role === 'ai'" class="h-8 w-8 rounded-lg bg-[#CBB48A]/10 border border-[#CBB48A]/20 flex items-center justify-center text-[#CBB48A] shrink-0 select-none">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                                </div>

                                <!-- Content Container -->
                                <div class="max-w-[70%] space-y-1 relative group">
                                    <div class="text-[9px] text-slate-500 font-mono">{{ message.timestamp }}</div>
                                    <div 
                                        class="rounded-2xl p-4 text-xs leading-relaxed"
                                        :class="message.role === 'user' 
                                            ? 'bg-slate-900 border border-slate-800 text-slate-200 rounded-tr-none text-left' 
                                            : 'bg-[#050811] border border-slate-800 text-slate-300 rounded-tl-none text-left'"
                                    >
                                        {{ message.content }}
                                        
                                        <!-- Render special card graphic if exists (e.g. poolreno summary) -->
                                        <div v-if="message.specialCard" class="mt-4 border border-white/5 bg-slate-950/60 rounded-xl p-4 space-y-4">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <span class="text-[8px] font-bold text-slate-500 uppercase tracking-widest block">Risk Score</span>
                                                    <div class="flex items-center gap-1.5 mt-0.5">
                                                        <div class="relative flex items-center justify-center w-7 h-7">
                                                            <svg class="absolute inset-0 w-full h-full text-rose-500" viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="10">
                                                                <polygon points="50,5 90,28 90,72 50,95 10,72 10,28" />
                                                            </svg>
                                                            <span class="z-10 font-mono font-bold text-[9px] text-white">{{ message.specialCard.riskScore }}</span>
                                                        </div>
                                                        <span class="text-[9px] font-black uppercase text-rose-500">{{ message.specialCard.riskLevel }}</span>
                                                    </div>
                                                </div>

                                                <div class="flex gap-4 text-center">
                                                    <div>
                                                        <span class="text-[8px] font-bold text-slate-500 uppercase tracking-widest block">Critical</span>
                                                        <span class="text-xs font-bold text-rose-500 font-mono">{{ message.specialCard.critical }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-[8px] font-bold text-slate-500 uppercase tracking-widest block">High</span>
                                                        <span class="text-xs font-bold text-amber-500 font-mono">{{ message.specialCard.high }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-[8px] font-bold text-slate-500 uppercase tracking-widest block">Medium</span>
                                                        <span class="text-xs font-bold text-yellow-500 font-mono">{{ message.specialCard.medium }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-[8px] font-bold text-slate-500 uppercase tracking-widest block">Low</span>
                                                        <span class="text-xs font-bold text-emerald-400 font-mono">{{ message.specialCard.low }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="space-y-2 border-t border-white/5 pt-3">
                                                <span class="text-[8px] font-bold text-slate-500 uppercase tracking-wider block">Top Security Risks</span>
                                                <div 
                                                    v-for="(risk, index) in message.specialCard.risks" 
                                                    :key="index"
                                                    class="flex items-center justify-between text-[10px] py-1 border-b border-white/[0.02]"
                                                >
                                                    <span class="font-semibold text-slate-300">{{ index + 1 }}. {{ risk.name }}</span>
                                                    <span 
                                                        class="px-1.5 py-0.5 rounded text-[8px] font-bold font-mono tracking-tight"
                                                        :class="
                                                            risk.severity === 'CRITICAL' ? 'bg-red-500/10 text-red-500 border border-red-500/25' :
                                                            risk.severity === 'HIGH' ? 'bg-amber-500/10 text-amber-500 border border-amber-500/25' :
                                                            risk.severity === 'MEDIUM' ? 'bg-yellow-500/10 text-yellow-500 border border-yellow-500/25' :
                                                            'bg-emerald-500/10 text-emerald-400 border border-emerald-500/25'
                                                        "
                                                    >
                                                        {{ risk.severity }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- User Avatar -->
                                <div v-if="message.role === 'user'" class="h-8 w-8 rounded-lg bg-indigo-600 font-bold text-white text-[10px] flex items-center justify-center shrink-0 border border-indigo-400/20 select-none">
                                    {{ userInitials }}
                                </div>
                            </div>

                            <!-- Processing AI Response typing status -->
                            <div v-if="isResponding" class="flex gap-4 items-start justify-start text-left">
                                <div class="h-8 w-8 rounded-lg bg-[#CBB48A]/10 border border-[#CBB48A]/20 flex items-center justify-center text-[#CBB48A] shrink-0">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                </div>
                                <div class="bg-[#050811] border border-slate-800 rounded-2xl rounded-tl-none p-4 max-w-[70%] text-xs text-slate-400 flex items-center gap-1.5 font-mono">
                                    <span>LUME is executing forensic reasoning</span>
                                    <span class="animate-bounce">.</span>
                                    <span class="animate-bounce" style="animation-delay: 0.2s">.</span>
                                    <span class="animate-bounce" style="animation-delay: 0.4s">.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Chat input panel matching design -->
                    <div class="p-4 border-t border-white/5 bg-slate-950/60 rounded-b-2xl shrink-0">
                        <div class="relative rounded-xl border border-white/5 focus-within:border-[#CBB48A]/50 bg-slate-950 p-2.5 transition-all">
                            <textarea 
                                v-model="messageInput"
                                @keydown.enter.prevent="handleSendMessage"
                                class="block w-full border-none bg-transparent p-1.5 text-xs text-white placeholder-slate-600 focus:ring-0 focus:outline-none resize-none h-14"
                                placeholder="Ask anything about your assets, scans, findings..."
                            ></textarea>
                            
                            <div class="flex items-center justify-between border-t border-white/5 pt-2 mt-1">
                                <div class="flex items-center gap-3 text-slate-500">
                                    <button class="hover:text-[#CBB48A]"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg></button>
                                    <button class="hover:text-[#CBB48A]"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9" /></svg></button>
                                    <button class="hover:text-[#CBB48A]"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg></button>
                                    <button class="hover:text-[#CBB48A]"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></button>
                                </div>

                                <button 
                                    @click="handleSendMessage"
                                    class="h-7 w-7 rounded-lg bg-[#CBB48A] hover:bg-[#CBB48A]/80 text-slate-900 flex items-center justify-center transition-all"
                                >
                                    <svg class="w-4 h-4 transform rotate-90" fill="currentColor" viewBox="0 0 24 24"><path d="M2 21l21-9L2 3v7l15 2-15 2z" /></svg>
                                </button>
                            </div>
                        </div>
                        <p class="text-[9px] text-slate-600 mt-2 text-center select-none font-mono">AI can make mistakes. Verify important findings.</p>
                    </div>
                </div>

                <!-- Right Side Widget Column matching mockup -->
                <div class="lg:col-span-3 space-y-6 flex flex-col h-full overflow-y-auto custom-scrollbar shrink-0">
                    <!-- Assistant Insights -->
                    <div class="bg-[#050811]/60 border border-white/5 rounded-2xl p-5 text-left space-y-4">
                        <div class="flex items-center justify-between border-b border-white/5 pb-2">
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Assistant Insights</h3>
                            <a href="#" class="text-[10px] font-bold text-[#CBB48A] hover:underline">View all</a>
                        </div>
                        <div class="space-y-4">
                            <div class="flex items-start gap-3 text-xs">
                                <div class="h-7 w-7 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-500 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                </div>
                                <div>
                                    <span class="font-bold text-white block">2 critical issues</span>
                                    <span class="text-[9.5px] text-slate-500 block leading-tight">Require immediate attention</span>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 text-xs">
                                <div class="h-7 w-7 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <div>
                                    <span class="font-bold text-white block">3 assets changed</span>
                                    <span class="text-[9.5px] text-slate-500 block leading-tight">Since your last scan</span>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 text-xs">
                                <div class="h-7 w-7 rounded-lg bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                </div>
                                <div>
                                    <span class="font-bold text-white block">12 new findings</span>
                                    <span class="text-[9.5px] text-slate-500 block leading-tight">Discovered in the last 7 days</span>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 text-xs">
                                <div class="h-7 w-7 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-500 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                                </div>
                                <div>
                                    <span class="font-bold text-white block">High risk trend</span>
                                    <span class="text-[9.5px] text-slate-500 block leading-tight">Risk score increased by 18%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-[#050811]/60 border border-white/5 rounded-2xl p-5 text-left space-y-3.5">
                        <h3 class="text-xs font-bold text-white uppercase tracking-wider border-b border-white/5 pb-2">Quick Actions</h3>
                        <div class="space-y-2 text-xs">
                            <button 
                                v-for="act in [
                                    { title: 'Generate Report', desc: 'Create a security report' },
                                    { title: 'Create Target', desc: 'Add a new target to monitor' },
                                    { title: 'Run Scan', desc: 'Start a new scan' },
                                    { title: 'Threat Intelligence', desc: 'Search external threat data' },
                                    { title: 'Explain Finding', desc: 'Get AI explanation for a finding' }
                                ]" 
                                :key="act.title"
                                class="w-full p-2.5 rounded-xl border border-white/[0.02] bg-slate-950/60 hover:bg-white/5 transition-all text-left flex items-center justify-between group"
                            >
                                <div class="min-w-0">
                                    <span class="font-bold text-white block">{{ act.title }}</span>
                                    <span class="text-[9.5px] text-slate-500 block leading-normal">{{ act.desc }}</span>
                                </div>
                                <span class="text-slate-500 group-hover:text-white transition-colors">&rsaquo;</span>
                            </button>
                        </div>
                    </div>

                    <!-- Recent Conversations -->
                    <div class="bg-[#050811]/60 border border-white/5 rounded-2xl p-5 text-left space-y-4">
                        <div class="flex items-center justify-between border-b border-white/5 pb-2">
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Recent Conversations</h3>
                            <a href="#" @click.prevent="showHistoryDrawer = true" class="text-[10px] font-bold text-[#CBB48A] hover:underline">View all</a>
                        </div>
                        <div class="space-y-3.5">
                            <div v-if="recentConversations.length === 0" class="text-slate-600 text-xs font-mono py-2 text-center">
                                No recent chats
                            </div>
                            <button 
                                v-for="conv in recentConversations.slice(0, 5)" 
                                :key="conv.conversation_id"
                                @click="loadConversation(conv.conversation_id)"
                                class="w-full text-left flex items-start justify-between gap-2 text-xs group hover:text-white"
                                :class="{ 'text-[#CBB48A]': activeConversationId === conv.conversation_id }"
                            >
                                <div class="min-w-0 flex-1">
                                    <span class="font-bold text-slate-300 group-hover:text-white truncate block transition-colors">{{ conv.title }}</span>
                                    <span class="text-[9.5px] text-slate-500 block font-mono">{{ conv.time }}</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- HISTORY DRAWER -->
        <div v-if="showHistoryDrawer" class="fixed inset-0 z-50 flex justify-end">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-[#020408]/80 backdrop-blur-sm" @click="showHistoryDrawer = false"></div>
            
            <!-- Drawer Content -->
            <div class="relative w-full max-w-md bg-[#050811] border-l border-slate-800 h-full shadow-2xl p-6 flex flex-col space-y-6 text-left">
                <div class="flex items-center justify-between border-b border-white/5 pb-4">
                    <div>
                        <h2 class="text-base font-bold text-white">Chat History</h2>
                        <p class="text-[10px] text-slate-500 mt-0.5">Resume your previous discussions with LUME Architect.</p>
                    </div>
                    <button @click="showHistoryDrawer = false" class="text-slate-500 hover:text-white">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Sessions List -->
                <div class="flex-1 overflow-y-auto space-y-3 pr-1 custom-scrollbar">
                    <div v-if="recentConversations.length === 0" class="py-12 text-center text-slate-600 text-xs font-mono">
                        No previous conversations found.
                    </div>
                    <button 
                        v-for="conv in recentConversations" 
                        :key="conv.conversation_id"
                        @click="loadConversation(conv.conversation_id)"
                        class="w-full p-4 rounded-xl border border-white/5 bg-slate-950/60 hover:bg-white/5 transition-all text-left flex flex-col space-y-2 group"
                        :class="{ 'border-[#CBB48A]/30 bg-[#CBB48A]/5': activeConversationId === conv.conversation_id }"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-bold text-white text-xs group-hover:text-[#CBB48A] transition-colors truncate block flex-1">{{ conv.title }}</span>
                            <span class="text-[9px] text-slate-500 font-mono whitespace-nowrap">{{ conv.time }}</span>
                        </div>
                        <p class="text-[10px] text-slate-400 truncate">{{ conv.latest_message }}</p>
                    </button>
                </div>
                
                <div class="pt-4 border-t border-white/5 flex gap-3">
                    <button @click="startNewChat(); showHistoryDrawer = false" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 text-xs font-bold text-black hover:from-amber-400 hover:to-amber-500 transition-all text-center">
                        + Start New Chat
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
/* Slim scrollbars */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(2, 4, 8, 0.5);
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(203, 180, 138, 0.2);
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(203, 180, 138, 0.4);
}
</style>
