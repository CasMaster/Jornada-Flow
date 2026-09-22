@if($paginator->hasPages())
    <nav class="directory-pagination" aria-label="Paginação">
        @if($paginator->onFirstPage())
            <span class="pagination-disabled" aria-disabled="true">← Anterior</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">← Anterior</a>
        @endif
        <strong aria-current="page">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</strong>
        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Próxima →</a>
        @else
            <span class="pagination-disabled" aria-disabled="true">Próxima →</span>
        @endif
    </nav>
@endif
