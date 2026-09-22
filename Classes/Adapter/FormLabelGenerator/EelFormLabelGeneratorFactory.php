<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Adapter\FormLabelGenerator;

use InvalidArgumentException;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Eel\EelEvaluatorInterface;
use Neos\Neos\Domain\Service\ContentContext;
use Throwable;
use Wwwision\Neos\Submissions\LabelGenerator\FormLabelGenerator\FormLabelGeneratorFactory;

final readonly class EelFormLabelGeneratorFactory implements FormLabelGeneratorFactory
{
    private const DEFAULT_EEL_EXPRESSION = '${q(site).find("#" + formId).get(0).label}';

    /**
     * @param array<mixed> $defaultContextConfiguration
     */
    public function __construct(
        private EelEvaluatorInterface $eelEvaluator,
        private ContextFactoryInterface $contextFactory,
        private array $defaultContextConfiguration = [],
    ) {}

    public function create(array $options): EelFormLabelGenerator
    {
        $eelExpression = $options['eelExpression'] ?? self::DEFAULT_EEL_EXPRESSION;
        if (!is_string($eelExpression)) {
            throw new InvalidArgumentException(sprintf('Option "eelExpression" must be a string, got %s', get_debug_type($eelExpression)), 1789995004);
        }
        return new EelFormLabelGenerator(
            $eelExpression,
            $this->eelEvaluator,
            $this->siteNode(),
            $this->defaultContextConfiguration,
        );
    }

    /**
     * The site node is resolved from the current domain (or the default site, e.g. in CLI context).
     * It is only used as context for the label expression, so a missing site node must not be fatal.
     */
    private function siteNode(): Node|null
    {
        try {
            $context = $this->contextFactory->create(['workspaceName' => 'live']);
            if (!$context instanceof ContentContext) {
                return null;
            }
            $siteNode = $context->getCurrentSiteNode();
        } catch (Throwable) {
            return null;
        }
        return $siteNode instanceof Node ? $siteNode : null;
    }
}
