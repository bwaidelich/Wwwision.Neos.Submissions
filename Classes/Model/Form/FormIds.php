<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Model\Form;

use IteratorAggregate;
use Traversable;
use Webmozart\Assert\Assert;

/**
 * @implements IteratorAggregate<FormId>
 */
final readonly class FormIds implements IteratorAggregate
{
    /**
     * @var list<FormId>
     */
    private array $items;

    private function __construct(
        FormId ...$items,
    ) {
        $this->items = array_values($items);
    }

    /**
     * @param array<FormId|string> $items
     */
    public static function fromArray(array $items): self
    {
        $processedItems = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $processedItems[] = FormId::fromString($item);
            } else {
                Assert::isInstanceOf($item, FormId::class);
                $processedItems[] = $item;
            }
        }
        return new self(...$processedItems);
    }

    public function getIterator(): Traversable
    {
        yield from $this->items;
    }
}
