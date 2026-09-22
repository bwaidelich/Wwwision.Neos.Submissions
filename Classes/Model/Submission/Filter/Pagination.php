<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Model\Submission\Filter;

use Webmozart\Assert\Assert;

final readonly class Pagination
{
    private const int RESULTS_PER_PAGE = 30;

    private function __construct(
        public int $offset,
        public int $resultsPerPage,
    ) {}

    public static function firstPage(): self
    {
        return new self(0, self::RESULTS_PER_PAGE);
    }

    public static function forPage(int $pageNumber): self
    {
        Assert::positiveInteger($pageNumber);
        return new self(self::RESULTS_PER_PAGE * ($pageNumber - 1), self::RESULTS_PER_PAGE);
    }
}
