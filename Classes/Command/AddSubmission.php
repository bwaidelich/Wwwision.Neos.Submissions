<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Command;

use Wwwision\Neos\Submissions\Model\Form\FormId;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionData;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;

final readonly class AddSubmission
{
    private function __construct(
        public SubmissionId $submissionId,
        public FormId $formId,
        public SubmissionData $data,
    ) {
    }

    /**
     * @param SubmissionData|array<string, mixed> $data
     */
    public static function create(
        SubmissionId|string $submissionId,
        FormId|string $formId,
        SubmissionData|array $data,
    ): self
    {
        if (is_string($submissionId)) {
            $submissionId = SubmissionId::fromString($submissionId);
        }
        if (is_string($formId)) {
            $formId = FormId::fromString($formId);
        }
        if (is_array($data)) {
            $data = SubmissionData::fromArray($data);
        }
        return new self($submissionId, $formId, $data);
    }
}
