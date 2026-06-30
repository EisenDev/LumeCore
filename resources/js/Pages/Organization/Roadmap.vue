<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// No props needed for this static roadmap page
</script>

<template>
    <Head title="Organization Roadmap" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex flex-col">
                    <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.15em] text-gray-500 mb-1">
                        <Link :href="route('organizations.index')" class="hover:text-[#CBB48A] transition-colors">Organazitaions</Link>
                        <span>/</span>
                        <span class="text-gray-400 italic">Strategic Roadmap</span>
                    </div>
                    <span class="text-2xl font-bold tracking-tight text-white uppercase">Feature Preview & Roadmap</span>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-20">
                
                <!-- Hero Section -->
                <div class="relative overflow-hidden rounded-[3rem] border border-white/5 bg-[#0A0A0B] p-12 md:p-20 text-center">
                    <div class="absolute inset-0 bg-grid-white/[0.02] bg-[length:50px_50px]"></div>
                    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-3xl h-64 bg-[#CBB48A]/10 blur-[120px] rounded-full"></div>
                    
                    <div class="relative z-10 max-w-3xl mx-auto">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#CBB48A]/10 border border-[#CBB48A]/20 text-[10px] font-bold tracking-wider text-[#CBB48A] mb-6">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#CBB48A] animate-pulse"></span>
                            Incoming Architecture
                        </div>
                        <h1 class="text-4xl md:text-6xl font-bold tracking-tight text-white uppercase mb-6 leading-tight">
                            The Lume <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#CBB48A] to-[#F3E7C9]">Organization</span> Module
                        </h1>
                        <p class="text-lg text-gray-400 leading-relaxed font-light">
                            Establishing the framework for sovereign operational units. From collaborative agency workspaces to siloed academic environments, the Organization module is designed to scale forensic capabilities across teams.
                        </p>
                    </div>
                </div>

                <!-- Section A: The Dual-Tenant Strategy -->
                <section>
                    <div class="flex items-center gap-4 mb-10">
                        <div class="h-px flex-1 bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
                        <h2 class="text-xl font-bold tracking-tight text-white uppercase">01. The Dual-Tenant Strategy</h2>
                        <div class="h-px flex-1 bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Corporate Workspace -->
                        <div class="group relative overflow-hidden rounded-[2.5rem] bg-white/[0.02] border border-white/5 p-10 hover:bg-white/[0.04] transition-all duration-500">
                            <div class="absolute top-0 right-0 p-8 opacity-20 group-hover:opacity-40 transition-opacity">
                                <svg class="w-32 h-32 text-[#CBB48A]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="0.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            </div>
                            <h3 class="text-2xl font-bold tracking-tight text-white uppercase mb-4">Corporate Workspace</h3>
                            <p class="text-sm text-gray-400 mb-8 leading-relaxed">Designed for agencies and dev teams requiring shared visibility and real-time collaboration. Assets are centralized, and credits are pooled.</p>
                            
                            <ul class="space-y-4">
                                <li class="flex items-center gap-3 text-xs font-bold text-gray-300 uppercase tracking-widest">
                                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-[#CBB48A]/20 text-[#CBB48A]"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg></span>
                                    Shared Vault Access & Asset Visibility
                                </li>
                                <li class="flex items-center gap-3 text-xs font-bold text-gray-300 uppercase tracking-widest">
                                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-[#CBB48A]/20 text-[#CBB48A]"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg></span>
                                    Team-Wide Credit Pooling
                                </li>
                                <li class="flex items-center gap-3 text-xs font-bold text-gray-300 uppercase tracking-widest">
                                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-[#CBB48A]/20 text-[#CBB48A]"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg></span>
                                    Collaborative Forensic Reports
                                </li>
                            </ul>
                        </div>

                        <!-- Academic Institute -->
                        <div class="group relative overflow-hidden rounded-[2.5rem] bg-white/[0.02] border border-white/5 p-10 hover:bg-white/[0.04] transition-all duration-500">
                            <div class="absolute top-0 right-0 p-8 opacity-20 group-hover:opacity-40 transition-opacity">
                                <svg class="w-32 h-32 text-[#F3E7C9]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="0.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                            </div>
                            <h3 class="text-2xl font-bold tracking-tight text-white uppercase mb-4">Academic Institute</h3>
                            <p class="text-sm text-gray-400 mb-8 leading-relaxed">Engineered for Universities. Professors maintain a "Gradebook" view while students operate in siloed environments, preventing peer plagiarism.</p>
                            
                            <ul class="space-y-4">
                                <li class="flex items-center gap-3 text-xs font-bold text-gray-300 uppercase tracking-widest">
                                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-[#F3E7C9]/20 text-[#F3E7C9]"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg></span>
                                    Siloed Student Workspaces
                                </li>
                                <li class="flex items-center gap-3 text-xs font-bold text-gray-300 uppercase tracking-widest">
                                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-[#F3E7C9]/20 text-[#F3E7C9]"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg></span>
                                    Professor "Gradebook" Dashboard
                                </li>
                                <li class="flex items-center gap-3 text-xs font-bold text-gray-300 uppercase tracking-widest">
                                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-[#F3E7C9]/20 text-[#F3E7C9]"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg></span>
                                    Anti-Plagiarism Cross-Check
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Section B: Revenue Architecture -->
                <section>
                    <div class="flex items-center gap-4 mb-10">
                        <div class="h-px flex-1 bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
                        <h2 class="text-xl font-bold tracking-tight text-white uppercase">02. Revenue Architecture</h2>
                        <div class="h-px flex-1 bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
                    </div>

                    <div class="overflow-x-auto rounded-[2.5rem]">
                        <table class="w-full min-w-[800px] border-collapse bg-[#0A0A0B]">
                            <thead class="">
                                <tr>
                                    <th class="p-8 text-left text-xs font-black text-gray-500 uppercase tracking-[0.2em] border-b border-white/5 bg-white/[0.01]">Plan Structure</th>
                                    <th class="p-8 text-left text-xs font-black text-[#CBB48A] uppercase tracking-[0.2em] border-b border-white/5 bg-[#CBB48A]/[0.02]">Team Plan</th>
                                    <th class="p-8 text-left text-xs font-black text-[#F3E7C9] uppercase tracking-[0.2em] border-b border-white/5 bg-[#F3E7C9]/[0.02]">University Plan</th>
                                    <th class="p-8 text-left text-xs font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5 bg-purple-500/[0.02]">Enterprise API</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="p-8 border-b border-white/5 text-sm font-bold text-white">Monthly Cost</td>
                                    <td class="p-8 border-b border-white/5 text-2xl font-black italic text-white">$59<span class="text-sm font-medium text-gray-500 not-italic">/mo</span></td>
                                    <td class="p-8 border-b border-white/5 text-2xl font-black italic text-white">$10<span class="text-sm font-medium text-gray-500 not-italic">/student</span></td>
                                    <td class="p-8 border-b border-white/5 text-sm font-bold text-gray-400 uppercase">Custom Pricing</td>
                                </tr>
                                <tr>
                                    <td class="p-8 border-b border-white/5 text-sm font-bold text-white">Seat Allocation</td>
                                    <td class="p-8 border-b border-white/5 text-sm text-gray-400">3 Seats Included</td>
                                    <td class="p-8 border-b border-white/5 text-sm text-gray-400">Bulk Licensing</td>
                                    <td class="p-8 border-b border-white/5 text-sm text-gray-400">Unlimited Seats</td>
                                </tr>
                                <tr>
                                    <td class="p-8 border-b border-white/5 text-sm font-bold text-white">Credit Quota</td>
                                    <td class="p-8 border-b border-white/5 text-sm text-gray-400">5,000 Credits</td>
                                    <td class="p-8 border-b border-white/5 text-sm text-gray-400">Allocated per Head</td>
                                    <td class="p-8 border-b border-white/5 text-sm text-gray-400">Pay-as-you-go</td>
                                </tr>
                                <tr>
                                    <td class="p-8 border-b border-white/5 text-sm font-bold text-white">Key Feature</td>
                                    <td class="p-8 border-b border-white/5 text-[10px] font-bold tracking-wider text-[#CBB48A]">White-Label Reports</td>
                                    <td class="p-8 border-b border-white/5 text-[10px] font-bold tracking-wider text-[#F3E7C9]">Grade Sync Engine</td>
                                    <td class="p-8 border-b border-white/5 text-[10px] font-bold tracking-wider text-purple-400">Headless Integration</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Section C: Implementation Timeline -->
                <section>
                    <div class="flex items-center gap-4 mb-10">
                        <div class="h-px flex-1 bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
                        <h2 class="text-xl font-bold tracking-tight text-white uppercase">03. Implementation Timeline</h2>
                        <div class="h-px flex-1 bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
                    </div>

                    <div class="relative pl-8 border-l border-white/10 space-y-12">
                        <!-- Phase 1 -->
                        <div class="relative">
                            <span class="absolute -left-[37px] top-1 h-4 w-4 rounded-full bg-[#CBB48A] border-4 border-[#0A0A0B] shadow-[0_0_20px_rgba(203, 180, 138, 0.5)]"></span>
                            <span class="text-[10px] font-black text-[#CBB48A] uppercase tracking-[0.2em] block mb-2">Phase 1 (Current Status)</span>
                            <h3 class="text-2xl font-bold tracking-tight text-white uppercase mb-4">Database Schema Migration</h3>
                            <p class="text-gray-400 text-sm max-w-2xl leading-relaxed">
                                Establishing the multi-tenant scaffolding. Implementation of the `search_path` logic for context isolation and creation of the sovereign `organizations` table structures with pivot-based role systems.
                            </p>
                        </div>
                        
                        <!-- Phase 2 -->
                        <div class="relative">
                            <span class="absolute -left-[37px] top-1 h-4 w-4 rounded-full bg-white/20 border-4 border-[#0A0A0B]"></span>
                            <span class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] block mb-2">Phase 2 (Immediate Next)</span>
                            <h3 class="text-2xl font-bold tracking-tight text-gray-300 uppercase mb-4">Role-Based Access Control & Invite System</h3>
                            <p class="text-gray-500 text-sm max-w-2xl leading-relaxed">
                                Deploying the invitation mailers and security clearance levels. Granular permissions (Project Auditor vs Full Auditor) and secure token-based onboarding for new operatives.
                            </p>
                        </div>

                        <!-- Phase 3 -->
                        <div class="relative">
                            <span class="absolute -left-[37px] top-1 h-4 w-4 rounded-full bg-white/20 border-4 border-[#0A0A0B]"></span>
                            <span class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] block mb-2">Phase 3</span>
                            <h3 class="text-2xl font-bold tracking-tight text-gray-500 uppercase mb-4">'Watchtower' Continuous Integration</h3>
                            <p class="text-gray-500 text-sm max-w-2xl leading-relaxed">
                                Integration with CI/CD pipelines. Enabling teams to set up automated forensic scans upon specific git events (Push/Merge), providing a "Zero-Bug" safety net before deployment.
                            </p>
                        </div>

                        <!-- Phase 4 -->
                        <div class="relative">
                            <span class="absolute -left-[37px] top-1 h-4 w-4 rounded-full bg-white/20 border-4 border-[#0A0A0B]"></span>
                            <span class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] block mb-2">Phase 4</span>
                            <h3 class="text-2xl font-bold tracking-tight text-gray-500 uppercase mb-4">Agency Command Center</h3>
                            <p class="text-gray-500 text-sm max-w-2xl leading-relaxed">
                                The ultimate dashboard for managers and professors. Unified view of all active projects, student performance metrics, and global vulnerability heatmaps across the entire organization.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Section D: Value Proposition -->
                <section>
                    <div class="flex items-center gap-4 mb-10">
                        <div class="h-px flex-1 bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
                        <h2 class="text-xl font-bold tracking-tight text-white uppercase">04. The Value Proposition</h2>
                        <div class="h-px flex-1 bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="p-8 rounded-[2rem] bg-gradient-to-br from-white/[0.03] to-transparent border border-white/5 hover:border-[#CBB48A]/30 transition-all group">
                            <div class="h-12 w-12 rounded-xl bg-[#CBB48A]/10 flex items-center justify-center text-[#CBB48A] mb-6 group-hover:bg-[#CBB48A] group-hover:text-black transition-all">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <h3 class="text-lg font-black font-mono text-white uppercase mb-2">Zero-Bug Handover</h3>
                            <p class="text-sm text-gray-400 leading-relaxed">For Agencies: Guarantee code quality before it reaches the client. Automated audits reduce manual QA time by 40%.</p>
                        </div>
                        
                        <div class="p-8 rounded-[2rem] bg-gradient-to-br from-white/[0.03] to-transparent border border-white/5 hover:border-[#F3E7C9]/30 transition-all group">
                            <div class="h-12 w-12 rounded-xl bg-[#F3E7C9]/10 flex items-center justify-center text-[#F3E7C9] mb-6 group-hover:bg-[#F3E7C9] group-hover:text-black transition-all">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                            </div>
                            <h3 class="text-lg font-black font-mono text-white uppercase mb-2">Automated Grading</h3>
                            <p class="text-sm text-gray-400 leading-relaxed">For Universities: Instant technical feedback for students. Professors get a breakdown of code structure vs requirements.</p>
                        </div>
                    </div>
                </section>
                
                <!-- CTA -->
                <div class="text-center pt-10">
                    <Link :href="route('organizations.index')" class="inline-flex items-center gap-3 px-8 py-4 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 text-xs font-black uppercase tracking-[0.2em] text-white transition-all">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        Return to Dashboard
                    </Link>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
