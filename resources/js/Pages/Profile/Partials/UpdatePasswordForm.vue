<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-xl font-bold tracking-tight text-white uppercase mt-1">
                Update Password
            </h2>

            <p class="mt-2 text-[10px] font-bold text-gray-500 uppercase tracking-widest leading-none">
                Ensure your account is using a long, random password to stay secure.
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="mt-6 space-y-6">
            <div>
                <InputLabel for="current_password" value="Current Password" class="text-[10px] font-bold tracking-wider text-gray-400 mb-2" />

                <TextInput
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="mt-1 block w-full bg-white/5 border-white/10 text-white text-sm font-bold tracking-tight px-4 py-3 rounded-xl focus:ring-[#CBB48A]/30 focus:border-[#CBB48A]/50"
                    autocomplete="current-password"
                />

                <InputError :message="form.errors.current_password" class="mt-2" />
            </div>

            <div>
                <InputLabel for="password" value="New Password" class="text-[10px] font-bold tracking-wider text-gray-400 mb-2" />

                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full bg-white/5 border-white/10 text-white text-sm font-bold tracking-tight px-4 py-3 rounded-xl focus:ring-[#F3E7C9]/30 focus:border-[#F3E7C9]/50"
                    autocomplete="new-password"
                />

                <InputError :message="form.errors.password" class="mt-2" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Confirm Password"
                    class="text-[10px] font-bold tracking-wider text-gray-400 mb-2"
                />

                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full bg-white/5 border-white/10 text-white text-sm font-bold tracking-tight px-4 py-3 rounded-xl focus:ring-[#F3E7C9]/30 focus:border-[#F3E7C9]/50"
                    autocomplete="new-password"
                />

                <InputError
                    :message="form.errors.password_confirmation"
                    class="mt-2"
                />
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton 
                    :disabled="form.processing"
                    class="bg-[#F3E7C9] hover:bg-[#F3E7C9]/70 text-slate-950 font-bold tracking-tight px-8 py-3 rounded-xl transition-all active:scale-95 shadow-lg shadow-[#F3E7C9]/10"
                >
                    Update Password
                </PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-[10px] font-bold tracking-wider text-[#F3E7C9] animate-pulse"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
