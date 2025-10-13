<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
  ArcElement,
  BarElement,
  CategoryScale,
  Legend as ChartLegend,
  Tooltip as ChartTooltip,
  LinearScale,
  LineElement,
  PointElement,
  Title,
} from 'chart.js';
import Chart from 'chart.js/auto';
import {
  Calendar,
  Download,
  Eye,
  Filter,
  FileText,
  Heart,
  TrendingUp,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Bar, Line, Pie } from 'vue-chartjs';

Chart.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  ChartTooltip,
  ChartLegend,
  ArcElement,
);

interface AnalyticsData {
  total_posts: number;
  published_posts: number;
  scheduled_posts: number;
  total_engagement: number;
  total_reach: number;
  best_performing_post: any;
  platform_performance: Record<string, any>;
  engagement_over_time: any[];
  post_types_performance: Record<string, any>;
}

const props = defineProps<{
  analytics: AnalyticsData;
  date_range: {
    start_date: string;
    end_date: string;
  };
}>();

const loading = ref(false);
const showFilters = ref(false);
const dateRange = ref({
  start: props.date_range.start_date,
  end: props.date_range.end_date,
});

const engagementChartData = computed(() => ({
  labels: props.analytics.engagement_over_time.map((item) =>
    new Date(item.period).toLocaleDateString(),
  ),
  datasets: [
    {
      label: 'Engagement',
      data: props.analytics.engagement_over_time.map(
        (item) => item.engagement,
      ),
      borderColor: 'rgb(59, 130, 246)',
      backgroundColor: 'rgba(59, 130, 246, 0.1)',
      tension: 0.4,
    },
    {
      label: 'Posts',
      data: props.analytics.engagement_over_time.map(
        (item) => item.posts,
      ),
      borderColor: 'rgb(16, 185, 129)',
      backgroundColor: 'rgba(16, 185, 129, 0.1)',
      tension: 0.4,
    },
  ],
}));

const platformChartData = computed(() => ({
  labels: Object.keys(props.analytics.platform_performance),
  datasets: [
    {
      label: 'Engagement',
      data: Object.values(props.analytics.platform_performance).map(
        (p: any) => p.total_engagement,
      ),
      backgroundColor: [
        'rgba(59, 130, 246, 0.8)',
        'rgba(29, 161, 242, 0.8)',
        'rgba(228, 64, 95, 0.8)',
        'rgba(0, 119, 181, 0.8)',
      ],
    },
  ],
}));

const postTypeChartData = computed(() => ({
  labels: Object.keys(props.analytics.post_types_performance),
  datasets: [
    {
      label: 'Average Engagement',
      data: Object.values(props.analytics.post_types_performance).map(
        (p: any) => p.average_engagement,
      ),
      backgroundColor: 'rgba(147, 51, 234, 0.8)',
    },
  ],
}));

const pieChartData = computed(() => ({
  labels: Object.keys(props.analytics.platform_performance),
  datasets: [
    {
      data: Object.values(props.analytics.platform_performance).map(
        (p: any) => p.posts_count,
      ),
      backgroundColor: [
        'rgba(59, 130, 246, 0.8)',
        'rgba(29, 161, 242, 0.8)',
        'rgba(228, 64, 95, 0.8)',
        'rgba(0, 119, 181, 0.8)',
      ],
    },
  ],
}));

function applyFilters() {
  loading.value = true;
  router.get(
    route('analytics.dashboard'),
    {
      start_date: dateRange.value.start,
      end_date: dateRange.value.end,
    },
    {
      preserveState: true,
      onFinish: () => {
        loading.value = false;
      },
    },
  );
}

function exportData() {
  router.post(route('analytics.export'), {
    start_date: dateRange.value.start,
    end_date: dateRange.value.end,
    format: 'csv',
  });
}

function goToPost(postId: number) {
  router.visit(route('social.posts.show', postId));
}

const formatNumber = (num: number) => {
  if (num >= 1000000) {
    return (num / 1000000).toFixed(1) + 'M';
  } else if (num >= 1000) {
    return (num / 1000).toFixed(1) + 'K';
  }
  return num.toString();
};

const setQuickDate = (period: string) => {
  const end = new Date();
  const start = new Date();

  switch (period) {
    case '7d':
      start.setDate(end.getDate() - 7);
      break;
    case '30d':
      start.setDate(end.getDate() - 30);
      break;
    case '90d':
      start.setDate(end.getDate() - 90);
      break;
    case '1y':
      start.setFullYear(end.getFullYear() - 1);
      break;
  }

  dateRange.value.start = start.toISOString().split('T')[0];
  dateRange.value.end = end.toISOString().split('T')[0];
  applyFilters();
};
</script>

