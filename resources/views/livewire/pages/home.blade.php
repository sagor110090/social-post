<?php
use Livewire\Volt\Component;

new class extends Component {
    public $testimonials = [
        [
            'name' => 'Sarah Johnson',
            'role' => 'CEO at TechCorp',
            'content' => 'This platform has completely transformed how we manage our projects. The intuitive interface and powerful features make it a game-changer.',
            'avatar' => 'https://picsum.photos/seed/sarah/100/100.jpg'
        ],
        [
            'name' => 'Michael Chen',
            'role' => 'Product Manager at InnovateCo',
            'content' => 'Outstanding service and support. The team went above and beyond to ensure our success. Highly recommended!',
            'avatar' => 'https://picsum.photos/seed/michael/100/100.jpg'
        ],
        [
            'name' => 'Emily Rodriguez',
            'role' => 'Marketing Director at GrowthHub',
            'content' => 'The best investment we\'ve made this year. ROI exceeded our expectations within the first month.',
            'avatar' => 'https://picsum.photos/seed/emily/100/100.jpg'
        ]
    ];

    public $features = [
        [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>',
            'title' => 'Lightning Fast',
            'description' => 'Experience blazing-fast performance with our optimized infrastructure and cutting-edge technology.'
        ],
        [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>',
            'title' => 'Secure & Reliable',
            'description' => 'Bank-level security ensures your data is always protected with encryption and regular backups.'
        ],
        [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" /></svg>',
            'title' => 'Easy to Use',
            'description' => 'Intuitive design and user-friendly interface make it simple for anyone to get started quickly.'
        ],
        [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>',
            'title' => 'Customizable',
            'description' => 'Tailor the platform to your specific needs with flexible configuration options and integrations.'
        ],
        [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>',
            'title' => 'Analytics',
            'description' => 'Get detailed insights and reports to make data-driven decisions and optimize your workflow.'
        ],
        [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" /></svg>',
            'title' => '24/7 Support',
            'description' => 'Round-the-clock support from our expert team ensures you never get stuck on any issue.'
        ]
    ];

    public $pricingPlans = [
        [
            'planName' => 'Basic',
            'price' => '9',
            'period' => '/month',
            'features' => [
                'Up to 10 users',
                '2 GB storage',
                'Email support',
                'Basic analytics',
                'Mobile app access'
            ],
            'featured' => false,
            'ctaText' => 'Start Free Trial',
            'ctaUrl' => '/contact'
        ],
        [
            'planName' => 'Pro',
            'price' => '29',
            'period' => '/month',
            'features' => [
                'Up to 50 users',
                '10 GB storage',
                'Priority email support',
                'Advanced analytics',
                'Mobile & desktop apps',
                'API access',
                'Custom integrations'
            ],
            'featured' => true,
            'ctaText' => 'Get Started',
            'ctaUrl' => '/contact'
        ],
        [
            'planName' => 'Enterprise',
            'price' => '99',
            'period' => '/month',
            'features' => [
                'Unlimited users',
                'Unlimited storage',
                '24/7 phone support',
                'Custom analytics',
                'All platform features',
                'Advanced API access',
                'Custom development',
                'Dedicated account manager'
            ],
            'featured' => false,
            'ctaText' => 'Contact Sales',
            'ctaUrl' => '/contact'
        ]
    ];
}; ?>

<div>
    <x-slot name="title">
        Home - Modern Web Solutions
    </x-slot>
    <x-slot name="description">
        Transform your business with our cutting-edge web platform. Fast, secure, and easy to use solutions for modern teams.
    </x-slot>
    <x-slot name="keywords">
        web development, saas, business solutions, productivity
    </x-slot>
    <!-- Hero Section -->
    <section class="hero-gradient text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 lg:py-32">
            <div class="text-center">
                <h1 class="text-4xl md:text-6xl font-bold mb-6 animate-fade-in">
                    Transform Your Business with
                    <span class="block text-yellow-300">Modern Web Solutions</span>
                </h1>
                <p class="text-xl md:text-2xl mb-8 text-blue-100 max-w-3xl mx-auto">
                    Experience the power of cutting-edge technology with our intuitive platform designed to streamline your workflow and boost productivity.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('contact') }}" class="bg-white text-blue-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition-all duration-200 transform hover:scale-105 shadow-lg">
                        Get Started Free
                    </a>
                    <a href="{{ route('features') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-blue-600 transition-all duration-200 transform hover:scale-105">
                        Learn More
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    Powerful Features for Modern Teams
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Everything you need to run your business efficiently, all in one place.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($features as $feature)
                    <x-feature-card
                        :icon="$feature['icon']"
                        :title="$feature['title']"
                        :description="$feature['description']"
                    />
                @endforeach
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    Trusted by Thousands of Businesses
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    See what our customers have to say about their experience with our platform.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach($testimonials as $testimonial)
                    <div class="bg-gray-50 rounded-xl p-6 card-hover">
                        <div class="flex items-center mb-4">
                            <img src="{{ $testimonial['avatar'] }}" alt="{{ $testimonial['name'] }}" class="w-12 h-12 rounded-full mr-4">
                            <div>
                                <h4 class="font-semibold text-gray-900">{{ $testimonial['name'] }}</h4>
                                <p class="text-sm text-gray-600">{{ $testimonial['role'] }}</p>
                            </div>
                        </div>
                        <div class="flex mb-3">
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            @endfor
                        </div>
                        <p class="text-gray-600 italic">"{{ $testimonial['content'] }}"</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    Simple, Transparent Pricing
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Choose the perfect plan for your business. No hidden fees, no surprises.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach($pricingPlans as $plan)
                    <x-pricing-card
                        :planName="$plan['planName']"
                        :price="$plan['price']"
                        :period="$plan['period']"
                        :features="$plan['features']"
                        :featured="$plan['featured']"
                        :ctaText="$plan['ctaText']"
                        :ctaUrl="$plan['ctaUrl']"
                    />
                @endforeach
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="hero-gradient text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-4">
                Ready to Transform Your Business?
            </h2>
            <p class="text-xl mb-8 text-blue-100 max-w-2xl mx-auto">
                Join thousands of satisfied customers who have already made the switch to our platform.
            </p>
            <a href="{{ route('contact') }}" class="bg-white text-blue-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition-all duration-200 transform hover:scale-105 shadow-lg">
                Start Your Free Trial
            </a>
        </div>
    </section>
</div>
