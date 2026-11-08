@props(['items' => []])

<nav aria-label="Fil d'Ariane" class="breadcrumb">
    <ol>
        @forelse ($items as $item)
            @if ($loop->last)
                <li aria-current="page">{{ $item['label'] }}</li>
            @else
                <li>
                    <a href="{{ $item['url'] ?? url('/') }}">{{ $item['label'] }}</a>
                </li>
            @endif
        @empty
            <li aria-current="page">Stage Tracker</li>
        @endforelse
    </ol>
</nav>
