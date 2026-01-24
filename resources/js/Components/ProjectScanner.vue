<script setup lang="ts">
/**
 * ProjectScanner Component
 * For scanning website URLs and GitHub repositories for M&A platform
 */
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

interface HealthCheckResult {
    website?: {
        alive: boolean;
        status_code: number | null;
        response_time_ms: number | null;
        ssl_valid: boolean | null;
        error: string | null;
    };
    domain?: {
        registered: boolean | null;
        expiration_date: string | null;
        registrar: string | null;
        days_until_expiry: number | null;
        error: string | null;
    };
    github?: {
        exists: boolean;
        private: boolean | null;
        owner: string | null;
        repo: string | null;
        stars: number | null;
        last_push: string | null;
        error: string | null;
    };
}

interface CredentialValidation {
    success: boolean;
    github: {
        valid: boolean;
        username: string | null;
        scopes: string[];
        has_repo_access: boolean;
        error: string | null;
        error_type: string | null;
    };
    cloudflare?: {
        valid: boolean;
        type: string | null;
        account_name: string | null;
        zones: { id: string; name: string; status: string }[];
        error: string | null;
        error_type: string | null;
    } | null;
    errors: Record<string, string>;
}

interface ProjectAsset {
    id: string;
    name: string;
    website_url: string | null;
    github_repo_url: string | null;
    verification_uuid: string;
    website_verified: boolean;
    github_verified: boolean;
    status: string;
    lume_score: number | null;
}

// Props
interface Props {
    credits?: number;
}

const props = withDefaults(defineProps<Props>(), {
    credits: 0,
});

// State
const activeStep = ref<'input' | 'health' | 'verify' | 'credentials' | 'complete'>('input');

// Form state
const projectName = ref('');
const websiteUrl = ref('');
const githubRepoUrl = ref('');
const monthlyRevenue = ref<number | null>(null);
const monthlyVisitors = ref<number | null>(null);

// Process state
const isLoading = ref(false);
const createdProject = ref<ProjectAsset | null>(null);
const verificationMetaTag = ref('');

// Health check state
const healthCheckResults = ref<HealthCheckResult | null>(null);
const isRunningHealthCheck = ref(false);

// Verification state
const isVerifying = ref(false);
const verificationResult = ref<{ verified: boolean; error: string | null } | null>(null);

// Credentials state
const showCredentialsModal = ref(false);
const githubToken = ref('');
const cloudflareKey = ref('');
const cloudflareEmail = ref('');
const isValidatingCredentials = ref(false);
const credentialValidation = ref<CredentialValidation | null>(null);

// Processing steps for UI
const processingSteps = ref<{ label: string; status: 'pending' | 'running' | 'success' | 'error' }[]>([]);

// Computed
const hasCredits = computed(() => props.credits > 0);
const canProceed = computed(() => projectName.value.trim() && (websiteUrl.value.trim() || githubRepoUrl.value.trim()));

/**
 * Create project and start scanning
 */
async function createProject(): Promise<void> {
    if (!canProceed.value) return;
    
    isLoading.value = true;
    processingSteps.value = [
        { label: 'Creating project...', status: 'running' },
    ];

    try {
        const { data } = await axios.post('/projects', {
            name: projectName.value,
            website_url: websiteUrl.value || null,
            github_repo_url: githubRepoUrl.value || null,
            monthly_revenue: monthlyRevenue.value,
            monthly_visitors: monthlyVisitors.value,
        });

        createdProject.value = data.project;
        verificationMetaTag.value = data.verification_meta_tag;
        
        processingSteps.value[0].status = 'success';
        activeStep.value = 'health';
        
        // Auto-run health check
        await runHealthCheck();
    } catch (error: any) {
        processingSteps.value[0].status = 'error';
        console.error('Failed to create project:', error);
        alert(error.response?.data?.message || 'Failed to create project');
    } finally {
        isLoading.value = false;
    }
}

