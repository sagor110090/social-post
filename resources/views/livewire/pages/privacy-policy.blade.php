
use Livewire\Volt\Component;

new class extends Component {
    // Privacy policy page - no dynamic content needed
}; ?>

<x-layouts.frontend>
    <x-slot name="title">
        Privacy Policy - Your Data Protection Rights
    </x-slot>
    <x-slot name="description">
        Read our comprehensive privacy policy to understand how we collect, use, and protect your personal information.
    </x-slot>
    <x-slot name="keywords">
        privacy policy, data protection, GDPR, privacy
    </x-slot>
    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-50 to-purple-50 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6">
                Privacy <span class="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Policy</span>
            </h1>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Your privacy is important to us. This policy explains how we collect, use, and protect your information.
            </p>
            <p class="text-sm text-gray-500 mt-4">Last updated: {{ date('F j, Y') }}</p>
        </div>
    </section>

    <!-- Content Section -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="prose prose-lg max-w-none">

                <!-- Introduction -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Introduction</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        Welcome to {{ config('app.name') }}. We respect your privacy and are committed to protecting your personal data. This privacy policy will inform you about how we look after your personal data when you visit our website and tell you about your privacy rights and how the law protects you.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        This policy is effective as of {{ date('F j, Y') }} and was last updated on {{ date('F j, Y') }}.
                    </p>
                </div>

                <!-- Data We Collect -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Information We Collect</h2>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Personal Information</h3>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        We may collect, use, store, and transfer different kinds of personal data about you which we have grouped together as follows:
                    </p>
                    <ul class="list-disc pl-6 space-y-2 text-gray-600 mb-6">
                        <li><strong>Identity Data:</strong> This includes title, first name, last name, username, and similar identifiers.</li>
                        <li><strong>Contact Data:</strong> This includes email address, phone numbers, and similar contact information.</li>
                        <li><strong>Technical Data:</strong> This includes internet protocol (IP) address, browser type and version, time zone setting, and other technical identifiers.</li>
                        <li><strong>Usage Data:</strong> This includes information about how you use our website, products, and services.</li>
                        <li><strong>Marketing and Communications Data:</strong> This includes your preferences in receiving marketing from us and your communication preferences.</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">How We Collect Information</h3>
                    <ul class="list-disc pl-6 space-y-2 text-gray-600">
                        <li>When you voluntarily provide information to us through forms on our website</li>
                        <li>When you interact with our website and services (automatically collected)</li>
                        <li>When you communicate with us via email, phone, or other channels</li>
                        <li>Through third-party services and integrations you connect with our platform</li>
                    </ul>
                </div>

                <!-- How We Use Your Data -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">How We Use Your Information</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        We use your personal data for the following purposes:
                    </p>
                    <ul class="list-disc pl-6 space-y-2 text-gray-600 mb-6">
                        <li><strong>Service Provision:</strong> To provide, maintain, and improve our services</li>
                        <li><strong>Communication:</strong> To communicate with you about your account and our services</li>
                        <li><strong>Customer Support:</strong> To respond to your inquiries, comments, and questions</li>
                        <li><strong>Security:</strong> To protect our services and prevent fraud</li>
                        <li><strong>Legal Compliance:</strong> To comply with legal obligations and protect our rights</li>
                        <li><strong>Marketing:</strong> To send you promotional materials (with your consent)</li>
                        <li><strong>Analytics:</strong> To analyze usage patterns and improve our services</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Legal Basis for Processing</h3>
                    <p class="text-gray-600 leading-relaxed">
                        We process your personal data lawfully based on one of the following legal bases: your consent, performance of a contract, legal obligation, legitimate interests, or vital interests.
                    </p>
                </div>

                <!-- Data Sharing -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Data Sharing and Disclosure</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        We may share your personal data with third parties in the following circumstances:
                    </p>
                    <ul class="list-disc pl-6 space-y-2 text-gray-600 mb-6">
                        <li><strong>Service Providers:</strong> With third-party service providers who assist us in operating our website and services</li>
                        <li><strong>Business Transfers:</strong> In connection with a merger, acquisition, or sale of assets</li>
                        <li><strong>Legal Requirements:</strong> When required by law, court order, or other legal process</li>
                        <li><strong>Protection of Rights:</strong> To protect our rights, property, or safety, or that of our users</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">International Data Transfers</h3>
                    <p class="text-gray-600 leading-relaxed">
                        Your personal data may be transferred to and processed in countries other than your own. We ensure appropriate safeguards are in place for such transfers in accordance with applicable data protection laws.
                    </p>
                </div>

                <!-- Data Security -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Data Security</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        We implement appropriate technical and organizational measures to protect your personal data against unauthorized access, alteration, disclosure, or destruction. These include:
                    </p>
                    <ul class="list-disc pl-6 space-y-2 text-gray-600 mb-6">
                        <li>SSL/TLS encryption for data in transit</li>
                        <li>Encryption at rest for stored data</li>
                        <li>Regular security assessments and audits</li>
                        <li>Access controls and authentication mechanisms</li>
                        <li>Employee training on data protection</li>
                    </ul>
                    <p class="text-gray-600 leading-relaxed">
                        However, please note that no method of transmission over the internet is 100% secure, and we cannot guarantee absolute security.
                    </p>
                </div>

                <!-- Your Rights -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Your Data Protection Rights</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        Depending on your location, you may have the following rights regarding your personal data:
                    </p>
                    <ul class="list-disc pl-6 space-y-2 text-gray-600 mb-6">
                        <li><strong>Access:</strong> The right to request a copy of your personal data</li>
                        <li><strong>Rectification:</strong> The right to correct inaccurate personal data</li>
                        <li><strong>Erasure:</strong> The right to request deletion of your personal data</li>
                        <li><strong>Restriction:</strong> The right to restrict processing of your personal data</li>
                        <li><strong>Portability:</strong> The right to receive your data in a structured format</li>
                        <li><strong>Objection:</strong> The right to object to processing of your personal data</li>
                        <li><strong>Withdraw Consent:</strong> The right to withdraw consent at any time</li>
                    </ul>
                    <p class="text-gray-600 leading-relaxed">
                        To exercise these rights, please contact us using the information provided below.
                    </p>
                </div>

                <!-- Data Retention -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Data Retention</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        We retain your personal data only as long as necessary for the purposes described in this privacy policy, unless a longer retention period is required or permitted by law.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        When determining the appropriate retention period, we consider the amount, nature, and sensitivity of the personal data, the potential risk of harm from unauthorized use or disclosure, and the applicable legal requirements.
                    </p>
                </div>

                <!-- Cookies -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Cookies and Tracking Technologies</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        We use cookies and similar tracking technologies to enhance your experience on our website. Types of cookies we use include:
                    </p>
                    <ul class="list-disc pl-6 space-y-2 text-gray-600 mb-6">
                        <li><strong>Essential Cookies:</strong> Required for the website to function properly</li>
                        <li><strong>Performance Cookies:</strong> Help us understand how our website is being used</li>
                        <li><strong>Functional Cookies:</strong> Enable enhanced functionality and personalization</li>
                        <li><strong>Marketing Cookies:</strong> Used to deliver relevant advertisements (with consent)</li>
                    </ul>
                    <p class="text-gray-600 leading-relaxed">
                        You can control cookie settings through your browser preferences. However, disabling certain cookies may affect website functionality.
                    </p>
                </div>

                <!-- Third-Party Websites -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Third-Party Websites</h2>
                    <p class="text-gray-600 leading-relaxed">
                        Our website may contain links to third-party websites. We are not responsible for the privacy practices of these websites. We encourage you to review the privacy policies of any third-party websites you visit.
                    </p>
                </div>

                <!-- Children's Privacy -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Children's Privacy</h2>
                    <p class="text-gray-600 leading-relaxed">
                        Our services are not intended for children under the age of 13. We do not knowingly collect personal data from children under 13. If we become aware that we have collected personal data from a child under 13, we will take steps to delete such information immediately.
                    </p>
                </div>

                <!-- Changes to This Policy -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Changes to This Privacy Policy</h2>
                    <p class="text-gray-600 leading-relaxed">
                        We may update this privacy policy from time to time. We will notify you of any changes by posting the new policy on this page and updating the "Last updated" date at the top of this policy.
                    </p>
                </div>

                <!-- Contact Information -->
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Contact Us</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        If you have any questions about this privacy policy or our data practices, please contact us:
                    </p>
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <p class="text-gray-600 mb-2"><strong>Email:</strong> privacy@example.com</p>
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
                <a href="{{ route('terms') }}" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow duration-200 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-purple-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="font-semibold text-gray-900">Terms & Conditions</h3>
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
