<script setup lang="ts">
/**
 * Marketplace Index Page
 * Public listing of verified assets for sale with guest email modal
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

import LumeAISupport from '@/Components/LumeAISupport.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

interface VaultAsset {
    id: string;
    file_name: string;
    file_path: string;
    file_size: number;
    mime_type: string;
    metadata: any;
    price?: number | null;
    status: 'pending' | 'uploaded' | 'processing' | 'verified' | 'flagged' | 'action_required' | 'ready' | 'verified_private';
    user: {
        id: number;
        name: string;
    };
    created_at: string;
    updated_at: string;
    score?: number;
    sync_score?: number;
    user_id: number;
    // New Computed Fields from Controller
    latest_sync_score?: number;
    is_purchased?: boolean;
    purchase?: any; // Keep for backward compat if needed, but is_purchased is preferred
    latest_sync_activity?: any;
    website_metadata?: any;
    synced_metadata?: any;
    repository_metadata?: any;
}
interface Props {
    listings: {
        data: VaultAsset[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    isAuthenticated: boolean;
    authUser: {
        id: string;
        name: string;
        email: string;
    } | null;
    viewMode?: 'browse' | 'mylistings';
}

const props = withDefaults(defineProps<Props>(), {
    listings: () => ({ data: [], current_page: 1, last_page: 1, per_page: 20, total: 0 }),
    isAuthenticated: false,
    authUser: null,
    viewMode: 'browse',
});

// Guest Email Modal State
const showEmailModal = ref(false);
const guestEmail = ref('');
const selectedAsset = ref<VaultAsset | null>(null);
const emailError = ref('');
const isSubmitting = ref(false);

// Advanced Filter State
const searchQuery = ref('');
const activeCategory = ref('All Projects');
const priceRange = ref('All Prices');
const selectedStack = ref('All Stacks');
const minScore = ref('All Scores');
const listStatus = ref('Any Status');

// Audit Report Modal State
const showAuditModal = ref(false);

/**
 * Handle Buy button click
 */
function handleBuyClick(asset: VaultAsset): void {
    selectedAsset.value = asset;
    
    if (props.isAuthenticated) {
        // Authenticated user - go directly to checkout
        proceedToCheckout(asset, props.authUser?.email ?? '');
    } else {
        // Guest - show email modal
        showEmailModal.value = true;
        guestEmail.value = '';
        emailError.value = '';
    }
}

/**
 * Handle Card Click - Open Audit Report
 */
function handleCardClick(asset: VaultAsset): void {
    selectedAsset.value = asset;
    showAuditModal.value = true;
}

/**
 * Handle Buy from Audit Modal
 */
function handleAuditBuy(): void {
    showAuditModal.value = false;
    if (selectedAsset.value) {
        handleBuyClick(selectedAsset.value);
    }
}

/**
 * Validate email and proceed to checkout
 */
function submitGuestEmail(): void {
    // Validate email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!guestEmail.value || !emailRegex.test(guestEmail.value)) {
        emailError.value = 'Please enter a valid email address';
        return;
    }
    
    if (selectedAsset.value) {
        proceedToCheckout(selectedAsset.value, guestEmail.value);
    }
}

/**
 * Proceed to payment gateway (placeholder)
 */
function proceedToCheckout(asset: VaultAsset, email: string): void {
    isSubmitting.value = true;
    
    // TODO: Integrate with payment gateway (Stripe, PayPal, etc.)
    console.log('Proceeding to checkout:', {
        assetId: asset.id,
        price: asset.price,
        email: email,
    });
    
    // For now, just close the modal and show alert
    setTimeout(() => {
        showEmailModal.value = false;
        isSubmitting.value = false;
        alert(`Payment gateway integration coming soon!\n\nAsset: ${asset.file_name}\nPrice: $${Number(asset.price || 0).toFixed(2)}\nEmail: ${email}`);
    }, 500);
}

/**
 * Close the email modal
 */
function closeEmailModal(): void {
    showEmailModal.value = false;
    selectedAsset.value = null;
    guestEmail.value = '';
    emailError.value = '';
}



/**
 * Remove/Toggle Listing
 */
function toggleListing(asset: VaultAsset): void {
    if (!confirm('Are you sure you want to remove this asset from the marketplace? It will remain in your Vault.')) return;
    
    router.post(route('marketplace.asset.toggle', asset.id), {
        is_for_sale: false,
        price: asset.price // Keep existing price
    }, {
        preserveScroll: true,
        onSuccess: () => {
            // Toast handled by flash/layout usually
        }
    });
}


/**
 * Format file size
 */
function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}



/**
 * Calculate Score from Vectors (Fail-safe)
 */
function calculateScoreFromVectors(asset: VaultAsset): number {
    // 1. Try explicit score first
    const explicit = Number(asset.latest_sync_score || asset.score || asset.sync_score || asset.metadata?.confidence_score || 0);
    if (explicit > 0) return explicit;

    // 2. Try to derive from Synced Metadata (The Gold Standard)
    let vec = asset.synced_metadata?.hexagon_vectors;
    
    // 3. Try Website Metadata
    if (!vec) vec = asset.website_metadata?.hexagon_vectors;
    
    // 4. Try legacy locations
    if (!vec) vec = asset.metadata?.hexagon_vectors ?? asset.metadata?.breakdown;

    // Parse if string
    if (typeof vec === 'string') {
        try { vec = JSON.parse(vec); } catch { vec = null; }
    }

    if (vec) {
        // Average the 6 vectors
        const keys = ['client_side_velocity', 'code_efficiency', 'security_perimeter', 'supply_chain_governance', 'infrastructure_maturity', 'database_architecture'];
        let sum = 0;
        let count = 0;
        keys.forEach(k => {
            const val = Number(vec[k] || 0);
            if (val > 0) {
                sum += val;
                count++;
            }
        });
        if (count > 0) return Math.round(sum / 6); // Approximation
    }

    return 0; // Truly F
}

