<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value?.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => {
            form.reset();
        },
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-xl font-black italic tracking-tighter text-white uppercase mt-1">
                Delete Account
            </h2>

            <p class="mt-2 text-[10px] font-bold text-gray-500 uppercase tracking-widest leading-none">
                Once your account is deleted, all of its resources and data will be permanently deleted.
            </p>
        </header>

        <DangerButton 
            @click="confirmUserDeletion"
            class="bg-rose-500 hover:bg-rose-400 text-slate-950 font-black italic tracking-tighter uppercase px-8 py-3 rounded-xl transition-all active:scale-95 shadow-lg shadow-rose-500/10 mt-6"
        >
            Delete Account
        </DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-8 bg-[#0A0A0B]/90 backdrop-blur-3xl border border-white/5 rounded-[2rem]">
                <h2
                    class="text-xl font-black italic tracking-tighter text-white uppercase"
                >
                    Are you sure you want to delete your account?
                </h2>

                <p class="mt-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">
                    Once your account is deleted, all of its resources and data
                    will be permanently deleted. Please enter your password to
                    confirm you would like to permanently delete your account.
                </p>

                <div class="mt-8">
                    <InputLabel
                        for="password"
                        value="Password"
                        class="sr-only"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-3/4 bg-white/5 border-white/10 text-white text-sm font-bold tracking-tight px-4 py-3 rounded-xl focus:ring-rose-500/30 focus:border-rose-500/50"
                        placeholder="ENTER PASSWORD TO CONFIRM DESTRUCTION"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-10 flex justify-end gap-4">
                    <SecondaryButton 
                        @click="closeModal"
                        class="bg-white/5 border-white/10 text-white text-[10px] font-black uppercase tracking-widest px-6 py-3 rounded-xl hover:bg-white/10"
                    >
                        Cancel
                    </SecondaryButton>

                    <DangerButton
                        class="bg-rose-500 hover:bg-rose-400 text-slate-950 font-black italic tracking-tighter uppercase px-8 py-3 rounded-xl shadow-lg shadow-rose-500/10"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Confirm Destruction
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
