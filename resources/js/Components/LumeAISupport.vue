<script setup lang="ts">
import { ref, onMounted, nextTick, computed, watch } from 'vue';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';

// Supports both embedded usage (DocumentReportModal) and standalone Modal (Dashboard)
const props = defineProps<{
    // EMBEDDED MODE PROPS
    context?: {
        assetId: string;
        type: string;
        textSnippet?: string;
    };
    
    // MODAL MODE PROPS
    show?: boolean;
    mode?: string; // 'pentest', 'analytic', etc.
    asset?: any;
}>();

const emit = defineEmits(['close']);

// Normalize context
const activeContext = computed(() => {
    if (props.context) {
        return {
            assetId: props.context.assetId,
            type: props.context.type,
            textSnippet: props.context.textSnippet || '',
            mode: 'document' // Implicit for report modal
        };
    }
    
    if (props.asset) {
        return {
            assetId: props.asset.id,
            type: props.asset.metadata?.audit_type || 'project',
            textSnippet: props.asset.metadata?.summary || '',
            mode: props.mode || 'global'
        };
    }

    return {
        assetId: null,
        type: 'General',
        textSnippet: '',
        mode: props.mode || 'global'
    };
});

const messages = ref<{ role: 'user' | 'assistant'; content: string; suggestions?: any[] }[]>([]);

const activeConversationId = ref<string | null>(null);
const conversations = ref<any[]>([]);
const showHistoryDropdown = ref(false);

// Initialize greeting based on mode
const initChat = async () => {
    // Generate new conversation ID if checking fresh
    if (!activeConversationId.value) {
        activeConversationId.value = crypto.randomUUID();
    }
    
    messages.value = [];
    const mode = activeContext.value.mode;
    
    let greeting = `I am Lume, your sovereign assistant.`;
    
    if (mode === 'document') {
        greeting = `I am Lume, your research scholar. I've analyzed this document. Ask me about methodology, citations, or risks.`;
    } else if (mode === 'pentest') {
        greeting = `I am the Lume PenTest Oracle. I can help you craft specific audit instructions for this asset.`;
    } else if (mode === 'analytic') {
        greeting = `I am the Lume Data Analyst. I'm ready to dive deep into the forensic data of this project.`;
    }

    messages.value.push({ role: 'assistant', content: greeting });
    
    // Also fetch previous conversations list
    await fetchConversations();
};

// Re-init on open (if modal)
watch(() => props.show, (val) => {
    if (val) {
        activeConversationId.value = null; // Reset ensures new chat on re-open unless we want persistence
        initChat();
    }
});

// Init on mount if embedded
onMounted(() => {
    if (!props.show) initChat();
});

const userInput = ref('');
const isLoading = ref(false);
const chatContainer = ref<HTMLElement | null>(null);

const scrollToBottom = async () => {
    await nextTick();
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
};

const sendMessage = async () => {
    if (!userInput.value.trim() || isLoading.value) return;

    const content = userInput.value;
    messages.value.push({ role: 'user', content });
    userInput.value = '';
    isLoading.value = true;
    scrollToBottom();

    try {
        const response = await axios.post('/api/ai/chat', {
            message: content,
            mode: activeContext.value.mode,
            asset_id: activeContext.value.assetId,
            conversation_id: activeConversationId.value, // Send ID
            error_context: null
        });

        messages.value.push({
            role: 'assistant',
            content: response.data.reply,
            suggestions: response.data.suggestions
        });
        
        // Refresh conversations list after sending a message to see the new title/timestamp
        fetchConversations();
    } catch (error) {
        messages.value.push({
            role: 'assistant',
            content: "I'm having trouble connecting to the Lume Neural Network. Please try again."
        });
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
};

const fetchConversations = async () => {
    try {
        const response = await axios.get('/api/ai/conversations', {
            params: {
                asset_id: activeContext.value.assetId,
                mode: activeContext.value.mode
            }
        });
        conversations.value = response.data.conversations;
    } catch (error) {
        console.error("Failed to fetch conversations", error);
    }
};

const loadConversation = async (conversationId: string) => {
    activeConversationId.value = conversationId;
    showHistoryDropdown.value = false;
    await fetchHistory();
};

const startNewChat = () => {
    activeConversationId.value = crypto.randomUUID();
    showHistoryDropdown.value = false;
    initChat();
};

const fetchHistory = async () => {
    isLoading.value = true;
    messages.value = []; // Clear current
    try {
        const response = await axios.get('/api/ai/history', {
            params: {
                asset_id: activeContext.value.assetId,
                mode: activeContext.value.mode,
                conversation_id: activeConversationId.value // Specific conversation
            }
        });
        
        if (response.data.messages && response.data.messages.length > 0) {
            messages.value = response.data.messages;
            scrollToBottom();
        } else {
             // If strictly loading history and it's empty, maybe just show greeting?
             // Or if it's a new chat.
             initChat(); 
        }
    } catch (error) {
        console.error("Failed to load history", error);
    } finally {
        isLoading.value = false;
    }
};

const parseMessageForPrompt = (content: string) => {
    // Regex to find content wrapped in ```prompt ... ```
    const promptRegex = /```prompt\s*([\s\S]*?)\s*```/;
    const match = content.match(promptRegex);

    if (match && match[1]) {
        return {
            text: content.replace(match[0], '').trim(), // Remove the prompt block from main text
            prompt: match[1].trim()
        };
    }
    
    return { text: content, prompt: null };
};

const copyToClipboard = (text: string | null) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
    // Optional: Add a toast notification here if desired
};


