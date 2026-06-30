<script setup>
import { ref, watch, nextTick, onMounted } from 'vue';
import Modal from '@/Components/Modal.vue';
import axios from 'axios';
import { marked } from 'marked';

const props = defineProps({
    show: Boolean,
    asset: Object, // The VaultAsset
    forensicData: Object // The detailed report context
});

const emit = defineEmits(['close']);

const messages = ref([]);
const userInput = ref('');
const isLoading = ref(false);
const chatContainer = ref(null);

// Scroll to bottom helper
const scrollToBottom = () => {
    nextTick(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    });
};

// Load History
const loadHistory = async () => {
    if (!props.asset?.id) return;
    
    try {
        // Use the same endpoint as GlobalHelper
        const response = await axios.get('/ai/chat/history', {
            params: { asset_id: props.asset.id }
        });
        
        if (response.data.messages) {
            messages.value = response.data.messages.map(msg => ({
                role: msg.role,
                content: msg.content,
                timestamp: 'Earlier'
            }));
            scrollToBottom();
        }
    } catch (e) {
        console.error("Failed to load chat history", e);
    }
};

watch(() => props.show, (newVal) => {
    if (newVal) {
        loadHistory();
    }
});

// Send Message
const sendMessage = async () => {
    if (!userInput.value.trim() || isLoading.value) return;

    const userMsg = userInput.value;
    userInput.value = '';
    
    // Optimistic UI
    messages.value.push({ role: 'user', content: userMsg, timestamp: 'Just now' });
    isLoading.value = true;
    scrollToBottom();

    try {
        // Use /ai/chat route with correct parameters (message, asset_id)
        const response = await axios.post('/ai/chat', {
            message: userMsg,
            asset_id: props.asset.id
        });

        const reply = response.data.reply;
        if (reply) {
            messages.value.push({ role: 'ai', content: reply, timestamp: 'Just now' });
        } else {
            messages.value.push({ role: 'ai', content: "I received an empty response. Please try again.", timestamp: 'System' });
        }
    } catch (error) {
        console.error("ProjectAnalyst chat error:", error);
        
        // Surface actual error message from server
        let errorMessage = "Connection lost. Please try again.";
        if (error.response) {
            // Server responded with an error
            if (error.response.status === 422) {
                errorMessage = "Invalid request. Please check your input.";
            } else if (error.response.status === 500) {
                errorMessage = "Server error. The AI service may be temporarily unavailable.";
            } else if (error.response.data?.message) {
                errorMessage = error.response.data.message;
            }
            console.error("Server response:", error.response.data);
        } else if (error.request) {
            // No response received
            errorMessage = "No response from server. Please check your connection.";
        }
        
        messages.value.push({ role: 'ai', content: errorMessage, timestamp: 'System' });
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
};

// Markdown Renderer
const renderMarkdown = (text) => {
    return marked(text || '');
};

// Send Suggestion - Pre-fills and sends a message from suggestion buttons
const sendSuggestion = (suggestionText) => {
    if (isLoading.value) return;
    userInput.value = suggestionText;
    sendMessage();
};
</script>

<template>
    <Modal :show="show" maxWidth="2xl" @close="emit('close')">
        <div class="bg-gray-900 text-white h-[80vh] flex flex-col rounded-xl overflow-hidden border border-gray-700 shadow-2xl">
            
            <!-- Header -->
            <div class="p-4 border-b border-gray-800 bg-gray-950 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-900 flex items-center justify-center border border-blue-500 shadow-[0_0_15px_rgba(59,130,246,0.5)]">
                        <svg class="w-6 h-6 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    </div>
                    <div>
                        <h2 class="font-bold text-lg text-blue-100 tracking-wide">LUME Architect</h2>
                        <p class="text-xs text-blue-400 font-mono" v-if="asset">ANALYST_UPLINK: {{ asset.file_name }}</p>
                    </div>
                </div>
                <button @click="emit('close')" class="text-gray-400 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Chat Area -->
            <div class="flex-1 overflow-y-auto p-4 space-y-6 bg-gray-900/50" ref="chatContainer">
                
                <!-- Empty State with Welcome & Suggestions -->
                <div v-if="messages.length === 0 && !isLoading" class="text-center py-8">
                    <!-- LUME AI Icon -->
                    <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gradient-to-br from-indigo-500/20 to-blue-600/20 flex items-center justify-center border border-indigo-500/30 shadow-[0_0_30px_rgba(99,102,241,0.2)]">
                        <svg class="w-10 h-10 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
                    </div>
                    
                    <!-- Welcome Text -->
                    <h3 class="text-lg font-semibold text-white mb-2">Hi! I'm LUME AI</h3>
                    <p class="text-sm text-slate-400 mb-6 max-w-md mx-auto">
                        I'm your dedicated analyst for <span class="text-indigo-400 font-medium">{{ asset?.file_name || 'this project' }}</span>. 
                        I can only answer questions about this scanned asset. How may I assist you?
                    </p>
                    
                    <!-- Smart Suggestions Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-w-lg mx-auto">
                        <button 
                            @click="sendSuggestion(`Why is my score ${asset?.score || 0}? What factors contributed to this rating?`)"
                            class="p-3 text-left text-xs bg-slate-800/50 hover:bg-slate-700/50 border border-slate-700 hover:border-indigo-500/50 rounded-lg transition-all group"
                        >
                            <span class="text-slate-300 group-hover:text-white">🎯 Why is my score {{ asset?.score || 0 }}?</span>
                        </button>
                        
                        <button 
                            @click="sendSuggestion('What specific actions can I take to improve my score?')"
                            class="p-3 text-left text-xs bg-slate-800/50 hover:bg-slate-700/50 border border-slate-700 hover:border-indigo-500/50 rounded-lg transition-all group"
                        >
                            <span class="text-slate-300 group-hover:text-white">📈 How can I improve my score?</span>
                        </button>
                        
                        <button 
                            @click="sendSuggestion('What do I need to do to make my website/repository eligible for the LUME Marketplace?')"
                            class="p-3 text-left text-xs bg-slate-800/50 hover:bg-slate-700/50 border border-slate-700 hover:border-indigo-500/50 rounded-lg transition-all group"
                        >
                            <span class="text-slate-300 group-hover:text-white">🏪 How to get Marketplace eligible?</span>
                        </button>
                        
                        <button 
                            @click="sendSuggestion('Based on my current tech stack, what modern alternatives would you recommend for better performance and maintainability?')"
                            class="p-3 text-left text-xs bg-slate-800/50 hover:bg-slate-700/50 border border-slate-700 hover:border-indigo-500/50 rounded-lg transition-all group"
                        >
                            <span class="text-slate-300 group-hover:text-white">⚡ Recommend modern tech stack</span>
                        </button>
                    </div>
                    
                    <!-- Context Notice -->
                    <p class="text-[10px] text-slate-600 mt-4 font-mono">CONTEXT_LOCKED: {{ asset?.file_name || 'ASSET' }} only</p>
                </div>

                <!-- Messages -->
                <div v-for="(msg, index) in messages" :key="index" 
                     class="flex gap-4" 
                     :class="msg.role === 'user' ? 'flex-row-reverse' : ''">
                    
                    <!-- Avatar -->
                    <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold"
                         :class="msg.role === 'user' ? 'bg-indigo-600 text-white' : 'bg-blue-900 text-blue-200 border border-blue-500/30'">
                        {{ msg.role === 'user' ? 'YOU' : 'AI' }}
                    </div>

                    <!-- Bubble -->
                    <div class="max-w-[80%] rounded-2xl p-4 shadow-sm text-sm leading-relaxed"
                         :class="msg.role === 'user' ? 'bg-indigo-600 text-white rounded-tr-none' : 'bg-gray-800 text-gray-100 rounded-tl-none border border-gray-700'">
                        <div v-if="msg.role === 'ai'" v-html="renderMarkdown(msg.content)" class="prose prose-invert prose-sm max-w-none"></div>
                        <div v-else>{{ msg.content }}</div>
                        <div class="text-[10px] opacity-40 mt-2 text-right font-mono">{{ msg.timestamp }}</div>
                    </div>
                </div>

                <!-- Thinking Indicator -->
                <div v-if="isLoading" class="flex gap-4">
                     <div class="w-8 h-8 rounded-full bg-blue-900 flex items-center justify-center border border-blue-500/30">
                        <span class="animate-pulse w-2 h-2 bg-blue-400 rounded-full"></span>
                    </div>
                    <div class="bg-gray-800 rounded-2xl rounded-tl-none p-4 border border-gray-700 flex items-center gap-2">
                        <span class="w-2 h-2 bg-gray-500 rounded-full animate-bounce"></span>
                        <span class="w-2 h-2 bg-gray-500 rounded-full animate-bounce delay-100"></span>
                        <span class="w-2 h-2 bg-gray-500 rounded-full animate-bounce delay-200"></span>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="p-4 bg-gray-950 border-t border-gray-800">
                <div class="relative flex items-center gap-2">
                    <input 
                        v-model="userInput"
                        @keydown.enter="sendMessage"
                        type="text" 
                        placeholder="Ask specifically about vulnerabilities, tech stack, or remediation..." 
                        class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all font-light"
                        :disabled="isLoading"
                    >
                    <button 
                        @click="sendMessage"
                        :disabled="isLoading || !userInput.trim()"
                        class="absolute right-2 p-2 bg-blue-600 hover:bg-blue-500 text-white rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    </button>
                </div>
                <div class="mt-2 text-center">
                    <p class="text-[10px] text-gray-600 font-mono">LUME SECURE CHANNEL // ENCRYPTED</p>
                </div>
            </div>

        </div>
    </Modal>
</template>

<style scoped>
/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 6px;
}
::-webkit-scrollbar-track {
    background: #111827; 
}
::-webkit-scrollbar-thumb {
    background: #374151; 
    border-radius: 3px;
}
::-webkit-scrollbar-thumb:hover {
    background: #4B5563; 
}
</style>
