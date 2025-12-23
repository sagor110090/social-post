<?php

use Livewire\Volt\Component;

new class extends Component {
    public $features = [
        [
            'category' => 'Core Features',
            'items' => [
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>',
                    'title' => 'Lightning Fast Performance',
                    'description' => 'Experience blazing-fast load times and smooth interactions with our optimized infrastructure.',
                    'highlighted' => true
                ],
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>',
                    'title' => 'Enterprise-Grade Security',
                    'description' => 'Bank-level encryption, regular security audits, and compliance with international standards.',
                    'highlighted' => true
                ],
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
                    'title' => 'Customizable Dashboard',
                    'description' => 'Personalize your workspace with drag-and-drop widgets and customizable layouts.',
                    'highlighted' => false
                ],
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>',
                    'title' => 'Real-Time Synchronization',
                    'description' => 'Instant updates across all devices and team members with zero lag time.',
                    'highlighted' => false
                ]
            ]
        ],
        [
            'category' => 'Analytics & Reporting',
            'items' => [
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>',
                    'title' => 'Advanced Analytics',
                    'description' => 'Deep insights into your data with predictive analytics and trend analysis.',
                    'highlighted' => true
                ],
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v1a3 3 0 003 3h0a3 3 0 003-3v-1m3-10V4a2 2 0 00-2-2H8a2 2 0 00-2 2v3m3 0h6" /></svg>',
                    'title' => 'Custom Reports',
                    'description' => 'Generate detailed reports tailored to your specific business needs.',
                    'highlighted' => false
                ],
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" /></svg>',
                    'title' => 'Data Visualization',
                    'description' => 'Interactive charts and graphs to make complex data easy to understand.',
                    'highlighted' => false
                ],
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
                    'title' => 'Real-Time Metrics',
                    'description' => 'Monitor KPIs and performance indicators in real-time.',
                    'highlighted' => false
                ]
            ]
        ],
        [
            'category' => 'Collaboration Tools',
            'items' => [
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>',
                    'title' => 'Team Management',
                    'description' => 'Efficiently manage teams with role-based access and permissions.',
                    'highlighted' => true
                ],
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>',
                    'title' => 'In-App Messaging',
                    'description' => 'Seamless communication with built-in chat and messaging features.',
                    'highlighted' => false
                ],
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" /></svg>',
                    'title' => 'Project Collaboration',
                    'description' => 'Work together on projects with real-time editing and commenting.',
                    'highlighted' => false
                ],
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>',
                    'title' => 'Smart Notifications',
                    'description' => 'Stay informed with intelligent notifications that matter to you.',
                    'highlighted' => false
                ]
            ]
        ],
        [
            'category' => 'Integration & API',
            'items' => [
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>',
                    'title' => 'RESTful API',
                    'description' => 'Comprehensive API for seamless integration with your existing tools.',
                    'highlighted' => true
                ],
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>',
                    'title' => 'Third-Party Integrations',
                    'description' => 'Connect with popular tools like Slack, Google Workspace, and more.',
                    'highlighted' => false
                ],
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>',
                    'title' => 'Data Import/Export',
                    'description' => 'Easily migrate data with CSV, Excel, and other format support.',
                    'highlighted' => false
                ],
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>',
                    'title' => 'Webhooks Support',
                    'description' => 'Automate workflows with custom webhook integrations.',
                    'highlighted' => false
                ]
            ]
        ],
        [
            'category' => 'Mobile & Accessibility',
            'items' => [
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>',
                    'title' => 'Mobile Apps',
                    'description' => 'Native iOS and Android apps for productivity on the go.',
                    'highlighted' => true
                ],
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
                    'title' => 'Offline Mode',
                    'description' => 'Work seamlessly even without an internet connection.',
                    'highlighted' => false
                ],
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>',
                    'title' => 'Multi-Language Support',
                    'description' => 'Available in 20+ languages to serve global teams.',
                    'highlighted' => false
                ],
                [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>',
                    'title' => 'WCAG Compliant',
                    'description' => 'Fully accessible design meeting international accessibility standards.',
                    'highlighted' => false
                ]
            ]
        ]
    ];
}; ?>