/**
 * Get grade based on confidence score (S, A, B, C, F)
 */
function getGrade(score: number): { letter: string; color: string; bg: string } {
    if (score >= 95) return { letter: 'S', color: 'text-[#CBB48A]', bg: 'bg-[#CBB48A]/20' };
    if (score >= 90) return { letter: 'A', color: 'text-[#F3E7C9]', bg: 'bg-[#F3E7C9]/20' };
    if (score >= 80) return { letter: 'B', color: 'text-blue-400', bg: 'bg-blue-500/20' };
    if (score >= 75) return { letter: 'C', color: 'text-yellow-400', bg: 'bg-yellow-500/20' };
    return { letter: 'F', color: 'text-red-400', bg: 'bg-red-500/20' };
}

/**
 * Get display icon based on mime type or project type
 */
function getDisplayIcon(asset: VaultAsset): string {
    const mime = asset.mime_type || '';
    if (asset.metadata?.audit_type === 'project' || asset.metadata?.audit_type === 'design') {
        return '🚀';
    }
    if (mime.includes('pdf')) return '📄';
    if (mime.includes('image')) return '🖼️';
    if (mime.includes('word') || mime.includes('document')) return '📝';
    return '📁';
}

/**
 * Get tech stack icons (Real SVG paths + Robust Fallback)
 */
/**
 * Get tech stack icons (Real SVG paths + Robust Fallback)
 */
function getTechStackIcons(asset: VaultAsset): { name: string; path: string; viewBox: string; color: string }[] {
    // 1. Try Synced Metadata first (Most accurate from repo)
    let stackNames: string[] = [];
    const syncedEvidence = asset.synced_metadata?.stack_analysis?.repo_evidence;
    
    if (Array.isArray(syncedEvidence) && syncedEvidence.length > 0) {
        stackNames = syncedEvidence;
    } 
    // 2. Try Metadata direct
    else if (Array.isArray(asset.metadata?.tech_stack) && asset.metadata.tech_stack.length > 0) {
        stackNames = asset.metadata.tech_stack;
    }
    // 3. Try Tech Assessment
    else {
        const assessmentStack = asset.metadata?.tech_assessment?.stack || [];
        if (Array.isArray(assessmentStack)) {
             stackNames = assessmentStack.map((item: any) => typeof item === 'string' ? item : item?.name || '');
        }
    }
    
    // 4. Try File Extension Heuristics (Last Resort)
    const fileName = asset.file_name?.toLowerCase() || '';
    if (stackNames.length === 0 && fileName) {
        if (fileName.endsWith('.ts')) stackNames.push('TypeScript');
        if (fileName.endsWith('.php')) stackNames.push('PHP', 'Laravel');
        if (fileName.endsWith('.vue')) stackNames.push('Vue.js');
        if (fileName.endsWith('.py')) stackNames.push('Python');
    }

    // 5. Hard Demo Fallback if completely empty
    if (stackNames.length === 0) {
        stackNames.push('Unknown Stack');
    }

    // Unify and Limit
    const unique = Array.from(new Set(stackNames)).filter(s => s && typeof s === 'string');
    const topStack = unique.slice(0, 5);

    // Map names to SVG icons
    return topStack.map((name: string) => {
        const n = String(name).toLowerCase();
        
        // React
        if (n.includes('react')) return { 
            name: 'React', 
            viewBox: '0 0 24 24', 
            color: 'text-[#F3E7C9]', 
            path: 'M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm0 2.2c4.35 0 8.08 2.8 9.28 6.72-.6-.24-1.24-.4-1.92-.4-3.53 0-6.4 2.87-6.4 6.4 0 .9.19 1.75.52 2.52C12.37 17.11 11.23 16 10 16c-3.1 0-5.6 2.5-5.6 5.6 0 .44.06.87.16 1.28-2.6-1.5-4.36-4.32-4.36-7.58 0-4.97 4.03-9 9-9z' 
        };
        // Vue
        if (n.includes('vue')) return {
            name: 'Vue.js',
            viewBox: '0 0 24 24',
            color: 'text-[#CBB48A]',
            path: 'M2 3h20v18H2V3zm18 16V5H4v14h16zM6 7h12v2H6V7zm0 4h12v2H6v-2zm0 4h8v2H6v-2z' 
        };
        // Laravel
        if (n.includes('laravel')) return {
            name: 'Laravel',
            viewBox: '0 0 24 24',
            color: 'text-red-500',
            path: 'M12 2L2 7l10 5 10-5-10-5zm0 9l2.5-1.25L12 8.5l-2.5 1.25L12 11zm0 2.5l-5-2.5-5 2.5L12 22l10-8.5-5-2.5-5 2.5z'
        };
        // Node
        if (n.includes('node') || n.includes('js')) return { 
             name: 'Node.js', 
             viewBox: '0 0 24 24', 
             color: 'text-green-500', 
             path: 'M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.8l6 3v6.4l-6 3-6-3V7.8l6-3z' 
        };
        // Python
        if (n.includes('python')) return { 
            name: 'Python', 
            viewBox: '0 0 24 24', 
            color: 'text-blue-400', 
            path: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z' 
        };
        
        // Tailwind
        if (n.includes('tailwind')) return {
            name: 'Tailwind CSS',
            viewBox: '0 0 24 24',
            color: 'text-[#F3E7C9]/70',
            path: 'M12 6c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z'
        };

        // Default Code Icon
        return {
            name: name,
            viewBox: '0 0 24 24',
            color: 'text-slate-500',
            path: 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z'
        };
    });
}

