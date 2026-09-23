<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Command;

use Wwwision\Neos\Submissions\Model\Submission\Filter\SubmissionFilter;

final readonly class ArchiveUnprotectedSubmissions
{
    private function __construct(
        public SubmissionFilter $filter,
    ) {}

    public static function create(
        SubmissionFilter|null $filter = null,
    ): self {
        return new self($filter ?? SubmissionFilter::default());
    }
}
