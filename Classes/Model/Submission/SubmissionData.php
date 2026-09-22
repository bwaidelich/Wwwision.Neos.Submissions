<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Model\Submission;

final readonly class SubmissionData
{
    /**
     * @param array<string, mixed> $value
     */
    private function __construct(
        private array $value,
    ) {}

    /**
     * @param array<string, mixed> $value
     */
    public static function fromArray(array $value): self
    {
        return new self($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->value;
    }
}