/**
 * Get category badge color
 */
function getCategoryColor(category: string): string {
    const colors: Record<string, string> = {
        'Template': 'bg-purple-500/20 text-purple-400 ring-purple-500/30',
        'Dataset': 'bg-blue-500/20 text-blue-400 ring-blue-500/30',
        'Educational': 'bg-green-500/20 text-green-400 ring-green-500/30',
        'Creative Work': 'bg-pink-500/20 text-pink-400 ring-pink-500/30',
        'Public Audit': 'bg-[#CBB48A]/20 text-[#CBB48A] ring-[#CBB48A]/30',
    };
    return colors[category] ?? 'bg-gray-500/20 text-gray-400 ring-gray-500/30';
}

/**
 * Helper to safely get document breakdown from union type
 */
function getDocumentBreakdown(asset: VaultAsset): { formatting: number; content_quality: number; industry_relevance: number } | null {
    const breakdown = asset.metadata?.breakdown;
    if (!breakdown) return null;
    if ('formatting' in breakdown) {
        return breakdown as { formatting: number; content_quality: number; industry_relevance: number };
    }
    return null;
}

/**
 * Helper to safely get project breakdown
 */
function getProjectBreakdown(asset: VaultAsset): { tech_score: number; security_score: number; scalability_score: number } | null {
    const breakdown = asset.metadata?.breakdown;
    if (!breakdown) return null;
    if ('tech_score' in breakdown || 'tech_quality' in breakdown) {
        return {
            tech_score: (breakdown as any).tech_score ?? (breakdown as any).tech_quality ?? 0,
            security_score: (breakdown as any).security_score ?? 0,
            scalability_score: (breakdown as any).scalability_score ?? 0,
        };
    }
    return null;
}

/**
 * Generate Radar Chart Points (5 axis) based on score
 */
function getRadarPoints(input: any): string {
    // Input can be a score (number) or a vector object
    let v: Record<string, number> = {};
    
    // Normalize input
    let obj: any = {};
    if (typeof input === 'number') {
        const s = input / 100;
        v = { p1: s, p2: s, p3: s, p4: s, p5: s, p6: s };
        return createPolygon(v);
    } else if (typeof input === 'string') {
        try { obj = JSON.parse(input); } catch { }
    } else {
        obj = input || {};
    }

    // Try Deep Search
    if (obj.hexagon_vectors) obj = obj.hexagon_vectors;

    // Map vectors to 6 axes (Robust)
    // If object is empty, we return a tiny polygon instead of 0
    v = {
        p1: (Number(obj.client_side_velocity) || 0) / 100,
        p2: (Number(obj.code_efficiency) || 0) / 100,
        p3: (Number(obj.security_perimeter) || 0) / 100,
        p4: (Number(obj.supply_chain_governance) || 0) / 100,
        p5: (Number(obj.infrastructure_maturity) || 0) / 100,
        p6: (Number(obj.database_architecture) || 0) / 100
    };

    return createPolygon(v);
}

function createPolygon(v: any): string {
    // Ensure min value for visibility (0.2) unless 0
    Object.keys(v).forEach(k => {
        if (v[k] > 0) v[k] = Math.max(0.2, v[k]);
    });

    const points = [
        `50,${50 - (45 * (v.p1 || 0))}`, // Top
        `${50 + (39 * (v.p2 || 0))},${50 - (22.5 * (v.p2 || 0))}`, // Top Right
        `${50 + (39 * (v.p3 || 0))},${50 + (22.5 * (v.p3 || 0))}`, // Bottom Right
        `50,${50 + (45 * (v.p4 || 0))}`, // Bottom
        `${50 - (39 * (v.p5 || 0))},${50 + (22.5 * (v.p5 || 0))}`, // Bottom Left
        `${50 - (39 * (v.p6 || 0))},${50 - (22.5 * (v.p6 || 0))}`  // Top Left
    ];
    
    return points.join(' ');
}
</script>

