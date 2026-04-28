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
    <nav class="bg-white shadow sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <a href="{{ url('/') }}" class="flex items-center space-x-2">
                    <img src="{{ asset('images/logo.png') }}" alt="MastoLens" class="h-8 w-auto">
                    <span class="text-xl font-bold tracking-tight"><span class="text-brand-dark">Masto</span><span class="text-brand-grey">Lens</span></span>
                </a>
                <div class="flex items-center">
                    @auth
                        {{-- Navigation group --}}
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center px-3 py-1 rounded-md text-sm {{ request()->routeIs('dashboard') || request()->routeIs('tracked-accounts.show') || request()->routeIs('tracked-accounts.archive') || request()->routeIs('statuses.show') ? 'bg-brand-pink/10 text-brand-dark font-medium' : 'text-gray-600 hover:text-brand-dark hover:bg-gray-50' }}">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/></svg>
                            Dashboard
                        </a>
                        <div class="h-5 w-px bg-gray-300 mx-1"></div>
                        <a href="{{ route('tracked-accounts.index') }}" class="inline-flex items-center px-3 py-1 rounded-md text-sm {{ request()->routeIs('tracked-accounts.index') || request()->routeIs('tracked-accounts.create') ? 'bg-brand-pink/10 text-brand-dark font-medium' : 'text-gray-600 hover:text-brand-dark hover:bg-gray-50' }}">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Manage Accounts
                        </a>

                        {{-- Spacer --}}
                        <div class="h-5 w-px bg-gray-300 mx-3"></div>

                        {{-- User group --}}
                        <a href="{{ route('profile') }}" class="inline-flex items-center px-3 py-1 rounded-md text-sm {{ request()->routeIs('profile') ? 'bg-brand-pink/10 text-brand-dark font-medium' : 'text-gray-600 hover:text-brand-dark hover:bg-gray-50' }}">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            My Profile
                        </a>
                        <div class="h-5 w-px bg-gray-300 mx-1"></div>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-1 rounded-md text-sm text-gray-600 hover:text-brand-dark hover:bg-gray-50">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Logout
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center px-3 py-1 rounded-md text-sm text-gray-600 hover:text-brand-dark hover:bg-gray-50">Login</a>
                        @if(Route::has('register'))
                            <div class="h-5 w-px bg-gray-300 mx-1"></div>
                            <a href="{{ route('register') }}" class="inline-flex items-center px-3 py-1 rounded-md text-sm text-gray-600 hover:text-brand-dark hover:bg-gray-50">Register</a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-1 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    @include('layouts.partials.footer')

    @stack('scripts')
</body>
</html>
