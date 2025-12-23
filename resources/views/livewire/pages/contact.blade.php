<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Validate;

new class extends Component {
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('required|string|max:1000')]
    public string $message = '';

    public bool $submitted = false;

    public function submit(): void
    {
        $this->validate();

        // Here you would typically send an email or save to database
        // For demo purposes, we'll just mark as submitted
        $this->submitted = true;

        // Reset form after successful submission
        $this->reset(['name', 'email', 'message']);
    }
};
?>

<div>
    <x-slot name="title">
        Contact Us - Get in Touch
    </x-slot>
    <x-slot name="description">
        Reach out to our team for inquiries, support, or partnerships. We're here to help you succeed.
    </x-slot>
    <x-slot name="keywords">
        contact, support, help, inquiry
    </x-slot>
    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-50 to-purple-50 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6">
                Get in <span class="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Touch</span>
            </h1>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                We'd love to hear from you. Whether you have a question about our services, pricing, or anything else, our team is ready to answer all your questions.
            </p>
        </div>
    </section>

    <!-- Contact Form & Info Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <!-- Contact Form -->
                <div class="bg-gray-50 rounded-xl p-8 shadow-lg">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Send us a Message</h2>

                    @if($submitted)
                        <div class="bg-green-50 border border-green-200 text-green-800 px-6 py-4 rounded-lg mb-6">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <p class="font-semibold">Thank you for your message!</p>
                                    <p class="text-sm">We'll get back to you within 24 hours.</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <form wire:submit.prevent="submit" class="space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Your Name</label>
                            <input wire:model="name" id="name" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" />
                            @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Your Email</label>
                            <input wire:model="email" id="email" type="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" />
                            @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700">Your Message</label>
                            <textarea wire:model="message" id="message" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"></textarea>
                            @error('message') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <button type="submit" class="w-full py-3 px-6 bg-blue-600 text-white font-bold rounded-md shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">Send Message</button>
                        </div>
                    </form>
                </div>

                <!-- Contact Info -->
                <div class="bg-gray-50 rounded-xl p-8 shadow-lg">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Contact Information</h2>
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <span class="text-gray-700">support@yourcompany.com</span>
                        </div>
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            <span class="text-gray-700">+1 (555) 123-4567</span>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-6 h-6 text-blue-600 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span class="text-gray-700">123 Business Ave<br>Suite 100<br>New York, NY 10001</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Map Section (placeholder) -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Find Us</h2>
            <div class="bg-gray-200 rounded-xl h-96 flex items-center justify-center">
                <div class="text-center">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                    </svg>
                    <p class="text-gray-600">Interactive Map</p>
                    <p class="text-gray-500 text-sm">Our office location will be displayed here</p>
                </div>
            </div>
        </div>
    </section>
</div>