/**
 * Run health check on website and GitHub repo
 */
async function runHealthCheck(): Promise<void> {
    if (!createdProject.value) return;
    
    isRunningHealthCheck.value = true;
    processingSteps.value = [
        { label: 'Checking domain health...', status: 'running' },
        { label: 'Verifying SSL certificate...', status: 'pending' },
        { label: 'Checking GitHub repository...', status: 'pending' },
    ];

    try {
        // Simulate step progress
        await new Promise(r => setTimeout(r, 800));
        processingSteps.value[0].status = 'success';
        processingSteps.value[1].status = 'running';
        
        await new Promise(r => setTimeout(r, 600));
        processingSteps.value[1].status = 'success';
        processingSteps.value[2].status = 'running';

        const { data } = await axios.post(`/projects/${createdProject.value.id}/health-check`);
        
        healthCheckResults.value = data.results;
        processingSteps.value[2].status = 'success';
        
        // Move to verification step
        activeStep.value = 'verify';
    } catch (error: any) {
        processingSteps.value[processingSteps.value.findIndex(s => s.status === 'running')].status = 'error';
        console.error('Health check failed:', error);
    } finally {
        isRunningHealthCheck.value = false;
    }
}

/**
 * Verify site ownership via meta tag
 */
async function verifySiteOwnership(): Promise<void> {
    if (!createdProject.value) return;
    
    isVerifying.value = true;
    
    try {
        const { data } = await axios.post(`/projects/${createdProject.value.id}/verify-ownership`);
        
        verificationResult.value = {
            verified: data.verified,
            error: data.error,
        };
        
        if (data.verified) {
            createdProject.value.website_verified = true;
            // Move to credentials step
            activeStep.value = 'credentials';
            showCredentialsModal.value = true;
        }
    } catch (error: any) {
        verificationResult.value = {
            verified: false,
            error: error.response?.data?.message || 'Verification failed',
        };
    } finally {
        isVerifying.value = false;
    }
}

/**
 * Validate credentials with dry-run
 */
async function validateCredentials(): Promise<void> {
    if (!createdProject.value || !githubToken.value) return;
    
    isValidatingCredentials.value = true;
    credentialValidation.value = null;

    try {
        const { data } = await axios.post(`/projects/${createdProject.value.id}/validate-credentials`, {
            github_token: githubToken.value,
            cloudflare_key: cloudflareKey.value || null,
            cloudflare_email: cloudflareEmail.value || null,
        });

        credentialValidation.value = data.validation || data;
        
        if (data.success) {
            createdProject.value.github_verified = true;
            showCredentialsModal.value = false;
            activeStep.value = 'complete';
        }
    } catch (error: any) {
        credentialValidation.value = error.response?.data?.validation || {
            success: false,
            github: { valid: false, error: 'Validation request failed' },
            errors: { github: error.response?.data?.message || 'Unknown error' },
        };
    } finally {
        isValidatingCredentials.value = false;
    }
}

/**
 * Skip website verification (GitHub only project)
 */
function skipWebsiteVerification(): void {
    if (!websiteUrl.value) {
        activeStep.value = 'credentials';
        showCredentialsModal.value = true;
    }
}

/**
 * Reset form
 */
function resetForm(): void {
    activeStep.value = 'input';
    projectName.value = '';
    websiteUrl.value = '';
    githubRepoUrl.value = '';
    monthlyRevenue.value = null;
    monthlyVisitors.value = null;
    createdProject.value = null;
    healthCheckResults.value = null;
    verificationResult.value = null;
    credentialValidation.value = null;
    githubToken.value = '';
    cloudflareKey.value = '';
    cloudflareEmail.value = '';
}
</script>

