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
                    'quotes' => 'Quotes',
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
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($monthStatuses as $status)
                        <a href="{{ route('statuses.show', $status) }}" class="flex flex-col bg-white rounded-lg shadow p-4 hover:ring-2 hover:ring-brand-pink/40 transition">
                            {{-- Badges + date --}}
                            <div class="flex flex-wrap items-center gap-1 text-xs text-gray-500 mb-2">
                                @if($status->is_boost)
                                    <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded">Boost</span>
                                    @if($status->boost_data_json)
                                        <span class="text-gray-500 truncate max-w-[120px]">
                                            of <span class="font-medium text-gray-700">{{ $status->boost_data_json['author_display_name'] ?? $status->boost_data_json['author_acct'] ?? 'unknown' }}</span>
                                        </span>
                                    @endif
                                @endif
                                @if($status->is_reply)
                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded">Reply</span>
                                @endif
                                @if($status->is_quote)
                                    <span class="bg-violet-100 text-violet-800 px-2 py-0.5 rounded">Quote</span>
                                @endif
                                @if($status->has_media)
                                    <span class="bg-purple-100 text-purple-800 px-2 py-0.5 rounded">Media</span>
                                @endif
                                @if($status->has_poll)
                                    <span class="bg-orange-100 text-orange-800 px-2 py-0.5 rounded">Poll</span>
                                @endif
                                <span class="ml-auto">{{ $status->created_at_remote?->format('M j, Y') ?? 'Unknown date' }}</span>
                            </div>

                            {{-- Post text --}}
                            <div class="text-sm text-gray-800 line-clamp-2 flex-1">
                                {{ html_entity_decode(strip_tags($status->content_html), ENT_QUOTES | ENT_HTML5, 'UTF-8') }}
                            </div>

                            {{-- Media thumbnails --}}
                            @if($status->has_media && $status->media_attachments_json)
                                <div class="mt-2 flex space-x-1 overflow-hidden">
                                    @foreach(array_slice($status->media_attachments_json, 0, 3) as $media)
                                        @if(($media['type'] ?? '') === 'image')
                                            <img src="{{ $media['preview_url'] ?? $media['url'] ?? '' }}" alt="{{ $media['description'] ?? '' }}" class="h-16 w-auto rounded object-cover flex-shrink-0" loading="lazy">
                                        @elseif(($media['type'] ?? '') === 'video' || ($media['type'] ?? '') === 'gifv')
                                            <div class="h-16 w-24 rounded bg-gray-100 flex items-center justify-center text-gray-400 text-xs flex-shrink-0">▶ Video</div>
                                        @elseif(($media['type'] ?? '') === 'audio')
                                            <div class="h-16 w-24 rounded bg-gray-100 flex items-center justify-center text-gray-400 text-xs flex-shrink-0">♫ Audio</div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            {{-- Engagement stats --}}
                            <div class="mt-3 pt-2 border-t border-gray-100 flex items-center gap-3 text-xs text-gray-500 flex-wrap">
                                <span>⭐ {{ $status->summary?->latest_favourites_count ?? 0 }}</span>
                                <span>🔁 {{ $status->summary?->latest_boosts_count ?? 0 }}</span>
                                <span>💬 {{ $status->summary?->latest_replies_count ?? 0 }}</span>
                                <span>❝ {{ $status->summary?->latest_quotes_count ?? 0 }}</span>
                                @if($status->summary)
                                    <span class="font-medium text-gray-700">{{ $status->summary->latestTotalEngagement() }} total</span>
                                    <span class="text-gray-400">{{ $status->summary->snapshot_count }} snaps</span>
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
