@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('tracked-accounts.show', $trackedAccount) }}" class="text-sm text-brand-dark hover:text-brand-deep">&larr; Back to {{ $trackedAccount->display_name ?: $trackedAccount->username }}</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex items-center space-x-4">
            @if($trackedAccount->avatar_url)
                <img src="{{ $trackedAccount->avatar_url }}" alt="" class="w-12 h-12 rounded-full">
            @else
                <div class="w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center text-gray-500 text-lg font-bold">
                    {{ strtoupper(substr($trackedAccount->username, 0, 1)) }}
                </div>
            @endif
            <div>
                <h1 class="text-xl font-bold">Archive — {{ $trackedAccount->display_name ?: $trackedAccount->username }}</h1>
                <p class="text-sm text-gray-500">{{ number_format($archivedCount) }} archived {{ Str::plural('post', $archivedCount) }}</p>
            </div>
        </div>
    </div>

    {{-- Type filters --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-1">
            @php
                $filters = [
                    'all' => 'All',
                    'originals' => 'Originals',
                    'replies' => 'Replies',
                    'boosts' => 'Boosts',
                    'media' => 'Media',
                ];
            @endphp
            @foreach($filters as $key => $label)
                <a href="{{ route('tracked-accounts.archive', ['trackedAccount' => $trackedAccount, 'filter' => $key]) }}"
                   class="px-3 py-1 rounded-full text-sm {{ $filter === $key ? 'bg-brand-dark text-white' : 'bg-white text-gray-600 hover:bg-gray-100' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @if($statuses->isEmpty())
        <div class="bg-white rounded-lg shadow p-6 text-center text-gray-500">
            @if($filter !== 'all')
                No archived {{ strtolower($filters[$filter] ?? 'matching') }} statuses found.
                <a href="{{ route('tracked-accounts.archive', $trackedAccount) }}" class="text-brand-dark hover:text-brand-deep ml-1">Show all</a>
            @else
                No archived statuses yet.
            @endif
        </div>
    @else
        @foreach($groupedStatuses as $month => $monthStatuses)
            <div class="mb-6">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ $month }}</h2>
                <div class="space-y-3">
                    @foreach($monthStatuses as $status)
                        <a href="{{ route('statuses.show', $status) }}" class="block bg-white rounded-lg shadow p-4 hover:ring-2 hover:ring-brand-pink/40 transition">
                            <div class="flex items-center space-x-2 text-xs text-gray-500 mb-2">
                                @if($status->is_boost)
                                    <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded">Boost</span>
                                    @if($status->boost_data_json)
                                        <span class="text-gray-500">
                                            of <span class="font-medium text-gray-700">{{ $status->boost_data_json['author_display_name'] ?? $status->boost_data_json['author_acct'] ?? 'unknown' }}</span>
                                            @if(!empty($status->boost_data_json['author_acct']))
                                                <span class="text-gray-400">({{ $status->boost_data_json['author_acct'] }})</span>
                                            @endif
                                        </span>
                                    @endif
                                @endif
                                @if($status->is_reply)
                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded">Reply</span>
                                @endif
                                @if($status->has_media)
                                    <span class="bg-purple-100 text-purple-800 px-2 py-0.5 rounded">Media</span>
                                @endif
                                @if($status->has_poll)
                                    <span class="bg-orange-100 text-orange-800 px-2 py-0.5 rounded">Poll</span>
                                @endif
                                <span>{{ $status->created_at_remote?->format('M j, Y \a\t g:ia') ?? 'Unknown date' }}</span>
                            </div>
                            <div class="text-sm text-gray-800 line-clamp-3">
                                {{ html_entity_decode(strip_tags($status->content_html)) }}
                            </div>
                            @if($status->has_media && $status->media_attachments_json)
                                <div class="mt-2 flex space-x-2 overflow-x-auto">
                                    @foreach(array_slice($status->media_attachments_json, 0, 4) as $media)
                                        @if(($media['type'] ?? '') === 'image')
                                            <img src="{{ $media['preview_url'] ?? $media['url'] ?? '' }}" alt="{{ $media['description'] ?? '' }}" class="h-20 w-auto rounded object-cover" loading="lazy">
                                        @elseif(($media['type'] ?? '') === 'video' || ($media['type'] ?? '') === 'gifv')
                                            <div class="h-20 w-32 rounded bg-gray-100 flex items-center justify-center text-gray-400 text-xs">▶ Video</div>
                                        @elseif(($media['type'] ?? '') === 'audio')
                                            <div class="h-20 w-32 rounded bg-gray-100 flex items-center justify-center text-gray-400 text-xs">♫ Audio</div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                            <div class="mt-2 flex items-center space-x-4 text-xs text-gray-500">
                                <span>⭐ {{ $status->summary?->latest_favourites_count ?? 0 }}</span>
                                <span>🔁 {{ $status->summary?->latest_boosts_count ?? 0 }}</span>
                                <span>💬 {{ $status->summary?->latest_replies_count ?? 0 }}</span>
                                @if($status->summary)
                                    <span class="font-medium text-gray-700">Total: {{ $status->summary->latestTotalEngagement() }}</span>
                                    <span class="text-gray-400">{{ $status->summary->snapshot_count }} snapshots</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $statuses->appends(['filter' => $filter])->links() }}
        </div>
    @endif
@endsection
