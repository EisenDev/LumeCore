<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import type { VaultAsset } from '@/types/vault';

const props = defineProps<{
    show: boolean;
    asset: VaultAsset | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submitted'): void;
}>();

const form = useForm({
    github_repo_url: '',
    github_token: '',
});

const isSubmitting = ref(false);

function submit() {
    if (!props.asset?.metadata?.project_asset_id) {
        console.error("No project asset ID found");
        return;
    }

    isSubmitting.value = true;
    
    // We update the project asset with the new repo URL and trigger a scan
    form.post(route('project.sync-repo', { project: props.asset.metadata.project_asset_id }), {
        preserveScroll: true,
        onSuccess: () => {
            emit('submitted');
            emit('close');
            form.reset();
        },
        onError: () => {
            // Error handling usually handled by Inertia form helper
        },
        onFinish: () => {
            isSubmitting.value = false;
        }
    });
}
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
            <div
                v-if="show"
                class="fixed inset-0 z-[60] overflow-y-auto"
                @click.self="$emit('close')"
            >
                <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" @click="$emit('close')" />

                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-gray-900 border border-white/10 shadow-2xl transition-all">
                        <!-- Header -->
                        <div class="border-b border-white/10 bg-black/20 px-6 py-4">
                            <h3 class="text-lg font-semibold text-white">
                                Sync Repository Code
                            </h3>
                            <p class="mt-1 text-sm text-gray-400">
                                Verify ownership and analyze your source code.
                            </p>
                        </div>

                        <!-- Body -->
                        <div class="p-6 space-y-4">
                            <!-- Warning -->
                            <div class="rounded-lg bg-orange-500/10 border border-orange-500/20 p-4">
                                <div class="flex gap-3">
                                    <svg class="h-5 w-5 flex-shrink-0 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <p class="text-sm text-orange-200">
                                        <span class="font-bold">Note:</span> Your LUME Score and verified status will be recalculated based on the source code. This process cannot be undone.
                                    </p>
                                </div>
                            </div>

                            <!-- Production URL (Read-only) -->
                            <div>
                                <label class="block text-xs font-medium uppercase tracking-wider text-gray-500">Production URL</label>
                                <div class="mt-1 flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2 text-gray-300">
                                    <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="truncate">{{ props.asset?.metadata?.website_url || 'N/A' }}</span>
                                </div>
                            </div>

                            <!-- GitHub URL -->
                            <div>
                                <label class="block text-xs font-medium uppercase tracking-wider text-gray-500">GitHub Repository URL</label>
                                <input
                                    v-model="form.github_repo_url"
                                    type="url"
                                    required
                                    placeholder="https://github.com/username/repo"
                                    class="mt-1 w-full rounded-lg bg-black/40 border border-white/10 px-3 py-2 text-white focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                                />
                                <p v-if="form.errors.github_repo_url" class="mt-1 text-xs text-red-400">{{ form.errors.github_repo_url }}</p>
                            </div>

                            <!-- Access Token -->
                            <div>
                                <label class="block text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Personal Access Token <span class="text-gray-600">(Optional - for Private Repos)</span>
                                </label>
                                <input
                                    v-model="form.github_token"
                                    type="password"
                                    placeholder="ghp_xxxxxxxxxxxx"
                                    class="mt-1 w-full rounded-lg bg-black/40 border border-white/10 px-3 py-2 text-white focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                                />
                                <p class="mt-1 text-xs text-gray-500">We do not store this token permanently. It is used once for the handshake.</p>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex justify-end gap-3 border-t border-white/10 bg-black/20 px-6 py-4">
                            <button
                                type="button"
                                class="rounded-lg px-4 py-2 text-sm font-medium text-gray-400 hover:text-white"
                                @click="$emit('close')"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2 text-sm font-bold text-white shadow-lg shadow-brand-primary/20 hover:bg-brand-primary/90 disabled:opacity-50"
                                :disabled="isSubmitting || !form.github_repo_url"
                                @click="submit"
                            >
                                <svg v-if="isSubmitting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ isSubmitting ? 'Starting Scan...' : 'Sync & Audit' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
