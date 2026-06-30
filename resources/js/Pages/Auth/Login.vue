<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    // Capture intended_url from query params
    const params = new URLSearchParams(window.location.search);
    const intendedUrl = params.get('intended_url');

    form.transform((data) => ({
        ...data,
        intended_url: intendedUrl || ''
    })).post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head title="Log in" />

    <div class="min-h-screen grid grid-cols-1 lg:grid-cols-12 bg-[#0a0a0b] text-[#888888] font-sans antialiased overflow-hidden selection:bg-[#fbe6af]/20 selection:text-white">
        <!-- Left Side: Content & Branding -->
        <div class="hidden lg:flex lg:col-span-5 flex-col justify-end relative min-h-screen bg-black overflow-hidden border-r border-white/5 pb-0">
            <!-- Top: Brand Logo (Absolute positioned) -->
            <div class="absolute top-16 left-16 flex items-center gap-3 z-20">
                <img src="/images/none-transparent-logo.png" alt="Lume Logo" class="h-8 w-8 object-contain" />
                <span class="font-display text-lg font-bold tracking-[0.1em] text-white">Lume</span>
            </div>

            <!-- Text Container (Closer to the image) -->
            <div class="w-full max-w-[380px] mx-auto space-y-6 text-left relative z-10 mb-8">
                <h1 class="text-4xl font-extrabold text-white tracking-tight leading-[1.15]">
                    Secure access to<br>
                    your <span class="text-[#fbe6af]">digital assets.</span>
                </h1>
                <p class="text-sm text-gray-400 leading-relaxed">
                    Log in to verify, monitor, and protect your software supply chain with confidence.
                </p>
            </div>

            <!-- Bottom: Cube Image aligned to bottom and oversized -->
            <div class="w-full flex justify-center relative z-10 translate-y-6 overflow-hidden">
                <img src="/images/authpage.png" class="w-[110%] min-w-[500px] h-auto object-contain opacity-95 filter brightness-110" />
            </div>
        </div>

        <!-- Right Side: Interaction Form -->
        <div class="col-span-12 lg:col-span-7 flex flex-col min-h-screen relative overflow-y-auto bg-[#0a0a0b]">
            <!-- Top Right Header: Switch to Register -->
            <div class="pt-8 pr-12 text-right hidden sm:block">
                <span class="text-xs text-gray-500 mr-4">Don't have an account?</span>
                <Link :href="route('register')" class="inline-flex items-center justify-center rounded-lg bg-[#fbe6af] hover:bg-[#F3E7C9] text-black text-xs font-bold py-2 px-5 transition-colors">
                    Sign up
                </Link>
            </div>

            <!-- Center Content -->
            <div class="flex flex-col justify-center items-center flex-1 py-12 px-6 sm:px-12 lg:px-24">
                <div class="w-full max-w-[440px] space-y-8">
                    <!-- Heading -->
                    <div>
                        <h2 class="text-2xl font-bold text-white tracking-tight">Welcome back</h2>
                        <p class="text-sm text-gray-400 mt-2">Choose your preferred sign in method</p>
                    </div>

                    <!-- Social Stack -->
                    <div class="space-y-3">
                        <button type="button" class="w-full flex items-center justify-center gap-3 px-4 py-3 rounded-xl border border-white/5 bg-white/[0.01] hover:bg-white/[0.03] text-gray-300 hover:text-white text-sm font-semibold transition-all">
                            <svg class="h-5 w-5" viewBox="0 0 24 24">
                                <path fill="#EA4335" d="M12 5.04c1.66 0 3.2.57 4.38 1.69l3.27-3.27C17.67 1.62 15.02 1 12 1 7.35 1 3.37 3.67 1.39 7.56l3.85 2.99c.96-2.87 3.66-4.99 6.76-4.99z"/>
                                <path fill="#4285F4" d="M23.49 12.27c0-.81-.07-1.59-.2-2.36H12v4.51h6.46c-.29 1.48-1.14 2.73-2.42 3.58v2.98h3.89c2.28-2.1 3.56-5.19 3.56-8.71z"/>
                                <path fill="#FBBC05" d="M5.24 14.88c-.24-.72-.38-1.49-.38-2.28 0-.79.14-1.56.38-2.28L1.39 7.33C.5 9.12 0 11.11 0 13.2c0 2.09.5 4.08 1.39 5.87l3.85-3.19z"/>
                                <path fill="#34A853" d="M12 23c3.24 0 5.97-1.07 7.96-2.92l-3.89-2.98c-1.1.74-2.5 1.18-4.07 1.18-3.1 0-5.8-2.12-6.76-4.99L1.39 16.3C3.37 20.33 7.35 23 12 23z"/>
                            </svg>
                            <span>Continue with Google</span>
                        </button>

                        <button type="button" class="w-full flex items-center justify-center gap-3 px-4 py-3 rounded-xl border border-white/5 bg-white/[0.01] hover:bg-white/[0.03] text-gray-300 hover:text-white text-sm font-semibold transition-all">
                            <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.167 6.839 9.49.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.579.688.481C19.137 20.164 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
                            </svg>
                            <span>Continue with GitHub</span>
                        </button>
                    </div>

                    <!-- Divider -->
                    <div class="flex items-center gap-4 text-xs font-mono tracking-widest text-[#555] uppercase my-8">
                        <div class="h-[1px] flex-1 bg-white/5"></div>
                        <span>or continue with email</span>
                        <div class="h-[1px] flex-1 bg-white/5"></div>
                    </div>

                    <!-- Login Form -->
                    <form @submit.prevent="submit" class="space-y-5">
                        <div v-if="status" class="mb-4 text-sm font-medium text-green-500">
                            {{ status }}
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="text-gray-400 text-xs font-medium mb-2 block">Email or Username</label>
                            <input
                                id="email"
                                type="email"
                                class="w-full bg-[#121315]/50 border border-white/10 focus:border-[#CBB48A] focus:ring-1 focus:ring-[#CBB48A]/30 text-white rounded-xl py-3 px-4 transition-all placeholder-gray-600 focus:outline-none"
                                v-model="form.email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="you@example.com or username"
                            />
                            <InputError class="mt-2 text-xs" :message="form.errors.email" />
                        </div>

                        <!-- Password -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label for="password" class="text-gray-400 text-xs font-medium block">Password</label>
                            </div>
                            <div class="relative">
                                <input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="w-full bg-[#121315]/50 border border-white/10 focus:border-[#CBB48A] focus:ring-1 focus:ring-[#CBB48A]/30 text-white rounded-xl py-3 px-4 pr-12 transition-all placeholder-gray-600 focus:outline-none"
                                    v-model="form.password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Enter your password"
                                />
                                <div class="absolute inset-y-0 right-0 flex items-center pr-4">
                                    <button type="button" @click="showPassword = !showPassword" class="focus:outline-none">
                                        <svg v-if="showPassword" class="h-5 w-5 text-gray-500 hover:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <svg v-else class="h-5 w-5 text-gray-500 hover:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <InputError class="mt-2 text-xs" :message="form.errors.password" />
                        </div>

                        <!-- Remember / Forgot -->
                        <div class="flex items-center justify-between pt-1 text-sm">
                            <label class="flex items-center cursor-pointer group">
                                <Checkbox name="remember" v-model:checked="form.remember" class="h-4 w-4 rounded border-white/10 bg-[#121315]/50 text-[#CBB48A] focus:ring-[#CBB48A] focus:ring-offset-[#0a0a0b] transition-all" />
                                <span class="ms-2.5 text-xs text-gray-400 group-hover:text-gray-300 transition-colors select-none">Remember me</span>
                            </label>
                            
                            <Link v-if="canResetPassword" :href="route('password.request')" class="text-xs font-bold text-[#CBB48A] hover:text-[#F3E7C9] transition-colors">
                                Forgot password?
                            </Link>
                        </div>

                        <!-- Submit -->
                        <div class="pt-4">
                            <button
                                type="submit"
                                class="w-full flex items-center justify-center rounded-xl bg-[#fbe6af] hover:bg-[#F3E7C9] py-3.5 text-sm font-bold text-black shadow-lg shadow-black/20 hover:shadow-xl transition-all duration-200 active:scale-98 disabled:opacity-50"
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing">Logging in...</span>
                                <span v-else>Log in</span>
                            </button>
                        </div>
                    </form>

                    <!-- Footer Disclaimer -->
                    <div class="text-xs text-gray-500 leading-relaxed pt-2">
                        By continuing, you agree to Lume's
                        <Link :href="route('terms-of-service')" class="text-[#CBB48A] hover:text-[#F3E7C9] font-semibold transition-colors">Terms of Service</Link>
                        and
                        <Link :href="route('privacy-policy')" class="text-[#CBB48A] hover:text-[#F3E7C9] font-semibold transition-colors">Privacy Policy</Link>.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
