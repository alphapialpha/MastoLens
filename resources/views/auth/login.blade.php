@extends('layouts.guest')

@section('content')
    <div class="bg-white p-8 rounded-lg shadow">
        <h2 class="text-xl font-bold mb-6 text-center">Login</h2>

        @if ($errors->any())
            <div class="mb-4 p-4 bg-red-100 text-red-700 rounded text-sm">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-brand-pink">
            </div>

            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input id="password" type="password" name="password" required
                    class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-brand-pink">
            </div>

            <div class="mb-4 flex items-center">
                <input id="remember" type="checkbox" name="remember" class="mr-2">
                <label for="remember" class="text-sm text-gray-600">Remember me</label>
            </div>

            <button type="submit"
                class="w-full bg-brand-dark text-white py-2 px-4 rounded hover:bg-brand-deep focus:outline-none focus:ring-2 focus:ring-brand-pink">
                Login
            </button>

            <div class="mt-4 text-center text-sm">
                <a href="{{ route('password.request') }}" class="text-brand-dark hover:underline">Forgot your password?</a>
            </div>
            @if(Route::has('register'))
            <div class="mt-2 text-center text-sm">
                <a href="{{ route('register') }}" class="text-brand-dark hover:underline">Don't have an account? Register</a>
            </div>
            @endif
        </form>
    </div>
@endsection
