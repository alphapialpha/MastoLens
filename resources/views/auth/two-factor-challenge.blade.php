@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto mt-10">
    <div class="bg-white shadow rounded-lg p-6">
        <h1 class="text-xl font-bold mb-2">Two-Factor Authentication</h1>
        <p class="text-gray-500 text-sm mb-6">
            Enter the 6-digit code from your authenticator app to continue.
        </p>

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded text-sm">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ url('/two-factor-challenge') }}" id="2fa-form">
            @csrf

            {{-- TOTP code input --}}
            <div id="code-section">
                <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Authentication Code</label>
                <input type="text" name="code" id="code" inputmode="numeric" autocomplete="one-time-code"
                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-brand-pink focus:border-brand-pink mb-4"
                       placeholder="000000" autofocus>
            </div>

            {{-- Recovery code input (hidden by default) --}}
            <div id="recovery-section" class="hidden">
                <label for="recovery_code" class="block text-sm font-medium text-gray-700 mb-1">Recovery Code</label>
                <input type="text" name="recovery_code" id="recovery_code"
                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-brand-pink focus:border-brand-pink mb-4"
                       placeholder="xxxxx-xxxxx">
            </div>

            <button type="submit"
                    class="w-full px-4 py-2 bg-brand-dark text-white rounded hover:bg-brand-deep text-sm font-medium">
                Verify
            </button>
        </form>

        <div class="mt-4 text-center">
            <button onclick="toggleRecovery()" class="text-sm text-brand-dark hover:text-brand-deep underline" id="toggle-btn">
                Use a recovery code instead
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
let usingRecovery = false;
function toggleRecovery() {
    usingRecovery = !usingRecovery;
    document.getElementById('code-section').classList.toggle('hidden', usingRecovery);
    document.getElementById('recovery-section').classList.toggle('hidden', !usingRecovery);
    document.getElementById('toggle-btn').textContent = usingRecovery
        ? 'Use an authentication code instead'
        : 'Use a recovery code instead';

    // Clear and focus the visible input
    const target = usingRecovery ? 'recovery_code' : 'code';
    const other = usingRecovery ? 'code' : 'recovery_code';
    document.getElementById(other).value = '';
    document.getElementById(target).focus();
}
</script>
@endpush
@endsection