<div>
    <x-slot name="title">
        Features - Complete Feature List
    </x-slot>
    <x-slot name="description">
        Explore all the powerful features our platform offers. From analytics to collaboration, everything you need to succeed.
    </x-slot>
    <x-slot name="keywords">
        features, platform capabilities, tools, functionality
    </x-slot>
    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-50 to-purple-50 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6">
                Powerful <span class="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Features</span>
            </h1>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto mb-8">
                Everything you need to run your business efficiently, all in one comprehensive platform.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('contact') }}" class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-3 rounded-lg text-lg font-semibold hover:from-blue-700 hover:to-purple-700 transition-all duration-200 transform hover:scale-105">
                    Start Free Trial
                </a>
                <a href="#" class="border-2 border-blue-600 text-blue-600 px-8 py-3 rounded-lg text-lg font-semibold hover:bg-blue-600 hover:text-white transition-all duration-200 transform hover:scale-105">
                    View Demo
                </a>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div class="card-hover p-6">
                    <div class="text-4xl font-bold text-blue-600 mb-2">50+</div>
                    <div class="text-gray-600">Features</div>
                </div>
                <div class="card-hover p-6">
                    <div class="text-4xl font-bold text-purple-600 mb-2">99.9%</div>
                    <div class="text-gray-600">Uptime</div>
                </div>
                <div class="card-hover p-6">
                    <div class="text-4xl font-bold text-green-600 mb-2">24/7</div>
                    <div class="text-gray-600">Support</div>
                </div>
                <div class="card-hover p-6">
                    <div class="text-4xl font-bold text-orange-600 mb-2">100+</div>
                    <div class="text-gray-600">Integrations</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Categories -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @foreach($features as $category)
                <div class="mb-20">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-12 text-center">
                        {{ $category['category'] }}
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        @foreach($category['items'] as $feature)
                            <div class="bg-white rounded-xl shadow-lg p-8 card-hover {{ $feature['highlighted'] ? 'ring-2 ring-blue-500' : '' }}">
                                <div class="flex items-start space-x-4">
                                    <div class="w-14 h-14 bg-gradient-to-br {{ $feature['highlighted'] ? 'from-blue-500 to-purple-600' : 'from-gray-400 to-gray-600' }} rounded-lg flex items-center justify-center flex-shrink-0">
                                        <div class="text-white text-2xl">
                                            {!! $feature['icon'] !!}
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="text-xl font-semibold text-gray-900 mb-2">
                                            {{ $feature['title'] }}
                                            @if($feature['highlighted'])
                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    Popular
                                                </span>
                                            @endif
                                        </h3>
                                        <p class="text-gray-600 leading-relaxed">
                                            {{ $feature['description'] }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <!-- Comparison Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    Compare Plans
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    See which features are included in each plan to find the perfect fit for your needs.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full bg-white rounded-lg shadow-lg overflow-hidden">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-900">Feature</th>
                            <th class="px-6 py-4 text-center text-sm font-medium text-gray-900">Basic</th>
                            <th class="px-6 py-4 text-center text-sm font-medium text-gray-900">Pro</th>
                            <th class="px-6 py-4 text-center text-sm font-medium text-gray-900">Enterprise</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900">Lightning Fast Performance</td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900">Enterprise-Grade Security</td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900">Advanced Analytics</td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900">RESTful API</td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900">Mobile Apps</td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-4">
                Ready to Experience All These Features?
            </h2>
            <p class="text-xl mb-8 text-blue-100">
                Start your free trial today and see how our platform can transform your business.
            </p>
            <a href="{{ route('contact') }}" class="bg-white text-blue-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition-all duration-200 transform hover:scale-105 shadow-lg">
                Start Your Free Trial
            </a>
        </div>
    </section>
</div>
