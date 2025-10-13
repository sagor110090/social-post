<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CreatePostButton from '@/components/QuickActions/CreatePostButton.vue';
import GlobalSearch from '@/components/Search/GlobalSearch.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import { toUrl, urlIsActive } from '@/lib/utils';
import { dashboard } from '@/routes';
import ai from '@/routes/ai';
import analytics from '@/routes/analytics';
import calendar from '@/routes/calendar';
import social from '@/routes/social';
import posts from '@/routes/social/posts';
import type { BreadcrumbItem, NavItem } from '@/types';
import { InertiaLinkProps, Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    BrainCircuit,
    CalendarDays,
    ChevronDown,
    Clock,
    LayoutGrid,
    Menu,
    PlusCircle,
    Settings,
    Share2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItem[];
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);

// Track which dropdown is open
const openDropdown = ref<string | null>(null);

const isCurrentRoute = computed(
    () => (url: NonNullable<InertiaLinkProps['href']>) =>
        urlIsActive(url, page.url),
);

const activeItemStyles = computed(
    () => (url: NonNullable<InertiaLinkProps['href']>) => {
        const currentUrl = page.url;
        const targetUrl = toUrl(url);

        // Check if current URL starts with the target URL (for nested routes)
        const isActive =
            currentUrl === targetUrl || currentUrl.startsWith(targetUrl + '/');

        return isActive
            ? 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100'
            : '';
    },
);

const toggleDropdown = (title: string) => {
    openDropdown.value = openDropdown.value === title ? null : title;
};

const closeDropdown = () => {
    openDropdown.value = null;
};

// Handle click outside to close dropdown
const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as HTMLElement;
    if (!target.closest('.dropdown-container')) {
        closeDropdown();
    }
};

// Add click outside listener when dropdown is open
import { onUnmounted, watchEffect } from 'vue';

watchEffect(() => {
    if (openDropdown.value) {
        document.addEventListener('click', handleClickOutside);
    } else {
        document.removeEventListener('click', handleClickOutside);
    }
});

// Clean up event listener when component is unmounted
onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'AI Generator',
        href: ai.generator(),
        icon: BrainCircuit,
    },
    {
        title: 'Social Media',
        href: social.accounts(),
        icon: Share2,
        children: [
            {
                title: 'Accounts',
                href: social.accounts(),
                icon: Share2,
            },
            {
                title: 'Create Post',
                href: posts.create(),
                icon: PlusCircle,
            },
            {
                title: 'History',
                href: posts.history(),
                icon: Clock,
            },
        ],
    },
    {
        title: 'Calendar',
        href: calendar.index(),
        icon: CalendarDays,
    },
    {
        title: 'Analytics',
        href: analytics.dashboard(),
        icon: BarChart3,
    },
];

