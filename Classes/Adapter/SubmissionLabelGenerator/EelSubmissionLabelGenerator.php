<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Adapter\SubmissionLabelGenerator;

use Neos\Eel\EelEvaluatorInterface;
use Neos\Eel\Utility;
use Wwwision\Neos\Submissions\LabelGenerator\SubmissionLabelGenerator\SubmissionLabelGenerator;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionData;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionLabel;

final readonly class EelSubmissionLabelGenerator implements SubmissionLabelGenerator
{
    public function __construct(
        private string $eelExpression,
        private EelEvaluatorInterface $eelEvaluator,
        private array $defaultContextConfiguration,
    ) {}

    public function generate(SubmissionId $submissionId, SubmissionData $data): SubmissionLabel
    {
        $label = (string) Utility::evaluateEelExpression(
            $this->eelExpression,
            $this->eelEvaluator,
            ['submissionId' => $submissionId, 'data' => $data->toArray()],
            $this->defaultContextConfiguration,
        );
        return SubmissionLabel::fromString($label);
    }
}
