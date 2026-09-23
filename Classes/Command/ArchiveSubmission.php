<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Command;

use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;

final readonly class ArchiveSubmission
{
    private function __construct(
        public SubmissionId $submissionId,
    ) {}

    public static function create(
        SubmissionId|string $submissionId,
    ): self {
        if (is_string($submissionId)) {
            $submissionId = SubmissionId::fromString($submissionId);
        }
        return new self($submissionId);
    }
}
