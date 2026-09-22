<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Model\Form;

use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

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
     * @param array<mixed> $items
     */
    public static function fromArray(array $items): self
    {
        $processedItems = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $processedItems[] = FormId::fromString($item);
            } elseif ($item instanceof FormId) {
                $processedItems[] = $item;
            } else {
                throw new InvalidArgumentException(sprintf('Expected string or instance of %s, got %s', FormId::class, get_debug_type($item)), 1789995006);
            }
        }
        return new self(...$processedItems);
    }

    public function getIterator(): Traversable
    {
        yield from $this->items;
    }
}
