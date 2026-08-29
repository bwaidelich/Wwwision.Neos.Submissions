<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Adapter\FormLabelGenerator;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Eel\EelEvaluatorInterface;
use Neos\Eel\Utility;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Service\ContentContext;
use Wwwision\Neos\Submissions\Model\Form\FormId;
use Wwwision\Neos\Submissions\Model\Form\FormLabel;
use Wwwision\Neos\Submissions\Model\Preset\Preset;
use Wwwision\Neos\Submissions\LabelGenerator\FormLabelGenerator\FormLabelGenerator;

final readonly class EelFormLabelGenerator implements FormLabelGenerator
{

    public function __construct(
        private string $eelExpression,
        private EelEvaluatorInterface $eelEvaluator,
        private Node $siteNode,
        private array $defaultContextConfiguration,
    )
    {
    }

    public function generate(FormId $formId, Preset $preset): FormLabel
    {
        $label = Utility::evaluateEelExpression(
            $this->eelExpression,
            $this->eelEvaluator,
            ['formId' => $formId, 'preset' => $preset, 'site' => $this->siteNode],
            $this->defaultContextConfiguration,
        );
        return FormLabel::fromString($label);
    }
}
