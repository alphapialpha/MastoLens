@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Profile Settings</h1>

    {{-- Flash messages --}}
    @if (session('two-factor-status'))
        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
            {{ session('two-factor-status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Account Info --}}
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Account</h2>
        <dl class="space-y-2">
            <div class="flex justify-between">
                <dt class="text-gray-500">Name</dt>
                <dd>{{ $user->name }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Email</dt>
                <dd>{{ $user->email }}</dd>
            </div>
        </dl>
    </div>

    {{-- Two-Factor Authentication --}}
    <div class="bg-white shadow rounded-lg p-6" x-data="twoFactor()" x-cloak>
        <h2 class="text-lg font-semibold mb-2">Two-Factor Authentication</h2>
        <p class="text-gray-500 text-sm mb-4">
            Add an extra layer of security using a TOTP authenticator app (Google Authenticator, Authy, 1Password, etc.).
        </p>

        @if ($twoFactorEnabled)
            {{-- 2FA is active --}}
            <div class="flex items-center gap-2 mb-4">
                <span class="inline-block w-3 h-3 bg-green-500 rounded-full"></span>
                <span class="font-medium text-green-700">Two-factor authentication is enabled.</span>
            </div>

            {{-- Show recovery codes --}}
            <div class="mb-4">
                <button @click="showRecovery = !showRecovery; if (showRecovery && !recoveryCodes.length) fetchRecoveryCodes()"
                        class="text-sm text-brand-dark hover:text-brand-deep underline">
                    <span x-text="showRecovery ? 'Hide Recovery Codes' : 'Show Recovery Codes'"></span>
                </button>
                <div x-show="showRecovery" class="mt-3">
                    <p class="text-sm text-gray-500 mb-2">Store these recovery codes in a secure location. Each can be used once to regain access if you lose your authenticator device.</p>
                    <div class="bg-gray-50 border rounded p-4 font-mono text-sm grid grid-cols-2 gap-1">
                        <template x-for="code in recoveryCodes" :key="code">
                            <span x-text="code"></span>
                        </template>
                    </div>
                    <form method="POST" action="/user/two-factor-recovery-codes" class="mt-2">
                        @csrf
                        <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 underline">
                            Regenerate Recovery Codes
                        </button>
                    </form>
                </div>
            </div>

            {{-- Disable 2FA --}}
            <form method="POST" action="{{ route('profile.two-factor.disable') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="disable-password" class="block text-sm font-medium text-gray-700 mb-1">Confirm password to disable</label>
                    <input type="password" name="password" id="disable-password" required
                           class="w-full border-gray-300 rounded-md shadow-sm focus:ring-brand-pink focus:border-brand-pink"
                           placeholder="Enter your password">
                </div>
                <button type="submit"
                        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm">
                    Disable Two-Factor Authentication
                </button>
            </form>

        @else
            {{-- 2FA is not active --}}
            <div class="flex items-center gap-2 mb-4">
                <span class="inline-block w-3 h-3 bg-gray-400 rounded-full"></span>
                <span class="text-gray-600">Two-factor authentication is not enabled.</span>
            </div>

            {{-- Step 1: Confirm password + enable --}}
            <div x-show="!enabling">
                <form @submit.prevent="enable()">
                    <div class="mb-3">
                        <label for="confirm-password" class="block text-sm font-medium text-gray-700 mb-1">Confirm your password to continue</label>
                        <input type="password" id="confirm-password" x-model="password"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-brand-pink focus:border-brand-pink"
                               placeholder="Enter your password" required>
                    </div>
                    <p x-show="error" x-text="error" class="text-sm text-red-600 mb-2"></p>
                    <button type="submit" :disabled="loading"
                            class="px-4 py-2 bg-brand-dark text-white rounded hover:bg-brand-deep text-sm disabled:opacity-50">
                        <span x-show="!loading">Enable Two-Factor Authentication</span>
                        <span x-show="loading">Setting up…</span>
                    </button>
                </form>
            </div>

            {{-- Step 2: Show QR code & confirm --}}
            <div x-show="enabling" class="space-y-4">
                <p class="text-sm text-gray-600">
                    Scan the QR code below with your authenticator app, then enter the 6-digit code to confirm.
                </p>

                <div class="flex justify-center bg-white p-4 border rounded w-fit mx-auto"
                     x-html="qrSvg">
                </div>

                <div class="bg-gray-50 border rounded p-3">
                    <p class="text-xs text-gray-500 mb-1">Or enter this key manually:</p>
                    <code class="text-sm font-mono select-all" x-text="secretKey"></code>
                </div>

                <form method="POST" action="/user/confirmed-two-factor-authentication">
                    @csrf
                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Verification Code</label>
                            <input type="text" name="code" id="code" inputmode="numeric" autocomplete="one-time-code"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-brand-pink focus:border-brand-pink"
                                   placeholder="000000" required autofocus>
                        </div>
                        <button type="submit"
                                class="px-4 py-2 bg-brand-dark text-white rounded hover:bg-brand-deep text-sm whitespace-nowrap">
                            Confirm &amp; Activate
                        </button>
                    </div>
                </form>

                <form method="POST" action="/user/two-factor-authentication">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 underline">
                        Cancel
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function twoFactor() {
    return {
        enabling: false,
        qrSvg: '',
        secretKey: '',
        showRecovery: false,
        recoveryCodes: [],
        password: '',
        error: '',
        loading: false,

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]').content;
        },

        async enable() {
            this.error = '';
            this.loading = true;

            try {
                // Step 1: Confirm password
                const confirmRes = await fetch('/user/confirm-password', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ password: this.password }),
                });

                if (!confirmRes.ok) {
                    const data = await confirmRes.json().catch(() => ({}));
                    this.error = data.message || data.errors?.password?.[0] || 'Password confirmation failed.';
                    this.loading = false;
                    return;
                }

                // Step 2: Enable 2FA
                await fetch('/user/two-factor-authentication', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                });

                // Step 3: Fetch QR code
                const qrRes = await fetch('/user/two-factor-qr-code', {
                    headers: { 'Accept': 'application/json' },
                });
                const qrData = await qrRes.json();
                this.qrSvg = qrData.svg;

                // Step 4: Fetch secret key
                const secretRes = await fetch('/user/two-factor-secret-key', {
                    headers: { 'Accept': 'application/json' },
                });
                const secretData = await secretRes.json();
                this.secretKey = secretData.secretKey;

                this.enabling = true;
            } catch (e) {
                this.error = 'Something went wrong. Please try again.';
            }

            this.loading = false;
        },

        async fetchRecoveryCodes() {
            const res = await fetch('/user/two-factor-recovery-codes', {
                headers: { 'Accept': 'application/json' },
            });
            this.recoveryCodes = await res.json();
        },
    };
}
</script>
@endpush
@endsection
