<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <span class="text-2xl font-bold tracking-tight text-white uppercase mt-1">Profile Settings</span>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-8 sm:px-6 lg:px-8">
                <!-- Profile Information -->
                <div class="bg-[#0A0A0B]/60 backdrop-blur-3xl p-8 rounded-[2rem] border border-white/5 shadow-2xl relative overflow-hidden group">
                    <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-[#CBB48A]/5 blur-[100px] group-hover:bg-[#CBB48A]/10 transition-all duration-700"></div>
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl relative z-10"
                    />
                </div>

                <!-- Update Password -->
                <div class="bg-[#0A0A0B]/60 backdrop-blur-3xl p-8 rounded-[2rem] border border-white/5 shadow-2xl relative overflow-hidden group">
                    <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-[#F3E7C9]/5 blur-[100px] group-hover:bg-[#F3E7C9]/10 transition-all duration-700"></div>
                    <UpdatePasswordForm class="max-w-xl relative z-10" />
                </div>

                <!-- Delete Account -->
                <div class="bg-[#0A0A0B]/60 backdrop-blur-3xl p-8 rounded-[2rem] border border-white/5 shadow-2xl relative overflow-hidden group border-rose-500/10">
                    <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-rose-500/5 blur-[100px] group-hover:bg-rose-500/10 transition-all duration-700"></div>
                    <DeleteUserForm class="max-w-xl relative z-10" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
