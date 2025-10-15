<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { computed, onMounted, ref, watch } from 'vue';

interface MetricData {
    date: string;
    delivered: number;
    failed: number;
    pending: number;
    total: number;
}

interface Props {
    data: MetricData[];
    title?: string;
    description?: string;
    height?: number;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Webhook Metrics',
    description: 'Delivery metrics over time',
    height: 300,
});

const chartType = ref<'line' | 'bar'>('line');
const timeRange = ref<'7d' | '30d' | '90d'>('30d');
const canvasRef = ref<HTMLCanvasElement>();
const chartInstance = ref<any>(null);

// Load Chart.js dynamically
const loadChart = async () => {
    if (typeof window !== 'undefined') {
        const { default: Chart } = await import('chart.js/auto');
        return Chart;
    }
    return null;
};

const initChart = async () => {
    if (!canvasRef.value || !props.data.length) return;

    const Chart = await loadChart();
    if (!Chart) return;

    // Destroy existing chart if it exists
    if (chartInstance.value) {
        chartInstance.value.destroy();
    }

    const ctx = canvasRef.value.getContext('2d');
    if (!ctx) return;

    const labels = props.data.map((item) => {
        const date = new Date(item.date);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
        });
    });

    const datasets = [
        {
            label: 'Delivered',
            data: props.data.map((item) => item.delivered),
            borderColor: 'rgb(34, 197, 94)',
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            tension: 0.4,
        },
        {
            label: 'Failed',
            data: props.data.map((item) => item.failed),
            borderColor: 'rgb(239, 68, 68)',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            tension: 0.4,
        },
        {
            label: 'Pending',
            data: props.data.map((item) => item.pending),
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
        },
    ];

    chartInstance.value = new Chart(ctx, {
        type: chartType.value,
        data: {
            labels,
            datasets,
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                    },
                },
            },
        },
    });
};

const updateChartType = () => {
    if (chartInstance.value) {
        chartInstance.value.config.type = chartType.value;
        chartInstance.value.update();
    }
};

const exportData = () => {
    const csvContent = [
        ['Date', 'Delivered', 'Failed', 'Pending', 'Total'],
        ...props.data.map((item) => [
            item.date,
            item.delivered,
            item.failed,
            item.pending,
            item.total,
        ]),
    ]
        .map((row) => row.join(','))
        .join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `webhook-metrics-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

onMounted(() => {
    initChart();
});

watch(
    () => props.data,
    () => {
        initChart();
    },
    { deep: true },
);

watch(chartType, () => {
    updateChartType();
});

const totalEvents = computed(() => {
    return props.data.reduce((sum, item) => sum + item.total, 0);
});

const successRate = computed(() => {
    const totalDelivered = props.data.reduce(
        (sum, item) => sum + item.delivered,
        0,
    );
    const totalFailed = props.data.reduce((sum, item) => sum + item.failed, 0);
    const total = totalDelivered + totalFailed;
    return total > 0 ? ((totalDelivered / total) * 100).toFixed(1) : '0';
});
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle>{{ title }}</CardTitle>
                    <CardDescription>{{ description }}</CardDescription>
                </div>
                <div class="flex items-center gap-2">
                    <Select v-model="chartType">
                        <SelectTrigger class="w-32">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="line">Line</SelectItem>
                            <SelectItem value="bar">Bar</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button variant="outline" size="sm" @click="exportData">
                        Export
                    </Button>
                </div>
            </div>
        </CardHeader>
        <CardContent>
            <div class="mb-6 grid grid-cols-3 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">
                        {{ totalEvents }}
                    </div>
                    <div class="text-sm text-muted-foreground">
                        Total Events
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">
                        {{ successRate }}%
                    </div>
                    <div class="text-sm text-muted-foreground">
                        Success Rate
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">
                        {{ data.length }}
                    </div>
                    <div class="text-sm text-muted-foreground">
                        Days Tracked
                    </div>
                </div>
            </div>

            <div :style="{ height: `${height}px` }">
                <canvas ref="canvasRef"></canvas>
            </div>
        </CardContent>
    </Card>
</template>
