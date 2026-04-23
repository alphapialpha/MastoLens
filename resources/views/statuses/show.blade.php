@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('tracked-accounts.show', $status->trackedAccount) }}" class="text-sm text-brand-dark hover:text-brand-deep">
            &larr; Back to {{ $status->trackedAccount->display_name ?: $status->trackedAccount->username }}
        </a>
    </div>

    {{-- Status header --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex items-center space-x-2 text-xs text-gray-500 mb-3">
            @if($status->is_boost)
                <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded">Boost</span>
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
            @if($status->has_card)
                <span class="bg-teal-100 text-teal-800 px-2 py-0.5 rounded">Link Card</span>
            @endif
            @if($status->is_sensitive)
                <span class="bg-red-100 text-red-800 px-2 py-0.5 rounded">Sensitive</span>
            @endif
            <span class="{{ $status->tracking_state === 'active' ? 'text-green-600' : 'text-gray-400' }}">
                {{ ucfirst($status->tracking_state) }}
            </span>
        </div>

        @if($status->is_boost && $status->boost_data_json)
            <div class="flex items-center space-x-3 mb-4 p-3 bg-blue-50 rounded-lg">
                @if(!empty($status->boost_data_json['author_avatar']))
                    <img src="{{ $status->boost_data_json['author_avatar'] }}" alt="" class="w-10 h-10 rounded-full" loading="lazy">
                @endif
                <div>
                    <div class="text-sm font-medium text-gray-800">
                        Boosted a post by {{ $status->boost_data_json['author_display_name'] ?? 'unknown' }}
                    </div>
                    @if(!empty($status->boost_data_json['author_acct']))
                        <div class="text-xs text-gray-500">
                            @if(!empty($status->boost_data_json['author_url']))
                                <a href="{{ $status->boost_data_json['author_url'] }}" target="_blank" rel="noopener" class="hover:text-brand-dark">
                                    {{ '@' . $status->boost_data_json['author_acct'] }}
                                </a>
                            @else
                                {{ '@' . $status->boost_data_json['author_acct'] }}
                            @endif
                        </div>
                    @endif
                </div>
                @if(!empty($status->boost_data_json['original_url']))
                    <a href="{{ $status->boost_data_json['original_url'] }}" target="_blank" rel="noopener" class="ml-auto text-xs text-brand-dark hover:text-brand-deep">
                        View original &rarr;
                    </a>
                @endif
            </div>
        @endif

        @if($status->spoiler_text)
            <div class="text-sm font-medium text-amber-700 bg-amber-50 rounded p-2 mb-3">
                CW: {{ $status->spoiler_text }}
            </div>
        @endif

        <div class="text-gray-800 leading-relaxed">
            {{ html_entity_decode(strip_tags($status->content_html)) }}
        </div>

        @if($status->has_media && $status->media_attachments_json)
            <div class="mt-4 grid grid-cols-2 gap-3">
                @foreach($status->media_attachments_json as $media)
                    @if(($media['type'] ?? '') === 'image')
                        <a href="{{ $media['url'] ?? '' }}" target="_blank" rel="noopener" class="block">
                            <img src="{{ $media['preview_url'] ?? $media['url'] ?? '' }}" alt="{{ $media['description'] ?? '' }}" class="w-full rounded-lg object-cover max-h-64" loading="lazy">
                            @if(!empty($media['description']))
                                <p class="text-xs text-gray-500 mt-1">{{ $media['description'] }}</p>
                            @endif
                        </a>
                    @elseif(($media['type'] ?? '') === 'video' || ($media['type'] ?? '') === 'gifv')
                        <video controls preload="metadata" class="w-full rounded-lg max-h-64" poster="{{ $media['preview_url'] ?? '' }}">
                            <source src="{{ $media['url'] ?? '' }}" type="video/mp4">
                        </video>
                    @elseif(($media['type'] ?? '') === 'audio')
                        <div class="bg-gray-50 rounded-lg p-4">
                            <audio controls preload="metadata" class="w-full">
                                <source src="{{ $media['url'] ?? '' }}">
                            </audio>
                            @if(!empty($media['description']))
                                <p class="text-xs text-gray-500 mt-1">{{ $media['description'] }}</p>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="mt-4 flex items-center justify-between text-sm text-gray-500">
            <div class="flex items-center space-x-4">
                <span>{{ $status->created_at_remote?->format('M j, Y g:i A') ?? 'Unknown date' }}</span>
                @if($status->language)
                    <span class="uppercase">{{ $status->language }}</span>
                @endif
                <span>{{ $status->visibility }}</span>
            </div>
            @php
                $viewUrl = ($status->is_boost && !empty($status->boost_data_json['original_url']))
                    ? $status->boost_data_json['original_url']
                    : $status->status_url;
            @endphp
            @if($viewUrl)
                <a href="{{ $viewUrl }}" target="_blank" rel="noopener" class="text-brand-dark hover:text-brand-deep">
                    View on Mastodon &rarr;
                </a>
            @endif
        </div>
    </div>

    {{-- Current totals --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-yellow-600">{{ $status->summary?->latest_favourites_count ?? 0 }}</div>
            <div class="text-xs text-gray-500">Favourites</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-blue-600">{{ $status->summary?->latest_boosts_count ?? 0 }}</div>
            <div class="text-xs text-gray-500">Boosts</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-green-600">{{ $status->summary?->latest_replies_count ?? 0 }}</div>
            <div class="text-xs text-gray-500">Replies</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-brand-dark">{{ $status->summary ? $status->summary->latestTotalEngagement() : 0 }}</div>
            <div class="text-xs text-gray-500">Total Engagement</div>
        </div>
    </div>

    {{-- Milestone engagement --}}
    @if($status->summary && ($status->summary->engagement_after_1h || $status->summary->engagement_after_24h || $status->summary->engagement_after_7d))
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-xl font-bold text-gray-700">{{ $status->summary->engagement_after_1h ?? '—' }}</div>
                <div class="text-xs text-gray-500">After 1 Hour</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-xl font-bold text-gray-700">{{ $status->summary->engagement_after_24h ?? '—' }}</div>
                <div class="text-xs text-gray-500">After 24 Hours</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-xl font-bold text-gray-700">{{ $status->summary->engagement_after_7d ?? '—' }}</div>
                <div class="text-xs text-gray-500">After 7 Days</div>
            </div>
        </div>
    @endif

    {{-- Engagement timeline chart --}}
    @if($status->metricSnapshots->count() > 0)
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Engagement Over Time</h2>
            <div style="position: relative; height: 300px;">
                <canvas id="engagementChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Engagement Breakdown</h2>
            <div style="position: relative; height: 300px;">
                <canvas id="breakdownChart"></canvas>
            </div>
        </div>
    @endif

    {{-- Snapshot history table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <h2 class="text-lg font-semibold px-6 pt-6 pb-2">Snapshot History</h2>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Checkpoint</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actual Age</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Captured</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Favs</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Boosts</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Replies</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                {{-- Synthetic baseline row: post had 0 interactions at time of publishing --}}
                <tr class="bg-gray-50">
                    <td class="px-6 py-3 text-sm text-gray-400 italic">Posted</td>
                    <td class="px-6 py-3 text-sm text-gray-400">0m</td>
                    <td class="px-6 py-3 text-sm text-gray-400">{{ $status->created_at_remote?->format('M j, g:i A') ?? '—' }}</td>
                    <td class="px-6 py-3 text-sm text-right text-gray-400">0</td>
                    <td class="px-6 py-3 text-sm text-right text-gray-400">0</td>
                    <td class="px-6 py-3 text-sm text-right text-gray-400">0</td>
                    <td class="px-6 py-3 text-sm text-right text-gray-400">0</td>
                </tr>
                @forelse($status->metricSnapshots as $snap)
                    <tr>
                        <td class="px-6 py-3 text-sm text-gray-900">{{ $snap->snapshot_target_age_minutes === 0 ? 'Initial' : ($snap->snapshot_target_age_minutes >= 1440 ? round($snap->snapshot_target_age_minutes / 1440, 1) . 'd' : ($snap->snapshot_target_age_minutes >= 60 ? round($snap->snapshot_target_age_minutes / 60, 1) . 'h' : $snap->snapshot_target_age_minutes . 'm')) }}</td>
                        <td class="px-6 py-3 text-sm text-gray-500">{{ number_format($snap->actual_age_minutes) }}m</td>
                        <td class="px-6 py-3 text-sm text-gray-500">{{ $snap->captured_at->format('M j, g:i A') }}</td>
                        <td class="px-6 py-3 text-sm text-right text-yellow-600">{{ $snap->favourites_count }}</td>
                        <td class="px-6 py-3 text-sm text-right text-blue-600">{{ $snap->boosts_count }}</td>
                        <td class="px-6 py-3 text-sm text-right text-green-600">{{ $snap->replies_count }}</td>
                        <td class="px-6 py-3 text-sm text-right font-medium text-gray-900">{{ $snap->totalEngagement() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-sm text-gray-500">No snapshots captured yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Status metadata --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-3">Tracking Details</h2>
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Remote ID</dt>
                <dd class="font-mono text-gray-800">{{ $status->remote_status_id }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">First Seen</dt>
                <dd class="text-gray-800">{{ $status->fetched_first_at?->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Last Fetched</dt>
                <dd class="text-gray-800">{{ $status->fetched_last_at?->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Next Snapshot</dt>
                <dd class="text-gray-800">{{ $status->next_snapshot_due_at?->diffForHumans() ?? ($status->tracking_state === 'archived' ? 'Archived' : '—') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Snapshots</dt>
                <dd class="text-gray-800">{{ $status->summary?->snapshot_count ?? $status->metricSnapshots->count() }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Tracking State</dt>
                <dd class="text-gray-800">{{ ucfirst($status->tracking_state) }}</dd>
            </div>
            @if($status->archived_at)
                <div>
                    <dt class="text-gray-500">Archived At</dt>
                    <dd class="text-gray-800">{{ $status->archived_at->format('M j, Y g:i A') }}</dd>
                </div>
            @endif
        </dl>
    </div>
@endsection

@push('scripts')
@if($status->metricSnapshots->count() > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const snapshots = @json($chartData);

    const labels = snapshots.map(s => s.label);

    // Combined engagement chart
    new Chart(document.getElementById('engagementChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Engagement',
                data: snapshots.map(s => s.total),
                borderColor: 'rgb(99, 102, 241)',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { title: { display: true, text: 'Status Age' } },
                y: { title: { display: true, text: 'Engagement' }, beginAtZero: true }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });

    // Breakdown chart
    new Chart(document.getElementById('breakdownChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Favourites',
                    data: snapshots.map(s => s.favourites),
                    borderColor: 'rgb(234, 179, 8)',
                    backgroundColor: 'rgba(234, 179, 8, 0.1)',
                    tension: 0.3,
                    pointRadius: 3,
                },
                {
                    label: 'Boosts',
                    data: snapshots.map(s => s.boosts),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.3,
                    pointRadius: 3,
                },
                {
                    label: 'Replies',
                    data: snapshots.map(s => s.replies),
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.3,
                    pointRadius: 3,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { title: { display: true, text: 'Status Age' } },
                y: { title: { display: true, text: 'Count' }, beginAtZero: true }
            }
        }
    });
});
</script>
@endif
@endpush
