<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PaginationThemeTest extends TestCase
{
    public function test_shared_pagination_uses_accessible_portuguese_controls(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 25), 50, 25, 1, ['path' => '/registros']);

        $html = view('components.pagination', compact('paginator'))->render();

        $this->assertStringContainsString('aria-label="Paginação"', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringContainsString('← Anterior', $html);
        $this->assertStringContainsString('Próxima →', $html);
        $this->assertStringContainsString('rel="next"', $html);
        $this->assertStringNotContainsString('Previous', $html);
        $this->assertStringNotContainsString('Next »', $html);
    }
}