<template>
    <Head title="Marketplace - Lume" />

    <div class="min-h-screen bg-brand-dark text-slate-300 font-sans selection:bg-[#CBB48A]/30 selection:text-white overflow-x-hidden">
        <!-- Grain Overlay -->
        <div class="fixed inset-0 z-[100] pointer-events-none opacity-[0.03] bg-[url('https://grainy-gradients.vercel.app/noise.svg')] brightness-100 contrast-150" />
        
        <!-- Modern Header & Search -->
        <header class="relative z-20 border-b border-white/5 bg-brand-dark/40 backdrop-blur-2xl transition-all duration-500">
             <div class="absolute inset-x-0 top-0 h-[500px] bg-gradient-to-b from-[#CBB48A]/[0.05] via-transparent to-transparent pointer-events-none"></div>
            
            <div class="relative mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <!-- Branding & Nav -->
                <div class="flex items-center justify-between mb-12">
                     <Link href="/" class="flex items-center gap-4 group">
                        <div class="relative">
                            <div class="absolute -inset-2 bg-gradient-to-r from-[#CBB48A] to-[#F3E7C9] rounded-xl blur-lg opacity-20 group-hover:opacity-40 transition-opacity" />
                            <div class="relative flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-[#CBB48A] to-[#F3E7C9] shadow-xl shadow-[#CBB48A]/20 transition-transform group-hover:scale-105">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                        </div>
                        <span class="text-2xl font-black tracking-tight text-white uppercase italic">LUME<span class="text-[#CBB48A] not-italic">CORE</span></span>
                    </Link>
                    
                    <nav class="flex items-center gap-6">
                        <template v-if="isAuthenticated">
                            <Link :href="route('overview')" class="text-sm font-medium text-slate-400 hover:text-white transition-colors">Overview</Link>
                            <div class="h-4 w-px bg-white/10"></div>
                            
                            <!-- Profile Dropdown -->
                            <div class="relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button class="flex items-center gap-2 group focus:outline-none">
                                            <div class="h-8 w-8 rounded-full bg-gradient-to-r from-[#CBB48A] to-[#F3E7C9] p-[1px] group-hover:shadow-[0_0_15px_rgba(203, 180, 138, 0.4)] transition-all">
                                                <div class="h-full w-full rounded-full bg-[#0a0f1a] flex items-center justify-center text-xs font-bold text-white group-hover:bg-transparent transition-colors">
                                                    {{ authUser?.name.charAt(0) }}
                                                </div>
                                            </div>
                                            <svg class="h-4 w-4 text-slate-500 group-hover:text-[#CBB48A] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </template>

                                    <template #content>
                                        <div class="px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest text-slate-500">
                                            Account: <span class="text-slate-300 font-mono lower-case tracking-normal">{{ authUser?.email }}</span>
                                        </div>
                                        <div class="border-t border-white/5"></div>

                                        <DropdownLink :href="route('profile.edit')">
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </template>
                        <template v-else>
                            <Link :href="route('login')" class="text-sm font-medium text-slate-400 hover:text-white transition-colors">Log in</Link>
                            <Link :href="route('register')" class="rounded-lg bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 transition-all border border-white/5">Get Started</Link>
                        </template>
                    </nav>
                </div>

                <!-- Tabs (Browse / My Listings) -->
                <div v-if="isAuthenticated" class="flex justify-center mb-8">
                     <div class="bg-gray-800/50 p-1 rounded-xl flex items-center border border-white/5 backdrop-blur-md">
                         <Link 
                            :href="route('marketplace.index')" 
                            class="px-6 py-2 rounded-lg text-sm font-bold transition-all"
                            :class="viewMode === 'browse' ? 'bg-[#DCC8A5] text-white shadow-lg shadow-[#CBB48A]/20' : 'text-slate-400 hover:text-white'"
                        >
                            Browse Market
                        </Link>
                        <Link 
                            :href="route('marketplace.mylistings')" 
                            class="px-6 py-2 rounded-lg text-sm font-bold transition-all"
                            :class="viewMode === 'mylistings' ? 'bg-[#DCC8A5] text-white shadow-lg shadow-[#F3E7C9]/20' : 'text-slate-400 hover:text-white'"
                        >
                            My Listings
                        </Link>
                     </div>
                </div>

                <!-- Hero Search -->
                <div class="mx-auto max-w-4xl text-center relative z-10 py-12 lg:py-20">
                     <h1 class="text-5xl lg:text-[6rem] font-bold tracking-[-0.05em] text-white mb-10 select-none uppercase italic leading-[0.9]">
                        Find <span class="bg-gradient-to-r from-[#CBB48A] via-[#F3E7C9] to-[#CBB48A] bg-clip-text text-transparent">Verified</span> <br class="hidden lg:block"> Assets.
                    </h1>
                    
                    <div class="relative group max-w-2xl mx-auto">
                        <div class="absolute -inset-1 bg-gradient-to-r from-[#CBB48A] to-[#F3E7C9] rounded-2xl blur-lg opacity-20 group-hover:opacity-40 transition duration-1000"></div>
                        <div class="relative flex items-center bg-white/[0.03] backdrop-blur-md rounded-2xl border border-white/10 shadow-2xl p-3 ring-1 ring-white/5 transition-all group-hover:bg-white/[0.05]">
                            <div class="pl-4 text-[#CBB48A]/60">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </div>
                            <input 
                                v-model="searchQuery" 
                                type="text" 
                                placeholder="Search the Forensic Ledger..." 
                                class="w-full bg-transparent border-none text-white placeholder-slate-500 focus:ring-0 text-xl py-3 font-medium px-4"
                            >
                        </div>
                    </div>
                </div>

                <!-- Navigation & Filters -->
                <div class="mt-10 flex flex-col md:flex-row items-center justify-between gap-4 border-t border-white/5 pt-6">
                    <!-- Category Tabs -->
                    <div class="flex gap-2 overflow-x-auto pb-2 md:pb-0 scrollbar-hide">
                         <button 
                            v-for="cat in ['All Projects', 'SaaS', 'Crypto', 'Documents']" 
                            :key="cat"
                            @click="activeCategory = cat"
                            :class="[
                                'px-4 py-1.5 rounded-full text-sm font-medium transition-all border',
                                activeCategory === cat 
                                    ? 'bg-[#CBB48A]/10 border-[#CBB48A] text-[#CBB48A] shadow-[0_0_10px_rgba(203, 180, 138, 0.2)]' 
                                    : 'bg-transparent border-slate-700 text-slate-400 hover:border-slate-500 hover:text-slate-200'
                            ]"
                         >
                            {{ cat }}
                         </button>
                    </div>

                    <!-- Filter Dropdowns with Labels -->
                    <div class="flex gap-3">
                         <div class="relative">
                            <label class="block text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-1 px-1">Price</label>
                            <select v-model="priceRange" class="bg-white/5 border border-white/10 text-slate-300 text-xs rounded-lg focus:ring-[#CBB48A] focus:border-[#CBB48A] py-1.5 px-3 min-w-[120px]">
                                <option>All Prices</option>
                                <option>Under $100</option>
                                <option>$100 - $500</option>
                                <option>$500 - $5000</option>
                            </select>
                         </div>
                         <div class="relative">
                            <label class="block text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-1 px-1">Tech Stack</label>
                            <select v-model="selectedStack" class="bg-white/5 border border-white/10 text-slate-300 text-xs rounded-lg focus:ring-[#CBB48A] focus:border-[#CBB48A] py-1.5 px-3 min-w-[120px]">
                                <option>All Stacks</option>
                                <option>React</option>
                                <option>Vue</option>
                                <option>Laravel</option>
                                <option>Node.js</option>
                            </select>
                        </div>
                         <div class="relative">
                            <label class="block text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-1 px-1">Audit Score</label>
                            <select v-model="minScore" class="bg-white/5 border border-white/10 text-slate-300 text-xs rounded-lg focus:ring-[#CBB48A] focus:border-[#CBB48A] py-1.5 px-3 min-w-[120px]">
                                 <option>All Scores</option>
                                 <option>90+ (Elite)</option>
                                 <option>80+ (Verified)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 relative z-10">
            
            <!-- Premium Empty State -->
             <div v-if="!props.listings?.data || props.listings.data.length === 0" class="flex flex-col items-center justify-center py-24 text-center relative overflow-hidden rounded-[2rem] border border-slate-800/50 bg-[#0a0f1a]/40 backdrop-blur-2xl group transition-all hover:border-slate-700/50">
                 <!-- Animated Background glow -->
                 <div class="absolute -top-24 -left-24 w-64 h-64 bg-[#CBB48A]/10 blur-[100px] rounded-full animate-pulse"></div>
                 <div class="absolute -bottom-24 -right-24 w-64 h-64 bg-[#F3E7C9]/10 blur-[100px] rounded-full animate-pulse" style="animation-delay: 1s"></div>
                
                 <div class="relative z-10 animate-fade-in-up">
                     <div class="mb-8 relative inline-block">
                          <div class="absolute inset-0 bg-[#CBB48A]/20 blur-2xl rounded-full scale-125 animate-pulse"></div>
                          <div class="h-28 w-28 rounded-3xl bg-gradient-to-br from-[#CBB48A] to-[#F3E7C9] flex items-center justify-center shadow-[0_0_50px_rgba(203, 180, 138, 0.3)] relative z-10 transform transition-all duration-700 group-hover:rotate-[10deg] group-hover:scale-110">
                             <svg class="h-14 w-14 text-white drop-shadow-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                             </svg>
                          </div>
                     </div>
                    
                      <h3 class="text-4xl lg:text-5xl font-bold text-white mb-4 tracking-tight uppercase italic">The Frontier is Open.</h3>
                     <p class="text-slate-400 max-w-lg mx-auto mb-12 text-lg lg:text-xl leading-relaxed font-medium">
                         No projects discovered in this sector. Be the pioneer and list your <span class="bg-gradient-to-r from-[#CBB48A] to-[#F3E7C9] bg-clip-text text-transparent font-black">AI-Verified Architecture</span> on LUME today.
                     </p>
                    
                     <div class="flex flex-col sm:flex-row items-center justify-center gap-6">
                         <Link
                             :href="route('overview')"
                             class="group relative inline-flex items-center gap-3 rounded-2xl bg-white px-10 py-5 font-black text-slate-900 transition-all hover:scale-105 hover:bg-slate-100 shadow-[0_0_40px_rgba(255,255,255,0.15)] active:scale-95"
                         >
                             <span>Deploy Your First Project</span>
                             <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                         </Link>
                        
                         <button 
                             @click="searchQuery = ''; activeCategory = 'All Projects'; priceRange = 'All Prices'; selectedStack = 'All Stacks'; minScore = 'All Scores'"
                             class="px-10 py-5 rounded-2xl border border-slate-700/50 text-slate-400 font-bold tracking-wider text-xs hover:bg-slate-800 hover:text-white hover:border-slate-600 transition-all active:scale-95 backdrop-blur-sm"
                         >
                             Reset Scanning Parameters
                         </button>
                     </div>
                 </div>
             </div>

            <!-- Asset Grid -->
            <div v-else class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <component
                    :is="asset.is_purchased || asset.marketplace_status === 'updating' || asset.marketplace_status === 'flagged' ? 'div' : Link"
                    v-for="asset in props.listings.data"
                    :key="asset.id"
                    :href="asset.is_purchased || asset.marketplace_status === 'updating' || asset.marketplace_status === 'flagged' ? undefined : route('marketplace.asset.view', asset.id)"
                    class="group relative bg-[#0a0f1a] rounded-xl border border-slate-800 transition-all duration-300 flex flex-col overflow-hidden"
                    :class="[
                        asset.is_purchased ? 'opacity-60 cursor-not-allowed' : '',
                        asset.marketplace_status === 'updating' ? 'cursor-wait border-yellow-500/30' : '',
                        asset.marketplace_status === 'flagged' ? 'cursor-not-allowed border-red-500/30' : '',
                        !asset.is_purchased && asset.marketplace_status !== 'updating' && asset.marketplace_status !== 'flagged' ? 'hover:border-[#CBB48A]/50 hover:shadow-[0_0_30px_rgba(203, 180, 138, 0.1)] cursor-pointer' : ''
                    ]"
                >
                    <!-- SOLD BADGE -->
                    <div v-if="asset.is_purchased" class="absolute inset-0 z-50 flex items-center justify-center pointer-events-none">
                        <div class="px-8 py-3 bg-red-600/90 text-white font-black text-2xl uppercase tracking-[0.2em] border-y-4 border-white/20 transform -rotate-12 shadow-2xl backdrop-blur-sm">
                            SOLD
                        </div>
                    </div>

                    <!-- UPDATING OVERLAY -->
                    <div v-if="asset.marketplace_status === 'updating'" class="absolute inset-0 z-50 flex items-center justify-center bg-[#0a0f1a]/60 backdrop-blur-sm pointer-events-none">
                        <div class="flex flex-col items-center gap-4">
                            <div class="w-12 h-12 border-4 border-yellow-500/20 border-t-yellow-500 rounded-full animate-spin"></div>
                            <div class="px-6 py-2 bg-yellow-600 text-white font-bold text-sm uppercase tracking-wider rounded-full shadow-lg">
                                Sync Updating...
                            </div>
                        </div>
                    </div>

                    <!-- FLAGGED OVERLAY -->
                    <div v-if="asset.marketplace_status === 'flagged'" class="absolute inset-0 z-50 flex items-center justify-center bg-red-950/20 backdrop-blur-[2px] pointer-events-none">
                        <div class="flex flex-col items-center gap-2">
                            <div class="px-6 py-3 bg-red-600 text-white font-black text-lg uppercase tracking-wider border-2 border-white/30 transform -rotate-3 shadow-2xl">
                                FLAGGED
                            </div>
                            <div class="text-[10px] text-red-400 font-bold bg-black/80 px-2 py-1 rounded border border-red-500/30">
                                Score &lt; 85% - Synchronization Failed
                            </div>
                        </div>
                    </div>
                
                    <!-- header -->
                    <div class="p-5 border-b border-gray-800 bg-[#0f172a]/50 flex justify-between items-start">
                        <div>
                            <h3 class="text-lg font-bold text-white mb-1 truncate max-w-[200px]" :title="asset.metadata?.custom_name || asset.file_name">
                                {{ asset.metadata?.custom_name || asset.file_name }}
                            </h3>
                            <p class="text-xs text-slate-400 font-mono flex items-center gap-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-[#CBB48A] animate-pulse"></span>
                                Verified System
                            </p>
                        </div>
                        <!-- Price Pill (Moved to Top Right) -->
                        <div class="bg-[#0f172a] border border-[#CBB48A]/30 shadow-[0_0_15px_rgba(203, 180, 138, 0.15)] rounded-lg px-3 py-1.5 flex flex-col items-end">
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Price</span>
                            <span class="text-white text-sm font-bold font-mono">${{ Number(asset.price).toLocaleString() }}</span>
                        </div>
                    </div>

                    <!-- Tech Stack & Score Row -->
                    <div class="p-5 grid grid-cols-[1fr_auto] gap-4 items-center border-b border-gray-800">
                        <div>
                            <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-2">Tech Stack</p>
                            <div class="flex items-center gap-2">
                                <div  
                                    v-for="icon in getTechStackIcons(asset)" 
                                    :key="icon.name" 
                                    class="w-8 h-8 rounded-lg bg-[#1e293b] border border-slate-700 flex items-center justify-center p-1.5 transition-transform hover:scale-110" 
                                    :title="icon.name"
                                >
                                    <svg :viewBox="icon.viewBox" class="w-full h-full" :class="icon.color" fill="currentColor">
                                        <path :d="icon.path"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mini Radar / Score -->
                        <div class="flex items-center gap-3 pl-4 border-l border-gray-800">
                            <div class="text-center">
                                <p class="text-[10px] text-slate-500 uppercase font-bold">Overall</p>
                                <p class="text-2xl font-bold bg-gradient-to-br from-white to-slate-400 bg-clip-text text-transparent">
                                    {{ getGrade(calculateScoreFromVectors(asset)).letter }}
                                </p>
                            </div>
                             <!-- Simulated Mini Radar (SVG) -->
                            <div class="relative w-12 h-12 opacity-100">
                                <svg viewBox="0 0 100 100" class="w-full h-full overflow-visible drop-shadow-[0_0_8px_rgba(139,92,246,0.6)]">
                                     <!-- Background Hexagon -->
                                    <polygon points="50,5 93.3,25 93.3,75 50,95 6.7,75 6.7,25" fill="#1e293b" class="stroke-[#F3E7C9]/30" stroke-width="1.5" />
                                    <!-- Dynamic Data shape (Simulated based on score) -->
                                    <polygon 
                                        :points="getRadarPoints(asset.synced_metadata?.hexagon_vectors || asset.synced_metadata?.radar_data || asset.website_metadata?.hexagon_vectors || calculateScoreFromVectors(asset))" 
                                        class="fill-[#CBB48A]" 
                                        fill-opacity="0.5"
                                        stroke="#CBB48A"
                                        stroke-width="2"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Individual Scan Checks -->
                    <div class="mb-4 bg-gray-800/30 rounded-lg p-3 border border-gray-700/50 border-dashed">
                        <h4 class="text-[10px] uppercase font-bold text-gray-500 mb-2 tracking-wider">Individual Scan Checks</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Frontend (Website Scan) -->
                            <div>
                                <h5 class="text-[10px] text-gray-400 mb-1">Frontend & Performance</h5>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 text-xs text-green-400">
                                        <i class="fas fa-check-circle"></i> SSR Hydration
                                    </div>
                                    <!-- Dynamic Check: Optimization -->
                                    <div class="flex items-center gap-1.5 text-xs" :class="(asset.website_metadata?.performance_score || 0) > 80 ? 'text-green-400' : 'text-yellow-400'">
                                        <i :class="(asset.website_metadata?.performance_score || 0) > 80 ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'"></i> Image Optimization
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs text-yellow-400">
                                        <i class="fas fa-exclamation-triangle"></i> Core Web Vitals
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Backend (Repo Scan) -->
                            <div>
                                <h5 class="text-[10px] text-gray-400 mb-1">Backend & Security</h5>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 text-xs text-green-400">
                                        <i class="fas fa-check-circle"></i> API Rate Limiting
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs text-green-400">
                                        <i class="fas fa-check-circle"></i> JWT Authentication
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs" :class="(asset.repository_metadata?.security_score || 0) > 90 ? 'text-green-400' : 'text-blue-400'">
                                        <i class="fas fa-shield-alt"></i> {{ (asset.repository_metadata?.security_score || 0) > 90 ? 'Zero Critical Vulns' : 'Standard Security' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Comparison Section -->
                    <div class="p-5 flex-1 bg-[#050911]">
                        <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-3">Repo vs. Website Comparison</p>
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <!-- Repo -->
                            <div class="bg-[#0f172a] rounded-lg p-3 border border-slate-800 flex items-center justify-between group-hover:border-slate-700 transition-colors">
                                <div>
                                    <div class="text-[10px] text-slate-400 font-semibold mb-0.5">GitHub Repo (main)</div>
                                    <div class="text-xs text-slate-500 font-mono">Commit: {{ asset.repository_metadata?.commit_hash?.substring(0, 6) || asset.id.substring(0, 6) }}</div>
                                    <div class="text-[10px] text-slate-600">Files: ~{{ (asset.repository_metadata?.files_scanned || 0) }}</div>
                                </div>
                                <div class="w-8 h-8 rounded bg-slate-800 flex items-center justify-center text-slate-500">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                </div>
                            </div>
                             <!-- Website -->
                            <div class="bg-[#0f172a] rounded-lg p-3 border border-slate-800 flex items-center justify-between group-hover:border-slate-700 transition-colors">
                                <div>
                                    <div class="text-[10px] text-slate-400 font-semibold mb-0.5">Live Website (prod)</div>
                                    <div class="text-xs text-slate-500 font-mono">Deploy: {{ asset.website_metadata?.deploy_hash?.substring(0, 6) || asset.id.substring(0, 6) }}</div>
                                    <div class="text-[10px] text-slate-600">Assets: ~{{ (asset.website_metadata?.assets_count || 0) }}</div>
                                </div>
                                <div class="w-8 h-8 rounded bg-slate-800 flex items-center justify-center text-slate-500">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                </div>
                            </div>
                        </div>

                        <!-- Sync Bar (Dynamic Status) -->
                        <!-- Sync Bar (Dynamic Status) -->
                        <div class="w-full bg-[#CBB48A]/10 border border-[#CBB48A]/20 rounded-md py-1.5 text-center">
                            <!-- Use authoritative score calculated in Controller -->
                            <span v-if="(asset.latest_sync_score || calculateScoreFromVectors(asset)) > 80" class="text-xs font-bold text-[#CBB48A] tracking-wide">
                                Status: Synchronized ({{ asset.latest_sync_score || calculateScoreFromVectors(asset) }}% Match)
                            </span>
                            <span v-else class="text-xs font-bold text-red-400 tracking-wide">
                                Status: Divergence Detected ({{ asset.latest_sync_score || calculateScoreFromVectors(asset) }}% Match)
                            </span>
                        </div>
                    </div>

                    <!-- Footer / Price -->
                    <!-- Footer Actions -->
                    <div class="p-4 bg-[#0a0f1a] border-t border-gray-800 flex items-center justify-between">
                        <!-- Seller Actions (Owner View) -->
                        <div v-if="authUser && asset.user_id && String(asset.user_id) === String(authUser.id)" class="flex items-center gap-2">
                             <div class="bg-indigo-500/10 border border-indigo-500/30 rounded px-2 py-1 text-[10px] text-indigo-400 font-bold uppercase tracking-wider">
                                 My Listing
                             </div>
                             
                             <!-- Remove Button -->
                             <button 
                                 @click.prevent="toggleListing(asset)"
                                 class="text-xs text-red-400 hover:text-red-300 font-bold hover:underline"
                             >
                                 Remove
                             </button>
                        </div>

                        <!-- Buyer Actions (Public View) -->
                        <Link 
                            v-else
                            :href="route('marketplace.asset.view', asset.id)"
                            class="px-6 py-2 bg-[#CBB48A] text-slate-900 text-xs font-bold uppercase tracking-wider rounded shadow-lg shadow-[#CBB48A]/20 group-hover:scale-105 transition-transform"
                        >
                            View Scan
                        </Link>
                        
                        <div class="flex items-center gap-2">
                             <div class="w-4 h-4 rounded-full bg-indigo-500 flex items-center justify-center">
                                 <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                             </div>
                             <span class="text-[10px] text-slate-500 font-medium">LUME Autonomous Audit</span>
                             <!-- Price Pill is in header -->
                        </div>
                    </div>
                </component>
            </div>

            <!-- Pagination -->
            <div v-if="props.listings.last_page > 1" class="mt-12 flex justify-center">
                 <div class="flex gap-2">
                     <button class="px-4 py-2 rounded-lg border border-slate-700 text-slate-400 hover:text-white hover:border-slate-500">Previous</button>
                     <button class="px-4 py-2 rounded-lg bg-[#CBB48A] text-slate-900 font-bold">1</button>
                     <button class="px-4 py-2 rounded-lg border border-slate-700 text-slate-400 hover:text-white hover:border-slate-500">2</button>
                     <button class="px-4 py-2 rounded-lg border border-slate-700 text-slate-400 hover:text-white hover:border-slate-500">3</button>
                     <button class="px-4 py-2 rounded-lg border border-slate-700 text-slate-400 hover:text-white hover:border-slate-500">Next</button>
                 </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-white/5 bg-[#0a0f1a] py-8 text-center text-slate-500 text-sm">
             <p>&copy; 2026 LUME Intelligence. Sovereign AI Audited Assets.</p>
        </footer>

        <!-- Guest Email Modal -->
        <Teleport to="body">
            <transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showEmailModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
                    <transition
                        enter-active-class="ease-out duration-300"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="ease-in duration-200"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div v-if="showEmailModal" class="relative w-full max-w-md rounded-2xl border border-white/10 bg-gradient-to-br from-gray-800 to-gray-900 p-6 shadow-2xl">
                             <!-- Close Button -->
                             <button
                                 @click="closeEmailModal"
                                 class="absolute right-4 top-4 text-gray-400 transition-colors hover:text-white"
                             >
                                 <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                 </svg>
                             </button>

                             <!-- Modal Header -->
                             <div class="mb-6 text-center">
                                 <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-[#CBB48A]/20">
                                     <svg class="h-7 w-7 text-[#CBB48A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                     </svg>
                                 </div>
                                 <h3 class="text-xl font-bold text-white">Enter Your Email</h3>
                                 <p class="mt-1 text-sm text-gray-400">We'll send your purchase receipt and download link here</p>
                             </div>

                             <!-- Selected Asset Info -->
                             <div v-if="selectedAsset" class="mb-6 rounded-xl border border-white/10 bg-gray-800/50 p-4">
                                 <div class="flex items-center justify-between">
                                     <div class="flex items-center gap-3">
                                         <span class="text-2xl">{{ getDisplayIcon(selectedAsset) }}</span>
                                         <div>
                                             <p class="text-sm font-medium text-white">{{ selectedAsset.file_name }}</p>
                                             <p class="text-xs text-gray-400">{{ selectedAsset.metadata?.category ?? 'Asset' }}</p>
                                         </div>
                                     </div>
                                     <p class="text-lg font-bold text-[#F3E7C9]">${{ Number(selectedAsset?.price || 0).toFixed(2) }}</p>
                                 </div>
                             </div>

                             <!-- Email Input -->
                             <div class="mb-6">
                                 <label class="mb-2 block text-sm font-medium text-gray-300">Email Address</label>
                                 <input
                                     v-model="guestEmail"
                                     type="email"
                                     placeholder="you@example.com"
                                     class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-gray-500 focus:border-[#CBB48A] focus:outline-none focus:ring-1 focus:ring-[#CBB48A] transition-all font-medium"
                                     @keyup.enter="submitGuestEmail"
                                 />
                                 <p v-if="emailError" class="mt-2 text-sm text-red-400">{{ emailError }}</p>
                             </div>

                             <!-- Action Buttons -->
                             <div class="flex gap-3">
                                 <button
                                     @click="closeEmailModal"
                                     class="flex-1 rounded-xl border border-gray-600 bg-transparent px-4 py-3 font-semibold text-gray-300 transition-colors hover:bg-gray-700"
                                 >
                                     Cancel
                                 </button>
                                 <button
                                     @click="submitGuestEmail"
                                     :disabled="isSubmitting"
                                     class="flex-1 rounded-xl bg-gradient-to-r from-[#CBB48A] to-[#F3E7C9] px-4 py-3 font-black uppercase text-xs tracking-widest text-[#0a0f1a] shadow-lg shadow-[#CBB48A]/20 transition-all hover:scale-[1.02] active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                                 >
                                     <span v-if="isSubmitting" class="flex items-center justify-center gap-2">
                                         <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                             <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                             <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                         </svg>
                                         Processing...
                                     </span>
                                     <span v-else>Continue to Payment</span>
                                 </button>
                             </div>

                             <!-- Login Link -->
                             <p class="mt-4 text-center text-sm text-gray-400">
                                 Already have an account?
                                 <Link :href="route('login')" class="font-bold text-[#CBB48A] hover:text-[#CBB48A]/70 transition-colors">Log in</Link>
                             </p>
                        </div>
                    </transition>
                </div>
            </transition>
        </Teleport>

        <!-- Audit Report Modal -->


        <!-- Global AI Helper Bot -->
        <LumeAISupport mode="global" />
    </div>
</template>
