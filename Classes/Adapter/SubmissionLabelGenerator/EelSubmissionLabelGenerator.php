<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Adapter\SubmissionLabelGenerator;

use Neos\Eel\EelEvaluatorInterface;
use Neos\Eel\Utility;
use Throwable;
use Wwwision\Neos\Submissions\LabelGenerator\SubmissionLabelGenerator\SubmissionLabelGenerator;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionData;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionLabel;

final readonly class EelSubmissionLabelGenerator implements SubmissionLabelGenerator
{
    /**
     * @param array<mixed> $defaultContextConfiguration
     */
    public function __construct(
        private string $eelExpression,
        private EelEvaluatorInterface $eelEvaluator,
        private array $defaultContextConfiguration,
    ) {}

    /**
     * Evaluates the configured Eel expression.
     * A label must never prevent a submission from being stored, so any failure (e.g. an expression referring to
     * a field that was not submitted, or yielding an empty result) falls back to the submission id.
     */
    public function generate(SubmissionId $submissionId, SubmissionData $data): SubmissionLabel
    {
        try {
            $label = Utility::evaluateEelExpression(
                $this->eelExpression,
                $this->eelEvaluator,
                ['submissionId' => $submissionId, 'data' => $data->toArray()],
                $this->defaultContextConfiguration,
            );
        } catch (Throwable) {
            $label = null;
        }
        return SubmissionLabel::fromString(self::normalizeLabel($label, $submissionId->value));
    }

    private static function normalizeLabel(mixed $label, string $fallback): string
    {
        if (!is_scalar($label) && !$label instanceof \Stringable) {
            return $fallback;
        }
        $label = trim((string) $label);
        if ($label === '') {
            return $fallback;
        }
        return mb_substr($label, 0, SubmissionLabel::MAX_LENGTH);
    }
}
