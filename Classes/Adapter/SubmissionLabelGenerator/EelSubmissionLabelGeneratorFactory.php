<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Adapter\SubmissionLabelGenerator;

use InvalidArgumentException;
use Neos\Eel\EelEvaluatorInterface;
use Wwwision\Neos\Submissions\LabelGenerator\SubmissionLabelGenerator\SubmissionLabelGeneratorFactory;

final readonly class EelSubmissionLabelGeneratorFactory implements SubmissionLabelGeneratorFactory
{
    private const DEFAULT_EEL_EXPRESSION = '${submissionId}';

    /**
     * @param array<mixed> $defaultContextConfiguration
     */
    public function __construct(
        private EelEvaluatorInterface $eelEvaluator,
        private array $defaultContextConfiguration = [],
    ) {}

    public function create(array $options): EelSubmissionLabelGenerator
    {
        $eelExpression = $options['eelExpression'] ?? self::DEFAULT_EEL_EXPRESSION;
        if (!is_string($eelExpression)) {
            throw new InvalidArgumentException(sprintf('Option "eelExpression" must be a string, got %s', get_debug_type($eelExpression)), 1789995003);
        }
        return new EelSubmissionLabelGenerator(
            $eelExpression,
            $this->eelEvaluator,
            $this->defaultContextConfiguration,
        );
    }
}
