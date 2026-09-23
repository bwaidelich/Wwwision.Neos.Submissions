<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Model\Submission\Exception;

use RuntimeException;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;

final class SubmissionIsProtected extends RuntimeException
{
    public static function cannotBeArchived(SubmissionId $submissionId): self
    {
        return new self(sprintf('Submission "%s" is protected and cannot be archived', $submissionId->value), 1790150401);
    }
}
