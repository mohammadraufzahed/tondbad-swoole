<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Pagination;

class LengthAwarePaginator
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(
        protected array $items,
        protected int $total,
        protected int $perPage,
        protected int $currentPage = 1,
        protected string $pageName = 'page',
    ) {
    }

    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return max((int) ceil($this->total / $this->perPage), 1);
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    public function path(?string $path = null): string
    {
        return $path ?? '';
    }

    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'data' => $this->items,
            'first_page_url' => $this->url(1),
            'from' => ($this->currentPage - 1) * $this->perPage + 1,
            'last_page' => $this->lastPage(),
            'last_page_url' => $this->url($this->lastPage()),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path(),
            'per_page' => $this->perPage,
            'prev_page_url' => $this->previousPageUrl(),
            'to' => min($this->currentPage * $this->perPage, $this->total),
            'total' => $this->total,
        ];
    }

    public function nextPageUrl(): ?string
    {
        if ($this->currentPage >= $this->lastPage()) {
            return null;
        }

        return $this->url($this->currentPage + 1);
    }

    public function previousPageUrl(): ?string
    {
        if ($this->currentPage <= 1) {
            return null;
        }

        return $this->url($this->currentPage - 1);
    }

    protected function url(int $page): string
    {
        $separator = str_contains($this->path(), '?') ? '&' : '?';

        return rtrim($this->path(), '?&') . $separator . $this->pageName . '=' . $page;
    }
}
