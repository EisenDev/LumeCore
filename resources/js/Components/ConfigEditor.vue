<script setup lang="ts">
/**
 * ConfigEditor Component
 * IDE-like editor for sellers to confirm their Variable Map
 * Uses a Monaco-style editor with syntax highlighting
 */
import { ref, computed, watch, onMounted } from 'vue';

interface EnvVariable {
    name: string;
    value: string;
    required: boolean;
    category: string;
    group?: string;
    description?: string;
    service?: string;
    source?: string;
    confirmed: boolean;
}

interface TechStackItem {
    name: string;
    category: string;
    icon: string;
    package?: string;
    version?: string;
}

interface Props {
    techStack?: TechStackItem[];
    envVariables?: Partial<EnvVariable>[];
    dnsProvider?: { provider: string; icon: string; nameservers: string[] } | null;
    readOnly?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    techStack: () => [],
    envVariables: () => [],
    dnsProvider: null,
    readOnly: false,
});

const emit = defineEmits<{
    (e: 'update:variables', variables: EnvVariable[]): void;
    (e: 'confirm', variables: EnvVariable[]): void;
}>();

// State
const activeTab = ref<'variables' | 'tech' | 'raw'>('variables');
const variables = ref<EnvVariable[]>([]);
const rawEnvContent = ref('');
const searchQuery = ref('');
const activeCategory = ref<string | null>(null);
const showAddModal = ref(false);
const newVariable = ref({ name: '', value: '', category: 'General', description: '' });

// Initialize variables from props
watch(() => props.envVariables, (newVars) => {
    variables.value = (newVars || []).map(v => ({
        name: v.name || '',
        value: v.value || '',
        required: v.required ?? false,
        category: v.category || 'General',
        group: v.group,
        description: v.description,
        service: v.service,
        source: v.source,
        confirmed: false,
    }));
    updateRawContent();
}, { immediate: true });

// Computed
const categories = computed(() => {
    const cats = new Set(variables.value.map(v => v.category));
    return ['All', ...Array.from(cats).sort()];
});

const filteredVariables = computed(() => {
    let result = variables.value;
    
    if (activeCategory.value && activeCategory.value !== 'All') {
        result = result.filter(v => v.category === activeCategory.value);
    }
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(v => 
            v.name.toLowerCase().includes(query) ||
            v.description?.toLowerCase().includes(query) ||
            v.service?.toLowerCase().includes(query)
        );
    }
    
    return result;
});

const confirmedCount = computed(() => variables.value.filter(v => v.confirmed).length);
const requiredCount = computed(() => variables.value.filter(v => v.required).length);
const allRequiredConfirmed = computed(() => 
    variables.value.filter(v => v.required).every(v => v.confirmed)
);

// Methods
function updateRawContent(): void {
    const groups: Record<string, EnvVariable[]> = {};
    
    variables.value.forEach(v => {
        const group = v.group || v.category || 'General';
        if (!groups[group]) groups[group] = [];
        groups[group].push(v);
    });

    let content = '';
    for (const [group, vars] of Object.entries(groups)) {
        content += `# ${group}\n`;
        vars.forEach(v => {
            if (v.description) content += `# ${v.description}\n`;
            content += `${v.name}=${v.value}\n`;
        });
        content += '\n';
    }

    rawEnvContent.value = content.trim();
}

function parseRawContent(): void {
    const lines = rawEnvContent.value.split('\n');
    const newVars: EnvVariable[] = [];
    let currentGroup = 'General';

    lines.forEach(line => {
        line = line.trim();
        
        if (line.startsWith('#')) {
            const comment = line.substring(1).trim();
            if (comment && !comment.includes('=')) {
                currentGroup = comment;
            }
            return;
        }

        if (!line) return;

        const match = line.match(/^([A-Z_][A-Z0-9_]*)=(.*)$/);
        if (match) {
            const existing = variables.value.find(v => v.name === match[1]);
            newVars.push({
                name: match[1],
                value: match[2],
                required: existing?.required ?? false,
                category: existing?.category ?? 'General',
                group: currentGroup,
                description: existing?.description,
                service: existing?.service,
                source: existing?.source ?? 'Manual',
                confirmed: existing?.confirmed ?? false,
            });
        }
    });

    variables.value = newVars;
    emit('update:variables', newVars);
}

function updateVariable(index: number, field: keyof EnvVariable, value: any): void {
    const varIndex = variables.value.findIndex(v => v.name === filteredVariables.value[index].name);
    if (varIndex !== -1) {
        (variables.value[varIndex] as any)[field] = value;
        updateRawContent();
        emit('update:variables', variables.value);
    }
}

