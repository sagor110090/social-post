<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { loadStripe } from '@stripe/stripe-js';
import { Check, CreditCard, Star, TrendingUp, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Plan {
    name: string;
    price_id: string;
    amount: number;
    interval: string;
    features: string[];
    popular?: boolean;
}

interface Subscription {
    id: string;
    stripe_status: string;
    stripe_price: string;
    trial_ends_at?: string;
    ends_at?: string;
}

const page = usePage();
const props = defineProps<{
    subscription: Subscription | null;
    plans: Plan[];
    intent: any;
}>();

const stripe = ref<any>(null);
const processing = ref(false);
const selectedPlan = ref<string | null>(null);
const error = ref<string | null>(null);

const currentPlan = computed(() => {
    if (!props.subscription) return null;
    return props.plans.find(
        (plan) => plan.price_id === props.subscription?.stripe_price,
    );
});

const isSubscribed = computed(() => {
    return props.subscription && props.subscription.stripe_status === 'active';
});

const isOnTrial = computed(() => {
    return (
        props.subscription?.trial_ends_at &&
        new Date(props.subscription.trial_ends_at) > new Date()
    );
});

const isCancelled = computed(() => {
    return (
        props.subscription?.ends_at &&
        new Date(props.subscription.ends_at) > new Date()
    );
});

const initializeStripe = async () => {
    if (!stripe.value) {
        stripe.value = await loadStripe(import.meta.env.VITE_STRIPE_KEY);
    }
};

const subscribe = async (priceId: string) => {
    if (!stripe.value) {
        await initializeStripe();
    }

    processing.value = true;
    selectedPlan.value = priceId;
    error.value = null;

    try {
        const { error: confirmError } = await stripe.value.confirmCardSetup(
            props.intent.client_secret,
            {
                payment_method: {
                    card: {
                        token: 'tok_visa', // In production, you'd use Elements
                    },
                },
            },
        );

        if (confirmError) {
            throw new Error(confirmError.message);
        }

        router.post(
            route('billing.subscribe'),
            {
                price_id: priceId,
                payment_method_id: 'pm_card_visa', // In production, use the actual payment method
            },
            {
                onSuccess: () => {
                    processing.value = false;
                    selectedPlan.value = null;
                },
                onError: (errors) => {
                    error.value =
                        errors.error || 'Failed to create subscription';
                    processing.value = false;
                    selectedPlan.value = null;
                },
            },
        );
    } catch (err: any) {
        error.value = err.message || 'Failed to process payment';
        processing.value = false;
        selectedPlan.value = null;
    }
};

const swapPlan = (priceId: string) => {
    processing.value = true;
    selectedPlan.value = priceId;

    router.patch(
        route('billing.swap'),
        {
            price_id: priceId,
        },
        {
            onSuccess: () => {
                processing.value = false;
                selectedPlan.value = null;
            },
            onError: (errors) => {
                error.value = errors.error || 'Failed to update subscription';
                processing.value = false;
                selectedPlan.value = null;
            },
        },
    );
};

const cancelSubscription = (immediately = false) => {
    if (!confirm('Are you sure you want to cancel your subscription?')) {
        return;
    }

    router.delete(
        route('billing.cancel'),
        {
            immediately,
        },
        {
            onSuccess: () => {
                // Success message will be shown via flash session
            },
            onError: (errors) => {
                error.value = errors.error || 'Failed to cancel subscription';
            },
        },
    );
};

const resumeSubscription = () => {
    router.post(
        route('billing.resume'),
        {},
        {
            onSuccess: () => {
                // Success message will be shown via flash session
            },
            onError: (errors) => {
                error.value = errors.error || 'Failed to resume subscription';
            },
        },
    );
};

const formatPrice = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
};
</script>

