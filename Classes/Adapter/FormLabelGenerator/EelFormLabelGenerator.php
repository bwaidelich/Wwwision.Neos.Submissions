<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Adapter\FormLabelGenerator;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\Eel\EelEvaluatorInterface;
use Neos\Eel\Utility;
use Throwable;
use Wwwision\Neos\Submissions\LabelGenerator\FormLabelGenerator\FormLabelGenerator;
use Wwwision\Neos\Submissions\Model\Form\FormId;
use Wwwision\Neos\Submissions\Model\Form\FormLabel;
use Wwwision\Neos\Submissions\Model\Preset\Preset;

final readonly class EelFormLabelGenerator implements FormLabelGenerator
{
    /**
     * @param array<mixed> $defaultContextConfiguration
     */
    public function __construct(
        private string $eelExpression,
        private EelEvaluatorInterface $eelEvaluator,
        private Node|null $siteNode,
        private array $defaultContextConfiguration,
    ) {}

    /**
     * Evaluates the configured Eel expression.
     * A label must never prevent a submission from being stored, so any failure (e.g. a form node that no longer
     * exists, a missing site node or an expression yielding an empty result) falls back to the form id.
     */
    public function generate(FormId $formId, Preset $preset): FormLabel
    {
        try {
            $label = Utility::evaluateEelExpression(
                $this->eelExpression,
                $this->eelEvaluator,
                ['formId' => $formId, 'preset' => $preset, 'site' => $this->siteNode],
                $this->defaultContextConfiguration,
            );
        } catch (Throwable) {
            $label = null;
        }
        return FormLabel::fromString(self::normalizeLabel($label, $formId->value));
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
        return mb_substr($label, 0, FormLabel::MAX_LENGTH);
    }
}
