<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Model\Form;

use JsonSerializable;
use Stringable;
use Webmozart\Assert\Assert;

final readonly class FormLabel implements Stringable, JsonSerializable
{

    public const MAX_LENGTH = 255;

    private function __construct(public string $value)
    {
        Assert::lengthBetween($value, 1, self::MAX_LENGTH);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
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
