<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Model\Submission;

use JsonSerializable;
use Ramsey\Uuid\Uuid;
use Stringable;
use Webmozart\Assert\Assert;

final readonly class SubmissionId implements Stringable, JsonSerializable
{
    private function __construct(public string $value)
    {
        Assert::uuid($value);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