function toggleConfirmed(index: number): void {
    updateVariable(index, 'confirmed', !filteredVariables.value[index].confirmed);
}

function confirmAll(): void {
    variables.value.forEach(v => v.confirmed = true);
    emit('update:variables', variables.value);
}

function addVariable(): void {
    if (!newVariable.value.name) return;
    
    variables.value.push({
        name: newVariable.value.name.toUpperCase().replace(/[^A-Z0-9_]/g, '_'),
        value: newVariable.value.value,
        required: false,
        category: newVariable.value.category,
        description: newVariable.value.description,
        confirmed: true,
        source: 'Manual',
    });

    newVariable.value = { name: '', value: '', category: 'General', description: '' };
    showAddModal.value = false;
    updateRawContent();
    emit('update:variables', variables.value);
}

function removeVariable(name: string): void {
    variables.value = variables.value.filter(v => v.name !== name);
    updateRawContent();
    emit('update:variables', variables.value);
}

function confirmAndProceed(): void {
    emit('confirm', variables.value);
}

function getCategoryColor(category: string): string {
    const colors: Record<string, string> = {
        'Database': 'bg-blue-500/20 text-blue-400 ring-blue-500/30',
        'Payments': 'bg-green-500/20 text-green-400 ring-green-500/30',
        'Email': 'bg-purple-500/20 text-purple-400 ring-purple-500/30',
        'Cache': 'bg-red-500/20 text-red-400 ring-red-500/30',
        'Queue': 'bg-orange-500/20 text-orange-400 ring-orange-500/30',
        'AWS': 'bg-yellow-500/20 text-yellow-400 ring-yellow-500/30',
        'Real-time': 'bg-[#F3E7C9]/20 text-[#F3E7C9] ring-[#F3E7C9]/30',
        'Search': 'bg-pink-500/20 text-pink-400 ring-pink-500/30',
        'Error Tracking': 'bg-rose-500/20 text-rose-400 ring-rose-500/30',
        'Storage': 'bg-indigo-500/20 text-indigo-400 ring-indigo-500/30',
    };
    return colors[category] || 'bg-gray-500/20 text-gray-400 ring-gray-500/30';
}
</script>

