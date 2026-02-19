<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    // Capture intended_url from query params
    const params = new URLSearchParams(window.location.search);
    const intendedUrl = params.get('intended_url');

    form.transform((data) => ({
        ...data,
        intended_url: intendedUrl || ''
    })).post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <div class="mb-10 text-center">
             <div class="flex justify-center mb-6">
                <div class="relative flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-cyan-400 shadow-lg shadow-emerald-400/20">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
            </div>
            <h2 class="text-3xl font-black italic uppercase tracking-tighter text-white mb-2">LUME<span class="text-emerald-400 not-italic">CORE</span></h2>
            <p class="text-xs font-bold uppercase tracking-widest text-gray-500">Create Identity</p>
        </div>

        <form @submit.prevent="submit">
            <div class="space-y-5">
                <!-- Nick name -->
                <div>
                    <InputLabel for="name" value="Nick Name" class="text-gray-400 text-xs uppercase tracking-widest mb-2" />
                    <TextInput
                        id="name"
                        type="text"
                        class="mt-1 block w-full border-transparent bg-black/50 text-white placeholder-gray-600 focus:border-emerald-500/50 focus:ring-emerald-500/50 rounded-lg py-3 px-4 transition-all font-medium"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Alex"
                    />
                    <InputError class="mt-2 text-xs" :message="form.errors.name" />
                </div>

                <!-- Email -->
                <div>
                    <InputLabel for="email" value="Email Address" class="text-gray-400 text-xs uppercase tracking-widest mb-2" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full border-transparent bg-black/50 text-white placeholder-gray-600 focus:border-emerald-500/50 focus:ring-emerald-500/50 rounded-lg py-3 px-4 transition-all font-medium"
                        v-model="form.email"
                        required
                        autocomplete="username"
                        placeholder="you@vault.tech"
                    />
                    <InputError class="mt-2 text-xs" :message="form.errors.email" />
                </div>

                <!-- Password -->
                <div>
                    <InputLabel for="password" value="Password" class="text-gray-400 text-xs uppercase tracking-widest mb-2" />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full border-transparent bg-black/50 text-white placeholder-gray-600 focus:border-emerald-500/50 focus:ring-emerald-500/50 rounded-lg py-3 px-4 transition-all font-medium"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2 text-xs" :message="form.errors.password" />
                </div>

                <!-- Confirm Password -->
                <div>
                    <InputLabel for="password_confirmation" value="Confirm Password" class="text-gray-400 text-xs uppercase tracking-widest mb-2" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="mt-1 block w-full border-transparent bg-black/50 text-white placeholder-gray-600 focus:border-emerald-500/50 focus:ring-emerald-500/50 rounded-lg py-3 px-4 transition-all font-medium"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2 text-xs" :message="form.errors.password_confirmation" />
                </div>

                <!-- Submit & Login Link -->
                <div class="pt-6">
                    <PrimaryButton
                        class="w-full flex items-center justify-center rounded-lg bg-white py-3 text-xs font-black text-black shadow-[0_0_20px_rgba(255,255,255,0.1)] transition-all hover:bg-emerald-400 hover:shadow-[0_0_30px_rgba(16,185,129,0.4)] active:scale-95 disabled:opacity-50 uppercase tracking-widest"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        [ ESTABLISH IDENTITY ]
                    </PrimaryButton>
                    
                    <div class="mt-8 text-center text-sm border-t border-white/5 pt-8">
                        <span class="text-gray-500">Already registered?</span>
                        <Link
                            :href="route('login')"
                            class="ms-2 font-bold text-brand-primary transition-colors hover:text-white"
                        >
                            Log in
                        </Link>
                    </div>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
