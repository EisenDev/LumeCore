<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: Boolean;
    status?: String;
}>();

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-xl font-bold tracking-tight text-white uppercase mt-1">
                Profile Information
            </h2>

            <p class="mt-2 text-[10px] font-bold text-gray-500 uppercase tracking-widest leading-none">
                Update your account's profile information and email address.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="name" value="Name" class="text-[10px] font-bold tracking-wider text-gray-400 mb-2" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full bg-white/5 border-white/10 text-white text-sm font-bold tracking-tight px-4 py-3 rounded-xl focus:ring-[#CBB48A]/30 focus:border-[#CBB48A]/50"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" class="text-[10px] font-bold tracking-wider text-gray-400 mb-2" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full bg-white/5 border-white/10 text-white text-sm font-bold tracking-tight px-4 py-3 rounded-xl focus:ring-[#CBB48A]/30 focus:border-[#CBB48A]/50"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800 dark:text-gray-200">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600 dark:text-green-400"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton 
                    :disabled="form.processing"
                    class="bg-[#CBB48A] hover:bg-[#CBB48A]/70 text-slate-950 font-bold tracking-tight px-8 py-3 rounded-xl transition-all active:scale-95 shadow-lg shadow-[#CBB48A]/10"
                >
                    Save Changes
                </PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-[10px] font-bold tracking-wider text-[#CBB48A] animate-pulse"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
