<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

/**
 * Privacy Policy Page
 * Explains LUME's data privacy practices for the Vault ecosystem
 */
</script>

<template>
    <Head title="Privacy Policy" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-white">Privacy Policy</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <!-- Privacy Policy Content Card -->
                <div class="rounded-2xl bg-gray-900/90 p-8 shadow-xl ring-1 ring-white/10 backdrop-blur-sm">
                    <!-- Header -->
                    <div class="mb-8 border-b border-gray-700 pb-8">
                        <div class="flex items-center gap-4">
                            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-sky-500/20">
                                <svg class="h-8 w-8 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold text-white">LUME Data Privacy Policy</h1>
                                <p class="mt-1 text-gray-400">How we protect your personal information</p>
                            </div>
                        </div>
                    </div>

                    <!-- Content Sections -->
                    <div class="space-y-8 text-gray-300">
                        <!-- Section 1: Introduction -->
                        <section>
                            <h2 class="mb-4 flex items-center gap-2 text-xl font-semibold text-white">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-primary/20 text-sm text-brand-primary">1</span>
                                Introduction
                            </h2>
                            <p class="leading-relaxed">
                                At LUME, we take data privacy seriously. Our AI-powered Sovereign Vault Auditor is designed to analyze documents for professional quality while detecting and protecting Personal Identifiable Information (PII).
                            </p>
                        </section>

                        <!-- Section 2: What is PII -->
                        <section>
                            <h2 class="mb-4 flex items-center gap-2 text-xl font-semibold text-white">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-primary/20 text-sm text-brand-primary">2</span>
                                What is Personal Identifiable Information (PII)?
                            </h2>
                            <p class="mb-4 leading-relaxed">
                                PII refers to any data that can be used to identify a specific individual. Our system detects the following types of PII:
                            </p>
                            <ul class="ml-4 list-inside list-disc space-y-2 text-gray-400">
                                <li><span class="text-white">Full Real Names</span> — Names that identify a specific person</li>
                                <li><span class="text-white">Contact Information</span> — Phone numbers, email addresses, home addresses</li>
                                <li><span class="text-white">Government IDs</span> — SSN, TIN, Passport numbers, Driver's License numbers</li>
                                <li><span class="text-white">Financial Data</span> — Bank account numbers, credit card information</li>
                                <li><span class="text-white">Biometric Data</span> — Photographs of individuals, fingerprints</li>
                                <li><span class="text-white">Specific Dates</span> — Birthdates, specific appointment dates with identifying context</li>
                            </ul>
                        </section>

                        <!-- Section 3: Why We Protect PII -->
                        <section>
                            <h2 class="mb-4 flex items-center gap-2 text-xl font-semibold text-white">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-primary/20 text-sm text-brand-primary">3</span>
                                Why We Protect PII from Marketplace Distribution
                            </h2>
                            <div class="rounded-lg border border-sky-500/30 bg-sky-500/10 p-4">
                                <div class="flex items-start gap-3">
                                    <svg class="mt-1 h-5 w-5 flex-shrink-0 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    <p class="leading-relaxed">
                                        Documents containing PII cannot be sold on the LUME Marketplace to prevent identity theft, financial fraud, and privacy violations. This protects both you and potential buyers from legal liability.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- Section 4: What Happens to Your Documents -->
                        <section>
                            <h2 class="mb-4 flex items-center gap-2 text-xl font-semibold text-white">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-primary/20 text-sm text-brand-primary">4</span>
                                What Happens to Documents with PII?
                            </h2>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="rounded-lg bg-gray-800/50 p-4">
                                    <h3 class="mb-2 flex items-center gap-2 font-semibold text-green-400">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        We DO:
                                    </h3>
                                    <ul class="space-y-2 text-sm text-gray-400">
                                        <li>✓ Store your documents securely in CloudVault</li>
                                        <li>✓ Provide quality scores and professional feedback</li>
                                        <li>✓ Encrypt all data at rest and in transit</li>
                                        <li>✓ Allow you to delete your data at any time</li>
                                    </ul>
                                </div>
                                <div class="rounded-lg bg-gray-800/50 p-4">
                                    <h3 class="mb-2 flex items-center gap-2 font-semibold text-red-400">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        We DON'T:
                                    </h3>
                                    <ul class="space-y-2 text-sm text-gray-400">
                                        <li>✗ Sell or share your PII with third parties</li>
                                        <li>✗ Allow PII documents on the public marketplace</li>
                                        <li>✗ Train our AI on your personal documents</li>
                                        <li>✗ Retain documents after you delete them</li>
                                    </ul>
                                </div>
                            </div>
                        </section>

                        <!-- Section 5: Verified Private Status -->
                        <section>
                            <h2 class="mb-4 flex items-center gap-2 text-xl font-semibold text-white">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-primary/20 text-sm text-brand-primary">5</span>
                                Verified (Private) Status
                            </h2>
                            <div class="rounded-lg border border-sky-500/30 bg-gradient-to-br from-sky-500/10 to-blue-500/10 p-5">
                                <div class="flex items-start gap-4">
                                    <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-sky-500/20">
                                        <svg class="h-6 w-6 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-sky-300">What does "Verified (Private)" mean?</h3>
                                        <p class="mt-2 text-sm leading-relaxed text-gray-400">
                                            A document with "Verified (Private)" status means LUME has verified it as a high-quality, professionally-crafted document (score 80+), but it contains PII that prevents it from being listed on the marketplace. This is a <strong class="text-sky-300">positive verification</strong> of your work quality, with privacy protection applied automatically.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Section 6: Making Documents Marketplace Ready -->
                        <section>
                            <h2 class="mb-4 flex items-center gap-2 text-xl font-semibold text-white">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-primary/20 text-sm text-brand-primary">6</span>
                                Making Documents Marketplace Ready
                            </h2>
                            <p class="mb-4 leading-relaxed">
                                If you want to sell a template version of your document, consider these steps:
                            </p>
                            <ul class="ml-4 list-inside list-decimal space-y-2 text-gray-400">
                                <li>Create a copy of your document</li>
                                <li>Replace all real names with placeholders (e.g., "John Doe")</li>
                                <li>Remove or obscure contact information (use "your@email.com")</li>
                                <li>Remove specific dates and ID numbers</li>
                                <li>Upload the anonymized version for marketplace listing</li>
                            </ul>
                        </section>

                        <!-- Section 7: Contact -->
                        <section>
                            <h2 class="mb-4 flex items-center gap-2 text-xl font-semibold text-white">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-primary/20 text-sm text-brand-primary">7</span>
                                Questions or Concerns?
                            </h2>
                            <p class="leading-relaxed">
                                If you have questions about our privacy practices or believe your document was incorrectly flagged, please contact our support team. We're committed to protecting your privacy while helping you showcase your professional work.
                            </p>
                        </section>
                    </div>

                    <!-- Footer -->
                    <div class="mt-8 border-t border-gray-700 pt-6 text-center text-sm text-gray-500">
                        <p>Last updated: January 2026</p>
                        <p class="mt-1">© 2026 LUME. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
