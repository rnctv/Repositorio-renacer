@if ($paginator->hasPages())
    <ul class="pagination">
        {{-- Prev --}}
        @if ($paginator->onFirstPage())
            <li><span class="chev" aria-hidden="true">‹</span></li>
        @else
            <li><a class="chev" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹</a></li>
        @endif

        {{-- Números / separadores --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <li><span>{{ $element }}</span></li>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <li class="active"><span>{{ $page }}</span></li>
                    @else
                        <li><a href="{{ $url }}">{{ $page }}</a></li>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <li><a class="chev" href="{{ $paginator->nextPageUrl() }}" rel="next">›</a></li>
        @else
            <li><span class="chev" aria-hidden="true">›</span></li>
        @endif
    </ul>
@endif
