<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, LogIn } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    errors?: Record<string, string>;
}>();

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Welcome back" description="Log in to access your sections and classrooms">
        <Head title="Log in" />

        <div v-if="status" class="mb-4 rounded-xl border border-primary/20 bg-primary/10 p-3 text-center text-xs font-semibold text-primary">
            {{ status }}
        </div>

        <a
            href="/auth/google/redirect"
            class="mb-4 flex min-h-11 w-full items-center justify-center gap-3 rounded-xl border border-border/80 bg-card px-5 text-sm font-semibold text-foreground transition-all hover:bg-secondary hover:border-border focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary shadow-xs"
        >
            <svg class="size-5" viewBox="0 0 24 24" aria-hidden="true">
                <path
                    fill="#4285F4"
                    d="M21.6 12.23c0-.71-.06-1.4-.18-2.07H12v3.92h5.38a4.6 4.6 0 0 1-2 3.02v2.54h3.24c1.9-1.75 2.98-4.33 2.98-7.41Z"
                />
                <path
                    fill="#34A853"
                    d="M12 22c2.7 0 4.98-.9 6.63-2.43l-3.24-2.54c-.9.6-2.05.96-3.39.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.62A10 10 0 0 0 12 22Z"
                />
                <path
                    fill="#FBBC05"
                    d="M6.39 13.86A6.02 6.02 0 0 1 6.07 12c0-.65.11-1.28.32-1.86V7.52H3.04A10 10 0 0 0 2 12c0 1.61.38 3.14 1.04 4.48l3.35-2.62Z"
                />
                <path
                    fill="#EA4335"
                    d="M12 6.01c1.47 0 2.79.5 3.83 1.5l2.87-2.88A9.63 9.63 0 0 0 12 2a10 10 0 0 0-8.96 5.52l3.35 2.62C7.18 7.77 9.39 6.01 12 6.01Z"
                />
            </svg>
            Continue with Google
        </a>

        <InputError :message="errors?.google" class="text-center text-xs" />

        <div class="my-4 flex items-center gap-3 text-[11px] font-bold uppercase tracking-[0.14em] text-muted-foreground">
            <span class="h-px flex-1 bg-border"></span>
            <span>or email</span>
            <span class="h-px flex-1 bg-border"></span>
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-4">
            <div class="grid gap-4">
                <div class="grid gap-1.5">
                    <Label for="login" class="text-xs font-semibold">Username or email</Label>
                    <Input
                        id="login"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="username"
                        v-model="form.login"
                        placeholder="teacher@school.edu"
                        class="rounded-xl h-10 text-sm"
                    />
                    <InputError class="text-xs mt-1" :message="form.errors.login" />
                </div>

                <div class="grid gap-1.5">
                    <div class="flex items-center justify-between">
                        <Label for="password" class="text-xs font-semibold">Password</Label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-xs text-primary hover:underline" :tabindex="5">
                            Forgot password?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        v-model="form.password"
                        placeholder="••••••••"
                        class="rounded-xl h-10 text-sm"
                    />
                    <InputError class="text-xs mt-1" :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between" :tabindex="3">
                    <Label for="remember" class="flex items-center space-x-2 text-xs font-medium cursor-pointer">
                        <Checkbox id="remember" v-model:checked="form.remember" :tabindex="4" class="rounded-md" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button type="submit" class="ink-button !h-10 !w-full !rounded-xl mt-2" :tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    <LogIn v-else class="size-4" />
                    <span>Log in</span>
                </Button>
            </div>

            <div class="text-center text-xs text-muted-foreground mt-2">
                Don't have an account?
                <TextLink :href="route('register')" class="font-bold text-primary hover:underline" :tabindex="5">Sign up</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
