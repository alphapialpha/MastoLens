<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/logo.png') }}">

    <title>{{ $title ?? config('app.name', 'MastoLens') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900 min-h-screen flex flex-col">
    <main class="flex-1 flex items-center justify-center">
    <div class="w-full max-w-md px-6 py-8">
        <div class="text-center mb-8">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-2xl font-bold tracking-tight">
                <img src="{{ asset('images/logo.png') }}" alt="MastoLens" class="h-8 w-auto">
                <span><span class="text-brand-dark">Masto</span><span class="text-brand-grey">Lens</span></span>
            </a>
        </div>

        @if (session('status'))
            <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </div>
    </main>

    @include('layouts.partials.footer')
</body>
</html>
