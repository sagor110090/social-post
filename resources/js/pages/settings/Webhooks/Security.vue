<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SecurityLog from '@/components/Webhooks/SecurityLog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface SecurityEvent {
    id: number;
    webhook_config_id: number;
    event_type:
        | 'signature_verification_failed'
        | 'rate_limit_exceeded'
        | 'ip_blocked'
        | 'unauthorized_access';
    ip_address: string;
    user_agent?: string;
    payload?: Record<string, any>;
    severity: 'low' | 'medium' | 'high' | 'critical';
    resolved: boolean;
    created_at: string;
    resolved_at?: string;
    webhook_config?: {
        social_account: {
            platform: string;
            platform_username: string;
        };
    };
}

interface SecuritySettings {
    ip_whitelist_enabled: boolean;
    ip_whitelist: string[];
    rate_limit_enabled: boolean;
    rate_limit_requests: number;
    rate_limit_window: number;
    signature_verification_enabled: boolean;
    webhook_timeout: number;
}

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Webhooks',
        href: '/settings/webhooks',
    },
    {
        title: 'Security',
        href: '/settings/webhooks/security',
    },
];

const securityEvents = ref<SecurityEvent[]>([]);
const securitySettings = ref<SecuritySettings>({
    ip_whitelist_enabled: false,
    ip_whitelist: [],
    rate_limit_enabled: true,
    rate_limit_requests: 100,
    rate_limit_window: 60,
    signature_verification_enabled: true,
    webhook_timeout: 30,
});

const loading = ref(true);
const error = ref<string | null>(null);
const saving = ref(false);
const showIpDialog = ref(false);
const newIpAddress = ref('');

const unresolvedEvents = computed(() => {
    return securityEvents.value.filter((event) => !event.resolved);
});

const criticalEvents = computed(() => {
    return securityEvents.value.filter(
        (event) => event.severity === 'critical' && !event.resolved,
    );
});

const highEvents = computed(() => {
    return securityEvents.value.filter(
        (event) => event.severity === 'high' && !event.resolved,
    );
});

const fetchSecurityEvents = async () => {
    try {
        const response = await fetch('/webhooks/manage/security/events');
        if (response.ok) {
            securityEvents.value = await response.json();
        }
    } catch (err) {
        console.error('Failed to fetch security events:', err);
    }
};

const fetchSecuritySettings = async () => {
    try {
        const response = await fetch('/webhooks/manage/security/settings');
        if (response.ok) {
            securitySettings.value = await response.json();
        }
    } catch (err) {
        console.error('Failed to fetch security settings:', err);
    }
};

const saveSecuritySettings = async () => {
    saving.value = true;
    error.value = null;

    try {
        const response = await fetch('/webhooks/manage/security/settings', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') || '',
            },
            body: JSON.stringify(securitySettings.value),
        });

        if (response.ok) {
            // Show success message
            alert('Security settings updated successfully');
        } else {
            const data = await response.json();
            error.value = data.error || 'Failed to update security settings';
        }
    } catch (err) {
        error.value = 'Failed to update security settings';
        console.error('Failed to save settings:', err);
    } finally {
        saving.value = false;
    }
};

const handleResolveEvent = async (eventId: number) => {
    try {
        const response = await fetch(
            `/webhooks/manage/security/events/${eventId}/resolve`,
            {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                },
            },
        );

        if (response.ok) {
            await fetchSecurityEvents();
        } else {
            error.value = 'Failed to resolve security event';
        }
    } catch (err) {
        error.value = 'Failed to resolve security event';
        console.error('Failed to resolve event:', err);
    }
};

const handleViewEventDetails = (event: SecurityEvent) => {
    // Show event details in a modal or expand section
    console.log('View event details:', event);
};

const addIpAddress = () => {
    if (newIpAddress.value.trim()) {
        if (
            !securitySettings.value.ip_whitelist.includes(
                newIpAddress.value.trim(),
            )
        ) {
            securitySettings.value.ip_whitelist.push(newIpAddress.value.trim());
            newIpAddress.value = '';
        }
    }
};

const removeIpAddress = (ip: string) => {
    const index = securitySettings.value.ip_whitelist.indexOf(ip);
    if (index > -1) {
        securitySettings.value.ip_whitelist.splice(index, 1);
    }
};

