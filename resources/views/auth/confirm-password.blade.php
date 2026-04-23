@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto mt-10">
    <div class="bg-white shadow rounded-lg p-6">
        <h1 class="text-xl font-bold mb-2">Confirm Password</h1>
        <p class="text-gray-500 text-sm mb-6">
            Please confirm your password before continuing.
        </p>

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded text-sm">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ url('/user/confirm-password') }}">
            @csrf
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" id="password" required autofocus
                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-brand-pink focus:border-brand-pink">
            </div>
            <button type="submit"
                    class="w-full px-4 py-2 bg-brand-dark text-white rounded hover:bg-brand-deep text-sm font-medium">
                Confirm
            </button>
        </form>
    </div>
</div>
@endsection
