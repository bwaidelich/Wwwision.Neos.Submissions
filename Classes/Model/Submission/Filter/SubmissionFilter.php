<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Model\Submission\Filter;

use Wwwision\Neos\Submissions\Model\Form\FormId;

final readonly class SubmissionFilter
{
    private function __construct(
        public SearchTerm|null $searchTerm,
        public FormId|null $formId,
    ) {
    }

    public static function create(
        SearchTerm|string|null $searchTerm = null,
        FormId|string|null $formId = null,
    ): self
    {
        if (is_string($searchTerm)) {
            $searchTerm = trim($searchTerm) === '' ? null : SearchTerm::fromString($searchTerm);
        }
        if (is_string($formId)) {
            $formId = FormId::fromString($formId);
        }
        return new self($searchTerm, $formId);
    }

    public static function default(): self
    {
        return new self(null, null);
    }

    public function isEmpty(): bool
    {
        return array_all(get_object_vars($this), fn ($v) => $v === null);
    }

    public function getArray(): array
    {
        return array_map(static fn ($v) => (string)$v, get_object_vars($this));
    }
}