const getSeverityColor = (severity: string) => {
    switch (severity) {
        case 'critical':
            return 'text-red-600 bg-red-50';
        case 'high':
            return 'text-red-600 bg-red-50';
        case 'medium':
            return 'text-yellow-600 bg-yellow-50';
        case 'low':
            return 'text-blue-600 bg-blue-50';
        default:
            return 'text-gray-600 bg-gray-50';
    }
};

onMounted(async () => {
    await Promise.all([fetchSecurityEvents(), fetchSecuritySettings()]);
    loading.value = false;
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Webhook Security" />

        <SettingsLayout>
            <div class="space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">Webhook Security</h1>
                        <p class="text-muted-foreground">
                            Manage security settings and monitor security events
                        </p>
                    </div>
                </div>

                <!-- Error Alert -->
                <Alert v-if="error" class="border-red-200 bg-red-50">
                    <AlertDescription class="text-red-800">
                        {{ error }}
                    </AlertDescription>
                </Alert>

                <!-- Security Overview -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent class="pt-6">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-red-600">
                                    {{ criticalEvents.length }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    Critical Events
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent class="pt-6">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-yellow-600">
                                    {{ highEvents.length }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    High Priority
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent class="pt-6">
                            <div class="text-center">
                                <div class="text-2xl font-bold">
                                    {{ unresolvedEvents.length }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    Unresolved
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent class="pt-6">
                            <div class="text-center">
                                <div class="text-2xl font-bold">
                                    {{ securityEvents.length }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    Total Events
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Security Settings -->
                <Card>
                    <CardHeader>
                        <CardTitle>Security Settings</CardTitle>
                        <CardDescription
                            >Configure webhook security
                            parameters</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <!-- Signature Verification -->
                            <div class="space-y-3">
                                <div class="flex items-center space-x-2">
                                    <Checkbox
                                        id="signature_verification"
                                        v-model:checked="
                                            securitySettings.signature_verification_enabled
                                        "
                                    />
                                    <Label for="signature_verification"
                                        >Enable Signature Verification</Label
                                    >
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    Verify webhook signatures to ensure request
                                    authenticity
                                </p>
                            </div>

                            <!-- Rate Limiting -->
                            <div class="space-y-3">
                                <div class="flex items-center space-x-2">
                                    <Checkbox
                                        id="rate_limit"
                                        v-model:checked="
                                            securitySettings.rate_limit_enabled
                                        "
                                    />
                                    <Label for="rate_limit"
                                        >Enable Rate Limiting</Label
                                    >
                                </div>
                                <div
                                    v-if="securitySettings.rate_limit_enabled"
                                    class="grid grid-cols-2 gap-2"
                                >
                                    <div>
                                        <Label
                                            for="rate_limit_requests"
                                            class="text-sm"
                                            >Requests</Label
                                        >
                                        <Input
                                            id="rate_limit_requests"
                                            v-model.number="
                                                securitySettings.rate_limit_requests
                                            "
                                            type="number"
                                            min="1"
                                        />
                                    </div>
                                    <div>
                                        <Label
                                            for="rate_limit_window"
                                            class="text-sm"
                                            >Window (sec)</Label
                                        >
                                        <Input
                                            id="rate_limit_window"
                                            v-model.number="
                                                securitySettings.rate_limit_window
                                            "
                                            type="number"
                                            min="1"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- IP Whitelist -->
                            <div class="space-y-3">
                                <div class="flex items-center space-x-2">
                                    <Checkbox
                                        id="ip_whitelist"
                                        v-model:checked="
                                            securitySettings.ip_whitelist_enabled
                                        "
                                    />
                                    <Label for="ip_whitelist"
                                        >Enable IP Whitelist</Label
                                    >
                                </div>
                                <div
                                    v-if="securitySettings.ip_whitelist_enabled"
                                    class="space-y-2"
                                >
                                    <Dialog v-model:open="showIpDialog">
                                        <DialogTrigger asChild>
                                            <Button variant="outline" size="sm"
                                                >Add IP Address</Button
                                            >
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle
                                                    >Add IP Address</DialogTitle
                                                >
                                            </DialogHeader>
                                            <div class="space-y-4">
                                                <div>
                                                    <Label for="new_ip"
                                                        >IP Address</Label
                                                    >
                                                    <Input
                                                        id="new_ip"
                                                        v-model="newIpAddress"
                                                        placeholder="192.168.1.1"
                                                    />
                                                </div>
                                                <div class="flex gap-2">
                                                    <Button
                                                        @click="addIpAddress"
                                                        >Add</Button
                                                    >
                                                    <Button
                                                        variant="outline"
                                                        @click="
                                                            showIpDialog = false
                                                        "
                                                        >Cancel</Button
                                                    >
                                                </div>
                                            </div>
                                        </DialogContent>
                                    </Dialog>

                                    <div class="space-y-1">
                                        <div
                                            v-for="ip in securitySettings.ip_whitelist"
                                            :key="ip"
                                            class="flex items-center justify-between rounded bg-muted p-2"
                                        >
                                            <code class="text-sm">{{
                                                ip
                                            }}</code>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="removeIpAddress(ip)"
                                            >
                                                Remove
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Webhook Timeout -->
                            <div class="space-y-3">
                                <Label for="webhook_timeout"
                                    >Webhook Timeout (seconds)</Label
                                >
                                <Input
                                    id="webhook_timeout"
                                    v-model.number="
                                        securitySettings.webhook_timeout
                                    "
                                    type="number"
                                    min="1"
                                    max="300"
                                />
                                <p class="text-sm text-muted-foreground">
                                    Maximum time to wait for webhook response
                                </p>
                            </div>
                        </div>

                        <div class="pt-4">
                            <Button
                                @click="saveSecuritySettings"
                                :disabled="saving"
                            >
                                {{ saving ? 'Saving...' : 'Save Settings' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Security Events -->
                <Card>
                    <CardHeader>
                        <CardTitle>Security Events</CardTitle>
                        <CardDescription
                            >Recent security-related events and
                            violations</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading" class="py-8 text-center">
                            <div
                                class="mx-auto h-8 w-8 animate-spin rounded-full border-b-2 border-primary"
                            ></div>
                            <p class="mt-2 text-muted-foreground">
                                Loading security events...
                            </p>
                        </div>

                        <div
                            v-else-if="securityEvents.length > 0"
                            class="space-y-4"
                        >
                            <SecurityLog
                                v-for="event in securityEvents.slice(0, 10)"
                                :key="event.id"
                                :event="event"
                                @resolve="handleResolveEvent"
                                @view-details="handleViewEventDetails"
                            />

                            <div
                                v-if="securityEvents.length > 10"
                                class="text-center"
                            >
                                <Button variant="outline"
                                    >View All Events</Button
                                >
                            </div>
                        </div>

                        <div v-else class="py-8 text-center">
                            <h3 class="mb-2 text-lg font-medium">
                                No Security Events
                            </h3>
                            <p class="text-muted-foreground">
                                No security events have been recorded. Your
                                webhooks are operating securely.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Security Recommendations -->
                <Card>
                    <CardHeader>
                        <CardTitle>Security Recommendations</CardTitle>
                        <CardDescription
                            >Best practices for webhook
                            security</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div class="flex items-start space-x-3">
                                <div class="mt-1 text-green-600">✓</div>
                                <div>
                                    <h4 class="font-medium">Use HTTPS URLs</h4>
                                    <p class="text-sm text-muted-foreground">
                                        Always use HTTPS endpoints for webhook
                                        URLs to encrypt data in transit.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start space-x-3">
                                <div class="mt-1 text-green-600">✓</div>
                                <div>
                                    <h4 class="font-medium">
                                        Verify Signatures
                                    </h4>
                                    <p class="text-sm text-muted-foreground">
                                        Enable signature verification to ensure
                                        webhook requests are authentic.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start space-x-3">
                                <div class="mt-1 text-green-600">✓</div>
                                <div>
                                    <h4 class="font-medium">
                                        Implement Rate Limiting
                                    </h4>
                                    <p class="text-sm text-muted-foreground">
                                        Protect against abuse by implementing
                                        appropriate rate limits.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start space-x-3">
                                <div class="mt-1 text-green-600">✓</div>
                                <div>
                                    <h4 class="font-medium">
                                        Monitor Security Events
                                    </h4>
                                    <p class="text-sm text-muted-foreground">
                                        Regularly review security events and
                                        investigate suspicious activity.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