<template>
    <Head title="Subscription" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col space-y-6 p-6">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Subscription</h1>
                <p class="text-muted-foreground">
                    Manage your subscription and billing
                </p>
            </div>

            <div
                v-if="page.props.flash.error"
                class="rounded-md bg-destructive/15 p-3"
            >
                <p class="text-sm text-destructive">
                    {{ page.props.flash.error }}
                </p>
            </div>

            <div
                v-if="page.props.flash.success"
                class="rounded-md bg-green-50 p-3"
            >
                <p class="text-sm text-green-800">
                    {{ page.props.flash.success }}
                </p>
            </div>

            <div v-if="error" class="rounded-md bg-destructive/15 p-3">
                <p class="text-sm text-destructive">{{ error }}</p>
            </div>

            <!-- Current Subscription Status -->
            <Card v-if="subscription">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CreditCard class="h-5 w-5" />
                        Current Subscription
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold">
                                {{ currentPlan?.name || 'Unknown Plan' }}
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                {{ formatPrice(currentPlan?.amount || 0) }}/{{
                                    currentPlan?.interval
                                }}
                            </p>
                            <div class="mt-2 flex items-center gap-2">
                                <Badge
                                    :variant="
                                        subscription.stripe_status === 'active'
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{ subscription.stripe_status }}
                                </Badge>
                                <Badge v-if="isOnTrial" variant="outline">
                                    Trial ends
                                    {{
                                        new Date(
                                            subscription.trial_ends_at!,
                                        ).toLocaleDateString()
                                    }}
                                </Badge>
                                <Badge v-if="isCancelled" variant="destructive">
                                    Cancels
                                    {{
                                        new Date(
                                            subscription.ends_at!,
                                        ).toLocaleDateString()
                                    }}
                                </Badge>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                v-if="isCancelled"
                                @click="resumeSubscription"
                                variant="outline"
                            >
                                Resume
                            </Button>
                            <Button
                                @click="cancelSubscription"
                                variant="destructive"
                                size="sm"
                            >
                                Cancel
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Pricing Plans -->
            <div>
                <h2 class="mb-4 text-2xl font-semibold">Choose Your Plan</h2>
                <div class="grid gap-6 md:grid-cols-3">
                    <Card
                        v-for="plan in plans"
                        :key="plan.price_id"
                        :class="{ 'ring-2 ring-primary': plan.popular }"
                    >
                        <CardHeader>
                            <CardTitle
                                class="flex items-center justify-between"
                            >
                                {{ plan.name }}
                                <Star
                                    v-if="plan.popular"
                                    class="h-5 w-5 text-primary"
                                />
                            </CardTitle>
                            <CardDescription>
                                <div class="mt-2">
                                    <span class="text-3xl font-bold">{{
                                        formatPrice(plan.amount)
                                    }}</span>
                                    <span class="text-muted-foreground"
                                        >/{{ plan.interval }}</span
                                    >
                                </div>
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul class="space-y-2">
                                <li
                                    v-for="feature in plan.features"
                                    :key="feature"
                                    class="flex items-center gap-2"
                                >
                                    <Check class="h-4 w-4 text-green-500" />
                                    <span class="text-sm">{{ feature }}</span>
                                </li>
                            </ul>
                        </CardContent>
                        <CardFooter>
                            <Button
                                v-if="!isSubscribed"
                                @click="subscribe(plan.price_id)"
                                :disabled="
                                    processing && selectedPlan === plan.price_id
                                "
                                class="w-full"
                                :variant="plan.popular ? 'default' : 'outline'"
                            >
                                <span
                                    v-if="
                                        processing &&
                                        selectedPlan === plan.price_id
                                    "
                                    class="mr-2 h-4 w-4 animate-spin rounded-full border-b-2 border-white"
                                ></span>
                                Subscribe
                            </Button>
                            <Button
                                v-else-if="
                                    currentPlan?.price_id !== plan.price_id
                                "
                                @click="swapPlan(plan.price_id)"
                                :disabled="
                                    processing && selectedPlan === plan.price_id
                                "
                                class="w-full"
                                variant="outline"
                            >
                                <span
                                    v-if="
                                        processing &&
                                        selectedPlan === plan.price_id
                                    "
                                    class="mr-2 h-4 w-4 animate-spin rounded-full border-b-2 border-primary"
                                ></span>
                                {{
                                    plan.amount > (currentPlan?.amount || 0)
                                        ? 'Upgrade'
                                        : 'Downgrade'
                                }}
                            </Button>
                            <Button v-else disabled class="w-full">
                                <Check class="mr-2 h-4 w-4" />
                                Current Plan
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>

            <!-- Features Comparison -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <TrendingUp class="h-5 w-5" />
                        Feature Comparison
                    </CardTitle>
                    <CardDescription>
                        Compare features across all plans
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b">
                                    <th class="p-2 text-left">Feature</th>
                                    <th
                                        v-for="plan in plans"
                                        :key="plan.price_id"
                                        class="p-2 text-center"
                                    >
                                        {{ plan.name }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b">
                                    <td class="p-2">Monthly Posts</td>
                                    <td
                                        v-for="plan in plans"
                                        :key="plan.price_id"
                                        class="p-2 text-center"
                                    >
                                        {{
                                            plan.features
                                                .find((f) =>
                                                    f.includes('posts'),
                                                )
                                                ?.split(' ')[0] || 'Limited'
                                        }}
                                    </td>
                                </tr>
                                <tr class="border-b">
                                    <td class="p-2">Social Accounts</td>
                                    <td
                                        v-for="plan in plans"
                                        :key="plan.price_id"
                                        class="p-2 text-center"
                                    >
                                        {{
                                            plan.features
                                                .find((f) =>
                                                    f.includes('social'),
                                                )
                                                ?.split(' ')[0] || 'Limited'
                                        }}
                                    </td>
                                </tr>
                                <tr class="border-b">
                                    <td class="p-2">AI Generations</td>
                                    <td
                                        v-for="plan in plans"
                                        :key="plan.price_id"
                                        class="p-2 text-center"
                                    >
                                        {{
                                            plan.features
                                                .find((f) => f.includes('AI'))
                                                ?.split(' ')[0] || 'Limited'
                                        }}
                                    </td>
                                </tr>
                                <tr class="border-b">
                                    <td class="p-2">Analytics</td>
                                    <td
                                        v-for="plan in plans"
                                        :key="plan.price_id"
                                        class="p-2 text-center"
                                    >
                                        <Check
                                            v-if="
                                                plan.features.some((f) =>
                                                    f.includes('analytics'),
                                                )
                                            "
                                            class="mx-auto h-4 w-4 text-green-500"
                                        />
                                        <X
                                            v-else
                                            class="mx-auto h-4 w-4 text-red-500"
                                        />
                                    </td>
                                </tr>
                                <tr>
                                    <td class="p-2">Team Collaboration</td>
                                    <td
                                        v-for="plan in plans"
                                        :key="plan.price_id"
                                        class="p-2 text-center"
                                    >
                                        <Check
                                            v-if="
                                                plan.features.some((f) =>
                                                    f.includes('Team'),
                                                )
                                            "
                                            class="mx-auto h-4 w-4 text-green-500"
                                        />
                                        <X
                                            v-else
                                            class="mx-auto h-4 w-4 text-red-500"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
