<script setup lang="ts">
import { ref, nextTick, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { marked } from 'marked';

// State
const isOpen = ref(false);
const input = ref('');
const isLoading = ref(false);
const messages = ref<{ role: 'user' | 'ai'; content: string }[]>([]);
const chatContainer = ref<HTMLElement | null>(null);
const currentAssetId = ref<string | null>(null);

// Get User Name
const page = usePage();
const userName = page.props.auth?.user?.name || 'Traveler';

/**
 * Render markdown content to HTML safely
 */
const renderMarkdown = (text: string) => {
    return marked(text);
};

// Initial Greeting
const initialGreeting = `Hello ${userName}, I am the LUME Architect. How can I help you scale today?`;

// Suggested prompts
const chips = [
    'How do I buy credits?',
    'What is a LUME Scalability Score?',
    'How do I sell my website?',
];

// Initialize chat with greeting
onMounted(() => {
    messages.value.push({
        role: 'ai',
        content: initialGreeting,
    });

    // Listen for Global AI Assistant Events (e.g. from Audit Modal)
    window.addEventListener('open-ai-assistant', (async (event: Event) => {
        const customEvent = event as CustomEvent<{ asset: any }>;
        const asset = customEvent.detail?.asset;
        if (!asset) return;

        // 1. Open Chat
        isOpen.value = true;
        
        // 2. Set Active Asset ID for Context
        currentAssetId.value = asset.id;

        // 3. Load chat history from database
        await loadChatHistory(asset.id);

        // 4. If no history, add context message
        if (messages.value.length <= 1) {
            const contextMsg = `I am now analyzing the specific forensic data for **${asset.file_name}**.\n\nAsk me about its security score, tech stack, or potential vulnerabilities.`;
            
            messages.value.push({
                role: 'ai',
                content: contextMsg
            });
        }

        // 5. Scroll to bottom
        nextTick(() => {
            if (chatContainer.value) {
                chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
            }
        });
    }) as EventListener);
});

/**
 * Load chat history from project_chats table.
 * Fetches last 10 messages for the given asset.
 */
const loadChatHistory = async (assetId: string) => {
    try {
        const response = await axios.get('/ai/chat/history', {
            params: { asset_id: assetId }
        });

        const history = response.data.messages || [];
        
        // Reset messages and add greeting + history
        messages.value = [{
            role: 'ai',
            content: initialGreeting,
        }];
        
        // Add history messages
        history.forEach((msg: { role: string; content: string }) => {
            messages.value.push({
                role: msg.role as any,
                content: msg.content
            });
        });
    } catch (error) {
        console.error('Failed to load chat history:', error);
    }
};

// Watch messages to auto-scroll
watch(
    () => messages.value.length,
    async () => {
        await nextTick();
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    }
);

// Actions
const toggleChat = () => {
    isOpen.value = !isOpen.value;
    // Clear context if closed? Optional. Keeping it allows follow-up.
};

const sendMessage = async (text?: string) => {
    const messageText = text || input.value.trim();
    if (!messageText) return;

    if (!text) input.value = ''; // Clear input if typed manually

    // Add User Message
    messages.value.push({ role: 'user', content: messageText });
    isLoading.value = true;

    try {
        const response = await axios.post('/ai/chat', {
            message: messageText,
            asset_id: currentAssetId.value // Pass the context ID
        });

        const reply = response.data.reply;

        // Add AI Message
        messages.value.push({ role: 'ai', content: reply });
    } catch (error) {
        console.error('AI Chat Error:', error);
        messages.value.push({
            role: 'ai',
            content: "I apologize, but I'm having trouble connecting to the LUME matrix right now.",
        });
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <div class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-4 font-mono">
        <!-- Chat Window -->
        <transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-4 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-4 scale-95"
        >
            <div
                v-if="isOpen"
                class="flex h-[500px] w-[380px] flex-col overflow-hidden rounded-lume border border-brand-primary/30 bg-black/90 shadow-2xl backdrop-blur-md"
            >
                <!-- Terminal Header -->
                <div class="flex items-center justify-between border-b border-white/10 bg-brand-dark px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="h-2.5 w-2.5 animate-pulse rounded-full bg-green-500"></div>
                        <span class="text-sm font-bold text-gray-200">LUME Command Center</span>
                    </div>
                    <button
                        @click="toggleChat"
                        class="text-gray-400 hover:text-white transition-colors"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Chat Body -->
                <div ref="chatContainer" class="flex-1 overflow-y-auto p-4 space-y-4">
                    <div
                        v-for="(msg, index) in messages"
                        :key="index"
                        class="group flex w-full flex-col gap-1"
                        :class="msg.role === 'user' ? 'items-end' : 'items-start'"
                    >
                        <span class="text-[10px] uppercase tracking-wider text-gray-500">
                            {{ msg.role === 'user' ? 'YOU' : 'ARCHITECT' }}
                        </span>
                        <div
                            class="max-w-[85%] rounded-lg px-3 py-2 text-sm leading-relaxed"
                            :class="[
                                msg.role === 'user'
                                    ? 'bg-brand-primary/20 text-brand-primary border border-brand-primary/30 rounded-tr-none'
                                    : 'bg-white/5 text-gray-300 border border-white/10 rounded-tl-none prose prose-invert prose-sm max-w-none'
                            ]"
                        >
                            <div v-if="msg.role === 'ai'" v-html="renderMarkdown(msg.content)"></div>
                            <span v-else>{{ msg.content }}</span>
                        </div>
                    </div>

                    <!-- Typing Indicator -->
                    <div v-if="isLoading" class="flex items-start gap-1">
                        <span class="text-[10px] uppercase tracking-wider text-gray-500">ARCHITECT</span>
                        <div class="flex h-8 items-center gap-1 rounded-lg rounded-tl-none border border-white/10 bg-white/5 px-3">
                            <div class="h-1.5 w-1.5 animate-bounce rounded-full bg-brand-primary"></div>
                            <div class="h-1.5 w-1.5 animate-bounce rounded-full bg-brand-primary delay-75"></div>
                            <div class="h-1.5 w-1.5 animate-bounce rounded-full bg-brand-primary delay-150"></div>
                        </div>
                    </div>
                </div>

                <!-- Chips (Only show if we have few messages or just started) -->
                <div v-if="messages.length < 3 && !isLoading" class="border-t border-white/10 px-4 py-2">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="chip in chips"
                            :key="chip"
                            @click="sendMessage(chip)"
                            class="rounded-full border border-brand-primary/30 bg-brand-primary/10 px-3 py-1 text-xs text-brand-primary transition-colors hover:bg-brand-primary/20"
                        >
                            {{ chip }}
                        </button>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="border-t border-white/10 bg-black/40 p-3">
                    <form @submit.prevent="sendMessage()" class="relative flex items-center">
                        <input
                            v-model="input"
                            type="text"
                            placeholder="Ask the LUME Architect..."
                            class="w-full rounded-lg bg-white/5 border border-white/10 px-4 py-2 pr-10 text-sm text-white placeholder-gray-500 focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"
                            :disabled="isLoading"
                        />
                        <button
                            type="submit"
                            :disabled="!input.trim() || isLoading"
                            class="absolute right-2 text-brand-primary transition-opacity hover:opacity-80 disabled:opacity-50"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </transition>

        <!-- Floating Action Button (FAB) -->
        <button
            @click="toggleChat"
            class="group relative flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-brand-primary to-brand-secondary text-white shadow-lg shadow-brand-primary/25 transition-all hover:scale-110 hover:shadow-brand-primary/50"
        >
            <!-- Badge Notification (Optional) -->
            <span class="absolute -top-1 -right-1 flex h-4 w-4">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex h-4 w-4 rounded-full bg-red-500"></span>
            </span>
            
            <svg v-if="!isOpen" class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            <svg v-else class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
    </div>
</template>

<style scoped>
/* Custom scrollbar for terminal feel */
::-webkit-scrollbar {
    width: 6px;
}
::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}
::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}
::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}
</style>
