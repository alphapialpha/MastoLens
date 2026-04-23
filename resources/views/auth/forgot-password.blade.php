@extends('layouts.guest')

@section('content')
    <div class="bg-white p-8 rounded-lg shadow">
        <h2 class="text-xl font-bold mb-6 text-center">Forgot Password</h2>
        <p class="text-sm text-gray-600 mb-6 text-center">Enter your email and we'll send you a password reset link.</p>

        @if ($errors->any())
            <div class="mb-4 p-4 bg-red-100 text-red-700 rounded text-sm">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-brand-pink">
            </div>

            <button type="submit"
                class="w-full bg-brand-dark text-white py-2 px-4 rounded hover:bg-brand-deep focus:outline-none focus:ring-2 focus:ring-brand-pink">
                Send Reset Link
            </button>

            <div class="mt-4 text-center text-sm">
                <a href="{{ route('login') }}" class="text-brand-dark hover:underline">Back to login</a>
            </div>
        </form>
    </div>
@endsection
