<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';
import axios from 'axios';

const props = defineProps<{
    show: boolean;
    initialError?: string;
    initialInput?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

interface Message {
    id: number;
    role: 'user' | 'agent';
    content: string;
    suggestions?: string[];
}

const messages = ref<Message[]>([]);
const input = ref('');
const isLoading = ref(false);
const chatContainer = ref<HTMLElement | null>(null);
const inputField = ref<HTMLInputElement | null>(null);

const scrollToBottom = async () => {
    await nextTick();
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
};

const sendMessage = async (text?: string) => {
    const prompt = text || input.value;
    if (!prompt.trim()) return;

    // Add User Message
    messages.value.push({
        id: Date.now(),
        role: 'user',
        content: prompt
    });

    if (!text) input.value = ''; // Clear input if manual send
    isLoading.value = true;
    scrollToBottom();

    try {
        const response = await axios.post('/api/support/github-guide', { 
            prompt,
            errorMessage: props.initialError,
            currentInput: props.initialInput
        });
        
        messages.value.push({
            id: Date.now() + 1,
            role: 'agent',
            content: response.data.answer,
            suggestions: response.data.suggestions || []
        });
    } catch (error: any) {
        const errorMsg = error.response?.data?.error || ':: SYSTEM ERROR :: Connection refused. Please try again.';
        messages.value.push({
            id: Date.now() + 1,
            role: 'agent',
            content: errorMsg
        });
    } finally {
        isLoading.value = false;
        scrollToBottom();
        // Focus input after response
        if (inputField.value && !text) {
             nextTick(() => inputField.value?.focus());
        }
    }
};

// Auto-trigger on open
watch(() => props.show, (newVal) => {
    if (newVal) {
        nextTick(() => {
            scrollToBottom();
            if (messages.value.length === 0) {
                if (props.initialError) {
                    sendMessage("I'm having trouble with my project URL.");
                } else {
                    sendMessage("How do I generate a GitHub Personal Access Token for a private repository?");
                }
            }
        });
    }
});
</script>

<template>
    <Teleport to="body">
        <transition
            enter-active-class="ease-out duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm" @click.self="$emit('close')">
                <transition
                    enter-active-class="ease-out duration-300"
                    enter-from-class="opacity-0 scale-95 translate-y-4"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="ease-in duration-200"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-4"
                >
                    <div class="relative flex h-[600px] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
                        
                        <!-- Terminal Header -->
                        <div class="flex items-center justify-between border-b border-slate-800 bg-slate-900 p-4">
                            <div class="flex items-center gap-3">
                                <div class="relative flex h-3 w-3">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"></span>
                                </div>
                                <div>
                                    <h3 class="font-mono text-sm font-bold text-slate-200">LUME_COMMAND_CENTER</h3>
                                    <p class="text-[10px] text-slate-500 uppercase tracking-wider">v3.0.0 // ONLINE</p>
                                </div>
                            </div>
                            <button @click="$emit('close')" class="text-slate-500 transition-colors hover:text-slate-300">
                                <span class="sr-only">Close</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Chat Area -->
                        <div 
                            ref="chatContainer"
                            class="flex-1 space-y-6 overflow-y-auto bg-slate-950 p-6 font-mono text-sm leading-relaxed scrollbar-thin scrollbar-track-slate-900 scrollbar-thumb-slate-700"
                        >
                            <div v-for="msg in messages" :key="msg.id" :class="['flex', msg.role === 'user' ? 'justify-end' : 'justify-start']">
                                <div 
                                    :class="[
                                        'max-w-[85%] rounded-lg p-4 shadow-sm',
                                        msg.role === 'user' 
                                            ? 'bg-blue-600/20 text-blue-200 border border-blue-500/30' 
                                            : 'bg-slate-800/50 text-slate-300 border border-slate-700'
                                    ]"
                                >
                                    <div class="mb-2 flex items-center gap-2 text-[10px] uppercase tracking-wider opacity-60">
                                        <span v-if="msg.role === 'agent'" class="text-emerald-400">LUME_BOT</span>
                                        <span v-else class="text-blue-400">USR_01</span>
                                    </div>
                                    <div class="whitespace-pre-wrap">{{ msg.content }}</div>

                                    <!-- Suggestions -->
                                    <div v-if="msg.suggestions && msg.suggestions.length > 0" class="mt-4 flex flex-wrap gap-2">
                                        <button 
                                            v-for="(suggestion, idx) in msg.suggestions" 
                                            :key="idx"
                                            @click="sendMessage(suggestion)"
                                            :disabled="isLoading"
                                            class="rounded border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs text-emerald-400 transition-all hover:bg-emerald-500/20 hover:text-emerald-300 disabled:opacity-50"
                                        >
                                            > {{ suggestion }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Loading Indicator -->
                            <div v-if="isLoading" class="flex justify-start">
                                <div class="flex items-center gap-1 rounded-lg border border-slate-700 bg-slate-800/50 px-4 py-3">
                                    <span class="h-2 w-2 animate-bounce rounded-full bg-emerald-500" style="animation-delay: 0ms" />
                                    <span class="h-2 w-2 animate-bounce rounded-full bg-emerald-500" style="animation-delay: 150ms" />
                                    <span class="h-2 w-2 animate-bounce rounded-full bg-emerald-500" style="animation-delay: 300ms" />
                                </div>
                            </div>
                        </div>

                        <!-- Input Area -->
                        <div class="border-t border-slate-800 bg-slate-900 p-4">
                            <form @submit.prevent="sendMessage()" class="relative flex items-center gap-3">
                                <span class="font-mono text-emerald-500 animate-pulse">></span>
                                <input 
                                    ref="inputField"
                                    v-model="input"
                                    type="text" 
                                    placeholder="Enter command or question..."
                                    class="w-full bg-transparent font-mono text-sm text-white placeholder-slate-600 focus:outline-none"
                                    :disabled="isLoading"
                                />
                                <button 
                                    type="submit"
                                    :disabled="!input.trim() || isLoading"
                                    class="rounded bg-slate-800 px-3 py-1 font-mono text-xs font-bold text-slate-400 transition-colors hover:bg-slate-700 hover:text-white disabled:opacity-50"
                                >
                                    SEND
                                </button>
                            </form>
                        </div>
                    </div>
                </transition>
            </div>
        </transition>
    </Teleport>
</template>