<template>

  <Head title="Analytics Dashboard" />

  <AppLayout>
    <div class="min-h-screen">
      <div class="p-6">
        <div class="mx-auto max-w-7xl">
          <!-- Header -->
          <div class="mb-12 animate-fade-in">
            <h1 class="text-display-1 mb-4 text-neutral-900 dark:text-white">
              Analytics <span class="text-gradient font-bold">Dashboard</span> 📊
            </h1>
            <p class="text-body-large max-w-3xl text-neutral-600 dark:text-neutral-400 leading-relaxed">
              Track your social media performance and engagement across all platforms
            </p>
            <div class="flex gap-4 mt-6">
              <Button @click="showFilters = !showFilters" variant="outline" class="hover-glow">
                <Filter class="mr-3 h-5 w-5" />
                {{ showFilters ? 'Hide' : 'Show' }} Filters
              </Button>
              <Button @click="exportData" variant="outline" class="hover-glow">
                <Download class="mr-3 h-5 w-5" />
                Export Data
              </Button>
            </div>
          </div>

          <!-- Enhanced Date Filters -->
          <div v-if="showFilters" class="card-elevated relative overflow-hidden animate-slide-up">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 to-purple-500"></div>
            <div class="mb-8">
              <h2 class="text-headline-1 mb-4 text-neutral-900 dark:text-white">
                Date Range
              </h2>
              <p class="text-body-large text-neutral-600 dark:text-neutral-400">
                Select the time period for your analytics data
              </p>
            </div>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
              <div>
                <label class="text-body-large mb-3 block font-semibold text-neutral-700 dark:text-neutral-300">Start
                  Date</label>
                <input v-model="dateRange.start" type="date" class="input-field h-12 text-base w-full" />
              </div>
              <div>
                <label class="text-body-large mb-3 block font-semibold text-neutral-700 dark:text-neutral-300">End
                  Date</label>
                <input v-model="dateRange.end" type="date" class="input-field h-12 text-base w-full" />
              </div>
              <div class="flex items-end">
                <Button @click="applyFilters" :disabled="loading"
                  class="btn-primary w-full py-4 text-base font-semibold hover-glow">
                  <Calendar class="mr-3 h-5 w-5" />
                  Apply Filters
                </Button>
              </div>
            </div>

            <!-- Quick Date Options -->
            <div class="mt-6 border-t border-neutral-200 pt-6 dark:border-neutral-700">
              <p class="text-body-small mb-3 text-neutral-600 dark:text-neutral-400">
                Quick select:
              </p>
              <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" @click="setQuickDate('7d')" class="text-xs">
                  Last 7 days
                </Button>
                <Button variant="outline" size="sm" @click="setQuickDate('30d')" class="text-xs">
                  Last 30 days
                </Button>
                <Button variant="outline" size="sm" @click="setQuickDate('90d')" class="text-xs">
                  Last 90 days
                </Button>
                <Button variant="outline" size="sm" @click="setQuickDate('1y')" class="text-xs">
                  Last year
                </Button>
              </div>
            </div>
          </div>

          <!-- Overview Cards -->
          <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
            <!-- Total Posts Card -->
            <div class="card-elevated relative overflow-hidden animate-slide-up">
              <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 to-purple-500"></div>
              <div class="mb-4 flex items-center justify-between">
                <h3 class="text-headline-4 text-neutral-900 dark:text-white">Total Posts</h3>
                <div
                  class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-purple-500 shadow-lg">
                  <FileText class="h-5 w-5 text-white" />
                </div>
              </div>
              <div>
                <div class="text-3xl font-bold text-neutral-900 dark:text-white">
                  {{ analytics.total_posts || 0 }}
                </div>
                <p class="text-body text-neutral-600 dark:text-neutral-400 mt-1">
                  {{ analytics.published_posts || 0 }} published, {{ analytics.scheduled_posts || 0 }} scheduled
                </p>
              </div>
            </div>

            <!-- Total Engagement Card -->
            <div class="card-elevated relative overflow-hidden animate-slide-up">
              <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-pink-500 to-rose-500"></div>
              <div class="mb-4 flex items-center justify-between">
                <h3 class="text-headline-4 text-neutral-900 dark:text-white">Total Engagement</h3>
                <div
                  class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-pink-500 to-rose-500 shadow-lg">
                  <Heart class="h-5 w-5 text-white" />
                </div>
              </div>
              <div>
                <div class="text-3xl font-bold text-neutral-900 dark:text-white">
                  {{ formatNumber(analytics.total_engagement || 0) }}
                </div>
                <p class="text-body text-neutral-600 dark:text-neutral-400 mt-1">
                  Likes, comments, and shares
                </p>
              </div>
            </div>

            <!-- Total Reach Card -->
            <div class="card-elevated relative overflow-hidden animate-slide-up">
              <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-purple-500 to-indigo-500"></div>
              <div class="mb-4 flex items-center justify-between">
                <h3 class="text-headline-4 text-neutral-900 dark:text-white">Total Reach</h3>
                <div
                  class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-purple-500 to-indigo-500 shadow-lg">
                  <Eye class="h-5 w-5 text-white" />
                </div>
              </div>
              <div>
                <div class="text-3xl font-bold text-neutral-900 dark:text-white">
                  {{ formatNumber(analytics.total_reach || 0) }}
                </div>
                <p class="text-body text-neutral-600 dark:text-neutral-400 mt-1">
                  Unique users reached
                </p>
              </div>
            </div>

            <!-- Engagement Rate Card -->
            <div class="card-elevated relative overflow-hidden animate-slide-up">
              <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-green-500 to-emerald-500"></div>
              <div class="mb-4 flex items-center justify-between">
                <h3 class="text-headline-4 text-neutral-900 dark:text-white">Engagement Rate</h3>
                <div
                  class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-green-500 to-emerald-500 shadow-lg">
                  <TrendingUp class="h-5 w-5 text-white" />
                </div>
              </div>
              <div>
                <div class="text-3xl font-bold text-neutral-900 dark:text-white">
                  {{
                    analytics.total_reach > 0
                      ? ((analytics.total_engagement / analytics.total_reach) * 100).toFixed(2)
                      : '0'
                  }}%
                </div>
                <p class="text-body text-neutral-600 dark:text-neutral-400 mt-1">
                  Average engagement rate
                </p>
              </div>
            </div>
          </div>

          <!-- Best Performing Post -->
          <div v-if="analytics.best_performing_post"
            class="card-elevated relative overflow-hidden mb-12 animate-slide-up group hover:scale-[1.01] transition-all duration-300">
            <div
              class="absolute top-0 left-0 w-full h-1 opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-gradient-to-r from-amber-500 to-orange-500">
            </div>
            <div class="mb-6">
              <h2 class="text-headline-1 mb-3 text-neutral-900 dark:text-white">Best Performing Post</h2>
              <p class="text-body-large text-neutral-600 dark:text-neutral-400">
                Your top performing post in the selected period
              </p>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <p class="mb-3 text-body-large text-neutral-900 dark:text-white">
                  {{ analytics.best_performing_post.content }}
                </p>
                <div class="flex items-center gap-4 text-body text-neutral-600 dark:text-neutral-400">
                  <span class="flex items-center gap-1">
                    <Badge variant="secondary"
                      class="bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200">
                      {{ analytics.best_performing_post.platform }}
                    </Badge>
                  </span>
                  <span>{{ new Date(analytics.best_performing_post.created_at).toLocaleDateString() }}</span>
                  <span class="flex items-center gap-1">
                    <Heart class="h-4 w-4" />
                    {{ formatNumber(analytics.best_performing_post.total_engagement) }} engagement
                  </span>
                </div>
              </div>
              <Button @click="goToPost(analytics.best_performing_post.id)" variant="outline" class="hover-glow">
                View Post
              </Button>
            </div>
          </div>

          <!-- Charts -->
          <div class="grid gap-8 lg:grid-cols-2 mt-12">
            <!-- Engagement Over Time -->
            <div
              class="card-elevated relative overflow-hidden animate-slide-up group hover:scale-[1.01] transition-all duration-300">
              <div
                class="absolute top-0 left-0 w-full h-1 opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-gradient-to-r from-blue-500 to-purple-500">
              </div>
              <div class="mb-6">
                <h2 class="text-headline-1 mb-3 text-neutral-900 dark:text-white">Engagement Over Time</h2>
                <p class="text-body-large text-neutral-600 dark:text-neutral-400">
                  Track your engagement and post volume
                </p>
              </div>
              <div style="width: 100%; height: 300px">
                <Line :data="engagementChartData" />
              </div>
            </div>

            <!-- Platform Performance -->
            <div
              class="card-elevated relative overflow-hidden animate-slide-up group hover:scale-[1.01] transition-all duration-300">
              <div
                class="absolute top-0 left-0 w-full h-1 opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-gradient-to-r from-green-500 to-teal-500">
              </div>
              <div class="mb-6">
                <h2 class="text-headline-1 mb-3 text-neutral-900 dark:text-white">Platform Performance</h2>
                <p class="text-body-large text-neutral-600 dark:text-neutral-400">
                  Compare performance across platforms
                </p>
              </div>
              <div style="width: 100%; height: 300px">
                <Bar :data="platformChartData" />
              </div>
            </div>

            <!-- Post Types Performance -->
            <div
              class="card-elevated relative overflow-hidden animate-slide-up group hover:scale-[1.01] transition-all duration-300">
              <div
                class="absolute top-0 left-0 w-full h-1 opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-gradient-to-r from-purple-500 to-pink-500">
              </div>
              <div class="mb-6">
                <h2 class="text-headline-1 mb-3 text-neutral-900 dark:text-white">Post Types Performance</h2>
                <p class="text-body-large text-neutral-600 dark:text-neutral-400">
                  Average engagement by post type
                </p>
              </div>
              <div style="width: 100%; height: 300px">
                <Bar :data="postTypeChartData" />
              </div>
            </div>

            <!-- Posts Distribution -->
            <div
              class="card-elevated relative overflow-hidden animate-slide-up group hover:scale-[1.01] transition-all duration-300">
              <div
                class="absolute top-0 left-0 w-full h-1 opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-gradient-to-r from-amber-500 to-orange-500">
              </div>
              <div class="mb-6">
                <h2 class="text-headline-1 mb-3 text-neutral-900 dark:text-white">Posts Distribution</h2>
                <p class="text-body-large text-neutral-600 dark:text-neutral-400">
                  Posts distribution across platforms
                </p>
              </div>
              <div style="width: 100%; height: 300px">
                <Pie :data="pieChartData" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
