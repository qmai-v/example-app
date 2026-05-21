<?php

namespace App\Services;

abstract class BaseService
{
    public const int DEFAULT_PER_PAGE = 25;

    /**
     * @var array<int, int>
     */
    public const array PER_PAGE_OPTIONS = [10, 25, 50, 100];

    protected function lastPageFromTotal(int $total, int $perPage): int
    {
        if ($perPage < 1) {
            return 1;
        }

        return max((int) ceil($total / $perPage), 1);
    }

    public function normalizePerPage(int $perPage): int
    {
        return in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : self::DEFAULT_PER_PAGE;
    }
}
