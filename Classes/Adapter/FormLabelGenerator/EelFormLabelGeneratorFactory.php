<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Adapter\FormLabelGenerator;

use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Eel\EelEvaluatorInterface;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\ContentContext;
use Webmozart\Assert\Assert;
use Wwwision\Neos\Submissions\LabelGenerator\FormLabelGenerator\FormLabelGeneratorFactory;

final readonly class EelFormLabelGeneratorFactory implements FormLabelGeneratorFactory
{
    public function __construct(
        private EelEvaluatorInterface $eelEvaluator,
        private ContextFactoryInterface $contextFactory,
        private array $defaultContextConfiguration = [],
    ) {
    }

    public function create(array $options): EelFormLabelGenerator
    {
        $context = $this->contextFactory->create(['workspaceName' => 'live']);
        Assert::isInstanceOf($context, ContentContext::class);
        return new EelFormLabelGenerator(
            $options['eelExpression'] ?? '${q(site).find("#" + formId).get(0).label}',
            $this->eelEvaluator,
            $context->getCurrentSiteNode(),
            $this->defaultContextConfiguration,
        );
    }
}
