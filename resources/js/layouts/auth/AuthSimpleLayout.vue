<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-screen">
        <!-- Left Side - Branding & Visual -->
        <div
            class="gradient-brand relative hidden overflow-hidden lg:flex lg:w-1/2"
        >
            <div class="absolute inset-0 bg-black/20"></div>
            <div
                class="relative z-10 flex flex-col justify-center px-12 text-white"
            >
                <div class="mb-8">
                    <Link :href="home()" class="inline-flex items-center gap-3">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm"
                        >
                            <AppLogoIcon
                                class="h-8 w-8 fill-current text-white"
                            />
                        </div>
                        <span class="text-2xl font-bold">AutoPost AI</span>
                    </Link>
                </div>

                <div class="space-y-6">
                    <h1 class="text-4xl leading-tight font-bold">
                        Transform Your Social Media Presence with AI
                    </h1>
                    <p class="max-w-md text-lg text-white/90">
                        Create, schedule, and analyze your social media content
                        with the power of artificial intelligence. Save time and
                        grow your audience.
                    </p>

                    <div class="space-y-4 pt-8">
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-lg bg-white/20 backdrop-blur-sm"
                            >
                                <svg
                                    class="h-6 w-6"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold">
                                    AI-Powered Content
                                </h3>
                                <p class="text-sm text-white/80">
                                    Generate engaging posts in seconds
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-lg bg-white/20 backdrop-blur-sm"
                            >
                                <svg
                                    class="h-6 w-6"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold">Smart Scheduling</h3>
                                <p class="text-sm text-white/80">
                                    Post at optimal times automatically
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-lg bg-white/20 backdrop-blur-sm"
                            >
                                <svg
                                    class="h-6 w-6"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold">
                                    Advanced Analytics
                                </h3>
                                <p class="text-sm text-white/80">
                                    Track performance and grow engagement
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Decorative elements -->
                <div
                    class="absolute top-20 right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl"
                ></div>
                <div
                    class="absolute bottom-20 left-10 h-40 w-40 rounded-full bg-white/10 blur-3xl"
                ></div>
            </div>
        </div>

        <!-- Right Side - Auth Form -->
        <div
            class="flex flex-1 items-center justify-center bg-neutral-50 px-6 py-12 dark:bg-neutral-900"
        >
            <div class="w-full max-w-md">
                <!-- Mobile Logo -->
                <div class="mb-8 flex flex-col items-center lg:hidden">
                    <Link
                        :href="home()"
                        class="mb-6 inline-flex items-center gap-3"
                    >
                        <div
                            class="bg-brand-primary flex h-10 w-10 items-center justify-center rounded-xl"
                        >
                            <AppLogoIcon
                                class="h-6 w-6 fill-current text-white"
                            />
                        </div>
                        <span
                            class="text-xl font-bold text-neutral-900 dark:text-white"
                            >AutoPost AI</span
                        >
                    </Link>
                </div>

                <!-- Auth Card -->
                <div
                    class="rounded-xl border bg-card text-card-foreground shadow-sm dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 px-8 py-12"
                >
                    <div class="mb-8 text-center">
                        <h2
                            class="text-headline-2 mb-3 text-neutral-900 dark:text-white"
                        >
                            {{ title }}
                        </h2>
                        <p
                            class="text-body text-neutral-600 dark:text-neutral-400"
                        >
                            {{ description }}
                        </p>
                    </div>

                    <slot />
                </div>

                <!-- Footer Links -->
                <div class="mt-8 text-center">
                    <p
                        class="text-body-small text-neutral-500 dark:text-neutral-500"
                    >
                        &copy; 2024 AutoPost AI. All rights reserved.
                    </p>
                    <div class="mt-4 flex justify-center gap-6">
                        <Link
                            href="/privacy"
                            class="text-body-small hover:text-brand-primary text-neutral-600 transition-colors dark:text-neutral-400"
                        >
                            Privacy Policy
                        </Link>
                        <Link
                            href="/terms"
                            class="text-body-small hover:text-brand-primary text-neutral-600 transition-colors dark:text-neutral-400"
                        >
                            Terms of Service
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
