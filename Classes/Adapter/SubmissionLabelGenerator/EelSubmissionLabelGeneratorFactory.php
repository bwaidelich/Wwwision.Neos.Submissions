<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Adapter\SubmissionLabelGenerator;

use Neos\Eel\EelEvaluatorInterface;
use Wwwision\Neos\Submissions\LabelGenerator\SubmissionLabelGenerator\SubmissionLabelGeneratorFactory;

final readonly class EelSubmissionLabelGeneratorFactory implements SubmissionLabelGeneratorFactory
{
    public function __construct(
        private EelEvaluatorInterface $eelEvaluator,
        private array $defaultContextConfiguration = [],
    ) {}

    public function create(array $options): EelSubmissionLabelGenerator
    {
        return new EelSubmissionLabelGenerator(
            $options['eelExpression'] ?? '${submissionId}',
            $this->eelEvaluator,
            $this->defaultContextConfiguration,
        );
    }
}
