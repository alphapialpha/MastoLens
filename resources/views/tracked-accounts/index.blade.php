@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Tracked Accounts</h1>
        <a href="{{ route('tracked-accounts.create') }}"
           class="inline-flex items-center px-4 py-2 bg-brand-dark text-white text-sm font-medium rounded-md hover:bg-brand-deep">
            + Add Account
        </a>
    </div>

    @if($accounts->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <p class="text-gray-500 mb-4">No tracked accounts yet.</p>
            <a href="{{ route('tracked-accounts.create') }}"
               class="inline-flex items-center px-4 py-2 bg-brand-dark text-white text-sm font-medium rounded-md hover:bg-brand-deep">
                Add Your First Account
            </a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($accounts as $account)
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            @if($account->avatar_url)
                                <img src="{{ $account->avatar_url }}" alt="" class="w-12 h-12 rounded-full">
                            @else
                                <div class="w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center text-gray-500 font-bold">
                                    {{ strtoupper(substr($account->username, 0, 1)) }}
                                </div>
                            @endif
                            <div>
                                <div class="text-lg font-medium text-brand-dark">
                                    {{ $account->display_name ?: $account->username }}
                                </div>
                                <div class="text-sm text-gray-500">{{ $account->acct_normalized }}</div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            @if($account->is_active)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800 ring-1 ring-green-200">Active</span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-gray-100 text-gray-800 ring-1 ring-gray-300">Paused</span>
                            @endif
                            <form method="POST" action="{{ route('tracked-accounts.toggle', $account) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium bg-brand-dark text-white hover:bg-brand-deep">
                                    {{ $account->is_active ? 'Pause' : 'Resume' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('tracked-accounts.destroy', $account) }}"
                                  onsubmit="return confirm('Remove this tracked account?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium bg-red-600 text-white hover:bg-red-700">Remove</button>
                            </form>
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-4 gap-4 text-sm text-gray-500">
                        <div>
                            <span class="font-medium text-gray-700">{{ $account->followers_count_latest !== null ? number_format($account->followers_count_latest) : '—' }}</span> followers
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">{{ $account->statuses_count_latest !== null ? number_format($account->statuses_count_latest) : '—' }}</span> posts
                        </div>
                        <div>
                            Last sync: {{ $account->last_sync_finished_at ? $account->last_sync_finished_at->diffForHumans() : 'Never' }}
                        </div>
                        <div>
                            @if($account->last_sync_status === 'error')
                                <span class="text-red-600">{{ $account->last_sync_error ?: 'Sync error' }}</span>
                            @elseif($account->last_sync_status)
                                {{ ucfirst($account->last_sync_status) }}
                            @else
                                Pending first sync
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