const rightNavItems: NavItem[] = [
    {
        title: 'Settings',
        href: '/settings/profile',
        icon: Settings,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <div>
        <div
            class="sticky top-0 z-50 border-b border-neutral-200/80 bg-white/80 backdrop-blur-sm dark:border-neutral-700/80 dark:bg-neutral-900/80"
        >
            <div class="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                <!-- Mobile Menu -->
                <div class="lg:hidden">
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="mr-2 h-9 w-9"
                            >
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-[300px] p-6">
                            <SheetTitle class="sr-only"
                                >Navigation Menu</SheetTitle
                            >
                            <SheetHeader class="flex justify-start text-left">
                                <AppLogoIcon
                                    class="size-6 fill-current text-black dark:text-white"
                                />
                            </SheetHeader>
                            <div
                                class="flex h-full flex-1 flex-col justify-between space-y-4 py-6"
                            >
                                <nav class="-mx-3 space-y-1">
                                    <template
                                        v-for="item in mainNavItems"
                                        :key="item.title"
                                    >
                                        <div
                                            v-if="item.children"
                                            class="space-y-1"
                                        >
                                            <div
                                                class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold text-neutral-600 dark:text-neutral-400"
                                            >
                                                <component
                                                    v-if="item.icon"
                                                    :is="item.icon"
                                                    class="h-5 w-5"
                                                />
                                            </div>
                                            <div class="ml-6 space-y-1">
                                                <Link
                                                    v-for="child in item.children"
                                                    :key="child.title"
                                                    :href="child.href"
                                                    class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                                                    :class="
                                                        activeItemStyles(
                                                            child.href,
                                                        )
                                                    "
                                                >
                                                    <component
                                                        v-if="child.icon"
                                                        :is="child.icon"
                                                        class="h-4 w-4"
                                                    />
                                                    {{ child.title }}
                                                </Link>
                                            </div>
                                        </div>
                                        <Link
                                            v-else
                                            :href="item.href"
                                            class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                                            :class="activeItemStyles(item.href)"
                                        >
                                            <component
                                                v-if="item.icon"
                                                :is="item.icon"
                                                class="h-5 w-5"
                                            />
                                            {{ item.title }}
                                        </Link>
                                    </template>
                                </nav>
                                <div class="flex flex-col space-y-4">
                                    <a
                                        v-for="item in rightNavItems"
                                        :key="item.title"
                                        :href="toUrl(item.href)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="flex items-center space-x-2 text-sm font-medium"
                                    >
                                        <component
                                            v-if="item.icon"
                                            :is="item.icon"
                                            class="h-5 w-5"
                                        />
                                        <span>{{ item.title }}</span>
                                    </a>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <Link
                    :href="dashboard()"
                    class="flex items-center gap-x-3 transition-opacity hover:opacity-80"
                >
                    <AppLogo />
                </Link>

                <!-- Desktop Menu -->
                <div class="hidden h-full lg:flex lg:flex-1">
                    <div class="ml-10 flex h-full items-stretch space-x-2">
                        <div
                            v-for="(item, index) in mainNavItems"
                            :key="index"
                            class="dropdown-container relative flex h-full items-center"
                        >
                            <template v-if="item.children">
                                <button
                                    type="button"
                                    :class="[
                                        'group inline-flex h-9 w-max items-center justify-center rounded-md bg-background px-4 py-2 text-sm font-medium transition-[color,box-shadow] outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50',
                                        activeItemStyles(item.href),
                                        openDropdown === item.title
                                            ? 'bg-accent/50 text-accent-foreground'
                                            : '',
                                    ]"
                                    @click="toggleDropdown(item.title)"
                                >
                                    <component
                                        v-if="item.icon"
                                        :is="item.icon"
                                        class="mr-2 h-4 w-4"
                                    />
                                    {{ item.title }}
                                    <ChevronDown
                                        class="relative top-[1px] ml-1 size-3 transition duration-300"
                                        :class="{
                                            'rotate-180':
                                                openDropdown === item.title,
                                        }"
                                        aria-hidden="true"
                                    />
                                </button>
                                <div
                                    v-if="openDropdown === item.title"
                                    class="absolute top-full left-0 z-50 mt-1.5 w-64 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-lg"
                                    @click.stop
                                >
                                    <div class="p-2">
                                        <div
                                            class="px-3 py-2 text-sm font-semibold"
                                        >
                                            {{ item.title }}
                                        </div>
                                        <div class="mt-1 space-y-1">
                                            <Link
                                                v-for="child in item.children"
                                                :key="child.title"
                                                :href="child.href"
                                                class="flex items-center gap-x-3 rounded-md px-3 py-2 text-sm font-medium hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground"
                                                @click="closeDropdown"
                                            >
                                                <component
                                                    v-if="child.icon"
                                                    :is="child.icon"
                                                    class="h-4 w-4"
                                                />
                                                {{ child.title }}
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                <Link
                                    :class="[
                                        'group inline-flex h-9 w-max items-center justify-center rounded-md bg-background px-4 py-2 text-sm font-medium transition-[color,box-shadow] outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50',
                                        activeItemStyles(item.href),
                                    ]"
                                    :href="item.href"
                                >
                                    <component
                                        v-if="item.icon"
                                        :is="item.icon"
                                        class="mr-2 h-4 w-4"
                                    />
                                    {{ item.title }}
                                </Link>
                            </template>
                            <div
                                v-if="
                                    !item.children && isCurrentRoute(item.href)
                                "
                                class="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"
                            ></div>
                        </div>
                    </div>
                </div>

                <div class="ml-auto flex items-center space-x-2">
                    <div class="relative flex items-center space-x-1">
                        <GlobalSearch />
                        <CreatePostButton />

                        <div class="hidden space-x-1 lg:flex">
                            <template
                                v-for="item in rightNavItems"
                                :key="item.title"
                            >
                                <TooltipProvider :delay-duration="0">
                                    <Tooltip>
                                        <TooltipTrigger>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                as-child
                                                class="group h-9 w-9 cursor-pointer"
                                            >
                                                <a
                                                    :href="toUrl(item.href)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <span class="sr-only">{{
                                                        item.title
                                                    }}</span>
                                                    <component
                                                        :is="item.icon"
                                                        class="size-5 opacity-80 group-hover:opacity-100"
                                                    />
                                                </a>
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ item.title }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </template>
                        </div>
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
                            >
                                <Avatar
                                    class="size-8 overflow-hidden rounded-full"
                                >
                                    <AvatarImage
                                        v-if="auth.user.avatar"
                                        :src="auth.user.avatar"
                                        :alt="auth.user.name"
                                    />
                                    <AvatarFallback
                                        class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ getInitials(auth.user?.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>

        <div
            v-if="props.breadcrumbs.length > 1"
            class="flex w-full border-b border-sidebar-border/70"
        >
            <div
                class="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </div>
</template>
