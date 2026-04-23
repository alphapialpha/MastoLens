@extends('layouts.app')

@section('content')
    <div class="max-w-xl mx-auto">
        <h1 class="text-2xl font-bold mb-6">Add Mastodon Account</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <form method="POST" action="{{ route('tracked-accounts.store') }}">
                @csrf

                <div class="mb-4">
                    <label for="handle" class="block text-sm font-medium text-gray-700 mb-1">
                        Mastodon Handle
                    </label>
                    <input type="text" name="handle" id="handle"
                           value="{{ old('handle') }}"
                           placeholder="user@instance.tld"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-pink focus:ring-brand-pink"
                           required>
                    @error('handle')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">
                        Enter a public Mastodon handle, e.g. <code class="text-gray-700">alphapialpha@alphapialpha.social</code>
                    </p>
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ route('tracked-accounts.index') }}" class="text-sm text-gray-600 hover:text-gray-800">Cancel</a>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-brand-dark text-white text-sm font-medium rounded-md hover:bg-brand-deep">
                        Add Account
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
