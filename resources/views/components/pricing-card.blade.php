@props([
    'planName',
    'price',
    'period',
    'features',
    'featured' => false,
    'ctaText' => 'Get Started',
    'ctaUrl' => '#'
])

<div class="bg-white rounded-xl shadow-lg overflow-hidden card-hover {{ $featured ? 'ring-2 ring-blue-500 transform scale-105' : '' }}">
    @if($featured)
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white text-center py-2 text-sm font-semibold">
            MOST POPULAR
        </div>
    @endif

    <div class="p-8">
        <h3 class="text-2xl font-bold text-gray-900 mb-2">
            {{ $planName }}
        </h3>

        <div class="mb-6">
            <span class="text-4xl font-bold text-gray-900">${{ $price }}</span>
            <span class="text-gray-600">{{ $period }}</span>
        </div>

        <ul class="space-y-3 mb-8">
            @foreach($features as $feature)
                <li class="flex items-start">
                    <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span class="text-gray-600">{{ $feature }}</span>
                </li>
            @endforeach
        </ul>

        <a href="{{ $ctaUrl }}"
           class="w-full block text-center py-3 px-6 rounded-lg font-medium transition-all duration-200 transform hover:scale-105 {{
               $featured
               ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white hover:from-blue-600 hover:to-purple-700'
               : 'bg-gray-100 text-gray-900 hover:bg-gray-200'
           }}">
            {{ $ctaText }}
        </a>
    </div>
</div>
