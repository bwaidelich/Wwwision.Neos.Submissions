<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Model\Submission;

use Closure;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<Submission>
 */
final readonly class Submissions implements IteratorAggregate
{

    /**
     * @param iterable<Submission> $items
     */
    private function __construct(
        private iterable $items,
    )
    {
    }

    /**
     * @param iterable<Submission> $items
     */
    public static function fromIterable(iterable $items): self
    {
        return new self($items);
    }

    public function getIterator(): Traversable
    {
        foreach ($this->items as $item) {
            if (!$item instanceof Submission) {
                throw new InvalidArgumentException(sprintf('Expected instance of %s, got %s', Submission::class, get_debug_type($item)));
            }
            yield $item;
        }
    }

    /**
     * @template T
     * @param Closure(Submission): T $callback
     * @return iterable<T>
     */
    public function map(Closure $callback): iterable
    {
        foreach ($this as $item) {
            yield $callback($item);
        }
    }
}
