@props([
    'icon',
    'title',
    'description'
])

<div class="bg-white rounded-xl shadow-lg p-6 card-hover group">
    <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-300">
        <div class="text-white text-2xl">
            {!! $icon !!}
        </div>
    </div>
    <h3 class="text-xl font-semibold text-gray-900 mb-3">
        {{ $title }}
    </h3>
    <p class="text-gray-600 leading-relaxed">
        {{ $description }}
    </p>
</div>
