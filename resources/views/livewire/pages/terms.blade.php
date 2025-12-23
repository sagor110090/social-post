use Livewire\Volt\Component;

new class extends Component {
    // Terms and conditions page - no dynamic content needed
}; ?>

<x-layouts.frontend>
    <x-slot name="title">
        Terms & Conditions - Legal Terms of Service
    </x-slot>
    <x-slot name="description">
        Read our comprehensive terms and conditions to understand the rules and guidelines for using our platform and services.
    </x-slot>
    <x-slot name="keywords">
        terms, conditions, legal, service agreement
    </x-slot>
    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-50 to-purple-50 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6">
                Terms & <span class="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Conditions</span>
            </h1>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Please read these terms and conditions carefully before using our services.
            </p>
            <p class="text-sm text-gray-500 mt-4">Last updated: {{ date('F j, Y') }}</p>
        </div>
    </section>

    <!-- Content Section -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="prose prose-lg max-w-none">

                <!-- Agreement -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Agreement to Terms</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        Welcome to {{ config('app.name') }}. These Terms and Conditions ("Terms") govern your access to and use of our website, services, and applications (collectively, the "Service") operated by {{ config('app.name') }} ("us," "we," or "our").
                    </p>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        By accessing or using our Service, you agree to be bound by these Terms. If you disagree with any part of these terms, then you may not access the Service.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        This Terms and Conditions Agreement is effective as of {{ date('F j, Y') }} and was last updated on {{ date('F j, Y') }}.
                    </p>
                </div>

                <!-- Definitions -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Definitions</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        For the purposes of these Terms:
                    </p>
                    <ul class="list-disc pl-6 space-y-2 text-gray-600">
                        <li><strong>Service:</strong> Refers to the website, applications, and services provided by {{ config('app.name') }}</li>
                        <li><strong>Content:</strong> Refers to text, graphics, images, videos, information, and other materials available on our Service</li>
                        <li><strong>User:</strong> Refers to any individual or entity that accesses or uses our Service</li>
                        <li><strong>Account:</strong> Refers to a unique account created for you to access our Service</li>
                        <li><strong>Subscription:</strong> Refers to a paid plan or service level</li>
                    </ul>
                </div>

                <!-- User Accounts -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">User Accounts</h2>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Registration</h3>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        To access certain features of our Service, you must register for an account. When you register:
                    </p>
                    <ul class="list-disc pl-6 space-y-2 text-gray-600 mb-6">
                        <li>You must provide accurate, current, and complete information</li>
                        <li>You are responsible for maintaining the confidentiality of your account credentials</li>
                        <li>You agree to accept responsibility for all activities under your account</li>
                        <li>You must be at least 13 years of age to create an account</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Account Security</h3>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        You are responsible for safeguarding the password that you use to access the Service and for any activities or actions under your password. You agree not to disclose your password to any third party.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        You must notify us immediately upon becoming aware of any breach of security or unauthorized use of your account.
                    </p>
                </div>

                <!-- Use License -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Use License</h2>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Granted License</h3>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        Permission is granted to temporarily download one copy of the materials on {{ config('app.name') }} for personal, non-commercial transitory viewing only. This is the grant of a license, not a transfer of title, and under this license you may not:
                    </p>
                    <ul class="list-disc pl-6 space-y-2 text-gray-600 mb-6">
                        <li>Modify or copy the materials</li>
                        <li>Use the materials for any commercial purpose or for any public display</li>
                        <li>Attempt to reverse engineer any software contained on the Service</li>
                        <li>Remove any copyright or other proprietary notations from the materials</li>
                        <li>Transfer the materials to another person or "mirror" the materials on any other server</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">License Restrictions</h3>
                    <p class="text-gray-600 leading-relaxed">
                        This license shall automatically terminate if you violate any of these restrictions and may be terminated by {{ config('app.name') }} at any time.
                    </p>
                </div>

                <!-- Subscription Terms -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Subscription Terms</h2>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Billing</h3>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        Subscription plans are billed on a recurring basis (monthly or annually) as specified at the time of purchase. Payment will be charged to your preferred payment method on the date of purchase.
                    </p>
                    <ul class="list-disc pl-6 space-y-2 text-gray-600 mb-6">
                        <li>All fees are non-refundable unless otherwise specified</li>
                        <li>Prices are subject to change with 30 days notice</li>
                        <li>We reserve the right to modify or discontinue subscription plans at any time</li>
                        <li>Failed payments may result in service interruption</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Cancellation</h3>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        You may cancel your subscription at any time through your account settings. Cancellation will take effect at the end of the current billing period.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        Upon cancellation, you will continue to have access to the Service until the end of your current billing period. No refunds will be provided for partial months.
                    </p>
                </div>

                <!-- User Content -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">User Content</h2>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Content Rights</h3>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        You retain ownership of any content you submit, post, or display on or through the Service ("User Content"). By submitting User Content, you grant us a worldwide, non-exclusive, royalty-free, sublicenseable, and transferable license to use, reproduce, distribute, prepare derivative works of, and display the User Content.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        You represent and warrant that you own or have the necessary licenses, rights, consents, and permissions to use and authorize us to use all patent, trademark, trade secret, copyright, or other proprietary rights in and to your User Content.
                    </p>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Content Standards</h3>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        You agree not to post, upload, or transmit any content that:
                    </p>
                    <ul class="list-disc pl-6 space-y-2 text-gray-600">
                        <li>Is unlawful, harmful, threatening, abusive, harassing, defamatory, vulgar, obscene</li>
                        <li>Infringes upon any third-party rights, including copyright, trademark, privacy, or publicity</li>
                        <li>Contains viruses, corrupted files, or any other malicious code</li>
                        <li>Violates any applicable laws or regulations</li>
                        <li>Is false, misleading, or deceptive</li>
                    </ul>
                </div>

                <!-- Intellectual Property -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Intellectual Property</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        The Service and its original content, features, and functionality are and will remain the exclusive property of {{ config('app.name') }} and its licensors. The Service is protected by copyright, trademark, and other laws.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        Our trademarks and trade dress may not be used in connection with any product or service without the prior written consent of {{ config('app.name') }}.
                    </p>
                </div>

                <!-- Privacy -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Privacy Policy</h2>
                    <p class="text-gray-600 leading-relaxed">
                        Your Privacy is important to us. Please review our Privacy Policy, which also governs the Service and informs users about our data collection practices.
                    </p>
                </div>

                <!-- Prohibited Activities -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Prohibited Activities</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        You may not access or use the Service for any purpose other than that for which we make the Service available. The Service may not be used in connection with any commercial endeavors except those that are specifically endorsed or approved by us.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        As a user of the Service, you agree not to:
                    </p>
                    <ul class="list-disc pl-6 space-y-2 text-gray-600">
                        <li>Use the Service for any illegal or unauthorized purpose</li>
                        <li>Attempt to gain unauthorized access to any portion of the Service</li>
                        <li>Use robots, spiders, or other automated means to access the Service</li>
                        <li>Interfere with or disrupt the Service or servers connected to the Service</li>
                        <li>Use the Service to transmit spam, chain letters, or other unsolicited messages</li>
                        <li>Impersonate any person or entity or misrepresent your affiliation with any person or entity</li>
                    </ul>
                </div>

                <!-- Service Availability -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Service Availability</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        We strive to maintain the Service's availability and performance. However, we do not guarantee that the Service will be available at all times or free from errors.
                    </p>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        We may, from time to time, modify, suspend, or discontinue the Service (or any part thereof) with or without notice. You agree that we will not be liable to you or to any third party for any modification, suspension, or discontinuance of the Service.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        We are not responsible for any loss, damage, or inconvenience caused by the unavailability of the Service.
                    </p>
                </div>

                <!-- Limitation of Liability -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Limitation of Liability</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        In no event shall {{ config('app.name') }}, our directors, employees, partners, agents, suppliers, or affiliates be liable for any indirect, incidental, special, consequential, or punitive damages, including without limitation, loss of profits, data, use, goodwill, or other intangible losses, resulting from your use of the Service.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        Our total liability to you for all claims arising from or relating to the Service shall not exceed the amount you paid to us in the twelve (12) months preceding the claim.
                    </p>
                </div>

                <!-- Indemnification -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Indemnification</h2>
                    <p class="text-gray-600 leading-relaxed">
                        You agree to defend, indemnify, and hold harmless {{ config('app.name') }} and our licensee and licensors, and their employees, contractors, agents, officers and directors, from and against any and all claims, damages, obligations, losses, liabilities, costs or debt, and expenses (including but not limited to attorney's fees).
                    </p>
                </div>

                <!-- Termination -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Termination</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        We may terminate or suspend your account immediately, without prior notice or liability, for any reason whatsoever, including without limitation if you breach the Terms.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        Upon termination, your right to use the Service will cease immediately. All provisions of the Terms which by their nature should survive termination shall survive termination.
                    </p>
                </div>

                <!-- Governing Law -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Governing Law</h2>
                    <p class="text-gray-600 leading-relaxed">
                        These Terms shall be interpreted and governed by the laws of the State of California, United States, without regard to its conflict of law provisions. Any disputes arising from these Terms shall be resolved in the courts located in California.
                    </p>
                </div>

                <!-- Changes to Terms -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Changes to Terms</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        We reserve the right to modify or replace these Terms at any time. If a revision is material, we will provide at least 30 days notice prior to any new terms taking effect.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        Your continued use of the Service after the effective date of the revised Terms constitutes acceptance of the changes.
                    </p>
                </div>

                <!-- Contact Information -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Contact Us</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        If you have any questions about these Terms and Conditions, please contact us:
                    </p>
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <p class="text-gray-600 mb-2"><strong>Email:</strong> legal@example.com</p>
                        <p class="text-gray-600 mb-2"><strong>Address:</strong> 123 Tech Street, Silicon Valley, CA 94025, United States</p>
                        <p class="text-gray-600"><strong>Phone:</strong> +1 (555) 123-4567</p>
            </div>
        </div>
    </section>
</x-layouts.frontend>

    <!-- Quick Links -->
    <section class="py-16 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">Quick Links</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <a href="{{ route('home') }}" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow duration-200 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <h3 class="font-semibold text-gray-900">Home</h3>
                </a>
                <a href="{{ route('privacy-policy') }}" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow duration-200 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-purple-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <h3 class="font-semibold text-gray-900">Privacy Policy</h3>
                </a>
                <a href="{{ route('contact') }}" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow duration-200 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <h3 class="font-semibold text-gray-900">Contact Us</h3>
                </a>
            </div>
        </div>
    </section>
</x-layouts.frontend>
