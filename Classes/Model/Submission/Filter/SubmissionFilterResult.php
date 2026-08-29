<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Model\Submission\Filter;

use IteratorAggregate;
use Traversable;
use Wwwision\Neos\Submissions\Model\Submission\Submission;
use Wwwision\Neos\Submissions\Model\Submission\Submissions;

/**
 * @implements IteratorAggregate<Submission>
 */
final readonly class SubmissionFilterResult implements IteratorAggregate
{

    private function __construct(
        public Submissions $items,
        public int $totalCount,
    ) {
    }

    public static function create(
        Submissions $submissions,
        int $totalCount,
    ): self
    {
        return new self($submissions, $totalCount);
    }

    public function isEmpty(): bool
    {
        return $this->totalCount === 0;
    }

    public function getIterator(): Traversable
    {
        return $this->items;
    }
}