const close = () => {
    emit('close');
}
</script>

<template>
    <!-- WRAPPER: Can be Modal or Div depending on props.show -->
    <component 
        :is="show !== undefined ? Modal : 'div'" 
        v-bind="show !== undefined ? { show: show, maxWidth: '2xl' } : {}"
        @close="close"
        class="h-full"
    >
        <div class="flex flex-col h-[650px] w-full bg-[#0A0A0B] border border-white/10 rounded-[1.5rem] overflow-hidden shadow-2xl relative" :class="{ 'h-full border-0 bg-transparent shadow-none rounded-none': show === undefined }">
            
            <!-- Modal Header (Only if Modal) -->
            <div v-if="show !== undefined" class="flex justify-between items-center p-6 border-b border-white/5 bg-white/[0.02] relative z-20">
                <div class="flex items-center gap-3">
                     <div class="w-2 h-2 rounded-full bg-lume-primary shadow-[0_0_10px_rgba(16,185,129,0.5)] animate-pulse"></div>
                     <h3 class="text-lg font-black text-white tracking-[0.2em] uppercase italic leading-none">
                        LUME<span class="text-lume-primary">CORE</span>
                    </h3>
                </div>
                <div class="flex items-center gap-2">
                    <!-- History Dropdown Wrapper -->
                    <div class="relative">
                        <button 
                            @click="showHistoryDropdown = !showHistoryDropdown" 
                            class="p-1.5 rounded-full hover:bg-white/5 text-gray-500 hover:text-white transition-colors flex items-center gap-2" 
                            title="Conversation History"
                        >
                             <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div v-if="showHistoryDropdown" class="absolute right-0 top-full mt-2 w-64 bg-[#0A0A0B] border border-white/10 rounded-xl shadow-2xl overflow-hidden z-50">
                            <!-- New Chat Header -->
                            <div class="p-3 border-b border-white/5">
                                <button 
                                    @click="startNewChat"
                                    class="w-full flex items-center justify-center gap-2 py-2 rounded-lg bg-lume-primary/10 hover:bg-lume-primary/20 text-lume-primary text-xs font-bold uppercase tracking-wider transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    New Chat
                                </button>
                            </div>

                            <!-- Conversation List -->
                            <div class="max-h-60 overflow-y-auto custom-scrollbar">
                                <div v-if="conversations.length === 0" class="p-4 text-center text-xs text-gray-500 italic">
                                    No history yet.
                                </div>
                                <button 
                                    v-for="conv in conversations" 
                                    :key="conv.id"
                                    @click="loadConversation(conv.id)"
                                    class="w-full text-left p-3 hover:bg-white/5 transition-colors border-b border-white/[0.02] last:border-0 group"
                                    :class="{'bg-lume-primary/5': activeConversationId === conv.id}"
                                >
                                    <div class="text-[11px] font-medium text-gray-300 group-hover:text-white truncate mb-1">
                                        {{ conv.title }}
                                    </div>
                                    <div class="text-[9px] text-gray-600 group-hover:text-gray-500 uppercase tracking-widest">
                                        {{ conv.date }}
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Close Button -->
                    <button @click="close" class="p-1.5 rounded-full hover:bg-white/5 text-gray-500 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Chat Area -->
            <div ref="chatContainer" class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar bg-black/40">
                <!-- Welcome/Empty State -->
                <div v-if="messages.length === 0" class="flex flex-col items-center justify-center h-full text-center space-y-4 opacity-80 px-4">
                     <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-lume-primary/10 to-transparent border border-white/5 flex items-center justify-center mb-2">
                        <svg class="w-8 h-8 text-lume-primary/60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                     </div>
                     <h4 class="text-xl font-bold text-white tracking-tight">
                        Hello, {{ $page.props.auth.user.name }}.<br/>
                        What kind of prompts do you want me to make?
                    </h4>
                    <p class="text-xs text-gray-500 max-w-xs leading-relaxed">
                        I am designed to generate specialized penetration testing prompts to help you audit your infrastructure's security posture.
                    </p>

                    <!-- Prompt Recommendations -->
                    <div class="flex flex-wrap justify-center gap-2 mt-4 max-w-lg">
                        <button 
                            @click="userInput = 'Identify exposed sensitive data endpoints'; sendMessage()"
                            class="px-4 py-2 rounded-full border border-white/10 bg-white/5 hover:bg-white/10 hover:border-lume-primary/30 hover:text-lume-primary text-[11px] text-gray-400 transition-all cursor-pointer"
                        >
                            Identify exposed sensitive data endpoints
                        </button>
                        <button 
                            @click="userInput = 'Test for SQL injection vulnerabilities'; sendMessage()"
                            class="px-4 py-2 rounded-full border border-white/10 bg-white/5 hover:bg-white/10 hover:border-lume-primary/30 hover:text-lume-primary text-[11px] text-gray-400 transition-all cursor-pointer"
                        >
                            Test for SQL injection vulnerabilities
                        </button>
                        <button 
                            @click="userInput = 'Analyze authentication bypass vectors'; sendMessage()"
                            class="px-4 py-2 rounded-full border border-white/10 bg-white/5 hover:bg-white/10 hover:border-lume-primary/30 hover:text-lume-primary text-[11px] text-gray-400 transition-all cursor-pointer"
                        >
                            Analyze authentication bypass vectors
                        </button>
                        <button 
                            @click="userInput = 'Scan for misconfigured CORS policies'; sendMessage()"
                            class="px-4 py-2 rounded-full border border-white/10 bg-white/5 hover:bg-white/10 hover:border-lume-primary/30 hover:text-lume-primary text-[11px] text-gray-400 transition-all cursor-pointer"
                        >
                            Scan for misconfigured CORS policies
                        </button>
                    </div>
                </div>

                <div 
                    v-for="(msg, idx) in messages" 
                    :key="idx"
                    class="flex gap-4 fade-in"
                    :class="msg.role === 'user' ? 'flex-row-reverse' : ''"
                >
                    <!-- Avatar -->
                    <div 
                        class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 border shadow-lg"
                        :class="msg.role === 'user' ? 'bg-gradient-to-br from-gray-700 to-black border-white/10' : 'bg-gradient-to-br from-lume-primary/20 to-black border-lume-primary/30'"
                    >
                        <span v-if="msg.role === 'user'" class="text-[10px] font-black text-white">YOU</span>
                        <svg v-else class="w-4 h-4 text-lume-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>

                    <!-- Bubble -->
                    <div class="flex flex-col max-w-[85%] gap-2">
                         <div 
                            class="rounded-2xl px-5 py-3 text-xs leading-relaxed shadow-lg backdrop-blur-md border"
                            :class="msg.role === 'user' 
                                ? 'bg-white text-black border-white font-medium rounded-tr-none' 
                                : 'bg-white/[0.03] text-gray-300 border-white/5 rounded-tl-none'"
                        >
                            <!-- Standard Message Content -->
                            <p class="whitespace-pre-wrap font-sans mb-2">{{ parseMessageForPrompt(msg.content).text }}</p>

                            <!-- Extracted Prompt Block -->
                            <div v-if="parseMessageForPrompt(msg.content).prompt" class="mt-3 rounded-xl overflow-hidden border border-lume-primary/30 bg-[#050505]">
                                <div class="flex justify-between items-center px-3 py-2 bg-lume-primary/10 border-b border-lume-primary/10">
                                    <span class="text-[10px] font-bold text-lume-primary uppercase tracking-wider flex items-center gap-1.5">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        Executable Prompt
                                    </span>
                                    <button 
                                        @click="copyToClipboard(parseMessageForPrompt(msg.content).prompt)"
                                        class="hover:text-white text-lume-primary transition-colors p-1"
                                        title="Copy Prompt"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="p-3 font-mono text-[10px] text-lume-primary/90 whitespace-pre-wrap select-all">
                                    {{ parseMessageForPrompt(msg.content).prompt }}
                                </div>
                            </div>
                        </div>

                        <!-- Horizontal Recommendation Cards -->
                        <div v-if="msg.suggestions?.length" class="mt-1">
                             <p class="text-[9px] font-black text-lume-primary uppercase tracking-[0.2em] mb-2 pl-2 flex items-center gap-2">
                                <span class="w-3 h-[1px] bg-lume-primary"></span>
                                Strategic Intelligence
                            </p>
                            <div class="flex gap-3 overflow-x-auto pb-2 px-1 custom-scrollbar-x snap-x">
                                <a 
                                    v-for="(sug, sIdx) in msg.suggestions" 
                                    :key="sIdx"
                                    :href="sug.url" 
                                    target="_blank"
                                    class="flex-shrink-0 w-64 p-4 rounded-xl bg-[#0F0F11] border border-white/5 hover:border-lume-primary/50 transition-all duration-300 group hover:-translate-y-1 hover:shadow-lg snap-start relative overflow-hidden"
                                >
                                    <div class="absolute inset-0 bg-gradient-to-br from-lume-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                    <div class="relative z-10">
                                        <div class="font-bold text-white text-[10px] leading-tight mb-2 group-hover:text-lume-primary transition-colors line-clamp-2 min-h-[2.5em]">
                                            {{ sug.title }}
                                        </div>
                                        <div class="w-6 h-[1px] bg-white/10 group-hover:bg-lume-primary/50 mb-2 transition-colors"></div>
                                        <div class="text-[9px] text-gray-500 line-clamp-2 leading-relaxed">{{ sug.snippet }}</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Loading Indicator -->
                <div v-if="isLoading" class="flex gap-3 fade-in">
                    <div class="w-8 h-8 rounded-lg bg-lume-primary/10 border border-lume-primary/20 flex items-center justify-center flex-shrink-0 animate-pulse">
                        <svg class="w-4 h-4 text-lume-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div class="bg-transparent px-2 py-1 flex items-center gap-1 h-8">
                        <span class="w-1 h-1 rounded-full bg-lume-primary animate-bounce"></span>
                        <span class="w-1 h-1 rounded-full bg-lume-primary animate-bounce delay-100"></span>
                        <span class="w-1 h-1 rounded-full bg-lume-primary animate-bounce delay-200"></span>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="p-5 bg-black/60 border-t border-white/5 backdrop-blur-xl">
                <div class="relative group">
                    <div class="absolute -inset-[1px] bg-gradient-to-r from-lume-primary to-sovereign-primary rounded-xl opacity-20 group-hover:opacity-50 transition duration-500 blur-sm group-focus-within:opacity-80"></div>
                    <input 
                        v-model="userInput"
                        @keydown.enter="sendMessage"
                        type="text" 
                        placeholder="Initialize query sequence..." 
                        class="relative w-full bg-[#050505] text-white rounded-xl pl-5 pr-12 py-4 border-none placeholder-gray-600 text-xs font-medium focus:ring-0 shadow-xl transition-all"
                        :disabled="isLoading"
                        autofocus
                    />
                    <button 
                        @click="sendMessage"
                        :disabled="!userInput.trim() || isLoading"
                        class="absolute right-2 top-1/2 -translate-y-1/2 p-2 rounded-lg bg-white text-black hover:bg-lume-primary hover:text-white transition-all disabled:opacity-0 disabled:scale-95 z-10 shadow-md"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
                <div class="mt-3 flex justify-between items-center px-1">
                     <p class="text-[8px] text-gray-600 uppercase tracking-[0.2em] font-black">
                        Lume Neural Net v3.0 • {{ activeContext.mode.toUpperCase() }}
                    </p>
                </div>
            </div>
        </div>
    </component>
</template>
