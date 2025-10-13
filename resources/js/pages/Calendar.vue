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
import calendarRoutes from '@/routes/calendar';
import posts from '@/routes/social/posts';
import scheduledPosts from '@/routes/social/scheduled-posts';
import type {
  CalendarOptions,
  DateSelectArg,
  EventClickArg,
} from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';
import FullCalendar from '@fullcalendar/vue3';
import { Head, router } from '@inertiajs/vue3';
import { Calendar, Clock, TrendingUp, Users } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

interface CalendarEvent {
  id: string;
  title: string;
  start: string;
  end?: string;
  backgroundColor?: string;
  borderColor?: string;
  extendedProps: {
    post_id?: number;
    platform?: string;
    status?: string;
  };
}

const calendarRef = ref<InstanceType<typeof FullCalendar>>();
const events = ref<CalendarEvent[]>([]);
const loading = ref(false);
const selectedEvent = ref<CalendarEvent | null>(null);

const calendarOptions: CalendarOptions = {
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,timeGridDay',
  },
  editable: true,
  selectable: true,
  selectMirror: true,
  dayMaxEvents: true,
  weekends: true,
  events: events.value,
  select: handleDateSelect,
  eventClick: handleEventClick,
  eventDrop: handleEventDrop,
  eventResize: handleEventResize,
  eventDidMount: handleEventMount,
};

function handleEventMount(info: any) {
  const event = info.event;
  const platform = event.extendedProps.platform;
  const status = event.extendedProps.status;

  if (platform) {
    const colors = {
      facebook: '#1877f2',
      twitter: '#1da1f2',
      instagram: '#e4405f',
      linkedin: '#0077b5',
    };

    event.setProp(
      'backgroundColor',
      colors[platform as keyof typeof colors] || '#6b7280',
    );
    event.setProp(
      'borderColor',
      colors[platform as keyof typeof colors] || '#6b7280',
    );
  }

  if (status === 'published') {
    event.setProp('opacity', 0.6);
  }
}

function handleDateSelect(selectInfo: DateSelectArg) {
  const title = prompt('Please enter a new title for your post');
  if (title) {
    const calendarApi = selectInfo.view.calendar;
    calendarApi.unselect();

    router.post(
      route('scheduled-posts.store'),
      {
        content: title,
        scheduled_for: selectInfo.startStr,
        scheduled_until: selectInfo.endStr,
      },
      {
        onSuccess: () => {
          fetchEvents();
        },
      },
    );
  }
}

function handleEventClick(clickInfo: EventClickArg) {
  const event = clickInfo.event;
  const postId = event.extendedProps.post_id;

  if (postId) {
    router.visit(route('scheduled-posts.edit', postId));
  }
}

function handleEventDrop(dropInfo: any) {
  const event = dropInfo.event;
  const postId = event.extendedProps.post_id;

  if (postId) {
    router.patch(
      route('scheduled-posts.update', postId),
      {
        scheduled_for: event.startStr,
        scheduled_until: event.endStr,
      },
      {
        onSuccess: () => {
          fetchEvents();
        },
      },
    );
  }
}

function handleEventResize(resizeInfo: any) {
  const event = resizeInfo.event;
  const postId = event.extendedProps.post_id;

  if (postId) {
    router.patch(
      route('scheduled-posts.update', postId),
      {
        scheduled_for: event.startStr,
        scheduled_until: event.endStr,
      },
      {
        onSuccess: () => {
          fetchEvents();
        },
      },
    );
  }
}

async function fetchEvents() {
  loading.value = true;
  try {
    const response = await fetch(calendarRoutes.events.url());
    const data = await response.json();
    events.value = data.map((event: any) => ({
      id: event.id.toString(),
      title:
        event.content.substring(0, 50) +
        (event.content.length > 50 ? '...' : ''),
      start: event.scheduled_for,
      end: event.scheduled_until,
      extendedProps: {
        post_id: event.id,
        platform: event.platform,
        status: event.status,
      },
    }));
  } catch (error) {
    console.error('Failed to fetch events:', error);
  } finally {
    loading.value = false;
  }
}

function goToCreatePost() {
  router.visit(posts.create.url());
}

function goToScheduledPosts() {
  router.visit(scheduledPosts.upcoming.url());
}

onMounted(() => {
  fetchEvents();
});
</script>

