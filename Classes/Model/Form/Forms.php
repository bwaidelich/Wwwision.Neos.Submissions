<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Model\Form;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<Form>
 */
final readonly class Forms implements IteratorAggregate, Countable
{
    /**
     * @var list<Form>
     */
    private array $forms;

    private function __construct(
        Form ...$forms,
    ) {
        $this->forms = array_values($forms);
    }

    /**
     * @param array<Form> $forms
     */
    public static function fromArray(array $forms): self
    {
        return new self(...$forms);
    }

    public function getIterator(): Traversable
    {
        yield from $this->forms;
    }

    public function first(): Form|null
    {
        return $this->forms[0] ?? null;
    }

    public function count(): int
    {
        return count($this->forms);
    }
}
