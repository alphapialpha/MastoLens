@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Dashboard</h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-indigo-400">
            <div class="text-sm font-medium text-gray-500">Tracked Accounts</div>
            <div class="mt-1 text-3xl font-bold text-gray-900">{{ $accountCount }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-400">
            <div class="text-sm font-medium text-gray-500">Active Accounts</div>
            <div class="mt-1 text-3xl font-bold text-gray-900">{{ $activeCount }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-400">
            <div class="text-sm font-medium text-gray-500">Tracked Statuses</div>
            <div class="mt-1 text-3xl font-bold text-gray-900">{{ $statusCount }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-400">
            <div class="text-sm font-medium text-gray-500">Failed Posts</div>
            <div class="mt-1 text-3xl font-bold {{ $failedCount > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $failedCount }}</div>
        </div>
    </div>

    @if($accounts->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <p class="text-gray-500 mb-4">You haven't added any Mastodon accounts yet.</p>
            <a href="{{ route('tracked-accounts.create') }}"
               class="inline-flex items-center px-4 py-2 bg-brand-dark text-white text-sm font-medium rounded-md hover:bg-brand-deep">
                Add Your First Account
            </a>
        </div>
    @else
        <h2 class="text-lg font-semibold mb-4">Your Tracked Accounts</h2>
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Sync</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Followers</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($accounts as $account)
                        <tr class="hover:bg-brand-pink/10 cursor-pointer transition" onclick="window.location='{{ route('tracked-accounts.show', $account) }}'">
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    @if($account->avatar_url)
                                        <img src="{{ $account->avatar_url }}" alt="" class="w-10 h-10 rounded-full">
                                    @else
                                        <div class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center text-gray-500 text-sm font-bold">
                                            {{ strtoupper(substr($account->username, 0, 1)) }}
                                        </div>
                                    @endif
                                    <div>
                                        <div class="text-sm font-medium text-brand-dark">
                                            {{ $account->display_name ?: $account->username }}
                                        </div>
                                        <div class="text-sm text-gray-500">{{ $account->acct_normalized }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    @if($account->is_active)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Paused</span>
                                    @endif
                                    @if(($failedCounts[$account->id] ?? 0) > 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            {{ $failedCounts[$account->id] }} failed
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $account->last_sync_finished_at ? $account->last_sync_finished_at->diffForHumans() : 'Never' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $account->followers_count_latest !== null ? number_format($account->followers_count_latest) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
