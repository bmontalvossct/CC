<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, UserPlus } from 'lucide-vue-next';

const form = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="Create your account" description="Start managing your classes with ClassCheck in minutes">
        <Head title="Register" />

        <form @submit.prevent="submit" class="flex flex-col gap-4">
            <div class="grid gap-3.5">
                <div class="grid gap-1.5">
                    <Label for="name" class="text-xs font-semibold">Full name</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        v-model="form.name"
                        placeholder="Prof. Jane Doe"
                        class="h-10 rounded-xl text-sm"
                    />
                    <InputError class="mt-1 text-xs" :message="form.errors.name" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="username" class="text-xs font-semibold">Username</Label>
                    <Input
                        id="username"
                        type="text"
                        required
                        :tabindex="2"
                        autocomplete="username"
                        v-model="form.username"
                        placeholder="janedoe"
                        class="h-10 rounded-xl text-sm"
                    />
                    <InputError class="mt-1 text-xs" :message="form.errors.username" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="email" class="text-xs font-semibold">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="3"
                        autocomplete="email"
                        v-model="form.email"
                        placeholder="jane@school.edu"
                        class="h-10 rounded-xl text-sm"
                    />
                    <InputError class="mt-1 text-xs" :message="form.errors.email" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="password" class="text-xs font-semibold">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        v-model="form.password"
                        placeholder="••••••••"
                        class="h-10 rounded-xl text-sm"
                    />
                    <InputError class="mt-1 text-xs" :message="form.errors.password" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="password_confirmation" class="text-xs font-semibold">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="5"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        placeholder="••••••••"
                        class="h-10 rounded-xl text-sm"
                    />
                    <InputError class="mt-1 text-xs" :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="ink-button mt-2 !h-10 !w-full !rounded-xl" :tabindex="6" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    <UserPlus v-else class="size-4" />
                    <span>Create account</span>
                </Button>
            </div>

            <div class="mt-2 text-center text-xs text-muted-foreground">
                Already have an account?
                <TextLink :href="route('login')" class="font-bold text-primary hover:underline" :tabindex="7">Log in</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
