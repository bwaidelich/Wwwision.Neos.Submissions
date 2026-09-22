<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Command;

use Wwwision\Neos\Submissions\Model\Form\FormId;

final readonly class RegenerateLabels
{
    private function __construct(
        public FormId|null $formId,
    ) {}

    public static function create(
        FormId|string|null $formId = null,
    ): self {
        if (is_string($formId)) {
            $formId = FormId::fromString($formId);
        }
        return new self($formId);
    }
}