<template>

  <Head title="Calendar" />

  <AppLayout>
    <div class="min-h-screen">
      <div class="p-6">
        <div class="flex h-full flex-1 flex-col space-y-8">
          <div class="flex items-center justify-between animate-fade-in">
            <div>
              <h1 class="text-display-1 mb-4 text-neutral-900 dark:text-white">
                Content <span class="text-gradient font-bold">Calendar</span> 📅
              </h1>
              <p class="text-body-large max-w-2xl text-neutral-600 dark:text-neutral-400 leading-relaxed">
                Schedule and organize your social media posts
              </p>
            </div>
            <div class="flex gap-4">
              <Button @click="goToCreatePost" variant="outline" class="hover-glow">
                <Calendar class="mr-3 h-5 w-5" />
                Create Post
              </Button>
              <Button @click="goToScheduledPosts" class="btn-primary hover-glow">
                <Clock class="mr-3 h-5 w-5" />
                Scheduled Posts
              </Button>
            </div>
          </div>

          <div class="grid gap-6 md:grid-cols-4">
            <div class="card-elevated relative overflow-hidden animate-slide-up">
              <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 to-purple-500"></div>
              <div class="mb-4 flex items-center justify-between">
                <h3 class="text-headline-4 text-neutral-900 dark:text-white">Total Posts</h3>
                <div
                  class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-purple-500 shadow-lg">
                  <TrendingUp class="h-5 w-5 text-white" />
                </div>
              </div>
              <div>
                <div class="text-3xl font-bold text-neutral-900 dark:text-white">
                  {{ events.length }}
                </div>
                <p class="text-body text-neutral-600 dark:text-neutral-400 mt-1">
                  Scheduled this month
                </p>
              </div>
            </div>

            <div class="card-elevated relative overflow-hidden animate-slide-up">
              <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-green-500 to-emerald-500"></div>
              <div class="mb-4 flex items-center justify-between">
                <h3 class="text-headline-4 text-neutral-900 dark:text-white">Published</h3>
                <div
                  class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-green-500 to-emerald-500 shadow-lg">
                  <Users class="h-5 w-5 text-white" />
                </div>
              </div>
              <div>
                <div class="text-3xl font-bold text-neutral-900 dark:text-white">
                  {{
                    events.filter(
                      (e) =>
                        e.extendedProps.status === 'published',
                    ).length
                  }}
                </div>
                <p class="text-body text-neutral-600 dark:text-neutral-400 mt-1">
                  Successfully published
                </p>
              </div>
            </div>

            <div class="card-elevated relative overflow-hidden animate-slide-up">
              <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-amber-500 to-orange-500"></div>
              <div class="mb-4 flex items-center justify-between">
                <h3 class="text-headline-4 text-neutral-900 dark:text-white">Pending</h3>
                <div
                  class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 shadow-lg">
                  <Clock class="h-5 w-5 text-white" />
                </div>
              </div>
              <div>
                <div class="text-3xl font-bold text-neutral-900 dark:text-white">
                  {{
                    events.filter(
                      (e) => e.extendedProps.status === 'pending',
                    ).length
                  }}
                </div>
                <p class="text-body text-neutral-600 dark:text-neutral-400 mt-1">
                  Waiting to be published
                </p>
              </div>
            </div>

            <div class="card-elevated relative overflow-hidden animate-slide-up">
              <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-purple-500 to-pink-500"></div>
              <div class="mb-4 flex items-center justify-between">
                <h3 class="text-headline-4 text-neutral-900 dark:text-white">Platforms</h3>
                <div
                  class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 shadow-lg">
                  <Users class="h-5 w-5 text-white" />
                </div>
              </div>
              <div>
                <div class="flex gap-1 mb-3">
                  <Badge v-if="
                    events.some(
                      (e) =>
                        e.extendedProps.platform ===
                        'facebook',
                    )
                  " variant="secondary" class="bg-blue-500 text-white">
                    FB
                  </Badge>
                  <Badge v-if="
                    events.some(
                      (e) =>
                        e.extendedProps.platform ===
                        'twitter',
                    )
                  " variant="secondary" class="bg-sky-500 text-white">
                    TW
                  </Badge>
                  <Badge v-if="
                    events.some(
                      (e) =>
                        e.extendedProps.platform ===
                        'instagram',
                    )
                  " variant="secondary" class="bg-pink-500 text-white">
                    IG
                  </Badge>
                  <Badge v-if="
                    events.some(
                      (e) =>
                        e.extendedProps.platform ===
                        'linkedin',
                    )
                  " variant="secondary" class="bg-blue-700 text-white">
                    LI
                  </Badge>
                </div>
                <p class="text-body text-neutral-600 dark:text-neutral-400">
                  Active platforms
                </p>
              </div>
            </div>
          </div>

          <div class="card-elevated relative overflow-hidden animate-slide-up">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-indigo-500 to-blue-500"></div>
            <div class="mb-4 flex items-center justify-between">
              <h3 class="text-headline-4 text-neutral-900 dark:text-white">Calendar View</h3>
              <div
                class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-blue-500 shadow-lg">
                <Calendar class="h-5 w-5 text-white" />
              </div>
            </div>
            <div>
              <p class="text-body text-neutral-600 dark:text-neutral-400 mb-4">
                Drag and drop posts to reschedule. Click on events to edit.
              </p>
              <div v-if="loading" class="flex justify-center p-8">
                <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-primary"></div>
              </div>
              <FullCalendar v-else ref="calendarRef" :options="calendarOptions" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
