<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- SEO Meta Tags -->
    <title>{{ $title ?? 'Welcome to ' . config('app.name') }}</title>
    <meta name="description" content="{{ $description ?? 'Modern web application built with Laravel and Livewire' }}">
    <meta name="keywords" content="{{ $keywords ?? 'laravel, livewire, web development' }}">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="{{ $title ?? 'Welcome to ' . config('app.name') }}">
    <meta property="og:description" content="{{ $description ?? 'Modern web application built with Laravel and Livewire' }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">

    <!-- Favicon -->
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=poppins:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
 

    <!-- Livewire Styles -->
    @livewireStyles

    <!-- Custom Styles -->
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        .gradient-mesh {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .hero-gradient {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Fade in animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.8s ease-out;
        }

        /* Gradient text animation */
        @keyframes gradient {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }

        .gradient-animate {
            background-size: 200% 200%;
            animation: gradient 3s ease infinite;
        }

        /* Pulse animation */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(59, 130, 246, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(59, 130, 246, 0);
            }
        }

        .pulse-on-hover:hover {
            animation: pulse 2s infinite;
        }

        /* Smooth transitions */
        * {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
    <!-- Navigation -->
    <x-navbar />

    <!-- Main Content -->
    <main>
        {{ $slot }}
    </main>

    <!-- Footer -->
    <x-footer />

    <!-- Livewire Scripts -->
    @livewireScripts
</body>
</html>
