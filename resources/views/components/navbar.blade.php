<nav class="bg-white shadow-md sticky top-0 z-50" x-data="{ open: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <div class="flex-shrink-0">
                <a href="{{ route('home') }}" class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                    {{ config('app.name') }}
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:block">
                <div class="ml-10 flex items-baseline space-x-8">
                    <a href="{{ route('home') }}"
                       class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium transition-colors duration-200 {{ request()->routeIs('home') ? 'text-blue-600 border-b-2 border-blue-600' : '' }}">
                        Home
                    </a>
                    <a href="{{ route('about') }}"
                       class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium transition-colors duration-200 {{ request()->routeIs('about') ? 'text-blue-600 border-b-2 border-blue-600' : '' }}">
                        About Us
                    </a>
                    <a href="{{ route('features') }}"
                       class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium transition-colors duration-200 {{ request()->routeIs('features') ? 'text-blue-600 border-b-2 border-blue-600' : '' }}">
                        Features
                    </a>
                    <a href="{{ route('contact') }}"
                       class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium transition-colors duration-200 {{ request()->routeIs('contact') ? 'text-blue-600 border-b-2 border-blue-600' : '' }}">
                        Contact
                    </a>
                </div>
            </div>

            <!-- CTA Button -->
            <div class="hidden md:block">
                <a href="{{ route('contact') }}"
                   class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:from-blue-700 hover:to-purple-700 transition-all duration-200 transform hover:scale-105">
                    Get Started
                </a>
            </div>

            <!-- Mobile menu button -->
            <div class="md:hidden">
                <button @click="open = !open"
                        class="text-gray-700 hover:text-blue-600 p-2 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <svg x-show="!open" class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="open" class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform -translate-y-1"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="md:hidden bg-white border-t border-gray-200">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
            <a href="{{ route('home') }}"
               class="text-gray-700 hover:text-blue-600 block px-3 py-2 text-base font-medium {{ request()->routeIs('home') ? 'text-blue-600 bg-blue-50' : '' }}">
                Home
            </a>
            <a href="{{ route('about') }}"
               class="text-gray-700 hover:text-blue-600 block px-3 py-2 text-base font-medium {{ request()->routeIs('about') ? 'text-blue-600 bg-blue-50' : '' }}">
                About Us
            </a>
            <a href="{{ route('features') }}"
               class="text-gray-700 hover:text-blue-600 block px-3 py-2 text-base font-medium {{ request()->routeIs('features') ? 'text-blue-600 bg-blue-50' : '' }}">
                Features
            </a>
            <a href="{{ route('contact') }}"
               class="text-gray-700 hover:text-blue-600 block px-3 py-2 text-base font-medium {{ request()->routeIs('contact') ? 'text-blue-600 bg-blue-50' : '' }}">
                Contact
            </a>
            <div class="pt-4 pb-2">
                <a href="{{ route('contact') }}"
                   class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:from-blue-700 hover:to-purple-700 transition-all duration-200 transform hover:scale-105 block text-center">
                    Get Started
                </a>
            </div>
        </div>
    </div>
</nav>
