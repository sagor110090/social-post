<?php
use Livewire\Volt\Component;

new class extends Component {
    public $teamMembers = [
        [
            'name' => 'Alex Thompson',
            'role' => 'Founder & CEO',
            'bio' => 'Visionary leader with 15+ years of experience in tech innovation and business strategy.',
            'photo' => 'https://picsum.photos/seed/alex/300/300.jpg'
        ],
        [
            'name' => 'Sarah Martinez',
            'role' => 'Chief Technology Officer',
            'bio' => 'Expert in scalable architecture and emerging technologies with a passion for elegant solutions.',
            'photo' => 'https://picsum.photos/seed/sarah/300/300.jpg'
        ],
        [
            'name' => 'David Chen',
            'role' => 'Head of Product',
            'bio' => 'User-focused product leader dedicated to creating experiences that delight and inspire.',
            'photo' => 'https://picsum.photos/seed/david/300/300.jpg'
        ],
        [
            'name' => 'Emily Rodriguez',
            'role' => 'VP of Marketing',
            'bio' => 'Creative marketing strategist who builds meaningful connections between brands and customers.',
            'photo' => 'https://picsum.photos/seed/emily2/300/300.jpg'
        ]
    ];

    public $companyStats = [
        'clients' => '5000+',
        'projects' => '10,000+',
        'team' => '50+',
        'countries' => '25+'
    ];
}; ?>

<div>
    <x-slot name="title">
        About Us - Our Story & Team
    </x-slot>
    <x-slot name="description">
        Learn about our mission, vision, and the passionate team behind our innovative platform.
    </x-slot>
    <x-slot name="keywords">
        about us, company, team, mission, vision
    </x-slot>
    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-50 to-purple-50 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6">
                About <span class="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">{{ config('app.name') }}</span>
            </h1>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto mb-8">
                We're on a mission to transform how businesses operate in the digital age through innovation, dedication, and exceptional user experiences.
            </p>
        </div>
    </section>

    <!-- Company Stats -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div class="card-hover p-6">
                    <div class="text-4xl font-bold text-blue-600 mb-2">{{ $companyStats['clients'] }}</div>
                    <div class="text-gray-600">Happy Clients</div>
                </div>
                <div class="card-hover p-6">
                    <div class="text-4xl font-bold text-purple-600 mb-2">{{ $companyStats['projects'] }}</div>
                    <div class="text-gray-600">Projects Completed</div>
                </div>
                <div class="card-hover p-6">
                    <div class="text-4xl font-bold text-green-600 mb-2">{{ $companyStats['team'] }}</div>
                    <div class="text-gray-600">Team Members</div>
                </div>
                <div class="card-hover p-6">
                    <div class="text-4xl font-bold text-orange-600 mb-2">{{ $companyStats['countries'] }}</div>
                    <div class="text-gray-600">Countries Served</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Mission & Vision -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <div class="bg-white rounded-xl shadow-lg p-8 card-hover">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Our Mission</h2>
                    <p class="text-gray-600 leading-relaxed">
                        To empower businesses of all sizes with cutting-edge technology that simplifies complex processes, drives innovation, and creates meaningful connections. We believe that great technology should be accessible, intuitive, and transformative for every organization that dares to dream bigger.
                    </p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-8 card-hover">
                    <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg flex items-center justify-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Our Vision</h2>
                    <p class="text-gray-600 leading-relaxed">
                        To create a world where technology seamlessly integrates with business operations, enabling organizations to focus on what truly matters: their customers, their innovation, and their growth. We envision a future where every business, regardless of size, can compete and thrive in the digital landscape.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Company Story -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-8">Our Story</h2>
            <div class="space-y-6 text-lg text-gray-600 leading-relaxed">
                <p>
                    Founded in 2018, {{ config('app.name') }} began as a simple idea shared among a group of tech enthusiasts who saw a gap in the market. We noticed that while technology was advancing rapidly, many businesses were being left behind due to complex, inaccessible solutions that required specialized knowledge and significant investment.
                </p>
                <p>
                    What started in a small co-working space with just three passionate individuals has grown into a thriving company serving thousands of clients across the globe. Our journey has been marked by relentless innovation, countless hours of development, and an unwavering commitment to our customers' success.
                </p>
                <p>
                    Today, we're proud to be at the forefront of digital transformation, helping businesses leverage the power of modern technology to achieve their goals. But our work is far from over – we continue to push boundaries, challenge conventions, and reinvent what's possible in the world of business technology.
                </p>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Meet Our Team</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    The passionate individuals behind our success, each bringing unique expertise and unwavering dedication.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                @foreach($teamMembers as $member)
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden card-hover group">
                        <div class="aspect-square overflow-hidden bg-gradient-to-br from-blue-100 to-purple-100">
                            <img src="{{ $member['photo'] }}" alt="{{ $member['name'] }}"
                                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                        </div>
                        <div class="p-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-1">{{ $member['name'] }}</h3>
                            <p class="text-blue-600 font-medium mb-3">{{ $member['role'] }}</p>
                            <p class="text-gray-600 text-sm leading-relaxed">{{ $member['bio'] }}</p>
                            <div class="flex space-x-3 mt-4">
                                <a href="#" class="text-gray-400 hover:text-blue-600 transition-colors duration-200">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                                    </svg>
                                </a>
                                <a href="#" class="text-gray-400 hover:text-blue-600 transition-colors duration-200">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-4">
                Join Us on Our Journey
            </h2>
            <p class="text-xl mb-8 text-blue-100">
                We're always looking for talented individuals who share our passion for innovation and excellence.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('contact') }}" class="bg-white text-blue-600 px-8 py-3 rounded-lg text-lg font-semibold hover:bg-gray-100 transition-all duration-200 transform hover:scale-105">
                    Partner With Us
                </a>
                <a href="#" class="border-2 border-white text-white px-8 py-3 rounded-lg text-lg font-semibold hover:bg-white hover:text-blue-600 transition-all duration-200 transform hover:scale-105">
                    View Careers
                </a>
            </div>
        </div>
    </section>
</div>