<template>
    <div class="project-scanner">
        <!-- Step Indicator -->
        <div class="mb-6 flex items-center justify-between">
            <div v-for="(step, index) in ['Input', 'Health Check', 'Verify', 'Credentials', 'Complete']" :key="step" class="flex items-center">
                <div
                    :class="[
                        'flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold',
                        activeStep === ['input', 'health', 'verify', 'credentials', 'complete'][index]
                            ? 'bg-brand-primary text-white'
                            : index < ['input', 'health', 'verify', 'credentials', 'complete'].indexOf(activeStep)
                            ? 'bg-brand-secondary text-white'
                            : 'bg-gray-700 text-gray-400'
                    ]"
                >
                    {{ index + 1 }}
                </div>
                <span v-if="index < 4" class="mx-2 h-px w-8 bg-gray-600" />
            </div>
        </div>

        <!-- Step 1: Input Form -->
        <div v-if="activeStep === 'input'" class="space-y-6">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-300">Project Name *</label>
                <input
                    v-model="projectName"
                    type="text"
                    placeholder="My SaaS Project"
                    class="w-full rounded-xl border border-gray-600 bg-gray-800 px-4 py-3 text-white placeholder-gray-500 focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"
                />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-300">Website URL</label>
                    <input
                        v-model="websiteUrl"
                        type="url"
                        placeholder="https://example.com"
                        class="w-full rounded-xl border border-gray-600 bg-gray-800 px-4 py-3 text-white placeholder-gray-500 focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"
                    />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-300">GitHub Repo URL</label>
                    <input
                        v-model="githubRepoUrl"
                        type="url"
                        placeholder="https://github.com/user/repo"
                        class="w-full rounded-xl border border-gray-600 bg-gray-800 px-4 py-3 text-white placeholder-gray-500 focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"
                    />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-300">Monthly Revenue ($)</label>
                    <input
                        v-model.number="monthlyRevenue"
                        type="number"
                        min="0"
                        placeholder="0"
                        class="w-full rounded-xl border border-gray-600 bg-gray-800 px-4 py-3 text-white placeholder-gray-500 focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"
                    />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-300">Monthly Visitors</label>
                    <input
                        v-model.number="monthlyVisitors"
                        type="number"
                        min="0"
                        placeholder="0"
                        class="w-full rounded-xl border border-gray-600 bg-gray-800 px-4 py-3 text-white placeholder-gray-500 focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"
                    />
                </div>
            </div>

            <button
                type="button"
                :disabled="!canProceed || isLoading"
                @click="createProject"
                class="w-full rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-6 py-4 font-semibold text-white shadow-lg transition-all hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span v-if="isLoading" class="flex items-center justify-center gap-2">
                    <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                    Processing...
                </span>
                <span v-else>Start Scanning</span>
            </button>
        </div>

        <!-- Step 2: Health Check Results -->
        <div v-else-if="activeStep === 'health'" class="space-y-6">
            <!-- Processing Steps -->
            <div class="rounded-xl border border-white/10 bg-gray-800/50 p-6">
                <h3 class="mb-4 text-lg font-semibold text-white">Running Health Checks...</h3>
                <div class="space-y-3">
                    <div v-for="step in processingSteps" :key="step.label" class="flex items-center gap-3">
                        <div v-if="step.status === 'running'" class="h-5 w-5 animate-spin rounded-full border-2 border-brand-primary border-t-transparent" />
                        <svg v-else-if="step.status === 'success'" class="h-5 w-5 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <svg v-else-if="step.status === 'error'" class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        <div v-else class="h-5 w-5 rounded-full border-2 border-gray-600" />
                        <span :class="step.status === 'pending' ? 'text-gray-500' : 'text-gray-300'">{{ step.label }}</span>
                    </div>
                </div>
            </div>

            <!-- Results -->
            <div v-if="healthCheckResults" class="space-y-4">
                <!-- Website Health -->
                <div v-if="healthCheckResults.website" class="rounded-xl border border-white/10 bg-gray-800/50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-300">Website Status</span>
                        <span :class="healthCheckResults.website.alive ? 'text-brand-secondary' : 'text-red-400'">
                            {{ healthCheckResults.website.alive ? '✓ Online' : '✗ Offline' }}
                        </span>
                    </div>
                    <div v-if="healthCheckResults.website.response_time_ms" class="mt-2 text-sm text-gray-500">
                        Response time: {{ healthCheckResults.website.response_time_ms }}ms
                    </div>
                </div>

                <!-- Domain Info -->
                <div v-if="healthCheckResults.domain" class="rounded-xl border border-white/10 bg-gray-800/50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-300">Domain Status</span>
                        <span :class="healthCheckResults.domain.days_until_expiry && healthCheckResults.domain.days_until_expiry > 30 ? 'text-brand-secondary' : 'text-amber-400'">
                            {{ healthCheckResults.domain.days_until_expiry ? `Expires in ${healthCheckResults.domain.days_until_expiry} days` : 'Unknown' }}
                        </span>
                    </div>
                </div>

                <!-- GitHub Info -->
                <div v-if="healthCheckResults.github" class="rounded-xl border border-white/10 bg-gray-800/50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-300">GitHub Repository</span>
                        <span :class="healthCheckResults.github.exists ? 'text-brand-secondary' : 'text-red-400'">
                            {{ healthCheckResults.github.exists ? '✓ Found' : '✗ Not Found' }}
                        </span>
                    </div>
                    <div v-if="healthCheckResults.github.stars !== null" class="mt-2 text-sm text-gray-500">
                        ⭐ {{ healthCheckResults.github.stars }} stars
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: Verification -->
        <div v-else-if="activeStep === 'verify'" class="space-y-6">
            <div class="rounded-xl border border-brand-primary/30 bg-brand-primary/10 p-6">
                <h3 class="mb-4 text-lg font-semibold text-white">Verify Site Ownership</h3>
                <p class="mb-4 text-sm text-gray-400">
                    Add this meta tag to your website's &lt;head&gt; section:
                </p>
                <code class="block rounded-lg bg-gray-900 p-4 text-sm text-brand-primary break-all">
                    {{ verificationMetaTag }}
                </code>
                <button
                    type="button"
                    :disabled="isVerifying"
                    @click="verifySiteOwnership"
                    class="mt-4 w-full rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-6 py-3 font-semibold text-white shadow-lg transition-all hover:brightness-110 disabled:opacity-50"
                >
                    <span v-if="isVerifying">Verifying...</span>
                    <span v-else>Verify Now</span>
                </button>

                <!-- Skip for GitHub-only -->
                <button
                    v-if="!websiteUrl"
                    type="button"
                    @click="skipWebsiteVerification"
                    class="mt-2 w-full rounded-xl border border-gray-600 px-6 py-3 font-medium text-gray-400 transition-colors hover:border-gray-500 hover:text-gray-300"
                >
                    Skip (GitHub Only)
                </button>

                <!-- Verification Result -->
                <div v-if="verificationResult" class="mt-4">
                    <div v-if="verificationResult.verified" class="flex items-center gap-2 text-brand-secondary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Site verified successfully!
                    </div>
                    <div v-else class="text-red-400">
                        {{ verificationResult.error || 'Verification tag not found. Please add it and try again.' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: Complete -->
        <div v-else-if="activeStep === 'complete'" class="text-center py-8">
            <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-brand-secondary/20">
                <svg class="h-10 w-10 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h3 class="mb-2 text-2xl font-bold text-white">Project Verified!</h3>
            <p class="mb-6 text-gray-400">Your project is ready for AI auditing and marketplace listing.</p>
            <button
                type="button"
                @click="resetForm"
                class="rounded-xl border border-gray-600 px-6 py-3 font-medium text-gray-400 transition-colors hover:border-gray-500 hover:text-gray-300"
            >
                Add Another Project
            </button>
        </div>

        <!-- Credentials Modal -->
        <Teleport to="body">
            <div v-if="showCredentialsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
                <div class="w-full max-w-lg rounded-2xl border border-white/10 bg-gradient-to-br from-gray-800 to-gray-900 p-6 shadow-2xl">
                    <h3 class="mb-4 text-xl font-bold text-white">Connect Your Credentials</h3>
                    <p class="mb-6 text-sm text-gray-400">
                        These credentials are required for ownership verification and asset transfer. They are encrypted and never shared.
                    </p>

                    <!-- GitHub Token -->
                    <div class="mb-4">
                        <label class="mb-2 block text-sm font-medium text-gray-300">GitHub Personal Access Token *</label>
                        <input
                            v-model="githubToken"
                            type="password"
                            placeholder="ghp_xxxxxxxxxxxxxx"
                            :class="[
                                'w-full rounded-xl border bg-gray-800 px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-1',
                                credentialValidation?.errors?.github
                                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                                    : 'border-gray-600 focus:border-brand-primary focus:ring-brand-primary'
                            ]"
                        />
                        <div v-if="credentialValidation?.errors?.github" class="mt-2 text-sm text-red-400">
                            {{ credentialValidation.errors.github }}
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Token needs "repo" scope. <a href="https://github.com/settings/tokens" target="_blank" class="text-brand-primary hover:underline">Generate here</a>
                        </p>
                    </div>

                    <!-- Cloudflare Key (Optional) -->
                    <div class="mb-4">
                        <label class="mb-2 block text-sm font-medium text-gray-300">Cloudflare API Key (Optional)</label>
                        <input
                            v-model="cloudflareKey"
                            type="password"
                            placeholder="Your API key or token"
                            :class="[
                                'w-full rounded-xl border bg-gray-800 px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-1',
                                credentialValidation?.errors?.cloudflare
                                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                                    : 'border-gray-600 focus:border-brand-primary focus:ring-brand-primary'
                            ]"
                        />
                        <div v-if="credentialValidation?.errors?.cloudflare" class="mt-2 text-sm text-red-400">
                            {{ credentialValidation.errors.cloudflare }}
                        </div>
                    </div>

                    <!-- Cloudflare Email -->
                    <div v-if="cloudflareKey" class="mb-6">
                        <label class="mb-2 block text-sm font-medium text-gray-300">Cloudflare Email (for Global API Key)</label>
                        <input
                            v-model="cloudflareEmail"
                            type="email"
                            placeholder="your@email.com"
                            class="w-full rounded-xl border border-gray-600 bg-gray-800 px-4 py-3 text-white placeholder-gray-500 focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"
                        />
                    </div>

                    <!-- Success indicator -->
                    <div v-if="credentialValidation?.success" class="mb-4 flex items-center gap-2 rounded-lg bg-brand-secondary/20 p-3 text-brand-secondary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Credentials validated successfully!
                    </div>

                    <!-- GitHub User Info -->
                    <div v-if="credentialValidation?.github?.username && credentialValidation.github.valid" class="mb-4 rounded-lg bg-gray-700/50 p-3">
                        <div class="flex items-center gap-2 text-sm text-gray-300">
                            <svg class="h-4 w-4 text-brand-primary" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd" />
                            </svg>
                            Connected as <strong>@{{ credentialValidation.github.username }}</strong>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3">
                        <button
                            type="button"
                            @click="showCredentialsModal = false"
                            class="flex-1 rounded-xl border border-gray-600 px-4 py-3 font-medium text-gray-400 transition-colors hover:border-gray-500 hover:text-gray-300"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            :disabled="!githubToken || isValidatingCredentials"
                            @click="validateCredentials"
                            class="flex-1 rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-4 py-3 font-semibold text-white shadow-lg transition-all hover:brightness-110 disabled:opacity-50"
                        >
                            <span v-if="isValidatingCredentials">Validating...</span>
                            <span v-else>Validate & Save</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
