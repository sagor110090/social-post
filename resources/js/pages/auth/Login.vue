<script setup lang="ts">
import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <AuthBase
        title="Log in to your account"
        description="Enter your email and password below to log in"
    >
        <Head title="Log in" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <Form
            v-bind="AuthenticatedSessionController.store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <!-- Email Field -->
            <div class="space-y-2">
                <Label
                    for="email"
                    class="text-body font-medium text-neutral-700 dark:text-neutral-300"
                    >Email address</Label
                >
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="Enter your email"
                    class="input-field"
                    :class="{ error: errors.email }"
                />
                <InputError :message="errors.email" />
            </div>

            <!-- Password Field -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <Label
                        for="password"
                        class="text-body font-medium text-neutral-700 dark:text-neutral-300"
                        >Password</Label
                    >
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-body text-brand-primary hover:text-brand-primary-dark transition-colors"
                        :tabindex="5"
                    >
                        Forgot password?
                    </TextLink>
                </div>
                <Input
                    id="password"
                    type="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    class="input-field"
                    :class="{ error: errors.password }"
                />
                <InputError :message="errors.password" />
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between">
                <Label
                    for="remember"
                    class="flex cursor-pointer items-center space-x-3"
                >
                    <Checkbox
                        id="remember"
                        name="remember"
                        :tabindex="3"
                        class="text-brand-primary focus:ring-brand-primary rounded border-neutral-300"
                    />
                    <span
                        class="text-body text-neutral-700 dark:text-neutral-300"
                        >Remember me</span
                    >
                </Label>
            </div>

            <!-- Submit Button -->
            <Button
                type="submit"
                class="btn-primary w-full py-3 text-base"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <LoaderCircle v-if="processing" class="h-5 w-5 animate-spin" />
                <span v-else>Sign In</span>
            </Button>

            <!-- Divider -->
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div
                        class="w-full border-t border-neutral-300 dark:border-neutral-600"
                    ></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span
                        class="bg-white px-4 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400"
                        >Or continue with</span
                    >
                </div>
            </div>

            <!-- Social Login Buttons -->
            <div class="grid grid-cols-2 gap-3">
                <Button
                    type="button"
                    variant="outline"
                    class="flex items-center justify-center gap-2 py-2"
                    :disabled="processing"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24">
                        <path
                            fill="currentColor"
                            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                        />
                        <path
                            fill="currentColor"
                            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                        />
                        <path
                            fill="currentColor"
                            d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                        />
                        <path
                            fill="currentColor"
                            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                        />
                    </svg>
                    <span class="text-body">Google</span>
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    class="flex items-center justify-center gap-2 py-2"
                    :disabled="processing"
                >
                    <svg
                        class="h-5 w-5"
                        fill="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"
                        />
                    </svg>
                    <span class="text-body">GitHub</span>
                </Button>
            </div>

            <!-- Sign Up Link -->
            <div
                class="border-t border-neutral-200 pt-4 text-center dark:border-neutral-700"
            >
                <p class="text-body text-neutral-600 dark:text-neutral-400">
                    Don't have an account?
                    <TextLink
                        :href="register()"
                        class="text-brand-primary hover:text-brand-primary-dark font-medium transition-colors"
                        :tabindex="5"
                    >
                        Sign up for free
                    </TextLink>
                </p>
            </div>
        </Form>
    </AuthBase>
</template>
