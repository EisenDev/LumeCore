<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { onMounted } from 'vue';

onMounted(() => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));
});

const scrollToSection = (id: string) => {
    const element = document.getElementById(id);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
    }
};
</script>

<template>
    <Head title="Terms of Service" />

    <PublicLayout>
        <!-- Hero Section -->
        <section class="relative pt-32 pb-20 lg:pt-40 lg:pb-24 overflow-hidden">
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute top-[-10%] left-[-10%] w-[600px] h-[600px] bg-lume-primary/5 rounded-full blur-[150px]" />
                <div class="absolute bottom-[-10%] right-[-10%] w-[600px] h-[600px] bg-lume-primary/5 rounded-full blur-[150px]" />
            </div>

            <div class="relative z-10 mx-auto max-w-5xl px-6 lg:px-8">
                <div class="reveal text-left max-w-4xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 backdrop-blur-sm mb-6">
                         <span class="w-1.5 h-1.5 rounded-full bg-[#CBB48A] animate-pulse"></span>
                         <span class="text-[10px] font-semibold uppercase tracking-wider text-[#CBB48A]">Operating agreement</span>
                    </div>
                    
                    <h1 class="text-4xl lg:text-6xl font-bold text-white tracking-tight leading-tight mb-6">
                        Terms of operational <br><span class="text-transparent bg-clip-text bg-gradient-to-r from-white to-gray-500">governance.</span>
                    </h1>
                    
                    <p class="text-base lg:text-lg text-gray-400 font-medium max-w-3xl leading-relaxed">
                        The definitive rules of engagement, verification responsibilities, and legal limits governing the Lume network.
                    </p>
                </div>
            </div>
        </section>

        <!-- Main Content -->
        <section class="relative pb-24 lg:pb-32">
            <div class="mx-auto max-w-5xl px-6 lg:px-8">
                <div class="grid lg:grid-cols-12 gap-12 lg:gap-16">
                     <!-- Navigation / Table of Contents -->
                     <div class="lg:col-span-4 hidden lg:block sticky top-32 self-start space-y-6">
                         <!-- Status -->
                         <div class="p-6 rounded-2xl bg-white/[0.01] border border-white/5">
                             <h3 class="text-xs font-bold uppercase tracking-wider text-white mb-2">Protocol status</h3>
                             <div class="flex items-center gap-2">
                                 <span class="relative flex h-2 w-2">
                                   <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#CBB48A] opacity-75"></span>
                                   <span class="relative inline-flex rounded-full h-2 w-2 bg-[#CBB48A]"></span>
                                 </span>
                                 <p class="text-xs font-bold text-[#CBB48A] tracking-wider uppercase">Active & Enforced</p>
                             </div>
                         </div>

                         <div class="p-6 rounded-2xl bg-white/[0.01] border border-white/5 backdrop-blur-sm">
                             <h3 class="text-xs font-bold uppercase tracking-wider text-white mb-4">Quick jump</h3>
                             <ul class="space-y-3">
                                  <li @click="scrollToSection('compliance')" class="group flex items-center gap-3 text-xs font-semibold text-gray-400 hover:text-[#CBB48A] transition-colors cursor-pointer">
                                     <span class="w-1.5 h-1.5 rounded-full bg-gray-800 group-hover:bg-[#CBB48A] transition-colors"></span>
                                     Protocol agreement
                                 </li>
                                  <li @click="scrollToSection('accounts')" class="group flex items-center gap-3 text-xs font-semibold text-gray-400 hover:text-[#CBB48A] transition-colors cursor-pointer">
                                     <span class="w-1.5 h-1.5 rounded-full bg-gray-800 group-hover:bg-[#CBB48A] transition-colors"></span>
                                     Workspace conditions
                                 </li>
                                  <li @click="scrollToSection('assets')" class="group flex items-center gap-3 text-xs font-semibold text-gray-400 hover:text-[#CBB48A] transition-colors cursor-pointer">
                                     <span class="w-1.5 h-1.5 rounded-full bg-gray-800 group-hover:bg-[#CBB48A] transition-colors"></span>
                                     Asset ownership
                                 </li>
                                  <li @click="scrollToSection('conduct')" class="group flex items-center gap-3 text-xs font-semibold text-gray-400 hover:text-[#CBB48A] transition-colors cursor-pointer">
                                     <span class="w-1.5 h-1.5 rounded-full bg-gray-800 group-hover:bg-[#CBB48A] transition-colors"></span>
                                     Acceptable use policy
                                 </li>
                                 <li @click="scrollToSection('liability')" class="group flex items-center gap-3 text-xs font-semibold text-gray-400 hover:text-[#CBB48A] transition-colors cursor-pointer">
                                     <span class="w-1.5 h-1.5 rounded-full bg-gray-800 group-hover:bg-[#CBB48A] transition-colors"></span>
                                     Liability & disclaimer
                                 </li>
                             </ul>
                         </div>

                         <!-- Contract Specs -->
                         <div class="p-6 rounded-2xl bg-white/[0.01] border border-white/5 backdrop-blur-sm">
                             <h3 class="text-xs font-bold uppercase tracking-wider text-white mb-4">Agreement specs</h3>
                             <dl class="space-y-4">
                                 <div>
                                     <dt class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A]">Jurisdiction</dt>
                                     <dd class="text-xs text-gray-400 mt-1 leading-relaxed">International Digital Commerce Zone.</dd>
                                 </div>
                                 <div>
                                     <dt class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A]">Governing Law</dt>
                                     <dd class="text-xs text-gray-400 mt-1 leading-relaxed">Lume Consensus Protocol Agreements & Common Commercial Law.</dd>
                                 </div>
                             </dl>
                         </div>
                     </div>

                     <!-- Main text block -->
                     <div class="lg:col-span-8 space-y-16 text-left">
                          <!-- Section 1 -->
                         <div id="compliance" class="reveal group relative pl-6 border-l border-white/5 hover:border-[#CBB48A] transition-colors duration-500">
                             <div class="absolute -left-[5px] top-0 h-2.5 w-2.5 rounded-full bg-[#0a0a0b] border-2 border-white/10 group-hover:border-[#CBB48A] group-hover:bg-[#CBB48A] transition-colors duration-500"></div>
                             <div class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A] mb-2">01 / Agreement</div>
                             <h2 class="text-2xl font-bold text-white tracking-tight mb-4">Acceptance of protocol</h2>
                             <div class="text-xs text-gray-400 space-y-4 leading-relaxed font-medium">
                                 <p>
                                     By initializing a secure connection to Lume Core interfaces, APIs, command line structures, or workspace environments, you explicitly agree to follow these Terms of Operation. If you are entering into this agreement on behalf of a company, organization, or sovereign development team, you represent that you hold the legal authority to bind that entity to these conditions.
                                 </p>
                             </div>
                         </div>

                          <!-- Section 2: Account Terms -->
                         <div id="accounts" class="reveal group relative pl-6 border-l border-white/5 hover:border-[#CBB48A] transition-colors duration-500">
                             <div class="absolute -left-[5px] top-0 h-2.5 w-2.5 rounded-full bg-[#0a0a0b] border-2 border-white/10 group-hover:border-[#CBB48A] group-hover:bg-[#CBB48A] transition-colors duration-500"></div>
                             <div class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A] mb-2">02 / Workspaces</div>
                             <h2 class="text-2xl font-bold text-white tracking-tight mb-4">Sovereign workspace conditions</h2>
                             <div class="text-xs text-gray-400 space-y-4 leading-relaxed font-medium">
                                 <p>
                                     Operators must maintain the confidentiality of local workspace keys, configuration tokens, and CloudVault access credentials. You are responsible for all audits, transactions, and credit consumptions occurring within your workspace profile. Lume is not liable for data loss or credit degradation resulting from compromised local credential keys.
                                 </p>
                                 <p>
                                     To ensure system integrity, workspaces are subject to rate limits. Abuse of the API Gateway, scripting of credit allocation endpoints without authorization, or actions leading to denial of sandbox runtime processes will result in immediate termination of the node.
                                 </p>
                             </div>
                         </div>

                         <!-- Section 3: Asset Sovereignty -->
                         <div id="assets" class="reveal group relative pl-6 border-l border-white/5 hover:border-[#CBB48A] transition-colors duration-500">
                             <div class="absolute -left-[5px] top-0 h-2.5 w-2.5 rounded-full bg-[#0a0a0b] border-2 border-white/10 group-hover:border-[#CBB48A] group-hover:bg-[#CBB48A] transition-colors duration-500"></div>
                             <div class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A] mb-2">03 / Ownership</div>
                             <h2 class="text-2xl font-bold text-white tracking-tight mb-4">Asset ownership & parsing rights</h2>
                             <div class="text-xs text-gray-400 space-y-4 leading-relaxed font-medium">
                                 <p>
                                     You retain absolute ownership, intellectual property rights, and sovereignty over all codebases, documents, and assets staged to the CloudVault. Lume acts exclusively as an automated parsing utility and ledger logging environment.
                                 </p>
                                 <p>
                                     By uploading files to the workspace, you grant Lume a temporary, non-exclusive, highly restricted license solely to mount, scan, deconstruct syntax into ASTs, and evaluate dependencies as necessary to output integrity metrics and compliance reports.
                                 </p>
                             </div>
                         </div>

                         <!-- Section 4: Conduct -->
                         <div id="conduct" class="reveal group relative pl-6 border-l border-white/5 hover:border-[#CBB48A] transition-colors duration-500">
                             <div class="absolute -left-[5px] top-0 h-2.5 w-2.5 rounded-full bg-[#0a0a0b] border-2 border-white/10 group-hover:border-[#CBB48A] group-hover:bg-[#CBB48A] transition-colors duration-500"></div>
                             <div class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A] mb-2">04 / Conduct</div>
                             <h2 class="text-2xl font-bold text-white tracking-tight mb-4">Acceptable use & security limits</h2>
                             <div class="text-xs text-gray-400 space-y-4 leading-relaxed font-medium">
                                 <p>
                                     Operators agree not to utilize the Lume verification pipeline or isolated sandboxes to host, review, or verify:
                                 </p>
                                 <ul class="space-y-2 pl-4">
                                     <li class="flex items-start gap-3">
                                         <span class="mt-1.5 h-1 w-1 rounded-full bg-[#CBB48A] shrink-0"></span>
                                         <span>Malicious executables, active ransomware, or binaries designed to exploit host kernels.</span>
                                     </li>
                                     <li class="flex items-start gap-3">
                                         <span class="mt-1.5 h-1 w-1 rounded-full bg-[#CBB48A] shrink-0"></span>
                                         <span>Intellectual property obtained through unauthorized access or breach of confidentiality covenants.</span>
                                     </li>
                                     <li class="flex items-start gap-3">
                                         <span class="mt-1.5 h-1 w-1 rounded-full bg-[#CBB48A] shrink-0"></span>
                                         <span>Code targeted at reverse-engineering the internal heuristic models of the Forensic Engine.</span>
                                     </li>
                                 </ul>
                             </div>
                         </div>
                         
                          <!-- Section 5: Liability -->
                         <div id="liability" class="reveal group relative pl-6 border-l border-white/5 hover:border-[#CBB48A] transition-colors duration-500">
                             <div class="absolute -left-[5px] top-0 h-2.5 w-2.5 rounded-full bg-[#0a0a0b] border-2 border-white/10 group-hover:border-[#CBB48A] group-hover:bg-[#CBB48A] transition-colors duration-500"></div>
                             <div class="text-[9px] font-bold uppercase tracking-wider text-[#CBB48A] mb-2">05 / Liabilities</div>
                             <h2 class="text-2xl font-bold text-white tracking-tight mb-4">Disclaimer & liability conditions</h2>
                             <div class="text-xs text-gray-400 space-y-4 leading-relaxed font-medium">
                                 <p>
                                     Lume services, security scores, AST outputs, and ledger tracking logs are provided on an "as is" and "as available" basis. Verification metrics are calculated using heuristic probability maps and do not represent a legal guarantee of vulnerability absence or absolute protection.
                                 </p>
                                 <p>
                                     In no event shall Lume be liable for indirect, incidental, special, or consequential damages, including loss of profit, codebase compromises, or deployment disruptions resulting from undetected security parameters.
                                 </p>
                             </div>
                         </div>

                     </div>
                </div>
            </div>
        </section>
        
        <!-- Final CTA / Info -->
        <section id="contact" class="py-24 border-t border-white/5 bg-white/[0.01]">
             <div class="mx-auto max-w-5xl px-6 lg:px-8 text-center reveal">
                 <h2 class="text-2xl font-bold text-white tracking-tight mb-4">Need clarity on our terms?</h2>
                 <p class="text-sm text-gray-400 mb-10 max-w-xl mx-auto font-medium leading-relaxed">Our legal and governance desk is available for encrypted consultation regarding corporate operational structures.</p>
                 <a href="mailto:lumecore.tech@gmail.com" class="inline-flex items-center justify-center px-6 py-3.5 text-xs font-semibold text-slate-950 bg-white rounded-lg hover:scale-[1.02] transition-transform">
                     Contact legal desk
                 </a>
                 <div class="mt-4 text-[10px] font-mono text-gray-500 uppercase tracking-widest">
                     lumecore.tech@gmail.com
                 </div>
             </div>
        </section>

    </PublicLayout>
</template>

<style scoped>
.reveal {
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, transform;
}

.reveal.active {
    opacity: 1;
    transform: translateY(0);
}

.delay-100 { transition-delay: 100ms; }
.delay-200 { transition-delay: 200ms; }
</style>