<template>
    <div class="config-editor rounded-2xl border border-white/10 bg-gradient-to-br from-gray-900 to-gray-800 overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-white/10 bg-black/20 px-4 py-3">
            <div class="flex items-center gap-3">
                <!-- Window Controls (Mac-style) -->
                <div class="flex gap-1.5">
                    <div class="h-3 w-3 rounded-full bg-red-500" />
                    <div class="h-3 w-3 rounded-full bg-yellow-500" />
                    <div class="h-3 w-3 rounded-full bg-green-500" />
                </div>
                <span class="text-sm font-mono text-gray-400">.env</span>
            </div>
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span>{{ confirmedCount }}/{{ variables.length }} confirmed</span>
                <span v-if="requiredCount > 0" class="text-amber-400">{{ requiredCount }} required</span>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-white/10 bg-black/10">
            <button
                v-for="tab in [{ id: 'variables', label: 'Variables', icon: '📋' }, { id: 'tech', label: 'Tech Stack', icon: '🔧' }, { id: 'raw', label: 'Raw Editor', icon: '📝' }]"
                :key="tab.id"
                @click="activeTab = tab.id as any"
                :class="[
                    'flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors',
                    activeTab === tab.id
                        ? 'border-b-2 border-brand-primary text-brand-primary bg-brand-primary/5'
                        : 'text-gray-400 hover:text-gray-300'
                ]"
            >
                <span>{{ tab.icon }}</span>
                {{ tab.label }}
            </button>
        </div>

        <!-- Variables Tab -->
        <div v-if="activeTab === 'variables'" class="p-4">
            <!-- Search & Filter -->
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search variables..."
                        class="w-full rounded-lg border border-gray-600 bg-gray-800 py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:border-brand-primary focus:outline-none"
                    />
                </div>
                
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="cat in categories"
                        :key="cat"
                        @click="activeCategory = cat === 'All' ? null : cat"
                        :class="[
                            'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                            (activeCategory === cat || (cat === 'All' && !activeCategory))
                                ? 'bg-brand-primary text-white'
                                : 'bg-gray-700 text-gray-400 hover:bg-gray-600'
                        ]"
                    >
                        {{ cat }}
                    </button>
                </div>

                <button
                    v-if="!readOnly"
                    @click="showAddModal = true"
                    class="flex items-center gap-1 rounded-lg bg-brand-primary/20 px-3 py-1.5 text-sm font-medium text-brand-primary hover:bg-brand-primary/30"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add
                </button>
            </div>

            <!-- Variables List -->
            <div class="space-y-2 max-h-[400px] overflow-y-auto">
                <div
                    v-for="(variable, index) in filteredVariables"
                    :key="variable.name"
                    :class="[
                        'group rounded-lg border p-3 transition-all',
                        variable.confirmed
                            ? 'border-brand-secondary/30 bg-brand-secondary/5'
                            : variable.required
                            ? 'border-amber-500/30 bg-amber-500/5'
                            : 'border-gray-700 bg-gray-800/50 hover:border-gray-600'
                    ]"
                >
                    <div class="flex items-start gap-3">
                        <!-- Confirm Checkbox -->
                        <button
                            @click="toggleConfirmed(index)"
                            :disabled="readOnly"
                            :class="[
                                'mt-1 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded border transition-colors',
                                variable.confirmed
                                    ? 'border-brand-secondary bg-brand-secondary text-white'
                                    : 'border-gray-600 hover:border-brand-primary'
                            ]"
                        >
                            <svg v-if="variable.confirmed" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>

                        <!-- Variable Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <code class="font-mono text-sm font-semibold text-white">{{ variable.name }}</code>
                                <span v-if="variable.required" class="rounded bg-amber-500/20 px-1.5 py-0.5 text-xs font-medium text-amber-400">Required</span>
                                <span :class="['rounded-full px-2 py-0.5 text-xs font-medium ring-1', getCategoryColor(variable.category)]">
                                    {{ variable.category }}
                                </span>
                            </div>
                            
                            <div v-if="variable.description" class="mt-1 text-xs text-gray-500">
                                {{ variable.description }}
                            </div>

                            <div v-if="variable.service" class="mt-1 flex items-center gap-1 text-xs text-gray-400">
                                <span>Service:</span>
                                <span class="font-medium text-brand-primary">{{ variable.service }}</span>
                            </div>
                        </div>

                        <!-- Value Input -->
                        <input
                            v-if="!readOnly"
                            :value="variable.value"
                            @input="updateVariable(index, 'value', ($event.target as HTMLInputElement).value)"
                            type="text"
                            placeholder="Enter value..."
                            class="w-48 rounded border border-gray-600 bg-gray-700 px-3 py-1.5 font-mono text-xs text-gray-300 placeholder-gray-500 focus:border-brand-primary focus:outline-none"
                        />
                        <code v-else class="font-mono text-xs text-gray-400">{{ variable.value || '(empty)' }}</code>

                        <!-- Remove Button -->
                        <button
                            v-if="!readOnly"
                            @click="removeVariable(variable.name)"
                            class="opacity-0 group-hover:opacity-100 p-1 text-gray-500 hover:text-red-400 transition-all"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div v-if="filteredVariables.length === 0" class="py-8 text-center text-gray-500">
                    No variables found
                </div>
            </div>
        </div>

        <!-- Tech Stack Tab -->
        <div v-else-if="activeTab === 'tech'" class="p-4">
            <!-- DNS Provider -->
            <div v-if="dnsProvider" class="mb-6 rounded-xl border border-white/10 bg-gray-800/50 p-4">
                <h4 class="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-400">DNS Provider</h4>
                <div class="flex items-center gap-3">
                    <span class="text-2xl">{{ dnsProvider.icon }}</span>
                    <div>
                        <p class="font-semibold text-white">{{ dnsProvider.provider }}</p>
                        <p class="text-xs text-gray-500">{{ dnsProvider.nameservers?.join(', ') }}</p>
                    </div>
                </div>
            </div>

            <!-- Tech Stack Grid -->
            <h4 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-400">Detected Technologies</h4>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="tech in techStack"
                    :key="tech.name"
                    class="flex items-center gap-3 rounded-lg border border-gray-700 bg-gray-800/50 p-3"
                >
                    <span class="text-xl">{{ tech.icon }}</span>
                    <div>
                        <p class="font-medium text-white">{{ tech.name }}</p>
                        <p class="text-xs text-gray-500">{{ tech.category }}</p>
                        <p v-if="tech.version" class="text-xs text-brand-primary">{{ tech.version }}</p>
                    </div>
                </div>
            </div>

            <div v-if="!techStack.length" class="py-8 text-center text-gray-500">
                No technologies detected. Run a scan first.
            </div>
        </div>

        <!-- Raw Editor Tab -->
        <div v-else-if="activeTab === 'raw'" class="p-4">
            <div class="relative">
                <div class="absolute left-0 top-0 bottom-0 w-10 bg-gray-900 border-r border-gray-700 flex flex-col items-end pt-3 pr-2 text-xs text-gray-600 font-mono select-none overflow-hidden">
                    <div v-for="n in Math.max(rawEnvContent.split('\n').length, 20)" :key="n" class="leading-6">{{ n }}</div>
                </div>
                <textarea
                    v-model="rawEnvContent"
                    @blur="parseRawContent"
                    :readonly="readOnly"
                    class="w-full h-96 pl-12 pr-4 py-3 bg-gray-900 border border-gray-700 rounded-lg font-mono text-sm text-green-400 leading-6 resize-none focus:outline-none focus:border-brand-primary"
                    spellcheck="false"
                />
            </div>
            <p class="mt-2 text-xs text-gray-500">
                Edit the raw .env content directly. Changes will be parsed when you click outside.
            </p>
        </div>

        <!-- Footer Actions -->
        <div v-if="!readOnly" class="flex items-center justify-between border-t border-white/10 bg-black/20 px-4 py-3">
            <button
                @click="confirmAll"
                class="text-sm font-medium text-gray-400 hover:text-white transition-colors"
            >
                Confirm All
            </button>
            <button
                @click="confirmAndProceed"
                :disabled="!allRequiredConfirmed"
                :class="[
                    'rounded-xl px-6 py-2 font-semibold text-white shadow-lg transition-all',
                    allRequiredConfirmed
                        ? 'bg-gradient-to-r from-brand-primary to-brand-secondary hover:brightness-110'
                        : 'bg-gray-700 cursor-not-allowed opacity-50'
                ]"
            >
                Save Variable Map
            </button>
        </div>

        <!-- Add Variable Modal -->
        <Teleport to="body">
            <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
                <div class="w-full max-w-md rounded-2xl border border-white/10 bg-gradient-to-br from-gray-800 to-gray-900 p-6 shadow-2xl">
                    <h3 class="mb-4 text-lg font-bold text-white">Add Variable</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Variable Name</label>
                            <input
                                v-model="newVariable.name"
                                type="text"
                                placeholder="MY_VARIABLE"
                                class="w-full rounded-lg border border-gray-600 bg-gray-700 px-4 py-2 font-mono text-white placeholder-gray-500 focus:border-brand-primary focus:outline-none"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Value</label>
                            <input
                                v-model="newVariable.value"
                                type="text"
                                placeholder="value"
                                class="w-full rounded-lg border border-gray-600 bg-gray-700 px-4 py-2 font-mono text-white placeholder-gray-500 focus:border-brand-primary focus:outline-none"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Category</label>
                            <select
                                v-model="newVariable.category"
                                class="w-full rounded-lg border border-gray-600 bg-gray-700 px-4 py-2 text-white focus:border-brand-primary focus:outline-none"
                            >
                                <option>General</option>
                                <option>Database</option>
                                <option>Payments</option>
                                <option>Email</option>
                                <option>AWS</option>
                                <option>Cache</option>
                                <option>Queue</option>
                                <option>Storage</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Description (optional)</label>
                            <input
                                v-model="newVariable.description"
                                type="text"
                                placeholder="What this variable configures"
                                class="w-full rounded-lg border border-gray-600 bg-gray-700 px-4 py-2 text-white placeholder-gray-500 focus:border-brand-primary focus:outline-none"
                            />
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button
                            @click="showAddModal = false"
                            class="flex-1 rounded-xl border border-gray-600 py-2 font-medium text-gray-400 hover:border-gray-500 hover:text-white transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            @click="addVariable"
                            :disabled="!newVariable.name"
                            class="flex-1 rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary py-2 font-semibold text-white hover:brightness-110 disabled:opacity-50"
                        >
                            Add Variable
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.config-editor ::-webkit-scrollbar {
    width: 8px;
}

.config-editor ::-webkit-scrollbar-track {
    background: #1f2937;
}

.config-editor ::-webkit-scrollbar-thumb {
    background: #374151;
    border-radius: 4px;
}

.config-editor ::-webkit-scrollbar-thumb:hover {
    background: #4b5563;
}
</style>
